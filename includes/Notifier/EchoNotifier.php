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

namespace MediaWiki\Extension\CommentStreams\Notifier;

use EchoEvent;
use Exception;
use MediaWiki\Extension\CommentStreams\Comment;
use MediaWiki\Extension\CommentStreams\CommentSerializer;
use MediaWiki\Extension\CommentStreams\ICommentStreamsStore;
use MediaWiki\Extension\CommentStreams\NotifierInterface;
use MediaWiki\Extension\CommentStreams\Reply;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageProps;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;
use MWException;
use WikiPage;

class EchoNotifier implements NotifierInterface {

	/**
	 * @var PageProps
	 */
	private $pageProps;

	/**
	 * @var CommentSerializer
	 */
	private $serializer;

	/**
	 * @param PageProps $pageProps
	 * @param CommentSerializer $serializer
	 */
	public function __construct(
		PageProps $pageProps,
		CommentSerializer $serializer
	) {
		$this->pageProps = $pageProps;
		$this->serializer = $serializer;
	}

	/**
	 * @return bool
	 */
	public function isLoaded(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'Echo' );
	}

	/**
	 * Send Echo notifications if Echo is installed.
	 *
	 * @param Comment $comment the comment to send notifications for
	 * @param WikiPage $associatedPage the associated page for the comment
	 * @param User $user
	 * @param string $commentTitle
	 * @throws MWException
	 * @throws Exception
	 */
	public function sendCommentNotifications(
		Comment $comment,
		WikiPage $associatedPage,
		User $user,
		string $commentTitle
	) {
		if ( !$this->isLoaded() ) {
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
			'comment_author_username' => $comment->getAuthor()->getName(),
			'comment_author_display_name' => $this->serializer->getDisplayNameFromUser( $comment->getAuthor(), false ),
			'comment_title' => $commentTitle,
			'associated_page_display_title' => $associatedPageDisplayTitle,
			'comment_wikitext' => $this->serializer->getWikitext( $comment ),
		];

		EchoEvent::create( [
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
		if ( !$this->isLoaded() ) {
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
			'comment_author_username' => $reply->getAuthor()->getName(),
			'comment_author_display_name' => $this->serializer->getDisplayNameFromUser( $reply->getAuthor(), false ),
			'comment_title' => $parentComment->getTitle(),
			'associated_page_display_title' => $associatedPageDisplayTitle,
			'comment_wikitext' => $this->serializer->getWikitext( $reply ),
		];

		EchoEvent::create( [
			'type' => 'commentstreams-reply-on-watched-page',
			'title' => $associatedPage->getTitle(),
			'extra' => $extra,
			'agent' => $user
		] );
		EchoEvent::create( [
			'type' => 'commentstreams-reply-to-watched-comment',
			'title' => $associatedPage->getTitle(),
			'extra' => $extra,
			'agent' => $user
		] );
	}

	/**
	 * Used by Echo to locate the users watching a comment being replied to.
	 * @param EchoEvent $event the Echo event
	 * @return array array mapping user id to User object
	 * @throws MWException
	 */
	public static function locateUsersWatchingComment( EchoEvent $event ): array {
		$id = $event->getExtraParam( 'comment_id' );
		if ( $id === null ) {
			throw new \RuntimeException( wfMessage( 'commentstreams-no-comment_id' )->plain() );
		}

		/** @var ICommentStreamsStore $store */
		$store = MediaWikiServices::getInstance()->getService( 'CommentStreamsStore' );
		$comment = $store->getComment( $id );
		if ( !$comment ) {
			return [];
		}
		return $store->getWatchers( $comment );
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
				[ self::class . '::locateUsersWatchingComment' ]
		];

		$notifications['commentstreams-reply-to-watched-comment'] = [
			'category' => 'commentstreams-notification-category',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => EchoCSPresentationModel::class,
			'user-locators' =>
				[ self::class . '::locateUsersWatchingComment' ]
		];
	}
}
