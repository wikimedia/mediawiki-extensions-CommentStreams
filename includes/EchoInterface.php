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

use ExtensionRegistry;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageProps;
use MediaWiki\User\User;
use MWException;
use WikiPage;

class EchoInterface {
	/**
	 * @var bool
	 */
	private $isLoaded;

	/**
	 * @var PageProps
	 */
	private $pageProps;

	/**
	 * @param ExtensionRegistry $extensionRegistry
	 * @param PageProps $pageProps
	 */
	public function __construct(
		ExtensionRegistry $extensionRegistry,
		PageProps $pageProps
	) {
		$this->isLoaded = $extensionRegistry->isLoaded( 'Echo' );
		$this->pageProps = $pageProps;
	}

	/**
	 * @return bool
	 */
	public function isLoaded(): bool {
		return $this->isLoaded;
	}

	/**
	 * Send Echo notifications if Echo is installed.
	 *
	 * @param Comment $comment the comment to send notifications for
	 * @param WikiPage $associatedPage the associated page for the comment
	 * @param User $user
	 * @param string $commentTitle
	 * @throws MWException
	 */
	public function sendCommentNotifications(
		Comment $comment,
		WikiPage $associatedPage,
		User $user,
		string $commentTitle
	) {
		if ( !$this->isLoaded ) {
			return;
		}

		$associatedPageDisplayTitle = $associatedPage->getTitle()->getPrefixedText();
		$associatedTitle = $associatedPage->getTitle();
		$values = $this->pageProps->getProperties( $associatedTitle, 'displaytitle' );
		if ( array_key_exists( $associatedTitle->getArticleID(), $values ) ) {
			$associatedPageDisplayTitle = $values[$associatedTitle->getArticleID()];
		}

		$extra = [
			'comment_id' => $comment->getId(),
			'comment_author_username' => $comment->getUsername(),
			'comment_author_display_name' => $comment->getUserDisplayNameUnlinked(),
			'comment_title' => $commentTitle,
			'associated_page_display_title' => $associatedPageDisplayTitle,
			'comment_wikitext' => $comment->getWikitext()
		];

		Event::create( [
			'type' => 'commentstreams-comment-on-watched-page',
			'title' => $associatedPage->getTitle(),
			'extra' => $extra,
			'agent' => $user
		] );
	}

	/**
	 * Send Echo notifications if Echo is installed.
	 *
	 * @param Reply $reply the comment to send notifications for
	 * @param WikiPage $associatedPage the associated page for the comment
	 * @param User $user
	 * @param Comment $parentComment
	 * @throws MWException
	 */
	public function sendReplyNotifications(
		Reply $reply,
		WikiPage $associatedPage,
		User $user,
		Comment $parentComment
	) {
		if ( !$this->isLoaded ) {
			return;
		}

		$associatedPageDisplayTitle = $associatedPage->getTitle()->getPrefixedText();
		$associatedTitle = $associatedPage->getTitle();
		$values = $this->pageProps->getProperties( $associatedTitle, 'displaytitle' );
		if ( array_key_exists( $associatedTitle->getArticleID(), $values ) ) {
			$associatedPageDisplayTitle = $values[$associatedTitle->getArticleID()];
		}

		$extra = [
			'comment_id' => $reply->getId(),
			'comment_author_username' => $reply->getUsername(),
			'comment_author_display_name' => $reply->getUserDisplayNameUnlinked(),
			'comment_title' => $parentComment->getCommentTitle(),
			'associated_page_display_title' => $associatedPageDisplayTitle,
			'comment_wikitext' => $reply->getWikitext()
		];

		Event::create( [
			'type' => 'commentstreams-reply-on-watched-page',
			'title' => $associatedPage->getTitle(),
			'extra' => $extra,
			'agent' => $user
		] );
		Event::create( [
			'type' => 'commentstreams-reply-to-watched-comment',
			'title' => $associatedPage->getTitle(),
			'extra' => $extra,
			'agent' => $user
		] );
	}

	/**
	 * Used by Echo to locate the users watching a comment being replied to.
	 * @param Event $event the Echo event
	 * @return array array mapping user id to User object
	 */
	public static function locateUsersWatchingComment( Event $event ): array {
		$id = $event->getExtraParam( 'parent_id' );
		if ( $id === null ) {
			$id = $event->getExtraParam( 'comment_id' );
		}
		return MediaWikiServices::getInstance()->getService( 'CommentStreamsStore' )->
			getWatchers( $id );
	}

	/**
	 * @param array &$notifications notifications
	 * @param array &$notificationCategories notification categories
	 * @param array &$icons notification icons
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$icons
	) {
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
			'user-filters' =>
				[ '\MediaWiki\Extension\CommentStreams\EchoInterface::locateUsersWatchingComment' ]
		];

		$notifications['commentstreams-reply-to-watched-comment'] = [
			'category' => 'commentstreams-notification-category',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => EchoCSPresentationModel::class,
			'user-locators' =>
				[ '\MediaWiki\Extension\CommentStreams\EchoInterface::locateUsersWatchingComment' ]
		];
	}
}
