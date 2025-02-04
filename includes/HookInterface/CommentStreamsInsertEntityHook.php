<?php

namespace MediaWiki\Extension\CommentStreams\HookInterface;

use MediaWiki\Extension\CommentStreams\AbstractComment;
use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserIdentity;

interface CommentStreamsInsertEntityHook {

	/**
	 * @param AbstractComment $entity
	 * @param UserIdentity $actor
	 * @param PageIdentity $associatedPage
	 * @param string $type
	 * @param string $wikitext
	 * @return void
	 */
	public function onCommentStreamsInsertEntity(
		AbstractComment $entity, UserIdentity $actor, PageIdentity $associatedPage, string $type, string $wikitext
	);
}
