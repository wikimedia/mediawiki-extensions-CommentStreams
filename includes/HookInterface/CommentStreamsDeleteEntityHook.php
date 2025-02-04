<?php

namespace MediaWiki\Extension\CommentStreams\HookInterface;

use MediaWiki\Extension\CommentStreams\AbstractComment;
use MediaWiki\User\UserIdentity;

interface CommentStreamsDeleteEntityHook {

	/**
	 * @param AbstractComment $entity
	 * @param UserIdentity $actor
	 * @return void
	 */
	public function onCommentStreamsDeleteEntity( AbstractComment $entity, UserIdentity $actor );
}
