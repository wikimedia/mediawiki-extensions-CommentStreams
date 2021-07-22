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

abstract class ApiCSBase extends ApiBase {
	/**
	 * whether this API module will be editing the database
	 * @var bool
	 */
	private $edit;

	/**
	 * @var Comment
	 */
	protected $comment;

	/**
	 * @var CommentStreamsFactory
	 */
	protected $commentStreamsFactory;

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param bool $edit whether this API module will be editing the database
	 */
	public function __construct( ApiMain $main, string $action, bool $edit = false ) {
		parent::__construct( $main, $action );
		$this->edit = $edit;
		$services = MediaWikiServices::getInstance();
		$this->commentStreamsFactory = $services->getService( 'CommentStreamsFactory' );
	}

	/**
	 * execute the API request
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$wikipage = $this->getTitleOrPageId( $params, $this->edit ? 'frommasterdb' : 'fromdb' );
		$comment = $this->commentStreamsFactory->newFromWikiPage( $wikipage );
		if ( $comment === null ) {
			$this->dieWithError( 'commentstreams-api-error-notacomment' );
		} else {
			$this->comment = $comment;
			$result = $this->executeBody();
			if ( $result !== null ) {
				$this->getResult()->addValue( null, $this->getModuleName(), $result );
			}
		}
	}

	/**
	 * the real body of the execute function
	 * @return ?array result of API request
	 */
	abstract protected function executeBody(): ?array;

	/**
	 * @return array allowed parameters
	 */
	public function getAllowedParams(): array {
		return [
			'pageid' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			],
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
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
	 * log action
	 * @param string $action the name of the action to be logged
	 * @param LinkTarget|Title|null $title the title of the page for the comment that the
	 *        action was performed upon, if different from the current comment
	 * @throws MWException
	 */
	protected function logAction( string $action, $title = null ) {
		$logEntry = new ManualLogEntry( 'commentstreams', $action );
		$logEntry->setPerformer( $this->getUser() );
		if ( $title ) {
			$logEntry->setTarget( $title );
		} else {
			$logEntry->setTarget( $this->comment->getTitle() );
		}
		$logEntry->insert();
	}
}
