<?php

namespace MediaWiki\Extension\CommentStreams\Notifier;

use Exception;
use MediaWiki\Extension\CommentStreams\Comment;
use MediaWiki\Extension\CommentStreams\NotifierInterface;
use MediaWiki\Extension\CommentStreams\Reply;
use MediaWiki\User\User;
use WikiPage;

class NullNotifier implements NotifierInterface {

	/**
	 * @return bool
	 */
	public function isLoaded(): bool {
		return true;
	}

	/**
	 * Send Echo notifications if Echo is installed.
	 *
	 * @param Comment $comment the comment to send notifications for
	 * @param WikiPage $associatedPage the associated page for the comment
	 * @param User $user
	 * @param string $commentTitle
	 * @throws Exception
	 */
	public function sendCommentNotifications(
		Comment $comment, WikiPage $associatedPage, User $user, string $commentTitle
	) {
		// NOOP
	}

	/**
	 * Send Echo notifications if Echo is installed.
	 *
	 * @param Reply $reply the comment to send notifications for
	 * @param WikiPage $associatedPage the associated page for the comment
	 * @param User $user
	 * @param Comment $parentComment
	 * @throws Exception
	 */
	public function sendReplyNotifications(
		Reply $reply,
		WikiPage $associatedPage,
		User $user,
		Comment $parentComment
	) {
		// NOOP
	}

}
