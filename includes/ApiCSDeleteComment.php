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

namespace MediaWiki\Extension\CommentStreams;

use ApiMain;
use ApiUsageException;
use ConfigException;
use MediaWiki\MediaWikiServices;
use MWException;
use User;

class ApiCSDeleteComment extends ApiCSBase {
	/**
	 * @var bool
	 */
	private $moderatorFastDelete;

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @throws ConfigException
	 */
	public function __construct( ApiMain $main, string $action ) {
		parent::__construct( $main, $action, true );
		$this->moderatorFastDelete = (bool)MediaWikiServices::getInstance()->getConfigFactory()->
			makeConfig( 'CommentStreams' )->get( 'CommentStreamsModeratorFastDelete' );
	}

	/**
	 * the real body of the execute function
	 *
	 * @return ?array result of API request
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	protected function executeBody() : ?array {
		$user = $this->getUser();
		if ( $user->isAnon() ) {
			$this->dieWithError( 'commentstreams-api-error-delete-notloggedin' );
		}

		if ( $this->comment->getParentId() !== null ) {
			$replyCount = 0;
		} else {
			$replyCount = $this->comment->getNumReplies();
		}

		if ( $user->getId() === $this->comment->getAuthor()->getId() &&
			$replyCount === 0 ) {
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
	 * @param Comment $comment
	 * @param string $action
	 * @param User $user
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	private function deleteComment( Comment $comment, string $action, User $user ) {
		$title = $comment->getTitle();
		if ( !CommentStreamsUtils::userCan( $action, $user, $title ) ) {
			$this->dieWithError( 'commentstreams-api-error-delete-permissions' );
		}

		$result = $comment->delete( $user );
		if ( !$result ) {
			$this->dieWithError( 'commentstreams-api-error-delete' );
		}
		if ( $action === 'cs-comment' ) {
			if ( $comment->getParentId() === null ) {
				$this->logAction( 'comment-delete' );
			} else {
				$this->logAction( 'reply-delete' );
			}
		} else {
			if ( $comment->getParentId() === null ) {
				$this->logAction( 'comment-moderator-delete' );
			} else {
				$this->logAction( 'reply-moderator-delete' );
			}
		}
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
		$commentStreamsStore = MediaWikiServices::getInstance()->getService( 'CommentStreamsStore' );
		$replies = $commentStreamsStore->getReplies( $comment->getId() );
		foreach ( $replies as $page_id ) {
			$wikipage = CommentStreamsUtils::newWikiPageFromId( $page_id );
			if ( $wikipage !== null ) {
				$reply = $this->commentStreamsFactory->newFromWikiPage( $wikipage );
				if ( $reply !== null ) {
					$this->deleteComment( $reply, $action, $user );
				}
			}
		}
	}
}
