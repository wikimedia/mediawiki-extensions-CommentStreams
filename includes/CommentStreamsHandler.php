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

use Action;
use ConfigException;
use ExtensionRegistry;
use MediaWiki\Permissions\PermissionManager;
use NamespaceInfo;
use OutputPage;
use Parser;
use PPFrame;

class CommentStreamsHandler {
	const COMMENTS_ENABLED = 1;
	const COMMENTS_DISABLED = -1;
	const COMMENTS_INHERITED = 0;

	/**
	 * no CommentStreams flag
	 *
	 * @var int
	 */
	private $areCommentsEnabled = self::COMMENTS_INHERITED;

	/**
	 * initially collapse CommentStreams flag
	 *
	 * @var bool
	 */
	private $initiallyCollapseCommentStreams = false;

	/**
	 * @var CommentStreamsFactory
	 */
	private $commentStreamsFactory;

	/**
	 * @var CommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var EchoInterface
	 */
	private $echoInterface;

	/**
	 * @var NamespaceInfo
	 */
	private $namespaceInfo;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @param CommentStreamsFactory $commentStreamsFactory
	 * @param CommentStreamsStore $commentStreamsStore
	 * @param EchoInterface $echoInterface
	 * @param NamespaceInfo $namespaceInfo
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		CommentStreamsFactory $commentStreamsFactory,
		CommentStreamsStore $commentStreamsStore,
		EchoInterface $echoInterface,
		NamespaceInfo $namespaceInfo,
		PermissionManager $permissionManager
	) {
		$this->commentStreamsFactory = $commentStreamsFactory;
		$this->commentStreamsStore = $commentStreamsStore;
		$this->echoInterface = $echoInterface;
		$this->namespaceInfo = $namespaceInfo;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * Implements tag function, <comment-streams/>, which enables
	 * CommentStreams on a page.
	 *
	 * @param ?string $input input between the tags (ignored)
	 * @param array $args tag arguments
	 * @param Parser $parser the parser
	 * @param PPFrame $frame the parent frame
	 * @return string to replace tag with
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function enableCommentStreams(
		?string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		$parser->getOutput()->updateCacheExpiry( 0 );
		$this->areCommentsEnabled = self::COMMENTS_ENABLED;
		if ( isset( $args['id'] ) ) {
			$ret = '<div class="cs-comments" data-id="' . htmlspecialchars( $args['id'] ) . '"></div>';
		} else {
			$ret = '<div class="cs-comments"></div>';
		}
		return $ret;
	}

	/**
	 * Implements tag function, <no-comment-streams/>, which disables
	 * CommentStreams on a page.
	 *
	 * @param ?string $input input between the tags (ignored)
	 * @param array $args tag arguments
	 * @param Parser $parser the parser
	 * @param PPFrame $frame the parent frame
	 * @return string to replace tag with
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function disableCommentStreams(
		?string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		$parser->getOutput()->updateCacheExpiry( 0 );
		$this->areCommentsEnabled = self::COMMENTS_DISABLED;
		return '';
	}

	/**
	 * Implements tag function, <comment-streams-initially-collapsed/>, which
	 * makes CommentStreams on a page start as collapsed when the page is viewed.
	 *
	 * @param ?string $input input between the tags (ignored)
	 * @param array $args tag arguments
	 * @param Parser $parser the parser
	 * @param PPFrame $frame the parent frame
	 * @return string to replace tag with
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function initiallyCollapseCommentStreams(
		?string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		$parser->getOutput()->updateCacheExpiry( 0 );
		$this->initiallyCollapseCommentStreams = true;
		return '';
	}

	/**
	 * initializes the display of comments
	 *
	 * @param OutputPage $output OutputPage object
	 * @throws ConfigException
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
	 * @throws ConfigException
	 */
	private function getComments( OutputPage $output ): array {
		$commentData = [];

		$comments = [];
		$commentWikiPages = $this->commentStreamsStore->getAssociatedComments( $output->getTitle()->getArticleID() );
		foreach ( $commentWikiPages as $wikiPage ) {
			$comment = $this->commentStreamsFactory->newCommentFromWikiPage( $wikiPage );
			if ( $comment ) {
				$comments[] = $comment;
			}
		}

		$config = $output->getConfig();
		$newestStreamsOnTop = $config->get( 'CommentStreamsNewestStreamsOnTop' );
		$votingEnabled = $config->get( 'CommentStreamsEnableVoting' );
		$sortedComments = $this->sortComments(
			$comments,
			$newestStreamsOnTop,
			$votingEnabled
		);

		foreach ( $sortedComments as $comment ) {
			$parentJSON = $comment->getJSON( $output );

			if ( $votingEnabled ) {
				$parentJSON['vote'] = $comment->getVote( $output->getUser() );
			}

			if ( $this->echoInterface->isLoaded() ) {
				$parentJSON['watching'] = $comment->isWatching( $output->getUser() );
			}

			$replies = [];
			$replyWikiPages = $this->commentStreamsStore->getReplies( $comment->getId() );
			foreach ( $replyWikiPages as $wikiPage ) {
				$reply = $this->commentStreamsFactory->newReplyFromWikiPage( $wikiPage );
				if ( $reply ) {
					$replies[] = $reply;
				}
			}
			$sortedReplies = $this->sortReplies( $replies );
			foreach ( $sortedReplies as $reply ) {
				$parentJSON['children'][] = $reply->getJSON( $output );
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
			$this->namespaceInfo->getSubject( $namespace );
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
		if ( !$this->permissionManager->userHasRight( $output->getUser(), 'cs-comment' ) ) {
			$canComment = false;
		}

		$commentStreamsParams = [
			'canComment' => $canComment,
			'moderatorEdit' => $this->permissionManager->userHasRight( $output->getUser(),
				'cs-moderator-edit' ),
			'moderatorDelete' => $this->permissionManager->userHasRight( $output->getUser(),
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
	 * Sort an array of comments by creation date and, if enabled, vote diff
	 * Counterintuitively, returns the oldest disussions/lowest vote disussions first if
	 * $newestOnTop is true, since they will be added from bottom up.
	 *
	 * @param Comment[] $comments an array of all comments on a page
	 * @param bool $newestOnTop true if array should be sorted from newest to oldest
	 * @param bool $enableVoting
	 * @return Comment[] sorted array of comments
	 */
	private function sortComments(
		array $comments,
		bool $newestOnTop,
		bool $enableVoting
	): array {
		usort( $comments, function ( $comment1, $comment2 ) use ( $newestOnTop, $enableVoting ) {
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
							return $date1 < $date2 ? -1 : 1;
						} else {
							return $date1 > $date2 ? -1 : 1;
						}
					} else {
						return $upvotes1 < $upvotes2 ? -1 : 1;
					}
				} else {
					return $votediff1 < $votediff2 ? -1 : 1;
				}
			} else {
				if ( $newestOnTop ) {
					return $date1 < $date2 ? -1 : 1;
				} else {
					return $date1 > $date2 ? -1 : 1;
				}
			}
		} );
		return $comments;
	}

	/**
	 * sort replies to a comment
	 *
	 * @param Reply[] $replies an array of all replies to a comment
	 * @return Reply[] sorted array of replies
	 */
	private function sortReplies( array $replies ): array {
		usort(
			$replies, static function ( $reply1, $reply2 ) {
				$date1 = $reply1->getCreationTimestamp()->timestamp;
				$date2 = $reply2->getCreationTimestamp()->timestamp;
				return $date1 < $date2 ? -1 : 1;
			}
		);
		return $replies;
	}
}
