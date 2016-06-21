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

class ApiCSDeleteComment extends ApiBase {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}
	public function execute() {
		$type = $this->getMain()->getVal( 'type' );
		$pageId = $this->getMain()->getVal( 'pageId' );
		$token = $this->getMain()->getVal( 'token' );
		if($type == 'childComment' ) {
			// Just delete the comment
			$result = self::deleteComment($pageId);
			if($result == 'success')
				$this->getResult()->addValue(null, $this->getModuleName(), array('result' => 'success'));
			else
				$this->getResult()->addValue(null, $this->getModuleName(), array('result' => 'failure', 'error' => $result));
		}
		else if($type == 'headComment') {
			// First check if it has any replies, if so, fail. Else, delete and succeed.
			$childrenCount = DatabaseQuerier::numberOfChildCommentsForParentCommentId($pageId);
			if($childrenCount > 0)
				$this->getResult()->addValue(null, $this->getModuleName(), array('result' => 'failure', 'error' => 'haschildren'));
			else {
				$result = self::deleteComment($pageId);
				if($result == 'success')
					$this->getResult()->addValue(null, $this->getModuleName(), array('result' => 'success'));
				else
					$this->getResult()->addValue(null, $this->getModuleName(), array('result' => 'failure', 'error' => $result));
			}
		}
		else {
			$this->getResult()->addValue(null, "error", array('code' => 'unrecognizedparameter', 'info' => 'type parameter unrecognized'));
		}

		return true;
	}
	public function getDescription() {
		return 'Delete a CommentStreams comment.';
	}
	public function getAllowedParams() {
		return array(
			'type' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'pageId' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			)
		);
	}
	public function getParamDescription() {
		return array(
			'type' => 'type of comment being deleted - allowed values are headComment, childComment',
			'pageId' => 'page ID of the comment being deleted'
		);
	}
	public function getExamples() {
		return null;
	}
	public function getHelpUrls() {
		return '';
	}

	public function needsToken() {
		return 'csrf';
		// return false;
	}
	public function deleteComment($pageId) {
		// Delete the comment.

		try {
			$wikipage = WikiPage::newFromId($pageId);
			if(!$wikipage)
				throw new MWException("Page does not exist");
			$status = $wikipage->doDeleteArticleReal('comment deleted', false, 0);
			if(!$status->isOK() && !$status->isGood()) {
				throw new MWException();
			}
		}
		catch(MWException $e) {
			return $e->getMessage();
		}

		// If no error, delete metadata for this comment from cs_comment_data table.
		DatabaseQuerier::deleteCommentDataFromDatabase($pageId);

		// Return 'success' to indicate deletion success
		return 'success';
	}
}
