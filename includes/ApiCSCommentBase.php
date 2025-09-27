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

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MWException;

abstract class ApiCSCommentBase extends ApiCSBase {
	/**
	 * @var Comment
	 */
	protected $comment;

	/**
	 * @param ApiMain $main main module
	 * @param string $action name of this module
	 * @param ICommentStreamsStore $commentStreamsStore
	 * @param bool $edit whether this API module will be editing the database
	 */
	public function __construct(
		ApiMain $main,
		string $action,
		ICommentStreamsStore $commentStreamsStore,
		bool $edit = false
	) {
		parent::__construct( $main, $action, $commentStreamsStore, $edit );
	}

	/**
	 * execute the API request
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		$comment = $this->commentStreamsStore->getComment( $this->getEntityId() );
		if ( $comment ) {
			$this->comment = $comment;
			$result = $this->executeBody();
			if ( $result ) {
				$this->getResult()->addValue( null, $this->getModuleName(), $result );
			}
		} else {
			$this->dieWithError( 'commentstreams-api-error-notacomment' );
		}
	}

}
