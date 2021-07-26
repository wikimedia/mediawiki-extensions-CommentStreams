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

class ApiCSVote extends ApiCSBase {
	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param CommentFactory $commentFactory
	 */
	public function __construct( ApiMain $main, string $action, CommentFactory $commentFactory ) {
		parent::__construct( $main, $action, $commentFactory, true );
	}

	/**
	 * the real body of the execute function
	 * @return ?array result of API request
	 * @throws ApiUsageException
	 */
	protected function executeBody(): ?array {
		if ( $this->getUser()->isAnon() ) {
			$this->dieWithError( 'commentstreams-api-error-vote-notloggedin' );
		}

		$vote = $this->getMain()->getVal( 'vote' );

		if ( $this->comment->getParentId() !== null ) {
			$this->dieWithError( 'commentstreams-api-error-vote-novoteonreply' );
		}

		$result = $this->comment->vote( $vote, $this->getUser() );
		if ( !$result ) {
			$this->dieWithError( 'commentstreams-api-error-vote' );
		}

		return null;
	}

	/**
	 * @return array allowed parameters
	 */
	public function getAllowedParams(): array {
		return array_merge( parent::getAllowedParams(),
			[
				'vote' =>
					[
						ApiBase::PARAM_TYPE => 'integer',
						ApiBase::PARAM_REQUIRED => true
					]
			]
		);
	}

	/**
	 * @return array examples of the use of this API module
	 */
	public function getExamplesMessages(): array {
		return [
			'action=' . $this->getModuleName() . '&pageid=3&vote=1' =>
				'apihelp-' . $this->getModuleName() . '-pageid-example',
			'action=' . $this->getModuleName() . '&title=CommentStreams:3&vote=-1' =>
				'apihelp-' . $this->getModuleName() . '-title-example'
		];
	}

	/**
	 * @return string indicates that this API module requires a CSRF toekn
	 */
	public function needsToken(): string {
		return 'csrf';
	}
}
