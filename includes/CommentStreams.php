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

class CommentStreams {

	// CommentStreams singleton instance
	private static $instance = null;

	const COMMENTS_ENABLED = 1;
	const COMMENTS_DISABLED = -1;
	const COMMENTS_INHERITED = 0;

	// no CommentStreams flag
	private $areCommentsEnabled = self::COMMENTS_INHERITED;

	/**
	 * create a CommentStreams singleton instance
	 *
	 * @return CommentStreams a singleton CommentStreams instance
	 */
	public static function singleton() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new CommentStreams();
		}
		return self::$instance;
	}

	/**
	 * enables the display of comments on the current page
	 */
	public function enableCommentsOnPage() {
		$this->areCommentsEnabled = self::COMMENTS_ENABLED;
	}

	/**
	 * disables the display of comments on the current page
	 */
	public function disableCommentsOnPage() {
		$this->areCommentsEnabled = self::COMMENTS_DISABLED;
	}

	// initially collapse CommentStreams flag
	private $initiallyCollapseCommentStreams = false;

	/**
	 * makes the comments appear initially collapsed when the current page
	 * is viewed
	 */
	public function initiallyCollapseCommentsOnPage() {
		$this->initiallyCollapseCommentStreams = true;
	}

	/**
	 * initializes the display of comments
	 *
	 * @param OutputPage $output OutputPage object
	 */
	public function init( $output ) {
		if ( $this->checkDisplayComments( $output ) ) {
			$comments = $this->getComments( $output );
			$this->initJS( $output, $comments );
		}
	}

	/**
	 * checks to see if comments should be displayed on this page
	 *
	 * @param OutputPage $output the OutputPage object
	 * @return boolean true if comments should be displayed on this page
	 */
	private function checkDisplayComments( $output ) {
		// don't display comments on this page if they are explicitly disabled
		if ( $this->areCommentsEnabled === self::COMMENTS_DISABLED ) {
			return false;
		}

		// don't display comments on any page action other than view action
		if ( Action::getActionName( $output->getContext() ) !== "view" ) {
			return false;
		}

		// if $wgCommentStreamsAllowedNamespaces is not set, display comments
		// in all content namespaces
		$csAllowedNamespaces = $GLOBALS['wgCommentStreamsAllowedNamespaces'];
		if ( is_null( $csAllowedNamespaces ) ) {
			$csAllowedNamespaces = $GLOBALS['wgContentNamespaces'];
		} elseif ( !is_array( $csAllowedNamespaces ) ) {
			$csAllowedNamespaces = [ $csAllowedNamespaces ];
		}

		$title = $output->getTitle();
		$namespace = $title->getNamespace();

		// don't display comments in CommentStreams namespace
		if ( $namespace === NS_COMMENTSTREAMS ) {
			return false;
		}

		// don't display comments on pages that do not exist
		if ( !$title->exists() ) {
			return false;
		}

		// don't display comments on redirect pages
		if ( $title->isRedirect() ) {
			return false;
		}

		// display comments on this page if they are explicitly enabled
		if ( $this->areCommentsEnabled === self::COMMENTS_ENABLED ) {
			return true;
		}

		// don't display comments in a talk namespace unless:
		// 1) $wgCommentStreamsEnableTalk is true, OR
		// 2) the namespace is a talk namespace for a namespace in the array of
		// allowed namespaces
		// 3) comments have been explicitly enabled on that namespace with
		// <comment-streams/>
		if ( $title->isTalkPage() ) {
			$subject_namespace = MWNamespace::getSubject( $namespace );
			if ( !$GLOBALS['wgCommentStreamsEnableTalk'] &&
				!in_array( $subject_namespace, $csAllowedNamespaces ) ) {
				return false;
			}
		} elseif ( !in_array( $namespace, $csAllowedNamespaces ) ) {
			// only display comments in subject namespaces in the list of allowed
			// namespaces
			return false;
		}

		return true;
	}

	/**
	 * retrieve all comments for the current page
	 *
	 * @param OutputPage $output the OutputPage object for the current page
	 * @return Comment[] array of comments
	 */
	private function getComments( $output ) {
		$commentData = [];
		$pageId = $output->getTitle()->getArticleID();
		$allComments = Comment::getAssociatedComments( $pageId );
		$parentComments = $this->getDiscussions( $allComments,
			$GLOBALS['wgCommentStreamsNewestStreamsOnTop'],
			$GLOBALS['wgCommentStreamsEnableVoting'] );
		foreach ( $parentComments as $parentComment ) {
			$parentJSON = $parentComment->getJSON();
			if ( $GLOBALS['wgCommentStreamsEnableVoting'] ) {
				$parentJSON['vote'] = $parentComment->getVote( $output->getUser() );
			}
			if ( ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
				$parentJSON['watching'] = $parentComment->isWatching( $output->getUser() );
			}
			$childComments = $this->getReplies( $allComments,
				$parentComment->getId() );
			foreach ( $childComments as $childComment ) {
				$childJSON = $childComment->getJSON();
				$parentJSON['children'][] = $childJSON;
			}
			$commentData[] = $parentJSON;
		}
		return $commentData;
	}

	/**
	 * initialize JavaScript
	 *
	 * @param OutputPage $output the OutputPage object
	 * @param Comment[] $comments array of comments on the current page
	 */
	private function initJS( $output, $comments ) {
		// determine if comments should be initially collapsed or expanded
		// if the namespace is a talk namespace, use state of its subject namespace
		$title = $output->getTitle();
		$namespace = $title->getNamespace();
		if ( $title->isTalkPage() ) {
			$namespace = MWNamespace::getSubject( $namespace );
		}

		if ( $this->initiallyCollapseCommentStreams ) {
			$initiallyCollapsed = true;
		} else {
			$initiallyCollapsed = in_array( $namespace,
				$GLOBALS['wgCommentStreamsInitiallyCollapsedNamespaces'] );
		}

		$canComment = true;
		if ( !in_array( 'cs-comment', $output->getUser()->getRights() ) ||
			$output->getUser()->isBlocked() ) {
			$canComment = false;
		}

		$commentStreamsParams = [
			'canComment' => $canComment,
			'moderatorEdit' => in_array( 'cs-moderator-edit',
				$output->getUser()->getRights() ),
			'moderatorDelete' => in_array( 'cs-moderator-delete',
				$output->getUser()->getRights() ),
			'moderatorFastDelete' =>
				$GLOBALS['wgCommentStreamsModeratorFastDelete'] ? 1 : 0,
			'showLabels' =>
				$GLOBALS['wgCommentStreamsShowLabels'] ? 1 : 0,
			'userDisplayName' =>
				Comment::getDisplayNameFromUser( $output->getUser() ),
			'userAvatar' =>
				Comment::getAvatarFromUser( $output->getUser() ),
			'newestStreamsOnTop' =>
				$GLOBALS['wgCommentStreamsNewestStreamsOnTop'] ? 1 : 0,
			'initiallyCollapsed' => $initiallyCollapsed,
			'enableVoting' =>
				$GLOBALS['wgCommentStreamsEnableVoting'] ? 1 : 0,
			'enableWatchlist' =>
				ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ? 1 : 0,
			'comments' => $comments
		];
		$output->addJsConfigVars( 'CommentStreams', $commentStreamsParams );
		$output->addModules( 'ext.CommentStreams' );
		if ( ExtensionRegistry::getInstance()->isLoaded( 'VEForAll' ) ) {
			$output->addModules( 'ext.veforall.main' );
		}
	}

	/**
	 * return all discussions (top level comments) in an array of comments
	 *
	 * @param array $allComments an array of all comments on a page
	 * @param boolean $newestOnTop true if array should be sorted from newest to
	 * @return array an array of all discussions
	 * oldest
	 */
	private function getDiscussions( $allComments, $newestOnTop, $enableVoting ) {
		$array = array_filter(
			$allComments, function ( $comment ) {
				return is_null( $comment->getParentId() );
			}
		);
		usort( $array, function ( $comment1, $comment2 ) use ( $newestOnTop, $enableVoting ) {
			$date1 = $comment1->getCreationTimestamp()->timestamp;
			$date2 = $comment2->getCreationTimestamp()->timestamp;
			if ( $enableVoting ) {
				$upvotes1 = $comment1->getNumUpVotes();
				$downvotes1 = $comment1->getNumDownVotes();
				$votediff1 = $upvotes1 - $downvotes1;
				$upvotes2 = $comment2->getNumUpVotes();
				$downvotes2 = $comment2->getNumDownVotes();
				$votediff2 = $upvotes2 - $downvotes2;
				if ( $votediff1 === $votediff2 ) {
					if ( $upvotes1 === $upvotes2 ) {
						if ( $newestOnTop ) {
							return $date1 > $date2 ? -1 : 1;
						} else {
							return $date1 < $date2 ? -1 : 1;
						}
					} else {
						return $upvotes1 > $upvotes2 ? -1 : 1;
					}
				} else {
					return $votediff1 > $votediff2 ? -1 : 1;
				}
			} else {
				if ( $newestOnTop ) {
					return $date1 > $date2 ? -1 : 1;
				} else {
					return $date1 < $date2 ? -1 : 1;
				}
			}
		} );
		return $array;
	}

	/**
	 * return all replies for a given discussion in an array of comments
	 *
	 * @param array $allComments an array of all comments on a page
	 * @param int $parentId the page ID of the discussion to get replies for
	 * @return array an array of replies for the given discussion
	 */
	private function getReplies( $allComments, $parentId ) {
		$array = array_filter(
			$allComments, function ( $comment ) use ( $parentId ) {
				if ( $comment->getParentId() === $parentId ) {
					return true;
				}
				return false;
			}
		);
		usort(
			$array, function ( $comment1, $comment2 ) {
				$date1 = $comment1->getCreationTimestamp()->timestamp;
				$date2 = $comment2->getCreationTimestamp()->timestamp;
				return $date1 < $date2 ? -1 : 1;
			}
		);
		return $array;
	}
}
