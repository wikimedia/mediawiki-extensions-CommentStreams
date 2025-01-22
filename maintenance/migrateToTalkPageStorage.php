<?php
/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

use MediaWiki\Extension\CommentStreams\ICommentStreamsStore;
use MediaWiki\Extension\CommentStreams\Store\TalkPageStore;
use MediaWiki\Maintenance\MaintenanceFatalError;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\User;

$IP ??= getenv( "MW_INSTALL_PATH" ) ?: dirname( __DIR__, 3 );
require_once "$IP/maintenance/Maintenance.php";

class MigrateToTalkPageStorage extends LoggedUpdateMaintenance {

	/** @var ICommentStreamsStore|null */
	private ?ICommentStreamsStore $store = null;

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Migrate to from pages in CommentStreams namespace to associated page\'s talk page'
		);
		$this->addOption(
			'delete-source',
			'Delete source pages after migration. Will not delete database association.'
		);
		$this->addOption( 'quick', 'Skip countdown' );
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'comment-streams-migrate-to-talk-page';
	}

	/**
	 * @return true
	 * @throws MaintenanceFatalError
	 */
	public function doDBUpdates() {
		$this->store = $this->getServiceContainer()->getService( 'CommentStreamsStore' );
		if ( !( $this->store instanceof TalkPageStore ) ) {
			$this->fatalError( 'This script can only be used with TalkPageStore' );
		}

		$this->output(
			"This script will migrate comments from CommentStreams namespace to associated page's talk page\n"
		);
		$this->output( "In case `delete-source` flag is set, source pages will be deleted\n" );
		$this->output(
			"If this script was already executed, without `delete-source` flag, it will duplicate comments\n"
		);
		$this->output( "Abort using CTRL+C if you are not sure if is should be executed..." );
		if ( !$this->hasOption( 'quick' ) ) {
			$this->countDown( 9 );
		}
		$this->output( "\n" );

		$comments = $this->getComments();
		$this->output( "Migrating comments on " . count( $comments ) . " pages\n" );

		foreach ( $comments as $data ) {
			$associatedText = $data['associated']->getPrefixedText();
			$this->output( "Migrating comments on $associatedText..." );
			$count = $this->migrateComments( $data['associated'], $data['comments'] );
			$this->output( "Migrated $count comments\n" );
		}

		$this->output( 'Done' );
		return true;
	}

	/**
	 * @return array
	 */
	private function getComments(): array {
		$res = $this->getDB( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'page_title', 'page_namespace', 'page_id' ] )
			->from( 'cs_comments', 'csc' )
			->from( 'page', 'p' )
			->join( 'page', 'p', [ 'cst_c_assoc_page_id = page_id' ] )
			->groupBy( 'page_id' )
			->fetchResultSet();

		$pages = [];
		foreach ( $res as $row ) {
			$associatedPage = $this->getServiceContainer()->getTitleFactory()->newFromRow( $row );
			$comments = $this->getAssociatedComments( $associatedPage );
			$pages[] = [
				'associated' => $associatedPage,
				'comments' => $comments
			];
		}
		return $pages;
	}

	/**
	 * @param \MediaWiki\Title\Title $page
	 * @param array $comments
	 * @return int
	 */
	private function migrateComments( \MediaWiki\Title\Title $page, array $comments ): int {
		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		$cnt = 0;
		foreach ( $comments as $comment ) {
			if ( !$comment['author']->isRegistered() ) {
				$comment['author'] = $user;
			}
			$instance = $this->store->insertComment(
				$comment['author'], $comment['wikitext'], $page->getId(), $comment['title'], $comment['block']
			);
			if ( !$instance ) {
				$this->output( "Failed to migrate comment {$comment['title']}\n" );
				continue;
			}
			$this->store->forceSetEntityData(
				$instance,
				[
					'created' => $comment['created'],
					'modified' => $comment['modified'],
					'lastEditor' => isset( $comment['lastEditor'] ) ? $comment['lastEditor']->getName() : null
				],
				User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] )
			);
			$this->maybeDeleteSource( $comment['source'] );
			$cnt++;
			foreach ( $comment['replies'] as $reply ) {
				if ( !$reply['author']->isRegistered() ) {
					$reply['author'] = $user;
				}
				$replyInstance = $this->store->insertReply(
					$reply['author'], $reply['wikitext'], $instance
				);
				if ( !$replyInstance ) {
					$this->output( "Failed to migrate reply\n" );
					continue;
				}
				$this->store->forceSetEntityData(
					$replyInstance,
					[
						'created' => $reply['created'],
						'modified' => $reply['modified'],
						'lastEditor' => isset( $reply['lastEditor'] ) ? $reply['lastEditor']->getName() : null
					],
					$user
				);
				$this->maybeDeleteSource( $reply['source'] );
			}
		}
		return $cnt;
	}

	/**
	 * @param \MediaWiki\Title\Title $associatedPage
	 * @return array
	 */
	private function getAssociatedComments( \MediaWiki\Title\Title $associatedPage ) {
		$res = $this->getDB( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'page_title', 'page_namespace', 'page_id', 'cst_c_comment_title', 'cst_c_block_name' ] )
			->from( 'cs_comments', 'csc' )
			->from( 'page', 'p' )
			->where( [ 'cst_c_assoc_page_id' => $associatedPage->getArticleID() ] )
			->join( 'page', 'p', [ 'cst_c_comment_page_id = page_id' ] )
			->fetchResultSet();

		$entities = [];
		foreach ( $res as $row ) {
			$commentPage = $this->getServiceContainer()->getTitleFactory()->newFromRow( $row );
			$commentInfo = $this->getCommentInfo( $commentPage, $row->cst_c_comment_title, $row->cst_c_block_name );
			foreach ( $this->getReplies( $commentPage ) as $replyPage ) {
				$commentInfo['replies'][] = $this->getReplyInfo( $replyPage );
			}
			$entities[] = $commentInfo;
		}

		return array_filter( $entities );
	}

	/**
	 * @param \MediaWiki\Title\Title $commentPage
	 * @param string $title
	 * @param string|null $block
	 * @return array|null
	 */
	private function getCommentInfo( \MediaWiki\Title\Title $commentPage, string $title, ?string $block ): ?array {
		$data = [
			'type' => 'comment',
			'title' => $title,
			'block' => $block,
			'replies' => [],
		];
		$this->decorateData( $commentPage, $data );

		return $data;
	}

	/**
	 * @param \MediaWiki\Title\Title $commentPage
	 * @return Generator
	 */
	private function getReplies( \MediaWiki\Title\Title $commentPage ): Generator {
		$res = $this->getDB( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'page_title', 'page_namespace', 'page_id' ] )
			->from( 'cs_replies', 'csc' )
			->from( 'page', 'p' )
			->where( [ 'cst_r_comment_page_id' => $commentPage->getId() ] )
			->join( 'page', 'p', [ 'cst_r_reply_page_id = page_id' ] )
			->fetchResultSet();

		foreach ( $res as $row ) {
			yield $this->getServiceContainer()->getTitleFactory()->newFromRow( $row );
		}
	}

	/**
	 * @param \MediaWiki\Title\Title $replyPage
	 * @return string[]
	 */
	private function getReplyInfo( \MediaWiki\Title\Title $replyPage ): array {
		$data = [
			'type' => 'reply',
		];
		$this->decorateData( $replyPage, $data );
		return $data;
	}

	/**
	 * @param \MediaWiki\Title\Title $page
	 * @param array &$data
	 * @return void
	 */
	private function decorateData( \MediaWiki\Title\Title $page, array &$data ) {
		$firstRevision = $this->getServiceContainer()->getRevisionLookup()->getFirstRevision( $page );
		if ( !$firstRevision ) {
			return null;
		}
		$latestRevision = $this->getServiceContainer()->getRevisionLookup()->getRevisionByTitle( $page );
		if ( !$latestRevision ) {
			return null;
		}
		$data['author'] = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity(
			$firstRevision->getUser()
		);
		$data['created'] = $firstRevision->getTimestamp();
		$data['modified'] = $latestRevision->getTimestamp();
		if ( !$firstRevision->isCurrent() ) {
			$data['lastEditor'] = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity(
				$latestRevision->getUser()
			);
		}
		$content = $latestRevision->getContent( SlotRecord::MAIN );
		if ( !( $content instanceof \MediaWiki\Content\TextContent ) ) {
			return null;
		}
		$data['wikitext'] = $this->removeAnnotations( $content->getText(), $data['title'] ?? '' );
		$data['source'] = $page;
	}

	/**
	 * Borrowed from NamespacePageStore
	 *
	 * @param string $wikitext the wikitext to which to add
	 * @param string $commentTitle
	 * @return string wikitext without annotations
	 */
	private function removeAnnotations( string $wikitext, string $commentTitle ): string {
		if ( !$commentTitle ) {
			return $wikitext;
		}
		$strip = <<<EOT
{{DISPLAYTITLE:
$commentTitle
}}
EOT;
		return str_replace( $strip, '', $wikitext );
	}

	/**
	 * @param \MediaWiki\Title\Title $source
	 * @return void
	 */
	private function maybeDeleteSource( \MediaWiki\Title\Title $source ) {
		if ( $this->hasOption( 'delete-source' ) ) {
			$this->output( "\n---> Deleting source page {$source->getPrefixedText()}..." );
			$dpFactory = $this->getServiceContainer()->getDeletePageFactory();
			$dp = $dpFactory->newDeletePage(
				$source->toPageIdentity(),
				User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] )
			);
			$dp->deleteUnsafe( 'Migrated to talk page' );
			$this->output( "Deleted\n" );
		}
	}

}

$maintClass = MigrateToTalkPageStorage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
