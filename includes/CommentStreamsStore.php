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

namespace MediaWiki\Extension\CommentStreams;

use FatalError;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserFactory;
use MWException;
use Title;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;
use WikiPage;
use WikitextContent;

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
	 * @param ILoadBalancer $loadBalancer
	 * @param PermissionManager $permissionManager
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		PermissionManager $permissionManager,
		UserFactory $userFactory
	) {
		$this->loadBalancer = $loadBalancer;
		$this->permissionManager = $permissionManager;
		$this->userFactory = $userFactory;
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
	 * @param User $user
	 * @param string $wikitext
	 * @param ?string $comment_block_id
	 * @param int $assoc_page_id
	 * @param ?int $parent_page_id
	 * @param ?string $comment_title
	 * @return ?WikiPage
	 * @throws MWException
	 */
	public function insertComment(
		User $user,
		string $wikitext,
		?string $comment_block_id,
		int $assoc_page_id,
		?int $parent_page_id,
		?string $comment_title
	): ?WikiPage {
		$annotated_wikitext = $this->addAnnotations( $wikitext, $comment_title );
		$content = new WikitextContent( $annotated_wikitext );

		$success = false;
		$index = wfRandomString();
		do {
			$title = Title::newFromText( $index, NS_COMMENTSTREAMS );
			$wikipage = new WikiPage( $title );
			$deleted = CommentStreamsUtils::hasDeletedEdits( $title );
			if ( !$deleted && !$title->exists() ) {
				if ( !$this->permissionManager->userCan( 'cs-comment', $user, $title ) ) {
					return null;
				}
				$status = CommentStreamsUtils::doEditContent(
					$wikipage,
					$content,
					$user,
					EDIT_NEW | EDIT_SUPPRESS_RC
				);
				if ( !$status->isOK() && !$status->isGood() ) {
					if ( $status->getMessage()->getKey() == 'edit-already-exists' ) {
						$index = wfRandomString();
					} else {
						return null;
					}
				} else {
					$success = true;
				}
			} else {
				$index = wfRandomString();
			}
		} while ( !$success );

		$data = [
			'cst_page_id' => $wikipage->getId(),
			'cst_assoc_page_id' => $assoc_page_id,
			'cst_parent_page_id' => $parent_page_id,
			'cst_comment_title' => $comment_title
		];
		if ( $comment_title !== null ) {
			$data[ 'cst_id' ] = $comment_block_id;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		$result = $dbw->insert(
			'cs_comment_data',
			$data,
			__METHOD__
		);

		if ( !$result ) {
			return null;
		}

		return $wikipage;
	}

	/**
	 * @param int $id
	 * @return ?array
	 */
	public function getComment( int $id ): ?array {
		$dbr = $this->getDBConnection( DB_REPLICA );
		$result = $dbr->selectRow(
			'cs_comment_data',
			[
			'cst_id',
			'cst_assoc_page_id',
			'cst_parent_page_id',
			'cst_comment_title'
			],
			[
			'cst_page_id' => $id
			],
			__METHOD__
		);
		if ( $result ) {
			return [
				'comment_block_id' => $result->cst_id,
				'assoc_page_id' => (int)$result->cst_assoc_page_id,
				'parent_page_id' => $result->cst_parent_page_id ? (int)$result->cst_parent_page_id
					: null,
				'comment_title' => $result->cst_comment_title
			];
		}

		return null;
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 * @return IResultWrapper
	 */
	public function getCommentPages( int $limit, int $offset ): IResultWrapper {
		$dbr = $this->getDBConnection( DB_REPLICA );
		return $dbr->select(
			[
				'cs_comment_data',
				'page',
				'revision'
			],
			[
				'page_id'
			],
			[
				'cst_page_id = page_id',
				'page_latest = rev_id'
			],
			__METHOD__,
			[
				'ORDER BY' => 'rev_timestamp DESC' ,
				'LIMIT' => $limit,
				'OFFSET' => $offset
			]
		);
	}

	/**
	 * @param WikiPage $wikipage
	 * @param ?string $comment_title
	 * @param string $wikitext
	 * @param User $user
	 * @return bool
	 * @throws MWException
	 */
	public function updateComment(
		WikiPage $wikipage,
		?string $comment_title,
		string $wikitext,
		User $user
	): bool {
		$annotated_wikitext = $this->addAnnotations( $wikitext, $comment_title );
		$content = new WikitextContent( $annotated_wikitext );

		$status = CommentStreamsUtils::doEditContent(
			$wikipage,
			$content,
			$user,
			EDIT_UPDATE | EDIT_SUPPRESS_RC
		);
		if ( !$status->isOK() && !$status->isGood() ) {
			return false;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		return $dbw->update(
			'cs_comment_data',
			[
				'cst_comment_title' => $comment_title
			],
			[
				'cst_page_id' => $wikipage->getId()
			],
			__METHOD__
		);
	}

	/**
	 * @param WikiPage $wikipage
	 * @param User $deleter
	 * @return bool
	 * @throws FatalError
	 * @throws MWException
	 */
	public function deleteComment( WikiPage $wikipage, User $deleter ): bool {
		// must save page ID before deleting page
		$pageid = $wikipage->getId();

		$status = $wikipage->doDeleteArticleReal( 'comment deleted', $deleter, true );

		if ( !$status->isOK() && !$status->isGood() ) {
			return false;
		}

		$dbw = $this->getDBConnection( DB_PRIMARY );
		return $dbw->delete(
			'cs_comment_data',
			[
				'cst_page_id' => $pageid
			],
			__METHOD__
		);
	}

	/**
	 * @param int $id
	 * @return array
	 */
	public function getAssociatedComments( int $id ): array {
		$dbr = $this->getDBConnection( DB_REPLICA );
		$result = $dbr->select(
			'cs_comment_data',
			[
				'cst_page_id'
			],
			[
				'cst_assoc_page_id' => $id
			],
			__METHOD__
		);
		$comment_page_ids = [];
		foreach ( $result as $row ) {
			$comment_page_ids[] = $row->cst_page_id;
		}
		return $comment_page_ids;
	}

	/**
	 * @param int $id
	 * @return array
	 */
	public function getReplies( int $id ): array {
		$dbr = $this->getDBConnection( DB_REPLICA );
		$result = $dbr->select(
			'cs_comment_data',
			[
				'cst_page_id'
			],
			[
				'cst_parent_page_id' => $id
			],
			__METHOD__
		);
		$reply_ids = [];
		foreach ( $result as $row ) {
			$reply_ids[] = $row->cst_page_id;
		}
		return $reply_ids;
	}

	/**
	 * @param int $id
	 * @return int
	 */
	public function getNumReplies( int $id ): int {
		$dbr = $this->getDBConnection( DB_REPLICA );

		return $dbr->selectRowCount(
			'cs_comment_data',
			'*',
			[
				'cst_parent_page_id' => $id
			],
			__METHOD__
		);
	}

	/**
	 * @param int $page_id
	 * @param int $user_id
	 * @return int -1, 0, or 1
	 */
	public function getVote( int $page_id, int $user_id ): int {
		$dbr = $this->getDBConnection( DB_REPLICA );
		$result = $dbr->selectRow(
			'cs_votes',
			[
				'cst_v_vote'
			],
			[
				'cst_v_page_id' => $page_id,
				'cst_v_user_id' => $user_id
			],
			__METHOD__
		);
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
		$dbr = $this->getDBConnection( DB_REPLICA );

		return $dbr->selectRowCount(
			'cs_votes',
			'*',
			[
				'cst_v_page_id' => $id,
				'cst_v_vote' => $up ? 1 : -1
			],
			__METHOD__
		);
	}

	/**
	 * @param int $vote
	 * @param int $page_id
	 * @param int $user_id
	 * @return bool true for OK, false for error
	 */
	public function vote( int $vote, int $page_id, int $user_id ): bool {
		$dbw = $this->getDBConnection( DB_PRIMARY );
		$result = $dbw->selectRow(
			'cs_votes',
			[
				'cst_v_vote'
			],
			[
				'cst_v_page_id' => $page_id,
				'cst_v_user_id' => $user_id
			],
			__METHOD__
		);
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
						'cst_v_page_id' => $page_id,
						'cst_v_user_id' => $user_id
					],
					__METHOD__
				);
			} else {
				return $dbw->delete(
					'cs_votes',
					[
						'cst_v_page_id' => $page_id,
						'cst_v_user_id' => $user_id
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
				'cst_v_page_id' => $page_id,
				'cst_v_user_id' => $user_id,
				'cst_v_vote' => $vote
			],
			__METHOD__
		);
	}

	/**
	 * @param int $page_id the page ID of the comment to watch
	 * @param int $user_id the user ID of the user watching the comment
	 * @return bool true for OK, false for error
	 */
	public function watch( int $page_id, int $user_id ): bool {
		if ( $this->isWatching( $page_id, $user_id, DB_PRIMARY ) ) {
			return true;
		}
		$dbw = $this->getDBConnection( DB_PRIMARY );

		return $dbw->insert(
			'cs_watchlist',
			[
				'cst_wl_page_id' => $page_id,
				'cst_wl_user_id' => $user_id
			],
			__METHOD__
		);
	}

	/**
	 * @param int $page_id the page ID of the comment to watch
	 * @param int $user_id the user ID of the user watching the comment
	 * @return bool true for OK, false for error
	 */
	public function unwatch( int $page_id, int $user_id ): bool {
		if ( !$this->isWatching( $page_id, $user_id, DB_PRIMARY ) ) {
			return true;
		}
		$dbw = $this->getDBConnection( DB_PRIMARY );

		return $dbw->delete(
			'cs_watchlist',
			[
				'cst_wl_page_id' => $page_id,
				'cst_wl_user_id' => $user_id
			],
			__METHOD__
		);
	}

	/**
	 * @param int $page_id the page ID of the comment to check
	 * @param int $user_id the user ID of the user watching the comment
	 * @param int $fromdb DB_PRIMARY or DB_REPLICA
	 * @return bool database true for OK, false for error
	 */
	public function isWatching( int $page_id, int $user_id, int $fromdb = DB_REPLICA ): bool {
		$db = $this->getDBConnection( $fromdb );
		$result = $db->selectRow(
			'cs_watchlist',
			[
				'cst_wl_page_id'
			],
			[
				'cst_wl_page_id' => $page_id,
				'cst_wl_user_id' => $user_id
			],
			__METHOD__
		);

		if ( $result ) {
			return true;
		}

		return false;
	}

	/**
	 * @param int $id
	 * @return array of user IDs
	 */
	public function getWatchers( int $id ): array {
		$dbr = $this->getDBConnection( DB_REPLICA );
		$result = $dbr->select(
			'cs_watchlist',
			[
				'cst_wl_user_id'
			],
			[
				'cst_wl_page_id' => $id
			],
			__METHOD__
		);
		$users = [];
		foreach ( $result as $row ) {
			$user_id = $row->cst_wl_user_id;
			$user = $this->userFactory->newFromId( $user_id );
			$users[$user_id] = $user;
		}

		return $users;
	}

	/**
	 * @param WikiPage $wikipage
	 * @param ?string $comment_title
	 * @return string
	 */
	public function getWikiText( WikiPage $wikipage, ?string $comment_title ): string {
		$wikitext = $wikipage->getContent( RevisionRecord::RAW )->getWikitextForTransclusion();
		return $this->removeAnnotations( $wikitext, $comment_title );
	}

	/**
	 * add extra information to wikitext before storage
	 *
	 * @param string $wikitext the wikitext to which to add
	 * @param ?string $comment_title string title of comment
	 * @return string annotated wikitext
	 */
	private function addAnnotations( string $wikitext, ?string $comment_title ): string {
		if ( $comment_title !== null ) {
			$wikitext .= <<<EOT
{{DISPLAYTITLE:
$comment_title
}}
EOT;
		}
		return $wikitext;
	}

	/**
	 * add extra information to wikitext before storage
	 *
	 * @param string $wikitext the wikitext to which to add
	 * @param ?string $comment_title
	 * @return string wikitext without annotations
	 */
	private function removeAnnotations( string $wikitext, ?string $comment_title ): string {
		if ( $comment_title !== null ) {
			$strip = <<<EOT
{{DISPLAYTITLE:
$comment_title
}}
EOT;
			$wikitext = str_replace( $strip, '', $wikitext );
		}
		return $wikitext;
	}
}
