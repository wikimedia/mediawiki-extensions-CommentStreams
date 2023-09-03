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
use Wikimedia\ParamValidator\ParamValidator;

abstract class ApiCSBase extends ApiBase {
	/**
	 * @var CommentStreamsFactory
	 */
	protected $commentStreamsFactory;

	/**
	 * whether this API module will be editing the database
	 * @var bool
	 */
	protected $edit;

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param CommentStreamsFactory $commentStreamsFactory
	 * @param bool $edit whether this API module will be editing the database
	 */
	public function __construct(
		ApiMain $main,
		string $action,
		CommentStreamsFactory $commentStreamsFactory,
		bool $edit = false
	) {
		parent::__construct( $main, $action );
		$this->commentStreamsFactory = $commentStreamsFactory;
		$this->edit = $edit;
	}

	/**
	 * @return array allowed parameters
	 */
	public function getAllowedParams(): array {
		return [
			'pageid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false
			],
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false
			]
		];
	}

	/**
	 * @return array examples of the use of this API module
	 */
	public function getExamplesMessages(): array {
		return [
			'action=' . $this->getModuleName() . '&pageid=3' =>
				'apihelp-' . $this->getModuleName() . '-pageid-example',
			'action=' . $this->getModuleName() . '&title=CommentStreams:3' =>
				'apihelp-' . $this->getModuleName() . '-title-example'
		];
	}

	/**
	 * @return string|false indicates that this API module requires a CSRF token
	 */
	public function needsToken() {
		if ( $this->edit ) {
			return 'csrf';
		} else {
			return false;
		}
	}

	/**
	 * the real body of the execute function
	 * @return ?array result of API request
	 */
	abstract protected function executeBody(): ?array;
}
