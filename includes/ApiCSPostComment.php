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

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\CommentStreams\Log\CommentStreamsLogFactory;
use MediaWiki\Page\WikiPageFactory;
use MWException;
use Wikimedia\ParamValidator\ParamValidator;

class ApiCSPostComment extends ApiBase {

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param ICommentStreamsStore $commentStreamsStore
	 * @param NotifierInterface $notifier
	 * @param CommentStreamsLogFactory $logFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ApiMain $main,
		string $action,
		private readonly ICommentStreamsStore $commentStreamsStore,
		private readonly NotifierInterface $notifier,
		private readonly CommentStreamsLogFactory $logFactory,
		private readonly WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $main, $action );
	}

	/**
	 * execute the API request
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		if ( !$this->getPermissionManager()->userHasRight( $this->getUser(), 'cs-comment' ) ) {
			$this->dieWithError( 'commentstreams-api-error-post-permissions' );
		}

		$associatedId = $this->getMain()->getVal( 'associatedid' );
		$commentTitle = $this->getMain()->getVal( 'commenttitle' );
		$wikitext = $this->getMain()->getVal( 'wikitext' );
		$commentBlockName = $this->getMain()->getVal( 'commentblockname' );

		$associatedId = (int)$associatedId;
		$associatedPage = $this->wikiPageFactory->newFromID( $associatedId );
		if ( $associatedPage === null || !$associatedPage->getTitle()->exists() ) {
			$this->dieWithError( 'commentstreams-api-error-post-associatedpagedoesnotexist' );
		} else {
			$comment = $this->commentStreamsStore->insertComment(
				$this->getUser(),
				$wikitext,
				$associatedId,
				$commentTitle,
				$commentBlockName
			);

			if ( !$comment ) {
				$this->dieWithError( 'commentstreams-api-error-post' );
			} else {
				$this->logFactory->logCommentAction( 'comment-create-v2', $this->getUser(), $comment );

				$this->getResult()->addValue( null, $this->getModuleName(), $comment->getId() );

				$this->notifier->sendCommentNotifications(
					$comment,
					$associatedPage,
					$this->getUser(),
					$commentTitle
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
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'wikitext' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'associatedid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			],
			'commentblockname' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false
			]
		];
	}

	/**
	 * @return string indicates that this API module requires a CSRF token
	 */
	public function needstoken(): string {
		return 'csrf';
	}

}
