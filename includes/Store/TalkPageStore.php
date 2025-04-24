<?php

namespace MediaWiki\Extension\CommentStreams\Store;

use JsonSerializable;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\JsonContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommentStreams\AbstractComment;
use MediaWiki\Extension\CommentStreams\Comment;
use MediaWiki\Extension\CommentStreams\HistoryHandler\JSHistoryHandler;
use MediaWiki\Extension\CommentStreams\ICommentStreamsStore;
use MediaWiki\Extension\CommentStreams\Reply;
use MediaWiki\Extension\CommentStreams\VoteHelper;
use MediaWiki\Extension\CommentStreams\WatchHelper;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Json\FormatJson;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdateStatus;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Rdbms\ILoadBalancer;

class TalkPageStore implements ICommentStreamsStore {

	/**
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param RevisionLookup $revisionLookup
	 * @param WikiPageFactory $wikiPageFactory
	 * @param LoggerInterface $logger
	 * @param UserFactory $userFactory
	 * @param UserGroupManager $userGroupManager
	 * @param NamespaceInfo $nsInfo
	 * @param HookContainer $hookContainer
	 * @param WatchHelper|null $watchHelper
	 * @param VoteHelper|null $voteHelper
	 * @param BagOStuff $cache
	 */
	public function __construct(
		private readonly ILoadBalancer $lb,
		private readonly TitleFactory $titleFactory,
		private readonly RevisionLookup $revisionLookup,
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly LoggerInterface $logger,
		private readonly UserFactory $userFactory,
		private readonly UserGroupManager $userGroupManager,
		private readonly NamespaceInfo $nsInfo,
		private readonly HookContainer $hookContainer,
		private ?WatchHelper $watchHelper = null,
		private ?VoteHelper $voteHelper = null,
		private readonly BagOStuff $cache = new HashBagOStuff()
	) {
		$this->watchHelper = $this->watchHelper ?? new WatchHelper( $this->lb, $this->userFactory );
		$this->voteHelper = $this->voteHelper ?? new VoteHelper( $this->lb );
	}

	/**
	 * @inheritDoc
	 */
	public function getComment( int $id ): ?Comment {
		$data = $this->getEntityData( $id );
		if ( !$data || $data['type'] !== 'comment' ) {
			return null;
		}

		$associatedPage = $this->cache->get( $this->makeCacheKeyForPageMapping( $id ) );
		if ( !$associatedPage ) {
			return null;
		}
		$author = $this->userFactory->newFromName( $data['author'] );
		return new Comment(
			$id,
			$data['title'],
			$data['block'],
			$associatedPage,
			$author,
			isset( $data['lastEditor'] ) ? $this->userFactory->newFromName( $data['lastEditor'] ) : $author,
			MWTimestamp::getInstance( $data['created'] ),
			MWTimestamp::getInstance( $data['modified'] ),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getReply( int $id ): ?Reply {
		$data = $this->getEntityData( $id );
		if ( !$data ) {
			return null;
		}
		$author = $this->userFactory->newFromName( $data['author'] );
		return new Reply(
			$this->getComment( $data['parent'] ),
			$id,
			$author,
			isset( $data['lastEditor'] ) ? $this->userFactory->newFromName( $data['lastEditor'] ) : $author,
			MWTimestamp::getInstance( $data['created'] ),
			MWTimestamp::getInstance( $data['modified'] ),
		);
	}

	/**
	 * @param string $action
	 * @param User $user
	 * @param AbstractComment $comment
	 * @return bool
	 */
	public function userCan( string $action, User $user, AbstractComment $comment ): bool {
		$author = $comment->getAuthor();
		if ( $user->getId() === $author->getId() ) {
			// User can do anything to own comments
			return true;
		}
		if ( in_array( 'sysop', $this->userGroupManager->getUserGroups( $user ) ) ) {
			// Sysops can do anything on anyone comments
			return true;
		}
		return false;
	}

	/**
	 * @param PageIdentity $page
	 * @return array|Comment[]
	 */
	public function getAssociatedComments( PageIdentity $page ): array {
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title->isTalkPage() ) {
			$title = $title->getTalkPageIfDefined();
		}
		if ( !$title ) {
			return [];
		}
		$data = $this->getPageData( $title );
		if ( !$data ) {
			return [];
		}
		$comments = [];
		foreach ( $data as $commentId => $commentData ) {
			$comments[] = $this->getComment( $commentId );
		}
		return array_filter( $comments );
	}

	/**
	 * @param Comment $parent
	 * @return array|Reply[]
	 */
	public function getReplies( Comment $parent ): array {
		$pageData = $this->getPageData( $parent->getAssociatedPage()->getTalkPageIfDefined() );
		$replies = [];
		foreach ( $pageData as $commentId => $commentData ) {
			if ( $commentData['type'] === 'reply' && $commentData['parent'] === $parent->getId() ) {
				$replies[] = $this->getReply( $commentId );
			}
		}
		return array_filter( $replies );
	}

	/**
	 * @param Comment $comment
	 * @return int
	 */
	public function getNumReplies( Comment $comment ): int {
		$pageData = $this->getPageData( $comment->getAssociatedPage()->getTalkPageIfDefined() );
		return count( array_filter( $pageData, static fn ( $commentData ) =>
			$commentData['type'] === 'reply' && $commentData['parent'] === $comment->getId() )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function insertComment(
		User $user, string $wikitext, int $assocPageId, string $commentTitle, ?string $commentBlockName
	): ?Comment {
		$title = $this->titleFactory->newFromID( $assocPageId );
		if ( !$title ) {
			$this->logger->error( 'Tried to add a comment on a non-existing page', [ 'page_id' => $assocPageId ] );
			return null;
		}
		$entity = $this->insertEntity(
			$title, $user, 'comment', [
				'title' => $commentTitle,
				'block' => $commentBlockName,
				'wikitext' => $wikitext
			]
		);
		if ( $entity instanceof Comment ) {
			$this->watch( $entity, $user );
			return $entity;
		}
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function insertReply( User $user, string $wikitext, Comment $parent ): ?Reply {
		$entity = $this->insertEntity(
			$parent->getAssociatedPage(), $user, 'reply', [
				'parent' => $parent->getId(),
				'wikitext' => $wikitext
			]
		);
		if ( $entity instanceof Reply ) {
			$this->watch( $parent, $user );
			return $entity;
		}
		return null;
	}

	private function insertEntity(
		PageIdentity $associatedPage, Authority $actor, string $type, array $data
	): ?AbstractComment {
		if ( !$associatedPage->exists() ) {
			$this->logger->error(
				'Tried to add a comment on a non-existing page', [ 'page_id' => $associatedPage->getId() ]
			);
			return null;
		}
		$talkPage = $associatedPage->getTalkPageIfDefined();
		if ( !$talkPage ) {
			$this->logger->error(
				'Tried to add a comment on a page without a talk page',
				[ 'page_id' => $associatedPage->getId() ]
			);
			return null;
		}
		$pageData = $this->getPageData( $talkPage );
		if ( !$pageData ) {
			$pageData = [];
		}

		$entityId = $this->insertAssociation( $associatedPage );
		$pageData[$entityId] = array_merge( [
			'type' => $type,
			'author' => $actor->getName(),
			'created' => wfTimestampNow(),
			'modified' => wfTimestampNow(),
		], $data );
		$status = $this->storeToPage( $talkPage, $pageData, $actor );
		if ( $status->isGood() ) {
			$this->cache->set( $this->makeCacheKeyForPage( $talkPage ), $pageData );
			$entity = $type === 'comment' ? $this->getComment( $entityId ) : $this->getReply( $entityId );
			if ( $entity ) {
				$this->cache->set( $this->makeCacheKeyForId( $entityId ), $pageData[$entityId] );
			}
			$this->hookContainer->run(
				'CommentStreamsInsertEntity', [
					$entity, $actor, $associatedPage, $type, $data['wikitext']
				]
			);
			return $entity;
		} else {
			$this->rollbackAssociation( $entityId );
			return null;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function updateComment( Comment $comment, string $commentTitle, string $wikitext, User $user ): bool {
		if ( $this->updateEntity( $comment, $commentTitle, $wikitext, $user ) ) {
			$this->watch( $comment, $user );
			return true;
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function updateReply( Reply $reply, string $wikitext, User $user ): bool {
		if ( $this->updateEntity( $reply, '', $wikitext, $user ) ) {
			$this->watch( $reply->getParent(), $user );
			return true;
		}

		return false;
	}

	private function updateEntity( AbstractComment $entity, string $title, string $text, Authority $actor ): bool {
		$talkPage = $entity->getAssociatedPage()->getTalkPageIfDefined();
		if ( !$talkPage ) {
			return false;
		}
		$pageData = $this->getPageData( $talkPage );
		if ( !$pageData || !isset( $pageData[$entity->getId()] ) ) {
			return false;
		}
		if ( $pageData[$entity->getId()]['type'] === 'comment' ) {
			$pageData[$entity->getId()]['title'] = $title;
		}
		$pageData[$entity->getId()]['modified'] = wfTimestampNow();
		$pageData[$entity->getId()]['wikitext'] = $text;
		$pageData[$entity->getId()]['lastEditor'] = $actor->getName();
		$oldText = $this->getWikitext( $entity );
		$pageData[$entity->getId()]['baseRevision'] = $talkPage->getLatestRevID();
		if ( $this->storeToPage( $talkPage, $pageData, $actor )->isGood() ) {
			$this->cache->set( $this->makeCacheKeyForPage( $talkPage ), $pageData );
			$this->cache->set( $this->makeCacheKeyForId( $entity->getId() ), $pageData[$entity->getId()] );
			$this->hookContainer->run(
				'CommentStreamsUpdateEntity', [
					$entity, $actor, $oldText, $text
				]
			);
			return true;
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function deleteComment( Comment $comment, Authority $actor ): bool {
		return $this->deleteEntity( $comment, $actor );
	}

	/**
	 * @inheritDoc
	 */
	public function deleteReply( Reply $reply, Authority $actor ): bool {
		return $this->deleteEntity( $reply, $actor );
	}

	/**
	 * @param AbstractComment $entity
	 * @param Authority $actor
	 * @return bool
	 */
	private function deleteEntity( AbstractComment $entity, Authority $actor ): bool {
		$talkPage = $entity->getAssociatedPage()->getTalkPageIfDefined();
		if ( !$talkPage ) {
			return false;
		}
		$pageData = $this->getPageData( $talkPage );
		if ( !$pageData || !isset( $pageData[$entity->getId()] ) ) {
			// Entity already doesnt exist
			return true;
		}
		unset( $pageData[$entity->getId()] );
		if ( $this->storeToPage( $talkPage, $pageData, $actor )->isGood() ) {
			$this->cache->set( $this->makeCacheKeyForPage( $talkPage ), $pageData );
			$this->cache->delete( $this->makeCacheKeyForId( $entity->getId() ) );
			$this->hookContainer->run(
				'CommentStreamsDeleteEntity', [
					$entity, $actor
				]
			);
			return true;
		}
		return false;
	}

	/**
	 * @param int $pageId
	 * @param int $assocPageId
	 * @param string $commentTitle
	 * @param string|null $blockName
	 * @return bool
	 */
	public function upsertCommentMetadata(
		int $pageId, int $assocPageId, string $commentTitle, ?string $blockName
	): bool {
		throw new RuntimeException( 'Not supported' );
	}

	/**
	 * @param int $pageId
	 * @param int $commentPageId
	 * @return mixed
	 */
	public function upsertReplyMetadata( int $pageId, int $commentPageId ) {
		throw new RuntimeException( 'Not supported' );
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
		return $this->voteHelper->vote( $comment, $vote, $user );
	}

	/**
	 * @param Title $page
	 * @return void
	 */
	public function updateAssociationIndex( Title $page ) {
		$data = $this->getPageData( $page, true );
		if ( !$data ) {
			return;
		}
		$associated = $this->titleFactory->castFromLinkTarget( $this->nsInfo->getSubjectPage( $page ) );
		// Clear associations for this page
		$this->lb->getConnection( DB_PRIMARY )->newDeleteQueryBuilder()
			->deleteFrom( 'cs_associated_pages' )
			->where( [ 'csa_page_id' => $associated->getId() ] )
			->caller( __METHOD__ )
			->execute();

		// Reassociate
		$parentMapping = [];
		$newCommentData = [];
		foreach ( $data as $commentId => $commentData ) {
			if ( $commentData['type'] === 'comment' ) {
				$parentMapping[$commentId] = $this->insertAssociation( $associated );
				$newCommentData[$parentMapping[$commentId]] = $commentData;
			}
		}
		foreach ( $data as $commentData ) {
			if ( $commentData['type'] === 'reply' ) {
				$newId = $this->insertAssociation( $associated );
				if ( isset( $parentMapping[$commentData['parent']] ) ) {
					$commentData['parent'] = $parentMapping[$commentData['parent']];
				}
				$newCommentData[$newId] = $commentData;
			}
		}
		// Store reassociation
		$status = $this->storeToPage(
			$page, $newCommentData, User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] )
		);

		if ( !$status->isGood() ) {
			$this->logger->error( 'Failed to update association index after import', [
				'page' => $page->getPrefixedText()
			] );
		} else {
			$this->cache->set( $this->makeCacheKeyForPage( $page ), $newCommentData );
		}
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
		$talkPage = $comment->getAssociatedPage()->getTalkPageIfDefined();
		if ( !$talkPage ) {
			return '';
		}
		$pageData = $this->getPageData( $talkPage );
		if ( !$pageData || !isset( $pageData[$comment->getId()] ) ) {
			return '';
		}
		return $pageData[$comment->getId()]['wikitext'] ?? '';
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	private function getEntityData( int $id ): ?array {
		$isCached = $this->cache->hasKey( $this->makeCacheKeyForId( $id ) );
		if ( !$isCached ) {
			$this->load( $id );
		}
		return $this->cache->get( $this->makeCacheKeyForId( $id ) );
	}

	/**
	 * @param int $id
	 * @return void
	 */
	private function load( int $id ): void {
		$this->cache->set( $this->makeCacheKeyForId( $id ), null );

		$associatedPage = $this->getAssociatedPage( $id );
		if ( !$associatedPage ) {
			return;
		}
		$talkPage = $associatedPage->getTalkPageIfDefined();
		if ( !$talkPage ) {
			return;
		}
		$isPageCached = $this->cache->hasKey( $this->makeCacheKeyForPage( $talkPage ) );
		if ( !$isPageCached ) {
			$this->loadPage( $talkPage );
		}

		$data = $this->cache->get( $this->makeCacheKeyForPage( $talkPage ) );
		foreach ( $data as $commentId => $commentData ) {
			$this->cache->set( $this->makeCacheKeyForPageMapping( $commentId ), $associatedPage );
			$this->cache->set(
				$this->makeCacheKeyForId( $commentId ),
				$commentData
			);
		}
	}

	/**
	 * @param PageIdentity $page
	 * @return void
	 */
	private function loadPage( PageIdentity $page ): void {
		$this->cache->set( $this->makeCacheKeyForPage( $page ), null );
		$revision = $this->revisionLookup->getRevisionByTitle( $page );
		$data = $this->rawLoadPage( $revision );
		if ( !$data ) {
			return;
		}
		$this->cache->set( $this->makeCacheKeyForPage( $page ), $data );
	}

	/**
	 * @param RevisionRecord|null $revision
	 * @return array|null
	 */
	private function rawLoadPage( ?RevisionRecord $revision ): ?array {
		if ( !$revision || !$revision->hasSlot( SLOT_COMMENTSTREAMS_COMMENTS ) ) {
			return null;
		}
		$content = $revision->getContent( SLOT_COMMENTSTREAMS_COMMENTS );
		if ( !( $content instanceof JsonContent ) ) {
			return null;
		}
		$text = $content->getText();
		$data = FormatJson::parse( $text, FormatJson::FORCE_ASSOC );
		if ( !$data->isGood() || !$this->isValidCommentJson( $data->getValue() ) ) {
			return null;
		}
		return $data->getValue();
	}

	/**
	 * @param int $id
	 * @return PageIdentity|null
	 */
	private function getAssociatedPage( int $id ): ?PageIdentity {
		$db = $this->lb->getConnection( DB_REPLICA );
		$row = $db->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title' ] )
			->from( 'cs_associated_pages', 'csa' )
			->from( 'page', 'p' )
			->where( [ 'csa_comment_id' => $id ] )
			->join( 'page', 'p', [ 'csa_page_id=page_id' ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row ) {
			return null;
		}
		$title = $this->titleFactory->newFromRow( $row );
		if ( !$title->exists() ) {
			return null;
		}
		return $title;
	}

	/**
	 * @param int $id
	 * @return string
	 */
	private function makeCacheKeyForId( int $id ): string {
		return $this->cache->makeKey( 'entity', $id );
	}

	/**
	 * @param int $id
	 * @return string
	 */
	private function makeCacheKeyForPageMapping( int $id ): string {
		return $this->cache->makeKey( 'page-mapping', $id );
	}

	/**
	 * @param PageIdentity $page
	 * @return string
	 */
	private function makeCacheKeyForPage( PageIdentity $page ): string {
		return $this->cache->makeKey( 'page', $page->getId() );
	}

	/**
	 * @param mixed $value
	 * @return bool
	 */
	private function isValidCommentJson( mixed $value ) {
		if ( !is_array( $value ) ) {
			return false;
		}
		// Check if it is an array with int keys (not sequential) and array values
		foreach ( $value as $key => $subValue ) {
			if ( !is_int( $key ) || !is_array( $subValue ) ) {
				return false;
			}
			$keys = [ 'type', 'author', 'created', 'modified', 'wikitext' ];
			foreach ( $keys as $subKey ) {
				if ( !array_key_exists( $subKey, $subValue ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param Title $talkPage
	 * @param bool|null $recache
	 * @return array|null
	 */
	private function getPageData( Title $talkPage, ?bool $recache = false ): ?array {
		$isPageCached = $this->cache->hasKey( $this->makeCacheKeyForPage( $talkPage ) );
		if ( !$isPageCached || $recache ) {
			$this->loadPage( $talkPage );
		}
		return $this->cache->get( $this->makeCacheKeyForPage( $talkPage ) );
	}

	/**
	 * @param PageIdentity $page
	 * @return int
	 */
	private function insertAssociation( PageIdentity $page ): int {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->newInsertQueryBuilder()
			->insertInto( 'cs_associated_pages' )
			->row( [ 'csa_page_id' => $page->getId() ] )
			->caller( __METHOD__ )
			->execute();
		return $db->insertId();
	}

	/**
	 * @param int $commentId
	 * @return void
	 */
	private function rollbackAssociation( int $commentId ) {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->newDeleteQueryBuilder()
			->deleteFrom( 'cs_associated_pages' )
			->where( [ 'csa_comment_id' => $commentId ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param Title $talkPage
	 * @param array $pageData
	 * @param Authority $actor
	 * @return PageUpdateStatus
	 */
	private function storeToPage( Title $talkPage, array $pageData, Authority $actor ): PageUpdateStatus {
		$content = new JsonContent( FormatJson::encode( $pageData ) );
		$wikiPage = $this->wikiPageFactory->newFromTitle( $talkPage );
		$updater = $wikiPage->newPageUpdater( $actor );
		$updater->setContent( SLOT_COMMENTSTREAMS_COMMENTS, $content );
		if ( !$talkPage->exists() ) {
			// Page must have `main` slot
			$updater->setContent( SlotRecord::MAIN, new WikitextContent( '' ) );
		}
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( '' ), EDIT_SUPPRESS_RC
		);
		return $updater->getStatus();
	}

	/**
	 * @inheritDoc
	 */
	public function getHistoryHandler(): ?JsonSerializable {
		return new JSHistoryHandler( 'cs.talkPageStore.historyHandler.init', [
			'ext.commentStreams.talkPageStore.history'
		] );
	}

	/**
	 * @param AbstractComment $entity
	 * @param array $data
	 * @param Authority $actor
	 * @return void
	 * @internal For use by migrations
	 */
	public function forceSetEntityData( AbstractComment $entity, array $data, Authority $actor ) {
		$talkPage = $entity->getAssociatedPage()->getTalkPageIfDefined();
		if ( !$talkPage ) {
			return;
		}
		$pageData = $this->getPageData( $talkPage, true );
		if ( !$pageData ) {
			return;
		}
		if ( !isset( $pageData[$entity->getId()] ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			if ( $value === null ) {
				unset( $pageData[$entity->getId()][$key] );
			} else {
				$pageData[$entity->getId()][$key] = $value;
			}
		}
		$status = $this->storeToPage( $talkPage, $pageData, $actor );
		if ( $status->isGood() ) {
			$this->cache->set( $this->makeCacheKeyForPage( $talkPage ), $pageData );
		}
	}

	/**
	 * @param AbstractComment $entity
	 * @return array
	 */
	public function getHistory( AbstractComment $entity ): array {
		$items = [];
		$talkPage = $entity->getAssociatedPage()->getTalkPageIfDefined();
		if ( !$talkPage ) {
			return $items;
		}
		$revision = $this->revisionLookup->getRevisionByTitle( $talkPage );
		$this->getHistoryItem( $entity, $revision, $items );
		return $items;
	}

	/**
	 * @param AbstractComment $entity
	 * @param RevisionRecord $revisionRecord
	 * @param array &$items
	 * @return void
	 */
	private function getHistoryItem( AbstractComment $entity, RevisionRecord $revisionRecord, array &$items ) {
		$data = $this->rawLoadPage( $revisionRecord );
		if ( !$data || !isset( $data[$entity->getId()] ) ) {
			return;
		}

		$itemData = $data[$entity->getId()];
		$items[$revisionRecord->getId()] = [
			'timestamp' => $itemData['modified'] ?? $itemData['created'],
			'actor' => $itemData['lastEditor'] ?? $itemData['author'],
			'text' => $itemData['wikitext']
		];
		if ( isset( $itemData['baseRevision'] ) ) {
			$baseRevision = $this->revisionLookup->getRevisionById( $itemData['baseRevision'] );
			if ( !$baseRevision || $baseRevision->getPage()->getId() !== $revisionRecord->getPage()->getId() ) {
				return;
			}
			$this->getHistoryItem( $entity, $baseRevision, $items );
		}
	}

}
