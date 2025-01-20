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
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommentStreams\Store\NamespacePageStore;
use MediaWiki\Html\Html;
use MediaWiki\Page\PageReference;
use MediaWiki\Permissions\PermissionManager;
use OutputPage;
use Parser;
use PPFrame;
use Throwable;
use Title;

class CommentStreamsHandler {
	public const CONSTRUCTOR_OPTIONS = [
		'CommentStreamsExportCommentsAutomatically'
	];

	public const COMMENTS_ENABLED = 1;
	public const COMMENTS_DISABLED = -1;
	public const COMMENTS_INHERITED = 0;

	/**
	 * @var bool
	 */
	private $exportCommentsAutomatically;

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
	 * @var ICommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var NotifierInterface
	 */
	private $notifier;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @var CommentSerializer
	 */
	private $commentSerializer;

	/**
	 * @param ServiceOptions $options
	 * @param ICommentStreamsStore $commentStreamsStore
	 * @param NotifierInterface $notifier
	 * @param PermissionManager $permissionManager
	 * @param CommentSerializer $commentSerializer
	 */
	public function __construct(
		ServiceOptions $options,
		ICommentStreamsStore $commentStreamsStore,
		NotifierInterface $notifier,
		PermissionManager $permissionManager,
		CommentSerializer $commentSerializer
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->exportCommentsAutomatically = $options->get( 'CommentStreamsExportCommentsAutomatically' );
		$this->commentStreamsStore = $commentStreamsStore;
		$this->notifier = $notifier;
		$this->permissionManager = $permissionManager;
		$this->commentSerializer = $commentSerializer;
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
		// unless they are explicitly enabled on the given page
		$config = $output->getConfig();
		$csAllowedNamespaces = $config->get( 'CommentStreamsAllowedNamespaces' );
		if ( $csAllowedNamespaces === null ) {
			$csAllowedNamespaces = $config->get( 'ContentNamespaces' );
		} elseif (
			$csAllowedNamespaces === self::COMMENTS_DISABLED && $this->areCommentsEnabled != self::COMMENTS_ENABLED
		) {
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

		$comments = $this->commentStreamsStore->getAssociatedComments( $output->getTitle() );

		$config = $output->getConfig();
		$newestStreamsOnTop = $config->get( 'CommentStreamsNewestStreamsOnTop' );
		$votingEnabled = $config->get( 'CommentStreamsEnableVoting' );
		$sortedComments = $this->sortComments(
			$comments,
			$newestStreamsOnTop,
			$votingEnabled
		);

		foreach ( $sortedComments as $comment ) {
			$parentJSON = $this->commentSerializer->serializeComment( $comment, $output );

			$replies = $this->commentStreamsStore->getReplies( $comment );
			$sortedReplies = $this->sortReplies( $replies );
			foreach ( $sortedReplies as $reply ) {
				$parentJSON['children'][] = $this->commentSerializer->serializeReply( $reply, $output );
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
		$config = $output->getConfig();

		if ( $this->initiallyCollapseCommentStreams ) {
			$initiallyCollapsed = true;
		} else {
			$initiallyCollapsedNamespaces = $config->get( 'CommentStreamsInitiallyCollapsedNamespaces' );
			$initiallyCollapsed = in_array( $output->getTitle()->getNamespace(), $initiallyCollapsedNamespaces );
		}

		$canComment = true;
		if ( !$this->permissionManager->userHasRight( $output->getUser(), 'cs-comment' ) ) {
			$canComment = false;
		}

		$commentStreamsParams = [
			'canComment' => $canComment,
			'moderatorEdit' => $this->permissionManager->userHasRight( $output->getUser(), 'cs-moderator-edit' ),
			'moderatorDelete' => $this->permissionManager->userHasRight(
				$output->getUser(), 'cs-moderator-delete'
			),
			'moderatorFastDelete' => $config->get( 'CommentStreamsModeratorFastDelete' ) ? 1 : 0,
			'showLabels' => $config->get( 'CommentStreamsShowLabels' ) ? 1 : 0,
			'newestStreamsOnTop' => $config->get( 'CommentStreamsNewestStreamsOnTop' ) ? 1 : 0,
			'initiallyCollapsed' => $initiallyCollapsed,
			'enableVoting' => $config->get( 'CommentStreamsEnableVoting' ) ? 1 : 0,
			'enableWatchlist' => $this->notifier->isLoaded() ? 1 : 0,
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
		usort( $comments, function ( Comment $comment1, Comment $comment2 ) use ( $newestOnTop, $enableVoting ) {
			$date1 = $comment1->getCreated()->timestamp;
			$date2 = $comment2->getCreated()->timestamp;
			if ( $enableVoting ) {
				$upvotes1 = $this->commentStreamsStore->getNumUpVotes( $comment1 );
				$downvotes1 = $this->commentStreamsStore->getNumDownVotes( $comment1 );
				$votediff1 = $upvotes1 - $downvotes1;
				$upvotes2 = $this->commentStreamsStore->getNumUpVotes( $comment2 );
				$downvotes2 = $this->commentStreamsStore->getNumDownVotes( $comment2 );
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
			$replies, static function ( Reply $reply1, Reply $reply2 ) {
				$date1 = $reply1->getCreated()->timestamp;
				$date2 = $reply2->getCreated()->timestamp;
				return $date1 < $date2 ? -1 : 1;
			}
		);
		return $replies;
	}

	/**
	 * Add extra pages to the list of pages to export.
	 *
	 * @param string[] $inputPages List of page titles to export
	 * @return PageReference[] List of extra page titles
	 */
	public function getExtraExportPages( array $inputPages ): array {
		if ( !( $this->commentStreamsStore instanceof NamespacePageStore ) ) {
			return [];
		}
		$extraPages = [];
		if ( $this->exportCommentsAutomatically ) {
			foreach ( $inputPages as $page ) {
				try {
					$title = Title::newFromText( $page );
					if ( $title->exists() ) {
						$comments = $this->commentStreamsStore->getAssociatedComments( $title );
						foreach ( $comments as $comment ) {
							$commentPage = Title::newFromID( $comment->getId() );
							if ( $commentPage ) {
								$extraPages[] = $commentPage;
								$replies = $this->commentStreamsStore->getReplies( $comment );
								foreach ( $replies as $reply ) {
									$replyTitle = Title::newFromID( $reply->getId() );
									if ( $replyTitle ) {
										$extraPages[] = $replyTitle;
									}
								}
							}
						}
					}
				} catch ( Throwable $ex ) {
					// Ignore errorsq
				}
			}
		}
		return $extraPages;
	}

	/**
	 * Show TOC for comment streams.
	 *
	 * @param ?string $input input between the tags (ignored)
	 * @param array $args tag arguments
	 * @param Parser $parser the parser
	 * @param PPFrame $frame the parent frame
	 * @return string to replace tag with
	 */
	public function tocTag(
		?string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		$lang = $parser->getTargetLanguage();
		$title = wfMessage( 'commentstreams-toc' )->inLanguage( $lang )->escaped();

		return '<div role="navigation" aria-labelledby="cs-toc-heading" class="cs-toc">'
			. Html::element( 'input', [
				'type' => 'checkbox',
				'role' => 'button',
				'id' => 'cs-toctogglecheckbox',
				'class' => 'toctogglecheckbox',
				'style' => 'display:none',
			] )
			. Html::openElement( 'div', [
				'class' => 'toctitle',
				'lang' => $lang->getHtmlCode(),
				'dir' => $lang->getDir(),
			] )
			. '<h2 id="cs-toc-heading">' . $title . '</h2>'
			. '<span class="toctogglespan">'
			. Html::label( '', 'cs-toctogglecheckbox', [
				'class' => 'toctogglelabel',
			] )
			. '</span>'
			. '</div>'
			. '<ul id="cs-comment-list"></ul></div>';
	}
}
