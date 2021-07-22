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

use EchoEvent;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use MWException;
use PageProps;
use User;
use WikiPage;

class CommentStreamsEchoInterface {
	/**
	 * @var bool
	 */
	private $isLoaded;

	/**
	 * @param ExtensionRegistry $extensionRegistry
	 */
	public function __construct(
		ExtensionRegistry $extensionRegistry
	) {
		$this->isLoaded = $extensionRegistry->isLoaded( 'Echo' );
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
	 * @param WikiPage $associated_page the associated page for the comment
	 * @param User $user
	 * @param string $comment_title
	 * @throws MWException
	 */
	public function sendNotifications(
		Comment $comment,
		WikiPage $associated_page,
		User $user,
		string $comment_title
	) {
		if ( !$this->isLoaded ) {
			return;
		}

		$associated_page_display_title =
			$associated_page->getTitle()->getPrefixedText();
		$associated_title = $associated_page->getTitle();
		$values = PageProps::getInstance()->getProperties( $associated_title,
			'displaytitle' );
		if ( array_key_exists( $associated_title->getArticleID(), $values ) ) {
			$associated_page_display_title =
				$values[$associated_title->getArticleID()];
		}

		$extra = [
			'comment_id' => $comment->getId(),
			'parent_id' => $comment->getParentId(),
			'comment_author_username' => $comment->getUsername(),
			'comment_author_display_name' => $comment->getUserDisplayNameUnlinked(),
			'comment_title' => $comment_title,
			'associated_page_display_title' => $associated_page_display_title,
			'comment_wikitext' => $comment->getWikitext()
		];

		if ( $comment->getParentId() !== null ) {
			EchoEvent::create( [
				'type' => 'commentstreams-reply-on-watched-page',
				'title' => $associated_page->getTitle(),
				'extra' => $extra,
				'agent' => $user
			] );
			EchoEvent::create( [
				'type' => 'commentstreams-reply-to-watched-comment',
				'title' => $associated_page->getTitle(),
				'extra' => $extra,
				'agent' => $user
			] );
		} else {
			EchoEvent::create( [
				'type' => 'commentstreams-comment-on-watched-page',
				'title' => $associated_page->getTitle(),
				'extra' => $extra,
				'agent' => $user
			] );
		}
	}

	/**
	 * Used by Echo to locate the users watching a comment being replied to.
	 * @param EchoEvent $event the Echo event
	 * @return array array mapping user id to User object
	 */
	public static function locateUsersWatchingComment( EchoEvent $event ): array {
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
				[ '\MediaWiki\Extension\CommentStreams\CommentStreamsEchoInterface::locateUsersWatchingComment' ]
		];

		$notifications['commentstreams-reply-to-watched-comment'] = [
			'category' => 'commentstreams-notification-category',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => EchoCSPresentationModel::class,
			'user-locators' =>
				[ '\MediaWiki\Extension\CommentStreams\CommentStreamsEchoInterface::locateUsersWatchingComment' ]
		];
	}
}
