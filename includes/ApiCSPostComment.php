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

class ApiCSPostComment extends ApiBase {

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 */
	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}

	/**
	 * execute the API request
	 */
	public function execute() {
		if ( !in_array( 'cs-comment', $this->getUser()->getRights() ) ||
			$this->getUser()->isBlocked() ) {
			$this->dieCustomUsageMessage(
				'commentstreams-api-error-post-permissions' );
		}

		$associatedid = $this->getMain()->getVal( 'associatedid' );
		$parentid = $this->getMain()->getVal( 'parentid' );
		$comment_title = $this->getMain()->getVal( 'commenttitle' );
		$wikitext = $this->getMain()->getVal( 'wikitext' );

		if ( is_null( $parentid ) && is_null( $comment_title ) ) {
			$this->dieCustomUsageMessage(
				'commentstreams-api-error-missingcommenttitle' );
		}

		if ( !is_null( $parentid ) && !is_null( $comment_title ) ) {
			$this->dieCustomUsageMessage(
				'commentstreams-api-error-post-parentandtitle' );
		}

		if ( !is_null( $parentid ) ) {
			$parent_page = WikiPage::newFromId( $parentid );
			if ( is_null( $parent_page ) || !$parent_page->getTitle()->exists() ) {
				$this->dieCustomUsageMessage(
					'commentstreams-api-error-post-parentpagedoesnotexist' );
			}
			$parent_comment = Comment::newFromWikiPage( $parent_page );
			if ( $parent_comment->getAssociatedId() !== (int)$associatedid ) {
				$this->dieCustomUsageMessage(
					'commentstreams-api-error-post-associatedpageidmismatch' );
			}
		}

		$associated_page = WikiPage::newFromId( $associatedid );
		if ( is_null( $associated_page ) ||
			!$associated_page->getTitle()->exists() ) {
			$this->dieCustomUsageMessage(
				'commentstreams-api-error-post-associatedpagedoesnotexist' );
		}

		$comment = Comment::newFromValues( $associatedid, $parentid,
			$comment_title, $wikitext, $this->getUser() );
		if ( !$comment ) {
			$this->dieCustomUsageMessage( 'commentstreams-api-error-post' );
		}

		$title = $comment->getWikiPage()->getTitle();
		if ( is_null( $comment->getParentId() ) ) {
			$this->logAction( 'comment-create', $title );
		} else {
			$this->logAction( 'reply-create', $title );
		}

		$json = $comment->getJSON();
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) &&
			is_null( $comment->getParentId() )
		) {
			$json['watching'] = 1;
		}
		$this->getResult()->addValue( null, $this->getModuleName(), $json );

		$this->sendNotifications( $comment, $associated_page );
	}

	/**
	 * @return array allowed paramters
	 */
	public function getAllowedParams() {
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
			]
		];
	}

	/**
	 * @return string indicates that this API module requires a CSRF token
	 */
	public function needstoken() {
		return 'csrf';
	}

	/**
	 * Send Echo notifications if Echo is installed.
	 *
	 * @param Comment $comment the comment to send notifications for
	 * @param WikiPage $associated_page the associated page for the comment
	 * @return not used
	 */
	private function sendNotifications( $comment, $associated_page ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			return;
		}

		$parent_id = $comment->getParentId();
		if ( is_null( $parent_id ) ) {
			$comment_title = $comment->getCommentTitle();
		} else {
			$parent_page = WikiPage::newFromId( $parent_id );
			if ( is_null( $parent_page ) ) {
				return;
			}
			$parent_comment = Comment::newFromWikiPage( $parent_page );
			if ( is_null( $parent_comment ) ) {
				return;
			} else {
				$comment_title = $parent_comment->getCommentTitle();
			}
		}

		$associated_page_display_title =
			$associated_page->getTitle()->getPrefixedText();
		if ( class_exists( 'PageProps' ) ) {
			$associated_title = $associated_page->getTitle();
			$values = PageProps::getInstance()->getProperties( $associated_title,
				'displaytitle' );
			if ( array_key_exists( $associated_title->getArticleID(), $values ) ) {
				$associated_page_display_title =
					$values[$associated_title->getArticleID()];
			}
		}

		$extra = [
			'comment_id' => $comment->getId(),
			'parent_id' => $comment->getParentId(),
			'comment_author_username' => $comment->getUsername(),
			'comment_author_display_name' => $comment->getUserDisplayNameUnlinked(),
			'comment_title' => $comment_title,
			'associated_page_display_title' => $associated_page_display_title,
			'comment_wikitext' => $comment->getWikitext()
		];

		if ( !is_null( $parent_id ) ) {
			EchoEvent::create( [
				'type' => 'commentstreams-reply-on-watched-page',
				'title' => $associated_page->getTitle(),
				'extra' => $extra,
				'agent' => $this->getUser()
			] );
			EchoEvent::create( [
				'type' => 'commentstreams-reply-to-watched-comment',
				'title' => $associated_page->getTitle(),
				'extra' => $extra,
				'agent' => $this->getUser()
			] );
		} else {
			EchoEvent::create( [
				'type' => 'commentstreams-comment-on-watched-page',
				'title' => $associated_page->getTitle(),
				'extra' => $extra,
				'agent' => $this->getUser()
			] );
		}
	}

	/**
	 * log action
	 * @param string $action the name of the action to be logged
	 * @param string|null $title the title of the page for the comment that the
	 *        action was performed upon
	 */
	protected function logAction( $action, $title ) {
		$logEntry = new ManualLogEntry( 'commentstreams', $action );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $title );
		$logid = $logEntry->insert();
	}

	/**
	 * die with a custom usage message
	 * @param string $message_name the name of the custom message
	 */
	private function dieCustomUsageMessage( $message_name ) {
		$error_message = wfMessage( $message_name );
		$this->dieUsageMsg(
			[
				ApiMessage::create( $error_message )
			]
		);
	}
}
