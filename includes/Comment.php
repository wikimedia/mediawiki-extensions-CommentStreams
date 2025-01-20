<?php

namespace MediaWiki\Extension\CommentStreams;

use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;

/**
 * Wrapper that provides a mapping between comment ID and its storage location
 */
class Comment extends AbstractComment {

	/** @var string */
	private string $title;

	/** @var string|null */
	private ?string $blockName;

	/**
	 * @param int $id
	 * @param string $title
	 * @param string|null $blockName
	 * @param PageIdentity|null $associatedPage
	 * @param UserIdentity $author
	 * @param UserIdentity $lastEditor
	 * @param MWTimestamp $created
	 * @param MWTimestamp $modified
	 */
	public function __construct(
		int $id, string $title, ?string $blockName, ?PageIdentity $associatedPage, UserIdentity $author,
		UserIdentity $lastEditor, MWTimestamp $created, MWTimestamp $modified
	) {
		parent::__construct( $id, $associatedPage, $author, $lastEditor, $created, $modified );
		$this->title = $title;
		$this->blockName = $blockName;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * @return string|null
	 */
	public function getBlockName(): ?string {
		return $this->blockName;
	}
}
