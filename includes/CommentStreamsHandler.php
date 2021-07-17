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

use Action;
use ConfigException;
use ExtensionRegistry;
use MWException;
use OutputPage;

class CommentStreamsHandler {
	const COMMENTS_ENABLED = 1;
	const COMMENTS_DISABLED = -1;
	const COMMENTS_INHERITED = 0;

	/**
	 * no CommentStreams flag
	 */
	private $areCommentsEnabled = self::COMMENTS_INHERITED;

	/**
	 * @var CommentStreamsFactory
	 */
	private $commentStreamsFactory;

	/**
	 * @var CommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var CommentStreamsEchoInterface
	 */
	private $echoInterface;

	/**
	 * @param CommentStreamsFactory $commentStreamsFactory
	 * @param CommentStreamsStore $commentStreamsStore
	 * @param CommentStreamsEchoInterface $echoInterface
	 */
	public function __construct(
		CommentStreamsFactory $commentStreamsFactory,
		CommentStreamsStore $commentStreamsStore,
		CommentStreamsEchoInterface $echoInterface
	) {
		$this->commentStreamsFactory = $commentStreamsFactory;
		$this->commentStreamsStore = $commentStreamsStore;
		$this->echoInterface = $echoInterface;
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

	/**
	 * initially collapse CommentStreams flag
	 */
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
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function init( OutputPage $output ) {
		if ( $this->checkDisplayComments( $output ) ) {
			$comments = $this->getComments( $output );
			$this->initJS( $output, $comments );
		}
	}

	/**
	 * checks to see if comments should be displayed on this page
	 *
	 * @param OutputPage $output the OutputPage object
	 * @return bool true if comments should be displayed on this page
	 * @throws ConfigException
	 */
	private function checkDisplayComments( OutputPage $output ): bool {
		// don't display comments on this page if they are explicitly disabled
		if ( $this->areCommentsEnabled === self::COMMENTS_DISABLED ) {
			return false;
		}

		// don't display comments on any page action other than view action
		if ( Action::getActionName( $output->getContext() ) !== "view" ) {
			return false;
		}

		// if $wgCommentStreamsAllowedNamespaces is not set, display comments
		// in all content namespaces and if set to -1, don't display comments
		$config = $output->getConfig();
		$csAllowedNamespaces = $config->get( 'CommentStreamsAllowedNamespaces' );
		if ( $csAllowedNamespaces === null ) {
			$csAllowedNamespaces = $config->get( 'ContentNamespaces' );
		} elseif ( $csAllowedNamespaces === self::COMMENTS_DISABLED ) {
			return false;
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

		// display comments on this page if it contains the <comment-streams/> tag function
		if ( $this->areCommentsEnabled === self::COMMENTS_ENABLED ) {
			return true;
		}

		// display comments on this page if this namespace is one of the explicitly allowed namespaces
		return in_array( $namespace, $csAllowedNamespaces );
	}

	/**
	 * retrieve all comments for the current page
	 *
	 * @param OutputPage $output the OutputPage object for the current page
	 * @return Comment[] array of comments
	 * @throws MWException
	 * @throws ConfigException
	 */
	private function getComments( OutputPage $output ): array {
		$commentData = [];
		$pageId = $output->getTitle()->getArticleID();
		$comment_page_ids = $this->commentStreamsStore->getAssociatedComments( $pageId );
		$allComments = [];
		foreach ( $comment_page_ids as $id ) {
			$wikipage = CommentStreamsUtils::newWikiPageFromId( $id );
			if ( $wikipage !== null ) {
				$comment = $this->commentStreamsFactory->newFromWikiPage( $wikipage );
				if ( $comment !== null ) {
					$allComments[] = $comment;
				}
			}
		}

		$config = $output->getConfig();
		$newestStreamsOnTop = $config->get( 'CommentStreamsNewestStreamsOnTop' );
		$votingEnabled = $config->get( 'CommentStreamsEnableVoting' );
		$parentComments = $this->getDiscussions(
			$allComments,
			$newestStreamsOnTop,
			$votingEnabled
		);
		foreach ( $parentComments as $parentComment ) {
			$parentJSON = $parentComment->getJSON( $output );
			if ( $votingEnabled ) {
				$parentJSON['vote'] = $parentComment->getVote( $output->getUser() );
			}
			if ( $this->echoInterface->isLoaded() ) {
				$parentJSON['watching'] = $parentComment->isWatching( $output->getUser() );
			}
			$childComments = $this->getReplies( $allComments, $parentComment->getId() );
			foreach ( $childComments as $childComment ) {
				$childJSON = $childComment->getJSON( $output );
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
	 * @throws ConfigException
	 */
	private function initJS( OutputPage $output, array $comments ) {
		// determine if comments should be initially collapsed or expanded
		// if the namespace is a talk namespace, use state of its subject namespace
		$title = $output->getTitle();
		$namespace = $title->getNamespace();
		if ( $title->isTalkPage() ) {
			$namespace = CommentStreamsUtils::getSubjectNamespace( $namespace );
		}

		$config = $output->getConfig();

		if ( $this->initiallyCollapseCommentStreams ) {
			$initiallyCollapsed = true;
		} else {
			$initiallyCollapsedNamespaces =
				$config->get( 'CommentStreamsInitiallyCollapsedNamespaces' );
			$initiallyCollapsed = in_array( $namespace, $initiallyCollapsedNamespaces );
		}

		$canComment = true;
		if ( !CommentStreamsUtils::userHasRight( $output->getUser(), 'cs-comment' ) ) {
			$canComment = false;
		}

		$commentStreamsParams = [
			'canComment' => $canComment,
			'moderatorEdit' => CommentStreamsUtils::userHasRight( $output->getUser(),
				'cs-moderator-edit' ),
			'moderatorDelete' => CommentStreamsUtils::userHasRight( $output->getUser(),
				'cs-moderator-delete' ),
			'moderatorFastDelete' => $config->get( 'CommentStreamsModeratorFastDelete' ) ? 1 : 0,
			'showLabels' => $config->get( 'CommentStreamsShowLabels' ) ? 1 : 0,
			'newestStreamsOnTop' => $config->get( 'CommentStreamsNewestStreamsOnTop' ) ? 1 : 0,
			'initiallyCollapsed' => $initiallyCollapsed,
			'enableVoting' => $config->get( 'CommentStreamsEnableVoting' ) ? 1 : 0,
			'enableWatchlist' => $this->echoInterface->isLoaded() ? 1 : 0,
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
	 * @param bool $newestOnTop true if array should be sorted from newest to
	 * @param bool $enableVoting
	 * @return array an array of all discussions
	 * oldest
	 */
	private function getDiscussions(
		array $allComments,
		bool $newestOnTop,
		bool $enableVoting
	): array {
		$array = array_filter(
			$allComments, static function ( $comment ) {
				return $comment->getParentId() === null;
			}
		);
		usort( $array, function ( $comment1, $comment2 ) use ( $newestOnTop, $enableVoting ) {
			$date1 = $comment1->getCreationTimestamp()->timestamp;
			$date2 = $comment2->getCreationTimestamp()->timestamp;
			if ( $enableVoting ) {
				$upvotes1 = $this->commentStreamsStore->getNumUpVotes( $comment1->getId() );
				$downvotes1 = $this->commentStreamsStore->getNumDownVotes( $comment1->getId() );
				$votediff1 = $upvotes1 - $downvotes1;
				$upvotes2 = $this->commentStreamsStore->getNumUpVotes( $comment2->getId() );
				$downvotes2 = $this->commentStreamsStore->getNumDownVotes( $comment2->getId() );
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
	private function getReplies( array $allComments, int $parentId ): array {
		$array = array_filter(
			$allComments, static function ( $comment ) use ( $parentId ) {
				if ( $comment->getParentId() === $parentId ) {
					return true;
				}
				return false;
			}
		);
		usort(
			$array, static function ( $comment1, $comment2 ) {
				$date1 = $comment1->getCreationTimestamp()->timestamp;
				$date2 = $comment2->getCreationTimestamp()->timestamp;
				return $date1 < $date2 ? -1 : 1;
			}
		);
		return $array;
	}
}
