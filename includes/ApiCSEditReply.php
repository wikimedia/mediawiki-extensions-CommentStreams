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
use MWException;

class ApiCSEditReply extends ApiCSReplyBase {
	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param CommentStreamsFactory $commentStreamsFactory
	 */
	public function __construct( ApiMain $main, string $action, CommentStreamsFactory $commentStreamsFactory ) {
		parent::__construct( $main, $action, $commentStreamsFactory, true );
	}

	/**
	 * the real body of the execute function
	 * @return ?array result of API request
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	protected function executeBody(): ?array {
		$user = $this->getUser();
		if ( $user->isAnon() ) {
			$this->dieWithError( 'commentstreams-api-error-edit-notloggedin' );
		}

		if ( $user->getId() === $this->reply->getAuthor()->getId() ) {
			$action = 'cs-comment';
		} else {
			$action = 'cs-moderator-edit';
		}

		$title = $this->reply->getTitle();
		if ( !$this->getPermissionManager()->userCan( $action, $user, $title ) ) {
			$this->dieWithError( 'commentstreams-api-error-edit-permissions' );
		}

		$wikitext = $this->getMain()->getVal( 'wikitext' );

		$result = $this->reply->update( $wikitext, $this->getUser() );
		if ( !$result ) {
			$this->dieWithError( 'commentstreams-api-error-edit' );
		}

		if ( $action === 'cs-comment' ) {
			$this->logAction( 'reply-edit' );
		} else {
			$this->logAction( 'reply-moderator-edit' );
		}

		return null;
	}

	/**
	 * @return array allowed parameters
	 */
	public function getAllowedParams(): array {
		return array_merge( parent::getAllowedParams(),
			[
				'wikitext' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
				]
			]
		);
	}
}
