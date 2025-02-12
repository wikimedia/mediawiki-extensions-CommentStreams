<?php

namespace MediaWiki\Extension\CommentStreams\Notifier;

use Exception;
use MediaWiki\Extension\CommentStreams\Comment;
use MediaWiki\Extension\CommentStreams\Notifier\Event\NewCommentEvent;
use MediaWiki\Extension\CommentStreams\Notifier\Event\NewReplyEvent;
use MediaWiki\Extension\CommentStreams\NotifierInterface;
use MediaWiki\Extension\CommentStreams\Reply;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;
use MWException;
use MWStake\MediaWiki\Component\Events\Notifier;
use WikiPage;

class NotifyMeNotifier implements NotifierInterface {

	/**
	 * @var Notifier
	 */
	private $notifier;

	/**
	 * @param Notifier $notifier
	 */
	public function __construct( Notifier $notifier ) {
		$this->notifier = $notifier;
	}

	/**
	 * @return bool
	 */
	public function isLoaded(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'NotifyMe' );
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
		Comment $comment, WikiPage $associatedPage, User $user, string $commentTitle
	) {
		$this->notifier->emit(
			new NewCommentEvent( $comment, $user, $associatedPage->getTitle() )
		);
	}

	/**
	 * Send Echo notifications if Echo is installed.
	 *
	 * @param Reply $reply the comment to send notifications for
	 * @param WikiPage $associatedPage the associated page for the comment
	 * @param User $user
	 * @param Comment $parentComment
	 * @throws MWException
	 * @throws Exception
	 */
	public function sendReplyNotifications(
		Reply $reply,
		WikiPage $associatedPage,
		User $user,
		Comment $parentComment
	) {
		$this->notifier->emit(
			new NewReplyEvent( $parentComment, $user, $associatedPage->getTitle() )
		);
	}

}
