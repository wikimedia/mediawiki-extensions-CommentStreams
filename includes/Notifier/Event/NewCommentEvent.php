<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CommentStreams\Notifier\Event;

use MediaWiki\Extension\CommentStreams\AbstractComment;
use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\TitleEvent;

class NewCommentEvent extends TitleEvent {

	/**
	 * @var AbstractComment
	 */
	private $entity;

	/**
	 * @param AbstractComment $entity
	 * @param UserIdentity $agent
	 * @param PageIdentity $title
	 */
	public function __construct( AbstractComment $entity, UserIdentity $agent, PageIdentity $title ) {
		parent::__construct( $agent, $title );
		$this->entity = $entity;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'cs-new-comment';
	}

	/**
	 * @return string
	 */
	protected function getMessageKey(): string {
		return 'commentstreams-event-new-comment';
	}

	/**
	 * @return string
	 */
	public function getIcon(): string {
		return 'speechBubble';
	}

	/**
	 * @return AbstractComment
	 */
	public function getEntity(): AbstractComment {
		return $this->entity;
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'commentstreams-event-new-comment-desc' );
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		return [];
	}
}
