<?php

namespace MediaWiki\Extension\CommentStreams\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommentStreams\AbstractComment;
use MediaWiki\Extension\CommentStreams\ICommentStreamsStore;
use MediaWiki\Extension\CommentStreams\Store\TalkPageStore;
use MediaWiki\Language\Language;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use Wikimedia\ParamValidator\ParamValidator;

class TaskPageStoreCommentHistoryHandler extends SimpleHandler {

	/**
	 * @param ICommentStreamsStore $store
	 * @param PermissionManager $permissionManager
	 * @param Language $language
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		private readonly ICommentStreamsStore $store,
		private readonly PermissionManager $permissionManager,
		private readonly Language $language,
		private readonly UserFactory $userFactory
	) {
	}

	/**
	 * @return \MediaWiki\Rest\Response
	 * @throws HttpException
	 */
	public function execute() {
		if ( !( $this->store instanceof TalkPageStore ) ) {
			throw new HttpException( 'storenotsupported', 400 );
		}
		$commentId = $this->getValidatedParams()['comment'];
		$entity = $this->getEntity( $commentId );
		if ( !$commentId || !$entity ) {
			throw new HttpException( 'commentnotfound', 404 );
		}
		$relatedPage = $entity->getAssociatedPage();
		$user = RequestContext::getMain()->getUser();
		if ( !$relatedPage || !$this->permissionManager->userCan( 'read', $user, $relatedPage ) ) {
			throw new HttpException( 'permissiondenied', 403 );
		}
		$history = $this->store->getHistory( $entity );
		$history = array_map( function ( $historyItem ) use ( $user ) {
			$userTime = $this->language->userTimeAndDate(
				$historyItem['timestamp'], $user, [ 'timecorrection' => true ]
			);
			$actor = $this->userFactory->newFromName( $historyItem['actor'] );
			if ( !$actor ) {
				return null;
			}
			return [
				'timestamp' => $userTime,
				'actor' => $actor->getRealName() ?: $actor->getName(),
				'text' => $historyItem['text']
			];
		}, $history );

		return $this->getResponseFactory()->createJson( array_filter( $history ) );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'comment' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @param int $id
	 * @return AbstractComment|null
	 */
	private function getEntity( int $id ): ?AbstractComment {
		$comment = $this->store->getComment( $id );
		if ( $comment ) {
			return $comment;
		}
		return $this->store->getReply( $id );
	}
}
