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
		if ( $this->getUser()->isAnon() ) {
			$this->dieCustomUsageMessage(
				'commentstreams-api-error-post-notloggedin' );
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
			if ( $parent_comment->getAssociatedId() !== (integer) $associatedid ) {
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

		$this->getResult()->addValue( null, $this->getModuleName(),
			$comment->getJSON() );
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
	 * @return string indicates that this API module requires a CSRF toekn
	 */
	public function needstoken() {
		return 'csrf';
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
