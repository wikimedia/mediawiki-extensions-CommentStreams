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

use ApiBase;
use ApiMain;
use ApiUsageException;
use ManualLogEntry;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MWException;
use Title;

class ApiCSPostComment extends ApiBase {
	/**
	 * @var CommentStreamsFactory
	 */
	private $commentStreamsFactory;

	/**
	 * @var CommentStreamsEchoInterface
	 */
	private $echoInterface;

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 */
	public function __construct( ApiMain $main, string $action ) {
		parent::__construct( $main, $action );
		$services = MediaWikiServices::getInstance();
		$this->commentStreamsFactory = $services->getService( 'CommentStreamsFactory' );
		$this->echoInterface = $services->getService( 'CommentStreamsEchoInterface' );
	}

	/**
	 * execute the API request
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		if ( !CommentStreamsUtils::userHasRight( $this->getUser(), 'cs-comment' ) ) {
			$this->dieWithError( 'commentstreams-api-error-post-permissions' );
		}

		$associatedid = $this->getMain()->getVal( 'associatedid' );
		$parentid = $this->getMain()->getVal( 'parentid' );
		$comment_title = $this->getMain()->getVal( 'commenttitle' );
		$wikitext = $this->getMain()->getVal( 'wikitext' );
		$commentblockid = $this->getMain()->getVal( 'commentblockid' );

		if ( $parentid === null && $comment_title === null ) {
			$this->dieWithError( 'commentstreams-api-error-missingcommenttitle' );
		}

		if ( $parentid !== null && $comment_title !== null ) {
			$this->dieWithError( 'commentstreams-api-error-post-parentandtitle' );
		}

		$parent_comment_title = null;
		if ( $parentid !== null ) {
			$parentid = (int)$parentid;
			$parent_page = CommentStreamsUtils::newWikiPageFromId( $parentid );
			if ( $parent_page === null || !$parent_page->getTitle()->exists() ) {
				$this->dieWithError( 'commentstreams-api-error-post-parentpagedoesnotexist' );
			} else {
				$parent_comment = $this->commentStreamsFactory->newFromWikiPage( $parent_page );
				if ( $parent_comment->getAssociatedId() !== (int)$associatedid ) {
					$this->dieWithError( 'commentstreams-api-error-post-associatedpageidmismatch' );
				}
				$parent_comment_title = $parent_comment->getCommentTitle();
			}
		}

		$associatedid = (int)$associatedid;
		$associated_page = CommentStreamsUtils::newWikiPageFromId( $associatedid );
		if ( $associated_page === null ||
			!$associated_page->getTitle()->exists() ) {
			$this->dieWithError( 'commentstreams-api-error-post-associatedpagedoesnotexist' );
		} else {
			$comment = $this->commentStreamsFactory->newFromValues(
				$commentblockid,
				$associatedid,
				$parentid,
				$comment_title,
				$wikitext,
				$this->getUser()
			);

			if ( !$comment ) {
				$this->dieWithError( 'commentstreams-api-error-post' );
			} else {
				$title = $comment->getTitle();
				if ( $comment->getParentId() === null ) {
					$this->logAction( 'comment-create', $title );
				} else {
					$this->logAction( 'reply-create', $title );
				}

				$this->getResult()->addValue( null, $this->getModuleName(), $comment->getId() );

				$this->echoInterface->sendNotifications(
					$comment,
					$associated_page,
					$this->getUser(),
					$parent_comment_title ?: $comment_title
				);
			}
		}
	}

	/**
	 * @return array allowed parameters
	 */
	public function getAllowedParams(): array {
		return [
			'commenttitle' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			],
			'wikitext' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'associatedid' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'parentid' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			],
			'commentblockid' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			]
		];
	}

	/**
	 * @return string indicates that this API module requires a CSRF token
	 */
	public function needstoken(): string {
		return 'csrf';
	}

	/**
	 * log action
	 * @param string $action the name of the action to be logged
	 * @param LinkTarget|Title $target the title of the page for the comment that the
	 *        action was performed upon, if different from the current comment
	 * @throws MWException
	 */
	protected function logAction( string $action, $target ) {
		$logEntry = new ManualLogEntry( 'commentstreams', $action );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $target );
		$logEntry->insert();
	}
}
