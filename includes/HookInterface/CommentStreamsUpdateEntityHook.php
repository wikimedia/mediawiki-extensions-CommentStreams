<?php

namespace MediaWiki\Extension\CommentStreams\HookInterface;

use MediaWiki\Extension\CommentStreams\AbstractComment;
use MediaWiki\User\UserIdentity;

interface CommentStreamsUpdateEntityHook {

	/**
	 * @param AbstractComment $entity
	 * @param UserIdentity $actor
	 * @param string $oldText
	 * @param string $newText
	 * @return void
	 */
	public function onCommentStreamsUpdateEntity(
		AbstractComment $entity, UserIdentity $actor, string $oldText, string $newText
	);
}
