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

class CommentStreamsHooks {

	/**
	 * Implements LoadExtensionSchemaUpdates hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * Updates database schema.
	 *
	 * @param DatabaseUpdater $updater database updater
	 * @return bool continue checking hooks
	 */
	public static function addCommentTableToDatabase( DatabaseUpdater $updater ) {
		$dir = $GLOBALS['wgExtensionDirectory'] . DIRECTORY_SEPARATOR .
			'CommentStreams' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR;
		$updater->addExtensionTable( 'cs_comment_data', $dir . 'commentData.sql' );
		$updater->addExtensionTable( 'cs_votes', $dir . 'votes.sql' );
		$updater->addExtensionTable( 'cs_watchlist', $dir . 'watch.sql' );
		$updater->modifyExtensionField( 'cs_comment_data', 'page_id',
			$dir . 'updateFieldNames.sql' );
		$updater->dropExtensionIndex( 'cs_comment_data', 'assoc_page_id',
			$dir . 'dropForeignKey1.sql' );
		$updater->dropExtensionIndex( 'cs_comment_data', 'cst_assoc_page_id',
			$dir . 'dropForeignKey2.sql' );
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
	public static function addCommentStreamsNamespaces( array &$namespaces ) {
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
	 */
	public static function onMediaWikiPerformAction( OutputPage $output,
		Article $article, Title $title, User $user, WebRequest $request,
		MediaWiki $wiki ) {
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
			$output->addHTML( '<p class="error">' . $message . '</p>' );
		}
		$wikipage = new WikiPage( $title );
		$comment = Comment::newFromWikiPage( $wikipage );
		if ( !is_null( $comment ) ) {
			$commentTitle = $comment->getCommentTitle();
			if ( !is_null( $commentTitle ) ) {
				$output->setPageTitle( $commentTitle );
			}
			$associatedTitle = Title::newFromId( $comment->getAssociatedId() );
			if ( !is_null( $associatedTitle ) ) {
				$values = [];
				if ( class_exists( 'PageProps' ) ) {
					$values = PageProps::getInstance()->getProperties( $associatedTitle,
						'displaytitle' );
				}
				if ( array_key_exists( $comment->getAssociatedId(), $values ) ) {
					$displaytitle = $values[$comment->getAssociatedId()];
				} else {
					$displaytitle = $associatedTitle->getPrefixedText();
				}
				$output->setSubtitle(
					CommentStreamsUtils::link( $associatedTitle, '< ' . $displaytitle )
				);
			} else {
				$message =
					wfMessage( 'commentstreams-error-comment-on-deleted-page' )->text();
				$output->addHTML( '<p class="error">' . $message . '</p>' );
			}
			$output->addWikitext( $comment->getHTML() );
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
	public static function onMovePageIsValidMove( Title $oldTitle,
		Title $newTitle, Status $status ) {
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
	 * @param Title &$title the title object in question
	 * @param User &$user the user performing the action
	 * @param string $action the action being performed
	 * @param bool &$result true means the user is allowed, false means the
	 * user is not allowed, untouched means this hook has no opinion
	 * @return bool continue checking hooks
	 */
	public static function userCan( Title &$title, User &$user, $action,
		&$result ) {
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			return true;
		}

		$wikipage = new WikiPage( $title );

		if ( !$wikipage->exists() ) {
			return true;
		}

		if ( $user->isBlocked() ) {
			$result = false;
			return false;
		}

		if ( $action === 'cs-comment' ) {
			if ( $user->getId() === $wikipage->getOldestRevision()->getUser() ) {
				$result = true;
			} else {
				$result = false;
			}
			return false;
		}

		if ( $action === 'cs-moderator-edit' ) {
			if ( in_array( 'cs-moderator-edit', $user->getRights() ) ) {
				$result = true;
			} else {
				$result = false;
			}
			return false;
		}

		if ( $action === 'cs-moderator-delete' ) {
			if ( in_array( 'cs-moderator-delete', $user->getRights() ) ) {
				$result = true;
			} else {
				$result = false;
			}
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
	 */
	public static function onParserSetup( Parser $parser ) {
		$parser->setHook( 'comment-streams',
			'CommentStreamsHooks::enableCommentStreams' );
		$parser->setHook( 'no-comment-streams',
			'CommentStreamsHooks::disableCommentStreams' );
		$parser->setHook( 'comment-streams-initially-collapsed',
			'CommentStreamsHooks::initiallyCollapseCommentStreams' );
		return true;
	}

	/**
	 * Implements tag function, <comment-streams/>, which enables
	 * CommentStreams on a page.
	 *
	 * @param string $input input between the tags (ignored)
	 * @param array $args tag arguments
	 * @param Parser $parser the parser
	 * @param PPFrame $frame the parent frame
	 * @return string to replace tag with
	 */
	public static function enableCommentStreams( $input, array $args,
		Parser $parser, PPFrame $frame ) {
		$parser->disableCache();
		$cs = CommentStreams::singleton();
		$cs->enableCommentsOnPage();
		if ( isset( $args['location'] ) && $args['location'] === 'footer' ) {
			$ret = '';
		} else {
			$ret = '<div id="cs-comments"></div>';
		}
		return $ret;
	}

	/**
	 * Implements tag function, <no-comment-streams/>, which disables
	 * CommentStreams on a page.
	 *
	 * @param string $input input between the tags (ignored)
	 * @param array $args tag arguments
	 * @param Parser $parser the parser
	 * @param PPFrame $frame the parent frame
	 * @return string to replace tag with
	 */
	public static function disableCommentStreams( $input, array $args,
		Parser $parser, PPFrame $frame ) {
		$parser->disableCache();
		$cs = CommentStreams::singleton();
		$cs->disableCommentsOnPage();
		return "";
	}

	/**
	 * Implements tag function, <comment-streams-initially-collapsed/>, which
	 * makes CommentStreams on a page start as collapsed when the page is viewed.
	 *
	 * @param string $input input between the tags (ignored)
	 * @param array $args tag arguments
	 * @param Parser $parser the parser
	 * @param PPFrame $frame the parent frame
	 * @return string to replace tag with
	 */
	public static function initiallyCollapseCommentStreams( $input, array $args,
		Parser $parser, PPFrame $frame ) {
		$parser->disableCache();
		$cs = CommentStreams::singleton();
		$cs->initiallyCollapseCommentsOnPage();
		return "";
	}

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Gets comments for page and initializes variables to be passed to JavaScript.
	 *
	 * @param OutputPage &$output OutputPage object
	 * @param Skin &$skin Skin object that will be used to generate the page
	 * @return bool continue checking hooks
	 */
	public static function addCommentsAndInitializeJS( OutputPage &$output,
		Skin &$skin ) {
		$cs = CommentStreams::singleton();
		$cs->init( $output );
		return true;
	}

	/**
	 * Implements ShowSearchHitTitle hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ShowSearchHitTitle
	 * Modifies search results pointing to comment pages to point to the
	 * associated content page instead.
	 *
	 * @param Title &$title title to link to
	 * @param string &$text text to use for the link
	 * @param SearchResult $result the search result
	 * @param array $terms the search terms entered
	 * @param SpecialSearch $page the SpecialSearch object
	 * @return bool continue checking hooks
	 */
	public static function showSearchHitTitle( Title &$title, &$text,
		SearchResult $result, array $terms, SpecialSearch $page ) {
		$comment = Comment::newFromWikiPage( WikiPage::factory( $title ) );
		if ( !is_null( $comment ) ) {
			$t = Title::newFromId( $comment->getAssociatedId() );
			if ( !is_null( $t ) ) {
				$title = $t;
			}
		}
		return true;
	}

	/**
	 * Implements extension registration callback.
	 * See https://www.mediawiki.org/wiki/Manual:Extension_registration#Customizing_registration
	 * Defines CommentStreams namespace constants.
	 *
	 */
	public static function onRegistration() {
		define( 'NS_COMMENTSTREAMS', $GLOBALS['wgCommentStreamsNamespaceIndex'] );
		define( 'NS_COMMENTSTREAMS_TALK',
			$GLOBALS['wgCommentStreamsNamespaceIndex'] + 1 );
		$GLOBALS['wgNamespacesToBeSearchedDefault'][NS_COMMENTSTREAMS] = true;
		$GLOBALS['smwgNamespacesWithSemanticLinks'][NS_COMMENTSTREAMS] = true;
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
		if ( !isset( $GLOBALS['wgGroupPermissions']['csmoderator']
			['cs-moderator-edit'] ) ) {
			$GLOBALS['wgGroupPermissions']['csmoderator']['cs-moderator-edit'] =
				false;
		}
		$GLOBALS['wgAvailableRights'][] = 'cs-comment';
		$GLOBALS['wgAvailableRights'][] = 'cs-moderator-edit';
		$GLOBALS['wgAvailableRights'][] = 'cs-moderator-delete';
		$GLOBALS['wgLogTypes'][] = 'commentstreams';
		$GLOBALS['wgLogActionsHandlers']['commentstreams/*'] = 'LogFormatter';
	}

	/**
	 * Initialize extra Semantic MediaWiki properties.
	 * This won't get called unless Semantic MediaWiki is installed.
	 */
	public static function initProperties() {
		$pr = SMW\PropertyRegistry::getInstance();
		$pr->registerProperty( '___CS_ASSOCPG', '_wpg', 'Comment on' );
		$pr->registerProperty( '___CS_REPLYTO', '_wpg', 'Reply to' );
		$pr->registerProperty( '___CS_TITLE', '_txt', 'Comment title of' );
		$pr->registerProperty( '___CS_UPVOTES', '_num', 'Comment up votes' );
		$pr->registerProperty( '___CS_DOWNVOTES', '_num', 'Comment down votes' );
		$pr->registerProperty( '___CS_VOTEDIFF', '_num', 'Comment vote diff' );
	}

	/**
	 * Implements Semantic MediaWiki SMWStore::updateDataBefore callback.
	 * This won't get called unless Semantic MediaWiki is installed.
	 * If the comment has not been added to the database yet, which is indicated
	 * by a null associated page id, this function will return early, but it
	 * will be invoked again by an update job.
	 *
	 * @param SMW\Store $store semantic data store
	 * @param SMW\SemanticData $semanticData semantic data for page
	 * @return bool true to continue
	 */
	public static function updateData( $store, $semanticData ) {
		$subject = $semanticData->getSubject();
		if ( !is_null( $subject ) && !is_null( $subject->getTitle() ) &&
			$subject->getTitle()->getNamespace() === NS_COMMENTSTREAMS ) {
			$page_id = $subject->getTitle()->getArticleID( Title::GAID_FOR_UPDATE );
			$wikipage = WikiPage::newFromId( $page_id );
			$comment = Comment::newFromWikiPage( $wikipage );

			if ( is_null( $comment ) ) {
				return true;
			}

			$assoc_page_id = $comment->getAssociatedId();
			if ( !is_null( $assoc_page_id ) ) {
				$assoc_wikipage = WikiPage::newFromId( $assoc_page_id );
				if ( !is_null( $assoc_wikipage ) ) {
					$propertyDI = new SMW\DIProperty( '___CS_ASSOCPG' );
					$dataItem =
						SMW\DIWikiPage::newFromTitle( $assoc_wikipage->getTitle() );
					$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
				}
			}

			$parent_page_id = $comment->getParentId();
			if ( !is_null( $parent_page_id ) ) {
				$parent_wikipage = WikiPage::newFromId( $parent_page_id );
				if ( !is_null( $parent_wikipage ) ) {
					$propertyDI = new SMW\DIProperty( '___CS_REPLYTO' );
					$dataItem =
						SMW\DIWikiPage::newFromTitle( $parent_wikipage->getTitle() );
					$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
				}
			}

			$commentTitle = $comment->getCommentTitle();
			if ( !is_null( $commentTitle ) ) {
				$propertyDI = new SMW\DIProperty( '___CS_TITLE' );
				$dataItem = new SMWDIBlob( $comment->getCommentTitle() );
				$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
			}

			if ( $GLOBALS['wgCommentStreamsEnableVoting'] === true ) {
				$upvotes = $comment->getNumUpVotes();
				$propertyDI = new SMW\DIProperty( '___CS_UPVOTES' );
				$dataItem = new SMWDINumber( $upvotes );
				$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
				$downvotes = $comment->getNumDownVotes();
				$propertyDI = new SMW\DIProperty( '___CS_DOWNVOTES' );
				$dataItem = new SMWDINumber( $downvotes );
				$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
				$votediff = $upvotes - $downvotes;
				$propertyDI = new SMW\DIProperty( '___CS_VOTEDIFF' );
				$dataItem = new SMWDINumber( $votediff );
				$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
			}
		}
		return true;
	}

	/**
	 * @param array &$notifications notifications
	 * @param array &$notificationCategories notification categories
	 * @param array &$icons notification icons
	 */
	public static function onBeforeCreateEchoEvent( &$notifications,
		&$notificationCategories, &$icons ) {
		$notificationCategories['commentstreams-notification-category'] = [
			'priority' => 3
		];

		$notifications['commentstreams-comment-on-watched-page'] = [
			'category' => 'commentstreams-notification-category',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => EchoCSPresentationModel::class,
			'user-locators' => [ 'EchoUserLocator::locateUsersWatchingTitle' ]
		];

		$notifications['commentstreams-reply-on-watched-page'] = [
			'category' => 'commentstreams-notification-category',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => EchoCSPresentationModel::class,
			'user-locators' => [ 'EchoUserLocator::locateUsersWatchingTitle' ],
			'user-filters' => [ 'Comment::locateUsersWatchingComment' ]
		];

		$notifications['commentstreams-reply-to-watched-comment'] = [
			'category' => 'commentstreams-notification-category',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => EchoCSPresentationModel::class,
			'user-locators' => [ 'Comment::locateUsersWatchingComment' ]
		];
	}
}
