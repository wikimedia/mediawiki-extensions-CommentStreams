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

namespace MediaWiki\Extension\CommentStreams\Store;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
use MediaWiki\Extension\CommentStreams\AbstractComment;
use MediaWiki\Extension\CommentStreams\Comment;
use MediaWiki\Extension\CommentStreams\HistoryHandler\UrlHistoryHandler;
use MediaWiki\Extension\CommentStreams\ICommentStreamsStore;
use MediaWiki\Extension\CommentStreams\Reply;
use MediaWiki\Extension\CommentStreams\SMWInterface;
use MediaWiki\Extension\CommentStreams\VoteHelper;
use MediaWiki\Extension\CommentStreams\WatchHelper;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
use MWException;
use Psr\Log\LoggerInterface;
use Title;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\Subquery;
use Wikimedia\Timestamp\TimestampException;
use WikiPage;
use WikitextContent;

/**
 * Comment Streams database backend interface
 */
class NamespacePageStore implements ICommentStreamsStore {
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
	 * @var TitleFactory
	 */
	private $titleFactory;

	/** @var SMWInterface */
	private SMWInterface $smwInterface;

	/** @var RevisionLookup */
	private RevisionLookup $revisionLookup;

	/** @var LoggerInterface */
	private $logger;

	/** @var DeletePageFactory */
	private $deletePageFactory;

	/** @var WatchHelper */
	private $watchHelper;

	/** @var VoteHelper */
	private $voteHelper;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param PermissionManager $permissionManager
	 * @param UserFactory $userFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param SMWInterface $smwInterface
	 * @param RevisionLookup $revisionLookup
	 * @param LoggerInterface $logger
	 * @param DeletePageFactory $deletePageFactory
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		SMWInterface $smwInterface,
		RevisionLookup $revisionLookup,
		LoggerInterface $logger,
		DeletePageFactory $deletePageFactory,
		private readonly HookContainer $hookContainer
	) {
		$this->loadBalancer = $loadBalancer;
		$this->permissionManager = $permissionManager;
		$this->userFactory = $userFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->smwInterface = $smwInterface;
		$this->revisionLookup = $revisionLookup;
		$this->deletePageFactory = $deletePageFactory;

		$this->logger = $logger;

		$this->watchHelper = new WatchHelper( $this->loadBalancer, $this->userFactory );
		$this->voteHelper = new VoteHelper( $this->loadBalancer );
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
	 * @return Comment|null
	 * @throws TimestampException
	 */
	public function getComment( int $id ): ?Comment {
		$wikiPage = $this->wikiPageFactory->newFromID( $id );
		if ( !$wikiPage || !$this->isValidAbstractComment( $wikiPage ) ) {
			return null;
		}
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
			$firstRevision = $this->revisionLookup->getFirstRevision( $wikiPage->getTitle() );
			if ( $firstRevision && $firstRevision->isCurrent() ) {
				$latestRevision = $firstRevision;
			} else {
				$latestRevision = $this->revisionLookup->getRevisionByTitle( $wikiPage->getTitle() );
			}
			if ( !$firstRevision || !$latestRevision ) {
				$this->logger->error( 'Could not find revision for comment page ID {id}', [
					'id' => $id,
					'page' => $wikiPage->getTitle()->getPrefixedDBkey()
				] );
				return null;
			}

			return new Comment(
				$id,
				$result->cst_c_comment_title,
				$result->cst_c_block_name,
				$this->titleFactory->newFromID( $result->cst_c_assoc_page_id ),
				$firstRevision->getUser(),
				$latestRevision->getUser(),
				MWTimestamp::getInstance( $firstRevision->getTimestamp() ),
				MWTimestamp::getInstance( $latestRevision->getTimestamp() ),
			);
		}
		return null;
	}

	/**
	 * @param int $id
	 * @return ?Reply
	 * @throws TimestampException
	 */
	public function getReply( int $id ): ?Reply {
		$wikiPage = $this->wikiPageFactory->newFromID( $id );
		if ( !$wikiPage || !$this->isValidAbstractComment( $wikiPage ) ) {
			return null;
		}
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
			$parent = $this->getComment( (int)$result->cst_r_comment_page_id );
			if ( !$parent ) {
				$this->logger->error( 'Could not find parent comment for reply page ID {id}', [
					'id' => $id
				] );
				return null;
			}

			$firstRevision = $this->revisionLookup->getFirstRevision( $wikiPage->getTitle() );
			$latestRevision = $this->revisionLookup->getRevisionByTitle( $wikiPage->getTitle() );
			if ( !$firstRevision || !$latestRevision ) {
				$this->logger->error( 'Could not find revision for comment page ID {id}', [
					'id' => $id,
					'page' => $wikiPage->getTitle()->getPrefixedDBkey()
				] );
				return null;
			}
			return new Reply(
				$parent,
				$id,
				$firstRevision->getUser(),
				$latestRevision->getUser(),
				new MWTimestamp( $firstRevision->getTimestamp() ),
				new MWTimestamp( $latestRevision->getTimestamp() ),
			);
		}

		return null;
	}

	/**
	 * @param string $action
	 * @param User $user
	 * @param AbstractComment $comment
	 * @return bool
	 */
	public function userCan( string $action, User $user, AbstractComment $comment ): bool {
		$commentPage = $this->wikiPageFactory->newFromID( $comment->getId() );
		if ( !$commentPage ) {
			return false;
		}
		return $this->permissionManager->userCan( $action, $user, $commentPage->getTitle() );
	}

	/**
	 * @param PageIdentity $page
	 * @return Comment[]
	 * @throws TimestampException
	 */
	public function getAssociatedComments( PageIdentity $page ): array {
		$result = $this->getDBConnection( DB_REPLICA )
				->newSelectQueryBuilder()
				->select( [
					'cst_c_comment_page_id'
				] )
				->from( 'cs_comments' )
				->where( [
					'cst_c_assoc_page_id' => $page->getId()
				] )
				->caller( __METHOD__ )
				->fetchResultSet();
		$commentWikiPages = [];
		foreach ( $result as $row ) {
			$commentWikiPages[] = $this->getComment( $row->cst_c_comment_page_id );
		}
		return array_filter( $commentWikiPages );
	}

	/**
	 * @param Comment $parent
	 * @return Reply[]
	 * @throws TimestampException
	 */
	public function getReplies( Comment $parent ): array {
		$result = $this->getDBConnection( DB_REPLICA )
				->newSelectQueryBuilder()
				->select( 'cst_r_reply_page_id' )
				->from( 'cs_replies' )
				->where( [
					'cst_r_comment_page_id' => $parent->getId(),
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

		$replies = [];
		foreach ( $result as $row ) {
			$reply = $this->getReply( $row->cst_r_reply_page_id );
			$replies[] = $reply;
		}
		return array_filter( $replies );
	}

	/**
	 * @param Comment $comment
	 * @return int
	 */
	public function getNumReplies( Comment $comment ): int {
		return $this->getDBConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->from( 'cs_replies' )
			->where( [
				'cst_r_comment_page_id' => $comment->getId(),
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
		$union = $dbr->newUnionQueryBuilder()->caller( __METHOD__ );
		$union->add(
			$dbr->newSelectQueryBuilder()
				->field( 'cst_c_comment_page_id', 'union_page_id' )
				->from( 'cs_comments' )
		);
		$union->add(
			$dbr->newSelectQueryBuilder()
				->field( 'cst_r_reply_page_id', 'union_page_id' )
				->from( 'cs_replies' )
		);
		return $dbr->newSelectQueryBuilder()
			->fields( [
				'page_id'
			] )
			->from( new Subquery( $union->getSQL() ), 'union_table' )
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
				$status = $this->doEditContent(
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
	 * @inheritDoc
	 */
	public function insertComment(
		User $user,
		string $wikitext,
		int $assocPageId,
		string $commentTitle,
		?string $commentBlockName
	): ?Comment {
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

		$this->smwInterface->update( $wikiPage->getTitle() );
		$createdComment = $this->getComment( $wikiPage->getId() );
		if ( !$createdComment ) {
			return null;
		}
		$associatedPage = $this->titleFactory->newFromID( $assocPageId );
		$this->watch( $createdComment, $user );
		$this->hookContainer->run(
			'CommentStreamsInsertEntity', [
				$createdComment, $user, $associatedPage, 'comment', $wikitext
			]
		);
		return $createdComment;
	}

	/**
	 * @inheritDoc
	 */
	public function insertReply(
		User $user,
		string $wikitext,
		Comment $parent
	): ?Reply {
		$wikiPage = $this->createCommentPage( $user, new WikitextContent( $wikitext ) );
		if ( !$wikiPage ) {
			return null;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		$result = $dbw->insert(
			'cs_replies',
			[
				'cst_r_reply_page_id' => $wikiPage->getId(),
				'cst_r_comment_page_id' => $parent->getId(),
			],
			__METHOD__
		);

		if ( !$result ) {
			return null;
		}

		$this->smwInterface->update( $wikiPage->getTitle() );
		$this->watch( $parent, $user );
		$this->hookContainer->run(
			'CommentStreamsInsertEntity', [
				$this->getReply( $wikiPage->getId() ), $user, $parent->getAssociatedPage(), 'reply', $wikitext
			]
		);

		return $this->getReply( $wikiPage->getId() );
	}

	/**
	 * @param Comment $comment
	 * @param string $commentTitle
	 * @param string $wikitext
	 * @param User $user
	 * @return bool
	 * @throws MWException
	 */
	public function updateComment(
		Comment $comment,
		string $commentTitle,
		string $wikitext,
		User $user
	): bool {
		$annotatedWikitext = $this->addAnnotations( $wikitext, $commentTitle );
		$content = new WikitextContent( $annotatedWikitext );

		$oldText = $this->getWikitext( $comment );
		$wikiPage = $this->wikiPageFactory->newFromID( $comment->getId() );
		$status = $this->doEditContent(
			$wikiPage,
			$content,
			$user,
			EDIT_UPDATE | EDIT_SUPPRESS_RC
		);
		if ( !$status->isOK() ) {
			return false;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );

		$this->smwInterface->update( $wikiPage->getTitle() );

		$res = $dbw->update(
			'cs_comments',
			[
				'cst_c_comment_title' => $commentTitle
			],
			[
				'cst_c_comment_page_id' => $wikiPage->getId()
			],
			__METHOD__
		);

		if ( !$res ) {
			return false;
		}
		$this->hookContainer->run(
			'CommentStreamsUpdateEntity', [
				$comment, $user, $oldText, $wikitext
			]
		);
		return $res;
	}

	/**
	 * @param Reply $reply
	 * @param string $wikitext
	 * @param User $user
	 * @return bool
	 * @throws MWException
	 */
	public function updateReply(
		Reply $reply,
		string $wikitext,
		User $user
	): bool {
		$oldText = $this->getWikitext( $reply );
		$wikiPage = $this->wikiPageFactory->newFromID( $reply->getId() );
		$status = $this->doEditContent(
			$wikiPage,
			new WikitextContent( $wikitext ),
			$user,
			EDIT_UPDATE | EDIT_SUPPRESS_RC
		);

		if ( $status->isOK() ) {
			$this->hookContainer->run(
				'CommentStreamsUpdateEntity', [
					$reply, $user, $oldText, $wikitext
				]
			);
		}

		return $status->isOK();
	}

	/**
	 * @param Comment $comment
	 * @param Authority $actor
	 * @return bool
	 */
	public function deleteComment( Comment $comment, Authority $actor ): bool {
		$wikiPage = $this->wikiPageFactory->newFromID( $comment->getId() );
		if ( !$wikiPage ) {
			$this->logger->error( __METHOD__ . ': Could not find wiki page for comment ID {id}', [
				'id' => $comment->getId()
			] );
			return false;
		}
		// must save page ID before deleting page
		$pageid = $wikiPage->getId();

		$deletePage = $this->deletePageFactory->newDeletePage(
			$wikiPage->getTitle()->toPageIdentity(), $actor
		);
		$deletePage->setSuppress( true );
		$status = $deletePage->deleteIfAllowed( 'comment deleted' );

		if ( !$status->isGood() ) {
			return false;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		$res = $dbw->delete(
			'cs_comments',
			[
				'cst_c_comment_page_id' => $pageid
			],
			__METHOD__
		);

		if ( !$res ) {
			return false;
		}
		$this->hookContainer->run( 'CommentStreamsDeleteEntity', [ $comment, $actor->getUser() ] );
		return $res;
	}

	/**
	 * @param Reply $reply
	 * @param Authority $actor
	 * @return bool
	 */
	public function deleteReply( Reply $reply, Authority $actor ): bool {
		$wikiPage = $this->wikiPageFactory->newFromID( $reply->getId() );
		if ( !$wikiPage ) {
			$this->logger->error( __METHOD__ . ': Could not find wiki page for reply ID {id}', [
				'id' => $reply->getId()
			] );
			return false;
		}
		// must save page ID before deleting page
		$pageid = $wikiPage->getId();

		$deletePage = $this->deletePageFactory->newDeletePage(
			$wikiPage->getTitle()->toPageIdentity(), $actor
		);
		$deletePage->setSuppress( true );
		$status = $deletePage->deleteIfAllowed( 'reply deleted' );

		if ( !$status->isGood() ) {
			return false;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		$res = $dbw->delete(
			'cs_replies',
			[
				'cst_r_reply_page_id' => $pageid
			],
			__METHOD__
		);

		if ( !$res ) {
			return false;
		}
		$this->hookContainer->run( 'CommentStreamsDeleteEntity', [ $reply, $actor->getUser() ] );
		return $res;
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
	): bool {
		$dbw = $this->getDBConnection( DB_PRIMARY );
		return $dbw->upsert(
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
	 * @param AbstractComment $comment
	 * @param UserIdentity $user
	 * @return int -1, 0, or 1
	 */
	public function getVote( AbstractComment $comment, UserIdentity $user ): int {
		return $this->voteHelper->getVote( $comment, $user );
	}

	/**
	 * @param AbstractComment $comment
	 * @return int
	 */
	public function getNumUpVotes( AbstractComment $comment ): int {
		return $this->voteHelper->getNumUpVotes( $comment );
	}

	/**
	 * @param AbstractComment $comment
	 * @return int
	 */
	public function getNumDownVotes( AbstractComment $comment ): int {
		return $this->voteHelper->getNumDownVotes( $comment );
	}

	/**
	 * @param AbstractComment $comment
	 * @param int $vote
	 * @param UserIdentity $user
	 * @return bool true for OK, false for error
	 */
	public function vote( AbstractComment $comment, int $vote, UserIdentity $user ): bool {
		$wikiPage = $this->wikiPageFactory->newFromID( $comment->getId() );
		if ( !$wikiPage ) {
			return false;
		}
		if ( $this->voteHelper->vote( $comment, $vote, $user ) ) {
			$this->smwInterface->update( $wikiPage->getTitle() );
			return true;
		}
		return false;
	}

	/**
	 * @param AbstractComment $comment
	 * @param UserIdentity $user
	 * @return bool true for OK, false for error
	 */
	public function watch( AbstractComment $comment, UserIdentity $user ): bool {
		return $this->watchHelper->watch( $comment, $user );
	}

	/**
	 * @param AbstractComment $comment
	 * @param UserIdentity $user
	 * @return bool true for OK, false for error
	 */
	public function unwatch( AbstractComment $comment, UserIdentity $user ): bool {
		return $this->watchHelper->unwatch( $comment, $user );
	}

	/**
	 * @param AbstractComment $comment
	 * @param UserIdentity $user
	 * @param int $fromdb DB_PRIMARY or DB_REPLICA
	 * @return bool database true for OK, false for error
	 */
	public function isWatching( AbstractComment $comment, UserIdentity $user, int $fromdb = DB_REPLICA ): bool {
		return $this->watchHelper->isWatching( $comment, $user, $fromdb );
	}

	/**
	 * @param AbstractComment $comment
	 * @return User[] array of users indexed by user ID
	 */
	public function getWatchers( AbstractComment $comment ): array {
		return $this->watchHelper->getWatchers( $comment );
	}

	/**
	 * @param AbstractComment $comment
	 * @return string
	 */
	public function getWikitext( AbstractComment $comment ): string {
		$wikiPage = $this->wikiPageFactory->newFromID( $comment->getId() );
		if ( !$wikiPage ) {
			return '';
		}
		$wikitext = $wikiPage->getContent( RevisionRecord::RAW )->getWikitextForTransclusion();
		if ( $comment instanceof Comment ) {
			return $this->removeAnnotations( $wikitext, $comment->getTitle() );
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

	/**
	 * Internal utility to determine if the page exists and is a comment or reply
	 *
	 * @param WikiPage $wikiPage page to check
	 * @return bool Page exists and is managed by CommentStreams
	 */
	private function isValidAbstractComment( WikiPage $wikiPage ): bool {
		return $wikiPage->getTitle()->getNamespace() === NS_COMMENTSTREAMS && $wikiPage->exists();
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param Content $content
	 * @param Authority $authority
	 * @param int $flags
	 * @return Status
	 */
	private function doEditContent(
		WikiPage $wikiPage,
		Content $content,
		Authority $authority,
		int $flags
	): Status {
		$updater = $wikiPage->newPageUpdater( $authority );
		$updater->setContent( SlotRecord::MAIN, $content );
		$summary = CommentStoreComment::newUnsavedComment( '' );
		$updater->saveRevision( $summary, $flags );
		return $updater->getStatus();
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param Authority $authority
	 * @return int|null
	 * @throws MWException
	 */
	public function createEmptyPage(
		WikiPage $wikiPage,
		Authority $authority
	) {
		$result = $this->doEditContent( $wikiPage, new WikitextContent( '' ), $authority, EDIT_NEW );
		if ( $result->isOK() ) {
			return $result->getValue()['revision-record']->getId();
		}
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getHistoryHandler(): ?\JsonSerializable {
		return new UrlHistoryHandler( '?curid={id}&action=history' );
	}
}
