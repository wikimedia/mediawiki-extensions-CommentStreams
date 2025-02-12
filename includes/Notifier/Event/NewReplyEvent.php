<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CommentStreams\Notifier\Event;

use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;

class NewReplyEvent extends NewCommentEvent {
	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'cs-new-comment-reply';
	}

	/**
	 * @return string
	 */
	protected function getMessageKey(): string {
		return 'commentstreams-event-new-comment-reply';
	}

	/**
	 * @return string
	 */
	public function getIcon(): string {
		return 'speechBubble';
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'commentstreams-event-new-comment-reply-desc' );
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		return [];
	}
}
