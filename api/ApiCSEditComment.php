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

class ApiCSEditComment extends ApiBase {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}
	public function execute() {
		$pageId = $this->getMain()->getVal( 'pageId' );
		$commentTitle = $this->getMain()->getVal( 'commentTitle' );
		$commentText = $this->getMain()->getVal( 'commentText' );

		// Edit child comment
		$result = $this->editComment($pageId, $commentTitle, $commentText);
		if($result == 'success') {
			// Now that the edit is complete, get back the HTML
			$api = new ApiMain(
				new DerivativeRequest(
					$this->getRequest(),
					array(
						'action' => 'csQueryComment',
						'pageId' => $pageId
					),
					true
				), 
				true
			);

			$api->execute();
			$data = $api->getResult()->getResultData(
				null, ['BC' => [], 'Types' => [], 'Strip' => 'all'] );
			$data['csQueryComment']['children'] = DatabaseQuerier::numberOfChildCommentsForParentCommentId($pageId);
			$this->getResult()->addValue(null, $this->getModuleName(), array('result' => 'success',
																			'data' => $data['csQueryComment']));
		}
		else
			$this->getResult()->addValue(null, "error", array('code' => $result, 
																	'info' => $result));

		return true;
	}
	public function getDescription() {
		return 'Edit a CommentStreams comment. Returns a result status (success or error), ' .
				'a data object containing the result of running csQueryComment on the newly edit comment, '.
				'and the number of children this comment has (always zero if this node was a child comment).';
	}
	public function getAllowedParams() {
		return array(
			'pageId' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			),
			'commentTitle' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			),
			'commentText' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			)
		);
	}
	public function getParamDescription() {
		return array(
			'pageId' => 'The page ID of the comment page being edited',
			'commentTitle' => 'title of this comment',
			'commentText' => 'text of this comment in wikitext format'
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
	}

	public function editComment($pageId, $commentTitle, $commentText) {
		// Create the wiki page for the comment.
		try {
			$title = Title::newFromID($pageId);
			if(!$title)
				throw new MWException("Page does not exist");
			$wikipage = WikiPage::factory($title);
			$content = new WikitextContent($commentText);
			$status = $wikipage->doEditContent($content, '', EDIT_UPDATE, false, $this->getUser(), null);
			if(!$status->isOK() && !$status->isGood()) {
				throw new MWException();
			}
		}
		catch(MWException $e) {
			return $e->getMessage();
		}

		// If no error, save new title in the cs_comment_data table.
		DatabaseQuerier::updateCommentTitle($pageId, $commentTitle);

		// Return 'success' to indicate comment editing success
		return 'success';
	}
}
