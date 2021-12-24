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

use Article;
use DatabaseUpdater;
use HtmlArmor;
use MediaWiki;
use MediaWiki\MediaWikiServices;
use MWException;
use OutputPage;
use PageProps;
use Parser;
use PPFrame;
use SearchResult;
use Skin;
use SpecialSearch;
use Status;
use Title;
use User;
use WebRequest;
use WikiPage;

class CommentStreamsHooks {
	/**
	 * Implements LoadExtensionSchemaUpdates hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * Updates database schema.
	 *
	 * @param DatabaseUpdater $updater database updater
	 * @return bool continue checking hooks
	 */
	public static function addCommentTableToDatabase( DatabaseUpdater $updater ) : bool {
		$dir = __DIR__ . '/../sql/';
		$updater->addExtensionTable( 'cs_comment_data', $dir . 'commentData.sql' );
		$updater->addExtensionTable( 'cs_votes', $dir . 'votes.sql' );
		$updater->addExtensionTable( 'cs_watchlist', $dir . 'watch.sql' );
		$updater->modifyExtensionField( 'cs_comment_data', 'page_id',
			$dir . 'updateFieldNames.sql' );
		$updater->dropExtensionIndex( 'cs_comment_data', 'assoc_page_id',
			$dir . 'dropForeignKey1.sql' );
		$updater->dropExtensionIndex( 'cs_comment_data', 'cst_assoc_page_id',
			$dir . 'dropForeignKey2.sql' );
		$updater->addExtensionField( 'cs_comment_data', 'cst_id',
			$dir . 'addCommentId.sql' );
		return true;
	}

	/**
	 * Implements CanonicalNamespaces hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/CanonicalNamespaces
	 * Adds CommentStreams namespaces.
	 *
	 * @param array &$namespaces modifiable array of namespace numbers with
	 * corresponding canonical names
	 * @return bool continue checking hooks
	 */
	public static function addCommentStreamsNamespaces( array &$namespaces ) : bool {
		$namespaces[NS_COMMENTSTREAMS] = 'CommentStreams';
		$namespaces[NS_COMMENTSTREAMS_TALK] = 'CommentStreams_Talk';
		return true;
	}

	/**
	 * Implement MediaWikiPerformAction hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/MediaWikiPerformAction
	 * Prevents comment pages from being edited or deleted. Displays
	 * comment title and link to associated page when comment is viewed.
	 *
	 * @param OutputPage $output OutputPage object
	 * @param Article $article Article object
	 * @param Title $title Title object
	 * @param User $user User object
	 * @param WebRequest $request WebRequest object
	 * @param MediaWiki $wiki MediaWiki object
	 * @return bool continue checking hooks
	 * @noinspection PhpUnusedParameterInspection
	 * @throws MWException
	 */
	public static function onMediaWikiPerformAction(
		OutputPage $output,
		Article $article,
		Title $title,
		User $user,
		WebRequest $request,
		MediaWiki $wiki
	) : bool {
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			return true;
		}
		$action = $wiki->getAction();
		if ( $action === 'info' || $action === 'history' ) {
			return true;
		}
		if ( $action !== 'view' ) {
			$message =
				wfMessage( 'commentstreams-error-prohibitedaction', $action )->text();
			$output->addHTML( '<p class="error">' . htmlentities( $message ) . '</p>' );
		}

		$commentStreamsFactory =
			MediaWikiServices::getInstance()->getService( 'CommentStreamsFactory' );

		$wikipage = new WikiPage( $title );
		$comment = $commentStreamsFactory->newFromWikiPage( $wikipage );
		if ( $comment !== null ) {
			$commentTitle = $comment->getCommentTitle();
			if ( $commentTitle !== null ) {
				$output->setPageTitle( $commentTitle );
			}
			$associatedTitle = Title::newFromId( $comment->getAssociatedId() );
			if ( $associatedTitle !== null ) {
				$values = PageProps::getInstance()->getProperties( $associatedTitle,
					'displaytitle' );
				if ( array_key_exists( $comment->getAssociatedId(), $values ) ) {
					$displaytitle = $values[$comment->getAssociatedId()];
				} else {
					$displaytitle = $associatedTitle->getPrefixedText();
				}
				$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
				$output->setSubtitle(
					$linkRenderer->makeLink( $associatedTitle, '< ' . $displaytitle ) );
			} else {
				$message =
					wfMessage( 'commentstreams-error-comment-on-deleted-page' )->text();
				$output->addHTML( '<p class="error">' . htmlentities( $message ) . '</p>' );
			}
			CommentStreamsUtils::addWikiTextToOutputPage(
				$comment->getHTML( $output->getContext() ),
				$output
			);
		}
		return false;
	}

	/**
	 * Implement MovePageIsValidMove hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/MovePageIsValidMove
	 * Prevents comment pages from being moved.
	 *
	 * @param Title $oldTitle Title object of the current (old) location
	 * @param Title $newTitle Title object of the new location
	 * @param Status $status Status object to pass error messages to
	 * @return bool continue checking hooks
	 */
	public static function onMovePageIsValidMove(
		Title $oldTitle,
		Title $newTitle,
		Status $status
	) : bool {
		if ( $oldTitle->getNamespace() === NS_COMMENTSTREAMS ||
			$newTitle->getNamespace() === NS_COMMENTSTREAMS ) {
			$status->fatal( wfMessage( 'commentstreams-error-prohibitedaction',
				'move' ) );
			return false;
		}
		return true;
	}

	/**
	 * Implements userCan hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/userCan
	 * Ensures that only the original author can edit a comment
	 *
	 * @param Title $title the title object in question
	 * @param User $user the user performing the action
	 * @param string $action the action being performed
	 * @param bool &$result true means the user is allowed, false means the
	 * user is not allowed, untouched means this hook has no opinion
	 * @return bool continue checking hooks
	 */
	public static function userCan(
		Title $title,
		User $user,
		string $action,
		bool &$result
	) : bool {
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			return true;
		}

		$wikipage = new WikiPage( $title );

		if ( !$wikipage->exists() ) {
			return true;
		}

		if ( $action === 'cs-comment' ) {
			if ( CommentStreamsUtils::userHasRight( $user, $action ) &&
				$user->getId() === CommentStreamsUtils::getAuthor( $title )->getId() ) {
				$result = true;
			} else {
				$result = false;
			}
			return false;
		}

		if ( in_array( $action, [ 'cs-moderator-edit', 'cs-moderator-delete' ] ) ) {
			$result = CommentStreamsUtils::userHasRight( $user, $action );
			return false;
		}

		return true;
	}

	/**
	 * Implements ParserFirstCallInit hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * Adds comment-streams, no-comment-streams, and
	 * comment-streams-initially-collapsed magic words.
	 *
	 * @param Parser $parser the parser
	 * @return bool continue checking hooks
	 * @throws MWException
	 */
	public static function onParserSetup( Parser $parser ) : bool {
		$parser->setHook( 'comment-streams',
			'MediaWiki\Extension\CommentStreams\CommentStreamsHooks::enableCommentStreams' );
		$parser->setHook( 'no-comment-streams',
			'MediaWiki\Extension\CommentStreams\CommentStreamsHooks::disableCommentStreams' );
		$parser->setHook( 'comment-streams-initially-collapsed',
			'MediaWiki\Extension\CommentStreams\CommentStreamsHooks::initiallyCollapseCommentStreams' );
		return true;
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
	public static function enableCommentStreams(
		?string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	) : string {
		$parser->getOutput()->updateCacheExpiry( 0 );
		$cs = MediaWikiServices::getInstance()->getService( 'CommentStreamsHandler' );
		$cs->enableCommentsOnPage();
		if ( isset( $args['id'] ) ) {
			$ret = '<div class="cs-comments" id="csc_' . md5( $args['id'] ) . '"></div>';
		} elseif ( isset( $args['location'] ) && $args['location'] === 'footer' ) {
			$ret = '';
		} else {
			$ret = '<div class="cs-comments" id="cs-comments"></div>';
		}
		// @phan-suppress-next-line SecurityCheck-XSS
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
	public static function disableCommentStreams(
		?string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	) : string {
		$parser->getOutput()->updateCacheExpiry( 0 );
		$cs = MediaWikiServices::getInstance()->getService( 'CommentStreamsHandler' );
		$cs->disableCommentsOnPage();
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
	public static function initiallyCollapseCommentStreams(
		?string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	) : string {
		$parser->getOutput()->updateCacheExpiry( 0 );
		$cs = MediaWikiServices::getInstance()->getService( 'CommentStreamsHandler' );
		$cs->initiallyCollapseCommentsOnPage();
		return "";
	}

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Gets comments for page and initializes variables to be passed to JavaScript.
	 *
	 * @param OutputPage $output OutputPage object
	 * @param Skin $skin Skin object that will be used to generate the page
	 * @return bool continue checking hooks
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function addCommentsAndInitializeJS(
		OutputPage $output,
		Skin $skin
	) : bool {
		$cs = MediaWikiServices::getInstance()->getService( 'CommentStreamsHandler' );
		$cs->init( $output );
		return true;
	}

	/**
	 * Implements ShowSearchHitTitle hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ShowSearchHitTitle
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
	 * @return bool|void True or no return value to continue or false to abort
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function showSearchHitTitle( &$title, &$titleSnippet, $result, $terms, $specialSearch, &$query,
		&$attributes ) {
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			return true;
		}
		$commentStreamsFactory =
			MediaWikiServices::getInstance()->getService( 'CommentStreamsFactory' );
		$comment = $commentStreamsFactory->newFromWikiPage(
			CommentStreamsUtils::newWikiPageFromId( $title->getArticleID() ) );
		if ( $comment !== null ) {
			$t = Title::newFromId( $comment->getAssociatedId() );
			if ( $t !== null ) {
				$title = $t;
			}
		}
		return true;
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
		if ( !isset( $GLOBALS['wgGroupPermissions']['csmoderator']
			['cs-moderator-delete'] ) ) {
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
