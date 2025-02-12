<?php

namespace MediaWiki\Extension\CommentStreams;

use MediaWiki\Extension\CommentStreams\Notifier\Event\NewCommentEvent;
use MediaWiki\Extension\NotifyMe\ISubscriberProvider;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\INotificationEvent;
use MWStake\MediaWiki\Component\Events\Notification;

class NotifyMeSubscriptionProvider implements ISubscriberProvider {

	/**
	 * @param ICommentStreamsStore $store
	 */
	public function __construct( private readonly ICommentStreamsStore $store ) {
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'comment-streams-watchers';
	}

	/**
	 * @param INotificationEvent $event
	 * @param IChannel $channel
	 * @return array|\MediaWiki\User\UserIdentity[]
	 */
	public function getSubscribers( INotificationEvent $event, IChannel $channel ): array {
		if ( $event instanceof NewCommentEvent ) {
			// All others are extending this class
			return $this->store->getWatchers( $event->getEntity() );
		}
		return [];
	}

	public function getDescription( Notification $notification ): Message {
		return Message::newFromKey( 'commentstreams-notifyme-subscription-description' );
	}

	/**
	 * @return string|null
	 */
	public function getConfigurationLink(): ?string {
		return null;
	}
}
