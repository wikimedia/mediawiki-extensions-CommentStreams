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

class ApiCSQueryComment extends ApiBase {
	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}
	public function execute() {
		$pageId = $this->getMain()->getVal( 'pageId' );
		$title = $this->getMain()->getVal( 'title' );
		$pageFormat = $this->getMain()->getVal( 'pageFormat' );
		if(!$pageId && !$title) {
			$this->getResult()->addValue(null, 'error', 
				array('code' => 'missingparam', 'info' => 'Must specify either page ID or title'));
			return true;
		}
		if(!$pageFormat)
			$pageFormat = 'html';

		$wikipage = null;
		$titleObject = null;

		if($pageId) {
			$wikipage = WikiPage::newFromId( $pageId );
			$titleObject = Title::newFromID($pageId);
		}
		else if($title) {
			$titleObject = Title::newFromText($title);
			$wikipage = WikiPage::factory(Title::newFromText($title, NS_COMMENTSTREAMS));
			$pageId = $wikipage->getId();
		}
		if(!$wikipage || !$wikipage->exists() || !$titleObject) {
			$this->getResult()->addValue(null, 'error',
				array('code' => 'nonexistentpage', 'info' => 'A comment doesn\'t exist for this title or page ID'));
			return true;
		}
		if($titleObject->getNamespace() != NS_COMMENTSTREAMS) {
			$this->getResult()->addValue(null, 'error',
				array('code' => 'incorrectnamespace', 
					'info' => 'The supplied page is not in the CommentStreams namespace.'
				)
			);
			return true;
		}

		// Get comment title, username, and timestamp
			$commentTitle = DatabaseQuerier::commentTitleForPageId( $pageId );
			$user = $wikipage->getUserText();
			$timestamp = MWTimestamp::getLocalInstance($titleObject->getEarliestRevTime())->format("M j \a\\t g:i a");

		if($pageFormat == 'html') {
			// Get OutputPage and save its current HTML
			$out = $this->getOutput();
			$oldOutputText = $out->getHTML();
			$out->clearHTML();

			// Add the text of this wikipage to the OutputPage, which also parses it
			$out->addWikitext( ContentHandler::getContentText( $wikipage->getContent( Revision::RAW ) ) );

			// Save the HTML of this OutputPage, and return the OutputPage to its original state
			$result = $out->getHTML();
			$out->clearHTML();
			$out->addHTML( $oldOutputText );

			$this->getResult()->addValue(null, $this->getModuleName(), array('title' => $commentTitle,
																			'user' => $user,
																			'timestamp' => $timestamp,
																			'html' => $result));
		}
		else {
			$wikitext = ContentHandler::getContentText( $wikipage ->getContent( Revision:: RAW ) );
			$this->getResult()->addValue(null, $this->getModuleName(), array('title' => $commentTitle,
																			  'user' => $user,
																			  'timestamp' => $timestamp,
																			  'wikitext' => $wikitext));
		}
		return true;

	}
	public function getDescription() {
		return 'Get HTML for a CommentStreams comment. ' . 
		'You may specify either the page ID of the wikipage containing the comment' . 
		' or the title of the wikipage containing the comment (e.g. \'CommentStreams:2\'), ' .
		'but at least one must be specified. If both are specified, the page ID is used and ' .
		'the title is ignored.';
	}
	public function getAllowedParams() {
		return array(
			'pageId' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			),
			'title' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			),
			'pageFormat' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			)
		);
	}
	public function getParamDescription() {
		return array(
			'pageId' => 'page ID of the wiki page which holds this comment',
			'title' => 'title of the wiki page which holds this comment',
			'pageFormat' => 'format of the data (html or wikitext). If unspecified, default is html'
		);
	}
	public function getExamples() {
		return array(
			'api.php?action=csQueryComment&pageId=20',
			'api.php?action=csQueryComment&title=CommentStreams:4',
			'api.php?action=csQueryComment&title=CommentStreams:4&pageFormat=wikitext'
		);
	}
	public function getHelpUrls() {
		return '';
	}
}
