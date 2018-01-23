<?php
/*
 * Copyright (c) 2016 The MITRE Corporation
 *
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

class ApiCSDeleteComment extends ApiCSBase {

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 */
	public function __construct( $main, $action ) {
		parent::__construct( $main, $action, true );
	}

	/**
	 * the real body of the execute function
	 *
	 * @return result of API request
	 */
	protected function executeBody() {
		if ( $this->getUser()->isAnon() ) {
			$this->dieCustomUsageMessage(
				'commentstreams-api-error-delete-notloggedin' );
		}

		if ( $this->getUser()->getId() ===
			$this->comment->getWikiPage()->getOldestRevision()->getUser() &&
			$this->comment->getNumReplies() === 0 ) {
			$action = 'cs-comment';
		} else {
			$action = 'cs-moderator-delete';
		}

		if ( !$this->comment->getWikiPage()->getTitle()->userCan( $action,
			$this->getUser() ) ) {
			$this->dieCustomUsageMessage(
				'commentstreams-api-error-delete-permissions' );
		}

		$childCount = $this->comment->getNumReplies();
		if ( $childCount > 0 ) {
			if ( $GLOBALS['wgCommentStreamsModeratorFastDelete'] ) {
				$result = $this->recursiveDelete( $this->comment );
			} else {
				$this->dieCustomUsageMessage(
					'commentstreams-api-error-delete-haschildren' );
			}
		} else {
			$result = $this->comment->delete();
			if ( $action === 'cs-comment' ) {
				if ( is_null( $this->comment->getParentId() ) ) {
					$this->logAction( 'comment-delete' );
				} else {
					$this->logAction( 'reply-delete' );
				}
			} else {
				if ( is_null( $this->comment->getParentId() ) ) {
					$this->logAction( 'comment-moderator-delete' );
				} else {
					$this->logAction( 'reply-moderator-delete' );
				}
			}
		}

		if ( !$result ) {
			$this->dieCustomUsageMessage(
				'commentstreams-api-error-delete' );
		}

		return null;
	}

	/**
	 * recursively delete comment and replies
	 *
	 * @param Comment $comment the comment to recursively delete
	 */
	private function recursiveDelete( $comment ) {
		$replies = Comment::getReplies( $comment->getId() );
		foreach ( $replies as $reply ) {
			$result = $this->recursiveDelete( $reply );
			if ( !$result ) {
				return $result;
			}
		}
		$result = $comment->delete();
		$title = $comment->getWikiPage()->getTitle();
		if ( is_null( $comment->getParentId() ) ) {
			$this->logAction( 'comment-moderator-delete', $title );
		} else {
			$this->logAction( 'reply-moderator-delete', $title );
		}
		return $result;
	}
}
