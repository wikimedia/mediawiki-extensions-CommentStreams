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

class ApiCSPostComment extends ApiBase {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}
	public function execute() {
		$type = $this->getMain()->getVal( 'type' );
		$commentText = $this->getMain()->getVal( 'commentText' );
		$commentTitle = $this->getMain()->getVal( 'commentTitle' );
		$commentedPageId = $this->getMain()->getVal( 'commentedPageId' );
		$parent = null;

		if($type != 'childComment' && $type != 'headComment') {
			$this->getResult()->addValue(null, "error", array('code' => 'unrecognizedparameter', 'info' => 'type parameter unrecognized'));
			return true;
		}

		if($type == 'childComment' ) {
			$parent = $this->getMain()->getVal( 'parent' );
			if(!$parent) {
				$this->getResult()->addValue(null, "error", array('code' => 'missingparent', 
																	'info' => 'you must provide a parent comment page ID'));
				return true;
			}
		}

		// Post comment - child or discussion
		$result = $this->createComment($commentedPageId, $commentTitle, $commentText, $parent);
		if(is_int($result)) {
			// Now that the post is complete, get back the HTML
			$api = new ApiMain(
				new DerivativeRequest(
					$this->getRequest(),
					array(
						'action' => 'csQueryComment',
						'pageId' => $result
					),
					true
				), 
				true
			);

			$api->execute();
			$data = $api->getResult()->getResultData(
				null, ['BC' => [], 'Types' => [], 'Strip' => 'all'] );
			$this->getResult()->addValue( null, $this->getModuleName(), array('result' => 'success', 'data' => array(
																							'type' => $type,
																							'commentTitle' => $commentTitle,
																							'commentText' => $commentText,
																							'pageIdCreated' => $result,
																							'commentData' => $data['csQueryComment']
																						)
																	)
			);
		}
		else
			$this->getResult()->addValue(null, $this->getModuleName(), array('result' => 'failure', 'error' => $result));

		return true;
	}
	public function getDescription() {
		return 'Post a new CommentStreams comment.';
	}
	public function getAllowedParams() {
		return array(
			'type' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'parent' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			),
			'commentTitle' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			),
			'commentText' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'commentedPageId' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			)
		);
	}
	public function getParamDescription() {
		return array(
			'type' => 'type of comment being posted - allowed values are headComment, childComment',
			'parent' => 'if this is a child comment, provide the page_id of the parent comment, else ignore this parameter',
			'commentTitle' => 'title of this comment',
			'commentText' => 'text of this comment in wikitext format',
			'commentedPageId' => 'page ID of the page being commented upon'
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

	public function createComment($commentedPageId, $commentTitle, $commentText, $parentCommentId = null) {
		// Get next page number for the comment
		$nextCommentNumber = DatabaseQuerier::getNextCommentNumber();

		$content = new WikitextContent($commentText);
		$success = false;
		while(!$success) {
					// Create the wiki page for the comment.
			$title = Title::makeTitleSafe(NS_COMMENTSTREAMS, (string)$nextCommentNumber);
			$wikipage = WikiPage::factory($title);
			try {
				$status = $wikipage->doEditContent($content, '', EDIT_NEW, false, $this->getUser(), null);
				if(!$status->isOK() && !$status->isGood()) {
					if($status->getMessage()->getKey() == 'edit-already-exists') {
						$nextCommentNumber++;
					}
					else {
						throw new MWException($status->getMessage());
					}
				}
				else {
					$success = true;
				}
			}
			catch(MWException $e) {
				return $e->getMessage();
			}
		}

		// Save metadata for this new comment in the cs_comment_data table.
		$pageId = $wikipage->getId();
		DatabaseQuerier::addCommentDataToDatabase($pageId, $commentedPageId, $commentTitle, $parentCommentId);

		// Set the next page number for the comment.
		DatabaseQuerier::setNextCommentNumberOrHigher($nextCommentNumber+1);

		return $pageId;
	}
}
