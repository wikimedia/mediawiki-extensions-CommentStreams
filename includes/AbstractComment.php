<?php

namespace MediaWiki\Extension\CommentStreams;

use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\Timestamp\TimestampException;

/**
 * Wrapper that provides a mapping between comment ID and its storage location
 */
abstract class AbstractComment {

	/** @var int */
	private int $id;

	/** @var PageIdentity|null */
	private ?PageIdentity $associatedPage;

	/** @var UserIdentity */
	private UserIdentity $author;

	/** @var UserIdentity */
	private UserIdentity $lastEditor;

	/** @var MWTimestamp */
	private MWTimestamp $created;

	/** @var MWTimestamp|null */
	private ?MWTimestamp $modified;

	/**
	 * @param int $id
	 * @param PageIdentity|null $associatedPage
	 * @param UserIdentity $author
	 * @param UserIdentity $lastEditor
	 * @param MWTimestamp $created
	 * @param MWTimestamp $modified
	 * @throws TimestampException
	 */
	public function __construct(
		int $id, ?PageIdentity $associatedPage, UserIdentity $author, UserIdentity $lastEditor,
		MWTimestamp $created, MWTimestamp $modified
	) {
		$this->id = $id;
		$this->associatedPage = $associatedPage;
		$this->author = $author;
		$this->lastEditor = $lastEditor;
		$this->created = $created;
		$this->modified = $modified->getTimestamp() !== $created->getTimestamp() ? $modified : null;
	}

	public function getId(): int {
		return $this->id;
	}

	/**
	 * @return PageIdentity|null
	 */
	public function getAssociatedPage(): ?PageIdentity {
		return $this->associatedPage;
	}

	/**
	 * @return UserIdentity
	 */
	public function getAuthor(): UserIdentity {
		return $this->author;
	}

	/**
	 * @return UserIdentity
	 */
	public function getLastEditor(): UserIdentity {
		return $this->lastEditor;
	}

	/**
	 * @return MWTimestamp
	 */
	public function getCreated(): MWTimestamp {
		return $this->created;
	}

	/**
	 * @return MWTimestamp|null
	 */
	public function getModified(): ?MWTimestamp {
		return $this->modified;
	}
}
