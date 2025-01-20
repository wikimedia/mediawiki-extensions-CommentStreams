<?php

namespace MediaWiki\Extension\CommentStreams;

use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;

/**
 * Wrapper that provides a mapping between comment ID and its storage location
 */
class Reply extends AbstractComment {

	/** @var Comment */
	private Comment $parent;

	/**
	 * @param Comment $parent
	 * @param int $id
	 * @param UserIdentity $author
	 * @param UserIdentity $lastEditor
	 * @param MWTimestamp $created
	 * @param MWTimestamp $modified
	 */
	public function __construct(
		Comment $parent, int $id, UserIdentity $author, UserIdentity $lastEditor,
		MWTimestamp $created, MWTimestamp $modified
	) {
		parent::__construct( $id, $parent->getAssociatedPage(), $author, $lastEditor, $created, $modified );
		$this->parent = $parent;
	}

	/**
	 * @return Comment
	 */
	public function getParent(): Comment {
		return $this->parent;
	}
}
