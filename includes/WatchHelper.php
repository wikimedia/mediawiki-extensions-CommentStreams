<?php

namespace MediaWiki\Extension\CommentStreams;

use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\ILoadBalancer;

class WatchHelper {
	public function __construct(
		private readonly ILoadBalancer $lb,
		private readonly UserFactory $userFactory
	) {
	}

	/**
	 * @param AbstractComment $comment
	 * @param UserIdentity $user
	 * @return bool true for OK, false for error
	 */
	public function watch( AbstractComment $comment, UserIdentity $user ): bool {
		if ( $this->isWatching( $comment, $user, DB_PRIMARY ) ) {
			return true;
		}

		return $this->lb->getConnection( DB_PRIMARY )->insert(
			'cs_watchlist',
			[
				'cst_wl_comment_id' => $comment->getId(),
				'cst_wl_user_id' => $user->getId(),
			],
			__METHOD__
		);
	}

	/**
	 * @param AbstractComment $comment
	 * @param UserIdentity $user
	 * @return bool true for OK, false for error
	 */
	public function unwatch( AbstractComment $comment, UserIdentity $user ): bool {
		if ( !$this->isWatching( $comment, $user, DB_PRIMARY ) ) {
			return true;
		}

		return $this->lb->getConnection( DB_PRIMARY )->delete(
			'cs_watchlist',
			[
				'cst_wl_comment_id' => $comment->getId(),
				'cst_wl_user_id' => $user->getId()
			],
			__METHOD__
		);
	}

	/**
	 * @param AbstractComment $comment
	 * @param UserIdentity $user
	 * @param int $fromdb DB_PRIMARY or DB_REPLICA
	 * @return bool database true for OK, false for error
	 */
	public function isWatching( AbstractComment $comment, UserIdentity $user, int $fromdb = DB_REPLICA ): bool {
		$count = $this->lb->getConnection( $fromdb )
			->newSelectQueryBuilder()
			->select( 'cst_wl_comment_id' )
			->from( 'cs_watchlist' )
			->where( [
				'cst_wl_comment_id' => $comment->getId(),
				'cst_wl_user_id' => $user->getId(),
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
		return $count > 0;
	}

	/**
	 * @param AbstractComment $comment
	 * @return User[] array of users indexed by user ID
	 */
	public function getWatchers( AbstractComment $comment ): array {
		$result = $this->lb->getConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->select( 'cst_wl_user_id' )
			->from( 'cs_watchlist' )
			->where( [
				'cst_wl_comment_id' => $comment->getId()
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$users = [];
		foreach ( $result as $row ) {
			$userId = (int)$row->cst_wl_user_id;
			if ( !$userId ) {
				continue;
			}
			$user = $this->userFactory->newFromId( $userId );
			$users[$userId] = $user;
		}

		return $users;
	}
}
