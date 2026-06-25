<?php

namespace MediaWiki\Extension\CommentStreams\Notifier;

use MediaWiki\Extension\CommentStreams\ICommentStreamsStore;
use MediaWiki\Extension\CommentStreams\Notifier\Event\NewCommentEvent;
use MediaWiki\Extension\NotifyMe\Hook\NotifyMeWatchlistProviderGetWatchersHook;
use MediaWiki\Extension\NotifyMe\Hook\NotifyMeWatchlistProviderGetWatchSourceHook;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\INotificationEvent;
use MWStake\MediaWiki\Component\Events\Notification;

class NotifyMeWatchlistProviderHooks implements
	NotifyMeWatchlistProviderGetWatchersHook,
	NotifyMeWatchlistProviderGetWatchSourceHook
{

	/**
	 * @param ICommentStreamsStore $store
	 */
	public function __construct( private readonly ICommentStreamsStore $store ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onNotifyMeWatchlistProviderGetWatchSource( Notification $notification, Message &$description ) {
		if ( $notification->getEvent() instanceof NewCommentEvent ) {
			$watchers = $this->store->getWatchers( $notification->getEvent()->getEntity() );
			foreach ( $watchers as $watcher ) {
				if ( $watcher->getId() === $notification->getTargetUser()->getId() ) {
					$description = Message::newFromKey( 'commentstreams-notifyme-subscription-description' );
					return;
				}
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onNotifyMeWatchlistProviderGetWatchers(
		INotificationEvent $event, IChannel $channel, array &$watchers
	): void {
		if ( $event instanceof NewCommentEvent ) {
			// All others are extending this class
			$watchers = array_merge( $watchers, $this->store->getWatchers( $event->getEntity() ) );
		}
	}
}
