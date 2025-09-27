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
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CommentStreams\Log\CommentStreamsLogFactory;
use MediaWiki\User\User;
use MWException;

class ApiCSDeleteComment extends ApiCSCommentBase {
	/**
	 * @var bool
	 */
	private $moderatorFastDelete;

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param ICommentStreamsStore $commentStreamsStore
	 * @param CommentStreamsLogFactory $logFactory
	 * @param Config $config
	 */
	public function __construct(
		ApiMain $main,
		string $action,
		ICommentStreamsStore $commentStreamsStore,
		private readonly CommentStreamsLogFactory $logFactory,
		Config $config,
	) {
		parent::__construct( $main, $action, $commentStreamsStore, true );
		$this->moderatorFastDelete = (bool)$config->get( 'CommentStreamsModeratorFastDelete' );
	}

	/**
	 * the real body of the execute function
	 *
	 * @return ?array result of API request
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	protected function executeBody(): ?array {
		$user = $this->getUser();
		if ( $user->isAnon() ) {
			$this->dieWithError( 'commentstreams-api-error-delete-notloggedin' );
		}

		$replyCount = $this->commentStreamsStore->getNumReplies( $this->comment );

		if ( $user->getId() === $this->comment->getAuthor()->getId() && $replyCount === 0 ) {
			$action = 'cs-comment';
		} else {
			$action = 'cs-moderator-delete';
		}

		if ( $replyCount > 0 ) {
			if ( $this->moderatorFastDelete ) {
				$this->deleteReplies( $this->comment, $action, $user );
			} else {
				$this->dieWithError( 'commentstreams-api-error-delete-haschildren' );
			}
		}

		$this->deleteComment( $this->comment, $action, $user );

		return null;
	}

	/**
	 * recursively delete comment and replies
	 *
	 * @param Comment $comment the comment to recursively delete
	 * @param string $action
	 * @param User $user
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	private function deleteReplies( Comment $comment, string $action, User $user ) {
		$replies = $this->commentStreamsStore->getReplies( $comment );
		foreach ( $replies as $reply ) {
			$this->deleteReply( $reply, $action, $user );
		}
	}

	/**
	 * @param Comment $comment
	 * @param string $action
	 * @param User $user
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	private function deleteComment( Comment $comment, string $action, User $user ) {
		if ( !$this->commentStreamsStore->userCan( $action, $user, $comment ) ) {
			$this->dieWithError( 'commentstreams-api-error-delete-permissions' );
		}

		$result = $this->commentStreamsStore->deleteComment( $comment, $user );
		if ( !$result ) {
			$this->dieWithError( 'commentstreams-api-error-delete' );
		}
		if ( $action === 'cs-comment' ) {
			$this->logFactory->logCommentAction( 'comment-delete-v2', $user, $comment );
		} else {
			$this->logFactory->logCommentAction( 'comment-moderator-delete-v2', $user, $comment );
		}
	}

	/**
	 * @param Reply $reply
	 * @param string $action
	 * @param User $user
	 * @throws ApiUsageException
	 * @throws MWException
	 * @throws FatalError
	 */
	private function deleteReply( Reply $reply, string $action, User $user ) {
		if ( !$this->commentStreamsStore->userCan( $action, $user, $reply ) ) {
			$this->dieWithError( 'commentstreams-api-error-delete-permissions' );
		}

		$result = $this->commentStreamsStore->deleteReply( $reply, $user );
		if ( !$result ) {
			$this->dieWithError( 'commentstreams-api-error-delete' );
		}

		if ( $action === 'cs-comment' ) {
			$this->logFactory->logCommentAction( 'reply-delete-v2', $user, $reply->getParent() );
		} else {
			$this->logFactory->logCommentAction( 'reply-moderator-delete-v2', $user, $reply->getParent() );
		}
	}
}
