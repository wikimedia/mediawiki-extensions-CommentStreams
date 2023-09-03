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

class ApiCSPostReply extends ApiBase {
	/**
	 * @var CommentStreamsFactory
	 */
	private $commentStreamsFactory;

	/**
	 * @var EchoInterface
	 */
	private $echoInterface;

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
	 * @param CommentStreamsFactory $commentStreamsFactory
	 * @param EchoInterface $commentStreamsEchoInterface
	 * @param Config $config
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ApiMain $main,
		string $action,
		CommentStreamsFactory $commentStreamsFactory,
		EchoInterface $commentStreamsEchoInterface,
		Config $config,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $main, $action );
		$this->commentStreamsFactory = $commentStreamsFactory;
		$this->echoInterface = $commentStreamsEchoInterface;
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

		$parentId = $this->getMain()->getVal( 'parentid' );
		$wikitext = $this->getMain()->getVal( 'wikitext' );

		$parentId = (int)$parentId;
		$parentPage = $this->wikiPageFactory->newFromId( $parentId );
		if ( !$parentPage || !$parentPage->getTitle()->exists() ) {
			$this->dieWithError( 'commentstreams-api-error-post-parentpagedoesnotexist' );
		} else {
			$parentComment = $this->commentStreamsFactory->newCommentFromWikiPage( $parentPage );
			if ( !$parentComment ) {
				$this->dieWithError( 'commentstreams-api-error-post-parentpagedoesnotexist' );
			} else {
				$associatedPage = $this->wikiPageFactory->newFromId( $parentComment->getAssociatedId() );
				if ( !$associatedPage ) {
					$this->dieWithError( 'commentstreams-api-error-post-parentpagedoesnotexist' );
				} else {
					$reply = $this->commentStreamsFactory->newReplyFromValues(
						$parentId,
						$wikitext,
						$this->getUser()
					);

					if ( !$reply ) {
						$this->dieWithError( 'commentstreams-api-error-post' );
					} else {
						$title = $reply->getTitle();
						$this->logAction( 'reply-create', $title );

						$this->getResult()->addValue( null, $this->getModuleName(), $reply->getId() );

						$this->echoInterface->sendReplyNotifications(
							$reply,
							$associatedPage,
							$this->getUser(),
							$parentComment

						);
					}
				}
			}
		}
	}

	/**
	 * @return array allowed parameters
	 */
	public function getAllowedParams(): array {
		return [
			'wikitext' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'parentid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
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
		$logId = $logEntry->insert();

		if ( !$this->suppressLogsFromRCs ) {
			$logEntry->publish( $logId );
		}
	}
}
