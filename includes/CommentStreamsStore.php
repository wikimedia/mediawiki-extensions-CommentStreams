<?php
/**
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
 *
 * @author Cindy Cicalese
 */

namespace MediaWiki\Extension\CommentStreams;

use FatalError;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MWException;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;
use WikiPage;
use WikitextContent;

/**
 * Comment Streams database backend interface
 */
class CommentStreamsStore {
	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @var UserFactory
	 */
	private $userFactory;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param PermissionManager $permissionManager
	 * @param UserFactory $userFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		WikiPageFactory $wikiPageFactory
	) {
		$this->loadBalancer = $loadBalancer;
		$this->permissionManager = $permissionManager;
		$this->userFactory = $userFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * @return ILoadBalancer
	 */
	private function getDBLoadBalancer(): ILoadBalancer {
		return $this->loadBalancer;
	}

	/**
	 * @param int $mode DB_PRIMARY or DB_REPLICA
	 * @return IDatabase
	 */
	private function getDBConnection( int $mode ): IDatabase {
		$lb = $this->getDBLoadBalancer();

		return $lb->getConnection( $mode );
	}

	/**
	 * @param int $id
	 * @return ?array
	 */
	public function getComment( int $id ): ?array {
		$result = $this->getDBConnection( DB_REPLICA )
				->newSelectQueryBuilder()
				->select( [
					'cst_c_assoc_page_id',
					'cst_c_comment_title',
					'cst_c_block_name'
				] )
				->from( 'cs_comments' )
				->where( [
					'cst_c_comment_page_id' => $id
				] )
				->caller( __METHOD__ )
				->fetchRow();
		if ( $result ) {
			return [
				'assoc_page_id' => (int)$result->cst_c_assoc_page_id,
				'comment_title' => $result->cst_c_comment_title,
				'block_name' => $result->cst_c_block_name
			];
		}
		return null;
	}

	/**
	 * @param int $id
	 * @return ?array
	 */
	public function getReply( int $id ): ?array {
		$result = $this->getDBConnection( DB_REPLICA )
				->newSelectQueryBuilder()
				->select( 'cst_r_comment_page_id' )
				->from( 'cs_replies' )
				->where( [
					'cst_r_reply_page_id' => $id
				] )
				->caller( __METHOD__ )
				->fetchRow();
		if ( $result ) {
			return [
				'comment_page_id' => (int)$result->cst_r_comment_page_id
			];
		}

		return null;
	}

	/**
	 * @param int $assocPageId
	 * @return WikiPage[]
	 */
	public function getAssociatedComments( int $assocPageId ): array {
		$result = $this->getDBConnection( DB_REPLICA )
				->newSelectQueryBuilder()
				->select( [
					'cst_c_comment_page_id'
				] )
				->from( 'cs_comments' )
				->where( [
					'cst_c_assoc_page_id' => $assocPageId
				] )
				->caller( __METHOD__ )
				->fetchResultSet();
		$commentWikiPages = [];
		foreach ( $result as $row ) {
			$wikiPage = $this->wikiPageFactory->newFromID( $row->cst_c_comment_page_id );
			if ( $wikiPage ) {
				$commentWikiPages[] = $wikiPage;
			}
		}
		return $commentWikiPages;
	}

	/**
	 * @param int $commentPageId
	 * @return WikiPage[]
	 */
	public function getReplies( int $commentPageId ): array {
		$result = $this->getDBConnection( DB_REPLICA )
				->newSelectQueryBuilder()
				->select( 'cst_r_reply_page_id' )
				->from( 'cs_replies' )
				->where( [
					'cst_r_comment_page_id' => $commentPageId
				] )
				->caller( __METHOD__ )
				->fetchResultSet();
		$replyWikiPages = [];
		foreach ( $result as $row ) {
			$wikiPage = $this->wikiPageFactory->newFromID( $row->cst_r_reply_page_id );
			if ( $wikiPage ) {
				$replyWikiPages[] = $wikiPage;
			}
		}
		return $replyWikiPages;
	}

	/**
	 * @param int $commentPageId
	 * @return int
	 */
	public function getNumReplies( int $commentPageId ): int {
		return $this->getDBConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->from( 'cs_replies' )
			->where( [
				'cst_r_comment_page_id' => $commentPageId
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 * @return IResultWrapper
	 */
	public function getCommentPages( int $limit, int $offset ): IResultWrapper {
		$dbr = $this->getDBConnection( DB_REPLICA );
		$union = $dbr->unionQueries( [
			$dbr->newSelectQueryBuilder()
				->field( 'cst_c_comment_page_id', 'union_page_id' )
				->from( 'cs_comments' )
				->getSQL(),
			$dbr->newSelectQueryBuilder()
				->field( 'cst_r_reply_page_id', 'union_page_id' )
				->from( 'cs_replies' )
				->getSQL()
		], IDatabase::UNION_DISTINCT );
		return $dbr->newSelectQueryBuilder()
			->fields( [
				'page_id'
			] )
			->from( '(' . $union . ') AS union_table' )
			->leftJoin( 'page', 'page', [
				'union_page_id=page_id'
			] )
			->leftJoin( 'revision', 'revision', [
				'page_latest=rev_id'
			] )
			->orderBy( 'rev_timestamp', 'DESC' )
			->limit( $limit )
			->offset( $offset )
			->fetchResultSet();
	}

	/**
	 * @param User $user
	 * @param WikitextContent $content
	 * @return WikiPage|null
	 * @throws MWException
	 */
	private function createCommentPage( User $user, WikitextContent $content ): ?WikiPage {
		do {
			$index = wfRandomString();
			$title = Title::newFromText( $index, NS_COMMENTSTREAMS );
			$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
			$deleted = $title->hasDeletedEdits();
			if ( !$deleted && !$title->exists() ) {
				if ( !$this->permissionManager->userCan( 'cs-comment', $user, $title ) ) {
					return null;
				}
				$status = CommentStreamsUtils::doEditContent(
					$wikiPage,
					$content,
					$user,
					EDIT_NEW | EDIT_SUPPRESS_RC
				);
				if ( $status->isGood() ) {
					return $wikiPage;
				} elseif ( $status->getMessage()->getKey() !== 'edit-already-exists' ) {
					return null;
				}
			}
		} while ( true );
	}

	/**
	 * @param User $user
	 * @param string $wikitext
	 * @param int $assocPageId
	 * @param string $commentTitle
	 * @param ?string $commentBlockName
	 * @return ?WikiPage
	 * @throws MWException
	 */
	public function insertComment(
		User $user,
		string $wikitext,
		int $assocPageId,
		string $commentTitle,
		?string $commentBlockName
	): ?WikiPage {
		$annotatedWikitext = $this->addAnnotations( $wikitext, $commentTitle );
		$content = new WikitextContent( $annotatedWikitext );

		$wikiPage = $this->createCommentPage( $user, $content );
		if ( !$wikiPage ) {
			return null;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		$result = $dbw->insert(
			'cs_comments',
			[
				'cst_c_comment_page_id' => $wikiPage->getId(),
				'cst_c_assoc_page_id' => $assocPageId,
				'cst_c_comment_title' => $commentTitle,
				'cst_c_block_name' => $commentBlockName
			],
			__METHOD__
		);

		if ( !$result ) {
			return null;
		}

		return $wikiPage;
	}

	/**
	 * @param User $user
	 * @param string $wikitext
	 * @param int $commentPageId
	 * @return ?WikiPage
	 * @throws MWException
	 */
	public function insertReply(
		User $user,
		string $wikitext,
		int $commentPageId
	): ?WikiPage {
		$wikiPage = $this->createCommentPage( $user, new WikitextContent( $wikitext ) );
		if ( !$wikiPage ) {
			return null;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		$result = $dbw->insert(
			'cs_replies',
			[
				'cst_r_reply_page_id' => $wikiPage->getId(),
				'cst_r_comment_page_id' => $commentPageId
			],
			__METHOD__
		);

		if ( !$result ) {
			return null;
		}

		return $wikiPage;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param string $commentTitle
	 * @param string $wikitext
	 * @param User $user
	 * @return bool
	 * @throws MWException
	 */
	public function updateComment(
		WikiPage $wikiPage,
		string $commentTitle,
		string $wikitext,
		User $user
	): bool {
		$annotatedWikitext = $this->addAnnotations( $wikitext, $commentTitle );
		$content = new WikitextContent( $annotatedWikitext );

		$status = CommentStreamsUtils::doEditContent(
			$wikiPage,
			$content,
			$user,
			EDIT_UPDATE | EDIT_SUPPRESS_RC
		);
		if ( !$status->isOK() ) {
			return false;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		return $dbw->update(
			'cs_comments',
			[
				'cst_c_comment_title' => $commentTitle
			],
			[
				'cst_c_comment_page_id' => $wikiPage->getId()
			],
			__METHOD__
		);
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param string $wikitext
	 * @param User $user
	 * @throws MWException
	 */
	public function updateReply(
		WikiPage $wikiPage,
		string $wikitext,
		User $user
	) {
		CommentStreamsUtils::doEditContent(
			$wikiPage,
			new WikitextContent( $wikitext ),
			$user,
			EDIT_UPDATE | EDIT_SUPPRESS_RC
		);
	}

	/**
	 * @param WikiPage $wikiPage to delete
	 * @param string $comment log line
	 * @param User $deleter actor
	 * @return Status
	 */
	protected function realDelete( WikiPage $wikiPage, string $comment, User $deleter ): Status {
		return $wikiPage->doDeleteArticleReal( $comment, $deleter, true );
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param User $deleter
	 * @return bool
	 * @throws FatalError
	 * @throws MWException
	 */
	public function deleteComment( WikiPage $wikiPage, User $deleter ): bool {
		// must save page ID before deleting page
		$pageid = $wikiPage->getId();

		$status = $this->realDelete( $wikiPage, 'comment deleted', $deleter );

		if ( !$status->isGood() ) {
			return false;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		return $dbw->delete(
			'cs_comments',
			[
				'cst_c_comment_page_id' => $pageid
			],
			__METHOD__
		);
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param User $deleter
	 * @return bool
	 * @throws FatalError
	 * @throws MWException
	 */
	public function deleteReply( WikiPage $wikiPage, User $deleter ): bool {
		// must save page ID before deleting page
		$pageid = $wikiPage->getId();

		$status = $this->realDelete( $wikiPage, 'comment deleted', $deleter );

		if ( !$status->isGood() ) {
			return false;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		return $dbw->delete(
			'cs_replies',
			[
				'cst_r_reply_page_id' => $pageid
			],
			__METHOD__
		);
	}

	/**
	 * @param int $pageId
	 * @param int $assocPageId
	 * @param string $commentTitle
	 * @param string|null $blockName
	 */
	public function upsertCommentMetadata(
		int $pageId,
		int $assocPageId,
		string $commentTitle,
		?string $blockName
	) {
		$dbw = $this->getDBConnection( DB_PRIMARY );
		$dbw->upsert(
			'cs_comments',
			[
				'cst_c_comment_page_id' => $pageId,
				'cst_c_assoc_page_id' => $assocPageId,
				'cst_c_comment_title' => $commentTitle,
				'cst_c_block_name' => $blockName
			],
			[
				'cst_c_comment_page_id'
			],
			[
				'cst_c_comment_page_id' => $pageId,
				'cst_c_assoc_page_id' => $assocPageId,
				'cst_c_comment_title' => $commentTitle,
				'cst_c_block_name' => $blockName
			],
			__METHOD__
		);
	}

	/**
	 * @param int $pageId
	 * @param int $commentPageId
	 * @throws MWException
	 */
	public function upsertReplyMetadata(
		int $pageId,
		int $commentPageId
	) {
		$dbw = $this->getDBConnection( DB_PRIMARY );
		$dbw->upsert(
			'cs_replies',
			[
				'cst_r_reply_page_id' => $pageId,
				'cst_r_comment_page_id' => $commentPageId
			],
			[
				'cst_r_reply_page_id'
			],
			[
				'cst_r_reply_page_id' => $pageId,
				'cst_r_comment_page_id' => $commentPageId
			],
			__METHOD__
		);
	}

	/**
	 * @param int $pageId
	 * @param int $userId
	 * @return int -1, 0, or 1
	 */
	public function getVote( int $pageId, int $userId ): int {
		$result = $this->getDBConnection( DB_REPLICA )
				->newSelectQueryBuilder()
				->select( 'cst_v_vote' )
				->from( 'cs_votes' )
				->where( [
					'cst_v_page_id' => $pageId,
					'cst_v_user_id' => $userId
				] )
				->caller( __METHOD__ )
				->fetchRow();
		if ( $result ) {
			$vote = (int)$result->cst_v_vote;
			if ( $vote > 0 ) {
				return 1;
			}
			if ( $vote < 0 ) {
				return -1;
			}
		}

		return 0;
	}

	/**
	 * @param int $id
	 * @return int
	 */
	public function getNumUpVotes( int $id ): int {
		return $this->getNumVotes( $id, true );
	}

	/**
	 * @param int $id
	 * @return int
	 */
	public function getNumDownVotes( int $id ): int {
		return $this->getNumVotes( $id, false );
	}

	/**
	 * @param int $id
	 * @param bool $up
	 * @return int
	 */
	private function getNumVotes( int $id, bool $up ): int {
		return $this->getDBConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->from( 'cs_votes' )
			->where( [
				'cst_v_page_id' => $id,
				'cst_v_vote' => $up ? 1 : -1
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	/**
	 * @param int $vote
	 * @param int $pageId
	 * @param int $userId
	 * @return bool true for OK, false for error
	 */
	public function vote( int $vote, int $pageId, int $userId ): bool {
		$dbw = $this->getDBConnection( DB_PRIMARY );
		$result = $dbw->newSelectQueryBuilder()
				->select( 'cst_v_vote' )
				->from( 'cs_votes' )
				->where( [
					'cst_v_page_id' => $pageId,
					'cst_v_user_id' => $userId
				] )
				->caller( __METHOD__ )
				->fetchRow();
		if ( $result ) {
			if ( $vote === (int)$result->cst_v_vote ) {
				return true;
			}
			if ( $vote === 1 || $vote === -1 ) {
				return $dbw->update(
					'cs_votes',
					[
						'cst_v_vote' => $vote
					],
					[
						'cst_v_page_id' => $pageId,
						'cst_v_user_id' => $userId
					],
					__METHOD__
				);
			} else {
				return $dbw->delete(
					'cs_votes',
					[
						'cst_v_page_id' => $pageId,
						'cst_v_user_id' => $userId
					],
					__METHOD__
				);
			}
		}
		if ( $vote === 0 ) {
			return true;
		}

		return $dbw->insert(
			'cs_votes',
			[
				'cst_v_page_id' => $pageId,
				'cst_v_user_id' => $userId,
				'cst_v_vote' => $vote
			],
			__METHOD__
		);
	}

	/**
	 * @param int $pageId the page ID of the comment to watch
	 * @param int $userId the user ID of the user watching the comment
	 * @return bool true for OK, false for error
	 */
	public function watch( int $pageId, int $userId ): bool {
		if ( $this->isWatching( $pageId, $userId, DB_PRIMARY ) ) {
			return true;
		}

		return $this->getDBConnection( DB_PRIMARY )->insert(
			'cs_watchlist',
			[
				'cst_wl_page_id' => $pageId,
				'cst_wl_user_id' => $userId
			],
			__METHOD__
		);
	}

	/**
	 * @param int $pageId the page ID of the comment to watch
	 * @param int $userId the user ID of the user watching the comment
	 * @return bool true for OK, false for error
	 */
	public function unwatch( int $pageId, int $userId ): bool {
		if ( !$this->isWatching( $pageId, $userId, DB_PRIMARY ) ) {
			return true;
		}

		return $this->getDBConnection( DB_PRIMARY )->delete(
			'cs_watchlist',
			[
				'cst_wl_page_id' => $pageId,
				'cst_wl_user_id' => $userId
			],
			__METHOD__
		);
	}

	/**
	 * @param int $pageId the page ID of the comment to check
	 * @param int $userId the user ID of the user watching the comment
	 * @param int $fromdb DB_PRIMARY or DB_REPLICA
	 * @return bool database true for OK, false for error
	 */
	public function isWatching( int $pageId, int $userId, int $fromdb = DB_REPLICA ): bool {
		$count = $this->getDBConnection( $fromdb )
			   ->newSelectQueryBuilder()
			   ->select( 'cst_wl_page_id' )
			   ->from( 'cs_watchlist' )
			   ->where( [
				   'cst_wl_page_id' => $pageId,
				   'cst_wl_user_id' => $userId
			   ] )
			   ->caller( __METHOD__ )
			   ->fetchRowCount();
		return $count > 0;
	}

	/**
	 * @param int $id
	 * @return User[] array of users indexed by user ID
	 */
	public function getWatchers( int $id ): array {
		$result = $this->getDBConnection( DB_REPLICA )
				->newSelectQueryBuilder()
				->select( 'cst_wl_user_id' )
				->from( 'cs_watchlist' )
				->where( [
					'cst_wl_page_id' => $id
				] )
				->caller( __METHOD__ )
				->fetchRow();
		$users = [];
		if ( $result ) {
			foreach ( $result as $row ) {
				$userId = $row->cst_wl_user_id;
				$user = $this->userFactory->newFromId( $userId );
				$users[$userId] = $user;
			}
		}
		return $users;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param ?string $commentTitle
	 * @return string
	 */
	public function getWikitext( WikiPage $wikiPage, ?string $commentTitle ): string {
		$wikitext = $wikiPage->getContent( RevisionRecord::RAW )->getWikitextForTransclusion();
		if ( $commentTitle ) {
			return $this->removeAnnotations( $wikitext, $commentTitle );
		}
		return $wikitext;
	}

	/**
	 * add extra information to wikitext before storage
	 *
	 * @param string $wikitext the wikitext to which to add
	 * @param string $commentTitle string title of comment
	 * @return string annotated wikitext
	 */
	private function addAnnotations( string $wikitext, string $commentTitle ): string {
		$wikitext .= <<<EOT
{{DISPLAYTITLE:
$commentTitle
}}
EOT;
		return $wikitext;
	}

	/**
	 * add extra information to wikitext before storage
	 *
	 * @param string $wikitext the wikitext to which to add
	 * @param string $commentTitle
	 * @return string wikitext without annotations
	 */
	private function removeAnnotations( string $wikitext, string $commentTitle ): string {
		$strip = <<<EOT
{{DISPLAYTITLE:
$commentTitle
}}
EOT;
		return str_replace( $strip, '', $wikitext );
	}
}
