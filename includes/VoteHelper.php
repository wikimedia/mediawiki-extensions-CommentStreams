<?php

namespace MediaWiki\Extension\CommentStreams;

use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\ILoadBalancer;

class VoteHelper {
	public function __construct(
		private readonly ILoadBalancer $lb
	) {
	}

	/**
	 * @param AbstractComment $comment
	 * @param UserIdentity $user
	 * @return int -1, 0, or 1
	 */
	public function getVote( AbstractComment $comment, UserIdentity $user ): int {
		$result = $this->lb->getConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->select( 'cst_v_vote' )
			->from( 'cs_votes' )
			->where( [
				'cst_v_comment_id' => $comment->getId(),
				'cst_v_user_id' => $user->getId(),
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
	 * @param AbstractComment $comment
	 * @return int
	 */
	public function getNumUpVotes( AbstractComment $comment ): int {
		return $this->getNumVotes( $comment->getId(), true );
	}

	/**
	 * @param AbstractComment $comment
	 * @return int
	 */
	public function getNumDownVotes( AbstractComment $comment ): int {
		return $this->getNumVotes( $comment->getId(), false );
	}

	/**
	 * @param int $id
	 * @param bool $up
	 * @return int
	 */
	private function getNumVotes( int $id, bool $up ): int {
		return $this->lb->getConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->from( 'cs_votes' )
			->where( [
				'cst_v_comment_id' => $id,
				'cst_v_vote' => $up ? 1 : -1
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	/**
	 * @param AbstractComment $comment
	 * @param int $vote
	 * @param UserIdentity $user
	 * @return bool true for OK, false for error
	 */
	public function vote( AbstractComment $comment, int $vote, UserIdentity $user ): bool {
		$dbw = $this->lb->getConnection( DB_PRIMARY );
		$result = $dbw->newSelectQueryBuilder()
			->select( 'cst_v_vote' )
			->from( 'cs_votes' )
			->where( [
				'cst_v_comment_id' => $comment->getId(),
				'cst_v_user_id' => $user->getId(),
			] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $result ) {
			if ( $vote === (int)$result->cst_v_vote ) {
				return true;
			}
			if ( $vote === 1 || $vote === -1 ) {
				$res = $dbw->update(
					'cs_votes',
					[
						'cst_v_vote' => $vote
					],
					[
						'cst_v_comment_id' => $comment->getId(),
						'cst_v_user_id' => $user->getId()
					],
					__METHOD__
				);
			} else {
				$res = $dbw->delete(
					'cs_votes',
					[
						'cst_v_comment_id' => $comment->getId(),
						'cst_v_user_id' => $user->getId()
					],
					__METHOD__
				);
			}
			return $res;
		}

		if ( $vote === 0 ) {
			return true;
		}

		return $dbw->insert(
			'cs_votes',
			[
				'cst_v_comment_id' => $comment->getId(),
				'cst_v_user_id' => $user->getId(),
				'cst_v_vote' => $vote
			],
			__METHOD__
		);
	}
}
