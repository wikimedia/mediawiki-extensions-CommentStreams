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

use Article;
use HtmlArmor;
use MediaWiki;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\CanonicalNamespacesHook;
use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\Hook\MovePageIsValidMoveHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Search\Hook\ShowSearchHitTitleHook;
use MWException;
use OutputPage;
use PageProps;
use Parser;
use SearchResult;
use Skin;
use SpecialSearch;
use Status;
use Title;
use User;
use WebRequest;
use WikiPage;

class MainHooks implements
	CanonicalNamespacesHook,
	MediaWikiPerformActionHook,
	MovePageIsValidMoveHook,
	GetUserPermissionsErrorsHook,
	BeforePageDisplayHook,
	ShowSearchHitTitleHook,
	ParserFirstCallInitHook
{
	/**
	 * @var CommentStreamsHandler
	 */
	private $commentStreamsHandler;

	/**
	 * @var CommentStreamsFactory
	 */
	private $commentStreamsFactory;

	/**
	 * @var LinkRenderer
	 */
	private $linkRenderer;

	/**
	 * @var RevisionStore
	 */
	private $revisionStore;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @param CommentStreamsHandler $commentStreamsHandler
	 * @param CommentStreamsFactory $commentStreamsFactory
	 * @param LinkRenderer $linkRenderer
	 * @param RevisionStore $revisionStore
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		CommentStreamsHandler $commentStreamsHandler,
		CommentStreamsFactory $commentStreamsFactory,
		LinkRenderer $linkRenderer,
		RevisionStore $revisionStore,
		PermissionManager $permissionManager
	) {
		$this->commentStreamsHandler = $commentStreamsHandler;
		$this->commentStreamsFactory = $commentStreamsFactory;
		$this->linkRenderer = $linkRenderer;
		$this->revisionStore = $revisionStore;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * Adds CommentStreams namespaces.
	 *
	 * @param string[] &$namespaces Array of namespace numbers with corresponding canonical names
	 */
	public function onCanonicalNamespaces( &$namespaces ) {
		$namespaces[NS_COMMENTSTREAMS] = 'CommentStreams';
		$namespaces[NS_COMMENTSTREAMS_TALK] = 'CommentStreams_Talk';
	}

	/**
	 * Prevents comment pages from being edited or deleted. Displays
	 * comment title and link to associated page when comment is viewed.
	 *
	 * @param OutputPage $output Context output
	 * @param Article $article Article on which the action will be performed
	 * @param Title $title Title on which the action will be performed
	 * @param User $user Context user
	 * @param WebRequest $request Context request
	 * @param MediaWiki $mediaWiki
	 * @return bool|void True or no return value to continue or false to abort
	 * @throws MWException
	 */
	public function onMediaWikiPerformAction(
		$output,
		$article,
		$title,
		$user,
		$request,
		$mediaWiki
	) {
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			return;
		}

		$action = $mediaWiki->getAction();
		if ( $action === 'info' || $action === 'history' ) {
			return;
		}
		if ( $action !== 'view' ) {
			$message = wfMessage( 'commentstreams-error-prohibitedaction', $action )->text();
			$output->addHTML( '<p class="error">' . htmlentities( $message ) . '</p>' );
			return false;
		}

		$wikiPage = new WikiPage( $title );
		$comment = $this->commentStreamsFactory->newCommentFromWikiPage( $wikiPage );
		if ( $comment ) {
			$output->setPageTitle( $comment->getCommentTitle() );
			$associatedTitle = Title::newFromId( $comment->getAssociatedId() );
			if ( $associatedTitle ) {
				$values = PageProps::getInstance()->getProperties( $associatedTitle, 'displaytitle' );
				if ( array_key_exists( $comment->getAssociatedId(), $values ) ) {
					$displaytitle = $values[$comment->getAssociatedId()];
				} else {
					$displaytitle = $associatedTitle->getPrefixedText();
				}
				$output->setSubtitle( $this->linkRenderer->makeLink( $associatedTitle, '< ' . $displaytitle ) );
			} else {
				$message = wfMessage( 'commentstreams-error-comment-on-deleted-page' )->text();
				$output->addHTML( '<p class="error">' . htmlentities( $message ) . '</p>' );
			}
		} else {
			$reply = $this->commentStreamsFactory->newReplyFromWikiPage( $wikiPage );
			if ( $reply ) {
				$parentCommentTitle = Title::newFromId( $reply->getParentCommentPageId() );
				if ( $parentCommentTitle ) {
					$values = PageProps::getInstance()->getProperties( $parentCommentTitle, 'displaytitle' );
					if ( array_key_exists( $reply->getParentCommentPageId(), $values ) ) {
						$displaytitle = $values[$reply->getParentCommentPageId()];
					} else {
						$displaytitle = $parentCommentTitle->getPrefixedText();
					}
					$output->setSubtitle( $this->linkRenderer->makeLink( $parentCommentTitle, '< ' . $displaytitle ) );
				} else {
					$message = wfMessage( 'commentstreams-error-reply-to-deleted-comment' )->text();
					$output->addHTML( '<p class="error">' . htmlentities( $message ) . '</p>' );
				}
			}
		}
	}

	/**
	 * Prevents comment pages from being moved.
	 *
	 * @param Title $oldTitle Current (old) location
	 * @param Title $newTitle New location
	 * @param Status $status Status object to pass error messages to
	 */
	public function onMovePageIsValidMove( $oldTitle, $newTitle, $status ) {
		if ( $oldTitle->getNamespace() === NS_COMMENTSTREAMS || $newTitle->getNamespace() === NS_COMMENTSTREAMS ) {
			$status->fatal( wfMessage( 'commentstreams-error-prohibitedaction', 'move' ) );
		}
	}

	/**
	 * Ensures that only the original author can edit a comment
	 *
	 * @param Title $title Title being checked against
	 * @param User $user Current user
	 * @param string $action Action being checked
	 * @param array &$result Pointer to result, if any
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			return;
		}

		$wikiPage = new WikiPage( $title );

		if ( !$wikiPage->exists() ) {
			return;
		}

		$allowed = true;
		if ( $action === 'cs-comment' ) {
			$revisionRecord = $this->revisionStore->getFirstRevision( $title );
			if ( $revisionRecord ) {
				$author = $revisionRecord->getUser( RevisionRecord::RAW );
				$allowed = ( $user->getId() === $author->getId() ) &&
					$this->permissionManager->userHasRight( $user, $action );
			} else {
				$allowed = false;
			}
		} elseif ( in_array( $action, [ 'cs-moderator-edit', 'cs-moderator-delete' ] ) ) {
			$allowed = $this->permissionManager->userHasRight( $user, $action );
		}

		if ( !$allowed ) {
			$result = 'badaccess-group0';
			return false;
		}
	}

	/**
	 * Gets comments for page and initializes variables to be passed to JavaScript.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$this->commentStreamsHandler->init( $out );
	}

	/**
	 * Modifies search results pointing to comment pages to point to the
	 * associated content page instead.
	 *
	 * @param Title &$title Title to link to
	 * @param string|HtmlArmor|null &$titleSnippet Label for the link representing
	 *   the search result. Typically the article title.
	 * @param SearchResult $result
	 * @param array $terms Array of search terms extracted by SearchDatabase search engines
	 *   (may not be populated by other search engines)
	 * @param SpecialSearch $specialSearch
	 * @param string[] &$query Array of query string parameters for the link representing the search
	 *   result
	 * @param string[] &$attributes Array of title link attributes, can be modified by extension
	 */
	public function onShowSearchHitTitle(
		&$title,
		&$titleSnippet,
		$result,
		$terms,
		$specialSearch,
		&$query,
		&$attributes
	) {
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			return;
		}

		$wikiPage = CommentStreamsUtils::newWikiPageFromId( $title->getArticleID() );
		if ( !$wikiPage ) {
			return;
		}

		$reply = $this->commentStreamsFactory->newReplyFromWikiPage( $wikiPage );
		if ( $reply ) {
			$wikiPage = CommentStreamsUtils::newWikiPageFromId( $reply->getParentCommentPageId() );
			if ( !$wikiPage ) {
				return;
			}
		}

		$comment = $this->commentStreamsFactory->newCommentFromWikiPage( $wikiPage );
		if ( $comment ) {
			$t = Title::newFromId( $comment->getAssociatedId() );
			if ( $t ) {
				$title = $t;
			}
		}
	}

	/**
	 * Adds comment-streams, no-comment-streams, and comment-streams-initially-collapsed magic words.
	 *
	 * @param Parser $parser Parser object being initialised
	 * @throws MWException
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'comment-streams', [ $this->commentStreamsHandler, 'enableCommentStreams' ] );
		$parser->setHook( 'no-comment-streams', [ $this->commentStreamsHandler, 'disableCommentStreams' ] );
		$parser->setHook( 'comment-streams-initially-collapsed',
			[ $this->commentStreamsHandler, 'initiallyCollapseCommentStreams' ] );
	}

	/**
	 * Implements extension registration callback.
	 * See https://www.mediawiki.org/wiki/Manual:Extension_registration#Customizing_registration
	 * Sets configuration constants.
	 */
	public static function onRegistration() {
		define( 'NS_COMMENTSTREAMS', $GLOBALS['wgCommentStreamsNamespaceIndex'] );
		define( 'NS_COMMENTSTREAMS_TALK', $GLOBALS['wgCommentStreamsNamespaceIndex'] + 1 );
		if ( $GLOBALS['wgCommentStreamsEnableSearch'] ) {
			$GLOBALS['wgNamespacesToBeSearchedDefault'][NS_COMMENTSTREAMS] = true;
		}
		$found = false;
		foreach ( $GLOBALS['wgGroupPermissions'] as $groupperms ) {
			if ( isset( $groupperms['cs-comment'] ) ) {
				$found = true;
				break;
			}
		}
		if ( !$found ) {
			foreach ( $GLOBALS['wgGroupPermissions'] as $group => $groupperms ) {
				if ( isset( $groupperms['edit'] ) ) {
					$GLOBALS['wgGroupPermissions'][$group]['cs-comment'] =
						$groupperms['edit'];
				}
			}
		}
		if ( !isset( $GLOBALS['wgGroupPermissions']['csmoderator']['cs-moderator-delete'] ) ) {
			$GLOBALS['wgGroupPermissions']['csmoderator']['cs-moderator-delete'] =
				true;
		}
		if ( !isset( $GLOBALS['wgGroupPermissions']['csmoderator']['cs-moderator-edit'] ) ) {
			$GLOBALS['wgGroupPermissions']['csmoderator']['cs-moderator-edit'] =
				false;
		}
		$GLOBALS['wgAvailableRights'][] = 'cs-comment';
		$GLOBALS['wgAvailableRights'][] = 'cs-moderator-edit';
		$GLOBALS['wgAvailableRights'][] = 'cs-moderator-delete';
		$GLOBALS['wgLogTypes'][] = 'commentstreams';
		$GLOBALS['wgLogActionsHandlers']['commentstreams/*'] = 'LogFormatter';
	}
}
