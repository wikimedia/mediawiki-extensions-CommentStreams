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

abstract class ApiCSBase extends ApiBase {

	private $edit;
	protected $comment;

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param bool $edit whether this API module will be editing the database
	 */
	public function __construct( $main, $action, $edit = false ) {
		parent::__construct( $main, $action );
		$this->edit = $edit;
	}

	/**
	 * execute the API request
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$wikipage = $this->getTitleOrPageId( $params,
			$this->edit ? 'frommasterdb' : 'fromdb' );
		$this->comment = Comment::newFromWikiPage( $wikipage );
		if ( is_null( $this->comment ) ) {
			$this->dieCustomUsageMessage( 'commentstreams-api-error-notacomment' );
		}
		$result = $this->executeBody();
		if ( !is_null( $result ) ) {
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
		}
	}

	/**
	 * the real body of the execute function
	 */
	abstract protected function executeBody();

	/**
	 * @return array allowed parameters
	 */
	public function getAllowedParams() {
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
	public function getExamplesMessages() {
		return [
			'action=' . $this->getModuleName() . '&pageid=3' =>
			'apihelp-' . $this->getModuleName() . '-pageid-example',
			'action=' . $this->getModuleName() . '&title=CommentStreams:3' =>
			'apihelp-' . $this->getModuleName() . '-title-example'
		];
	}

	/**
	 * @return string indicates that this API module requires a CSRF token
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
	 * @param string|null $title the title of the page for the comment that the
	 *        action was performed upon, if differen from the current comment
	 */
	protected function logAction( $action, $title = null ) {
		$logEntry = new ManualLogEntry( 'commentstreams', $action );
		$logEntry->setPerformer( $this->getUser() );
		if ( $title ) {
			$logEntry->setTarget( $title );
		} else {
			$logEntry->setTarget( $this->comment->getWikiPage()->getTitle() );
		}
		$logid = $logEntry->insert();
	}

	/**
	 * die with a custom usage message
	 * @param string $message_name the name of the custom message
	 */
	protected function dieCustomUsageMessage( $message_name ) {
		$error_message = wfMessage( $message_name );
		$this->dieUsageMsg(
			[
				ApiMessage::create( $error_message )
			]
		);
	}
}
