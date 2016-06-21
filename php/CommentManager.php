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

class CommentManager {
	private static $thisPageId, $thisPageTitle, $thisDisplayName, $thisUserPageURL, $thisPageNamespace, $thisAction, $isLoggedIn;
	private static $noCommentStreams = false, $noCommentStreamsSuppressMessage = false;

	static function hideCommentStreams( $input, array $args, Parser $parser, PPFrame $frame ) {
		$parser->disableCache();
		self::$noCommentStreams = true;
		if( array_key_exists( 'suppress', $args ) ) {
			$suppress = $args['suppress'];
			self::$noCommentStreamsSuppressMessage = $suppress == 'true';
		}

		return "";
	}
	static function addCommentsAndInitializeJS(  OutputPage &$output, Skin &$skin ) {
		if( self::$noCommentStreams == true ) {
			$output->addHTML( Html::openElement('div', array('id' => 'cs-comments')) );
			if( !self::$noCommentStreamsSuppressMessage ) {
				$output->addHTML( Html::rawElement('hr') );
				$output->addHTML( Html::rawElement('h5', array('id' => 'cs-comments-title'), 'Comments are disabled for this page.') );
			}
			$output->addHTML( Html::closeElement('div') );
			return;
		}

		global $wgContentNamespaces;
		global $wgCommentStreamsEnableTalk;
		global $wgCommentStreamsNewestStreamsOnTop;
		global $wgCommentStreamsAllowedNamespaces;
		global $wgCommentStreamsInitiallyCollapsedNamespaces;

		if($wgCommentStreamsAllowedNamespaces === null)
			$wgCommentStreamsAllowedNamespaces = $wgContentNamespaces;

		if(self::$thisAction !== "view") {
			// Don't show comments on any page action other than view (e.g. action=edit, action=delete)
			return true;
		}
		if(self::$thisPageNamespace == NS_COMMENTSTREAMS) {
			// Show comment title on the CommentStreams comment page.
			$commentTitle = htmlspecialchars(DatabaseQuerier::commentTitleForPageId(self::$thisPageId));
			if($commentTitle)
				$output->setPageTitle($commentTitle);
		}
		if(self::$thisPageNamespace === null || 
			( !in_array(self::$thisPageNamespace, $wgCommentStreamsAllowedNamespaces) &&
			!($wgCommentStreamsEnableTalk && self::$thisPageNamespace%2 != 0))) {
			// Don't show comments on a null-namespace page (special page).
			// Don't show comments unless the namespace is a key in $wgCommentStreamsAllowedNamespaces, or
			// if the namespace is a talk page and $wgCommentStreamsEnableTalk is true.
			return true;
		}
		// For rest of page types, display comments - either initially collapsed or expanded.
		// If the namespace is a talk namespace, its expanded/collapsed state mirrors its 'partner' namespace
		if(self::$thisPageNamespace%2 == 0)
			$expanded = in_array(self::$thisPageNamespace, $wgCommentStreamsInitiallyCollapsedNamespaces) ? false : true;
		else
			$expanded = in_array(self::$thisPageNamespace-1, $wgCommentStreamsInitiallyCollapsedNamespaces) ? false : true;

		// Fetch comments
		$comments = DatabaseQuerier::commentsForPageId(self::$thisPageId);
		$parentComments = self::getParentComments($comments, $wgCommentStreamsNewestStreamsOnTop);

		// Add CommentStreams title
		$output->addHTML( Html::openElement('div', array('id' => 'cs-comments')) );
		$output->addHTML( Html::rawElement('hr') );
		$output->addHTML( Html::rawElement('h3', array('id' => 'cs-comments-title'), 'Comment Streams') );

		// Add header div for reply button if logged in and configured for newest streams on top
		if(self::$isLoggedIn && $wgCommentStreamsNewestStreamsOnTop) {
			$output->addHTML( Html::openElement('div', array('id' => 'cs-comments-header')) );	
			$output->addHTML( Html::rawElement('button', array('class' => 'cs-button', 
													'id' => 'cs-newStreamButton', 
													'type' => 'button'
												), 'START A NEW DISCUSSION') );
			$output->addHTML( Html::closeElement('div') );
		}
		

		// Add each parent comment as a new section
		foreach($parentComments as $index => $comment) {
			if($expanded)
				$output->addHTML( Html::openElement('div', array('class' => 'cs-comment-thread cs-expanded')) );
			else
				$output->addHTML( Html::openElement('div', array('class' => 'cs-comment-thread cs-collapsed')) );
			$childComments = self::getChildComments($comments, $comment->getPageId());

			self::addComment( $output, $comment, 'cs-head-comment', count($childComments) );
			// Add child comments for this parent comment
			foreach($childComments as $childComment) {
				self::addComment($output, $childComment, 'cs-reply-comment');
			}

			// Add footer div for reply button if logged in
			if(self::$isLoggedIn) {
				$output->addHTML( Html::openElement('div', array('class' => 'cs-thread-footer')) );
				$output->addHTML( Html::rawElement('button', array('class' => 'cs-button cs-newReplyButton', 
																	'type' => 'button', 
																	'data-thread-id' => $comment->getPageId()
																), 'REPLY') );
				$output->addHTML( Html::closeElement('div') );
			}
			$output->addHTML( Html::closeElement('div') );
		}

		// Add button to start a new comment stream if logged in
		// Otherwise, add a message at the bottom indicating that you must be logged in to post comments.
		if(self::$isLoggedIn) {
			if(!$wgCommentStreamsNewestStreamsOnTop) {
				$output->addHTML( Html::openElement('div', array('id' => 'cs-comments-footer')) );
				$output->addHTML( Html::rawElement('button', array('class' => 'cs-button', 
																	'id' => 'cs-newStreamButton', 
																	'type' => 'button'
																), 'START A NEW DISCUSSION') );
				$output->addHTML( Html::closeElement('div') );
			}
		}
		else {
			$output->addHTML( Html::rawElement('h5', array('id' => 'cs-comments-title'), 'You must be logged in to post, edit, or delete comments.') );
		}

		// Close CommentStreams div.
		$output->addHTML( Html::closeElement('div') );

		// Initialize JS with relevant data
		self::initializeJS($output);

		return true;
	}
	static function storePageInfo( $output, $article, $title, $user, $request, $wiki ) {
		global $wgCommentStreamsSMWinstalled;
		global $wgCommentStreamsUserRealNamePropertyName;

		$output->addModules( "ext.CommentStreams" );

		$pageId = $article->getPage()->getId();
		self::$thisPageId = $pageId;

		self::$thisPageTitle = $title->getPrefixedText();

		$userTitleObject = Title::newFromText($user->getName(), NS_USER);
		if($wgCommentStreamsSMWinstalled && $wgCommentStreamsUserRealNamePropertyName !== null)
			self::$thisDisplayName = htmlspecialchars(DatabaseQuerier::queryForUserRealNameProperty($userTitleObject, $wgCommentStreamsUserRealNamePropertyName));
		if(self::$thisDisplayName == null) {
			if($user->getRealName() != null)
				self::$thisDisplayName = $user->getRealName();
			else
				self::$thisDisplayName = $user->getName();
		}
		self::$thisUserPageURL = self::getHTMLFromWikitext( '[[User:' . $user->getName() . '|' . self::$thisDisplayName . ']]', $output );
		self::$thisPageNamespace = $title->getNamespace();
		self::$thisAction = $request->getVal("action", "view");
		self::$isLoggedIn = $user->isLoggedIn();

		return true;
	}
	static function addComment( $output, $comment, $class, $childrenCount = null ) {
		global $wgServer, $wgScriptPath;

		$html = Html::openElement('div', array('class' => 'cs-comment ' . $class, 'data-id' => $comment->getPageId()));

		$html .= Html::openElement('div', array('class' => 'cs-commentTitle'));
		if($class == 'cs-head-comment')
			$html .= Html::rawElement( 'button', array('class' => 'cs-button cs-toggleButton',
																'type' => 'button',
															), 'COLLAPSE' );
		$html .= Html::openElement('img', array('class' => 'cs-userImage', 'src' => $wgServer . $wgScriptPath .  '/extensions/CommentStreams/js+css/images/user.png'));
		$html .= Html::openElement('div', array('class' => 'cs-commentAuthor'));
		
		$html .= self::getHTMLFromWikitext( '[[User:' . $comment->getUsername() . '|' . $comment->getDisplayName() . ']]', $output );	
		
		$html .= Html::closeElement('div');
		$html .= Html::rawElement('p', array(), $comment->getTitle());
		$html .= Html::closeElement('div');
		$output->addHTML($html);

		$output->addHTML( Html::openElement('div', array('class' => 'cs-commentBody')));
		$output->addWikiText($comment->getText());
		$output->addHTML( Html::closeElement('div'));
		$html = Html::openElement('div', array('class' => 'cs-commentFooter'));
		$html .= Html::openElement('span', array('class' => 'cs-commentDetails'));
		$html .= 'Posted on ' . $comment->getCreationDate()->format("M j \a\\t g:i a");
		$html .= Html::closeElement('span');
		if(self::$thisDisplayName == $comment->getDisplayName()) {
			$html .= "|";
			$html .= Html::rawElement('button', array('class' => 'cs-button cs-editCommentButton'), 'EDIT');
			if($class == 'cs-reply-comment' || ($class == 'cs-head-comment' && $childrenCount == 0)) {
				$html .= Html::openElement('span', array('class' => 'cs-deleteSpan'));
				$html .= "|";
				$html .= Html::rawElement('button', array('class' => 'cs-button cs-deleteCommentButton'), 'DELETE');
				$html .= Html::closeElement('span');
			}
		}
		$html.= Html::closeElement('div');
		$output->addHTML($html);

		$output->addHTML( Html::closeElement('div'));
	}

	static function getParentComments( $allComments, $newestOnTop = false ) {
		$array = array_filter($allComments, function($comment) { return $comment->getParentId() == null; } );
		if($newestOnTop) {
			usort($array, function($comment1, $comment2) { 
				$date1 = $comment1->getCreationDate()->timestamp;
				$date2 = $comment2->getCreationDate()->timestamp;
				return $date1 > $date2 ? -1 : 1;
			} );
		}
		else {
			usort($array, function($comment1, $comment2) { 
				$date1 = $comment1->getCreationDate()->timestamp;
				$date2 = $comment2->getCreationDate()->timestamp;
				return $date1 < $date2 ? -1 : 1;
			} );
		}
		return $array;
	}
	static function getChildComments( $allComments, $parentId ) {
		$array = array_filter($allComments, function($comment) use($parentId) { return $comment->getParentId() == $parentId; } ); 
		usort($array, function($comment1, $comment2) { 
			$date1 = $comment1->getCreationDate()->timestamp;
			$date2 = $comment2->getCreationDate()->timestamp;

			return $date1 < $date2 ? -1 : 1;
		} );
		return $array;
	}
	static function initializeJS( $output ) {
		global $wgCommentStreamsSMWinstalled;
		global $wgSemanticTitleProperties;
		global $wgCommentStreamsNewestStreamsOnTop;
		global $wgCommentStreamsCommentTitlePropertyName;

		$commentStreamsParams = array(
			'isLoggedIn' => self::$isLoggedIn,
			'userPageURL' => self::$thisUserPageURL,
			'pageId' => self::$thisPageId,
			'pageTitle' => self::$thisPageTitle,
			'smwInstalled' => $wgCommentStreamsSMWinstalled ? 1 : 0,
			'semanticTitlePropertyName' => $wgCommentStreamsCommentTitlePropertyName,
			'newestStreamsOnTop' => $wgCommentStreamsNewestStreamsOnTop ? 1 : 0
			);
		
		$output->addJsConfigVars('CommentStreams', $commentStreamsParams);
	}
	static function getHTMLFromWikitext( $wikitext, $outputPage, $endOfLine = false ) {
			// Get OutputPage and save its current HTML
			$oldOutputText = $outputPage->getHTML();
			$outputPage->clearHTML();

			// Add the text of this wikipage to the OutputPage, which also parses it
			$outputPage->addWikitext( $wikitext, false );

			// Save the HTML of this OutputPage, and return the OutputPage to its original state
			$result = $outputPage->getHTML();
			$outputPage->clearHTML();
			$outputPage->addHTML( $oldOutputText );

			return $result;
	}
}
