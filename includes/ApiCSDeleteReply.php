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

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\CommentStreams\Log\CommentStreamsLogFactory;
use MWException;

class ApiCSDeleteReply extends ApiCSReplyBase {
	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param ICommentStreamsStore $commentStreamsStore
	 * @param CommentStreamsLogFactory $logFactory
	 */
	public function __construct(
		ApiMain $main,
		string $action,
		ICommentStreamsStore $commentStreamsStore,
		private readonly CommentStreamsLogFactory $logFactory,
	) {
		parent::__construct( $main, $action, $commentStreamsStore, true );
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

		if ( $user->getId() === $this->reply->getAuthor()->getId() ) {
			$action = 'cs-comment';
		} else {
			$action = 'cs-moderator-delete';
		}

		if ( !$this->commentStreamsStore->userCan( $action, $user, $this->reply ) ) {
			$this->dieWithError( 'commentstreams-api-error-delete-permissions' );
		}

		$result = $this->commentStreamsStore->deleteReply( $this->reply, $user );
		if ( !$result ) {
			$this->dieWithError( 'commentstreams-api-error-delete' );
		}

		if ( $action === 'cs-comment' ) {
			$this->logFactory->logCommentAction( 'reply-delete-v2', $user, $this->reply->getParent() );
		} else {
			$this->logFactory->logCommentAction( 'reply-moderator-delete-v2', $user, $this->reply->getParent() );
		}

		return null;
	}
}
