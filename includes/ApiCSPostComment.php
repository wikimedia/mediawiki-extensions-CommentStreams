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

use ApiBase;
use ApiMain;
use ApiUsageException;
use Config;
use ManualLogEntry;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPageFactory;
use MWException;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiCSPostComment extends ApiBase {

	/**
	 * @var ICommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var NotifierInterface
	 */
	private $notifier;

	/**
	 * @var bool
	 */
	private $suppressLogsFromRCs;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param ICommentStreamsStore $commentStreamsStore
	 * @param NotifierInterface $notifier
	 * @param Config $config
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ApiMain $main,
		string $action,
		ICommentStreamsStore $commentStreamsStore,
		NotifierInterface $notifier,
		Config $config,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $main, $action );
		$this->commentStreamsStore = $commentStreamsStore;
		$this->notifier = $notifier;
		$this->suppressLogsFromRCs = (bool)$config->get( "CommentStreamsSuppressLogsFromRCs" );
		$this->wikiPageFactory = $wikiPageFactory;
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
				$this->logAction( 'comment-create', Title::castFromPageIdentity( $comment->getAssociatedPage() ) );

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

	/**
	 * log action
	 * @param string $action the name of the action to be logged
	 * @param LinkTarget|Title $target the title of the page for the comment that the
	 *		  action was performed upon, if different from the current comment
	 * @throws MWException
	 */
	protected function logAction( string $action, $target ) {
		$logEntry = new ManualLogEntry( 'commentstreams', $action );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $target );
		$logId = $logEntry->insert();

		if ( !$this->suppressLogsFromRCs ) {
			$logEntry->publish( $logId );
		}
	}
}
