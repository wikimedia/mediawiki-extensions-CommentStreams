<?php
/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

namespace MediaWiki\Extension\CommentStreams;

use FatalError;
use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MWException;
use ParserFactory;
use RepoGroup;
use User;
use Wikimedia\Assert\Assert;
use WikiPage;

class Comment extends AbstractComment {
	/**
	 * @var CommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var EchoInterface
	 */
	private $echoInterface;

	/**
	 * @var mixed
	 */
	private $enableVoting;

	/**
	 * page ID for the wiki page this comment is on
	 * @var int
	 */
	private $assocPageId;

	/**
	 * title of comment
	 * @var ?string
	 */
	private $commentTitle;

	/**
	 * unique id to identify comment block on a page; null if comment is in default comment block
	 * or is a reply
	 * @var ?string
	 */
	private $commentBlockName;

	/**
	 * Do not instantiate directly. Use CommentStreamsFactory instead.
	 * @param CommentStreamsStore $commentStreamsStore
	 * @param EchoInterface $echoInterface
	 * @param SMWInterface $smwInterface
	 * @param SocialProfileInterface $socialProfileInterface
	 * @param LinkRenderer $linkRenderer
	 * @param RepoGroup $repoGroup
	 * @param RevisionStore $revisionStore
	 * @param ParserFactory $parserFactory
	 * @param UserFactory $userFactory
	 * @param ?string $userAvatarPropertyName
	 * @param ?string $userRealNamePropertyName
	 * @param bool $enableVoting
	 * @param WikiPage $wikiPage
	 * @param int $assocPageId
	 * @param string $commentTitle
	 * @param ?string $commentBlockName
	 * @param string $wikitext
	 */
	public function __construct(
		CommentStreamsStore $commentStreamsStore,
		EchoInterface $echoInterface,
		SMWInterface $smwInterface,
		SocialProfileInterface $socialProfileInterface,
		LinkRenderer $linkRenderer,
		RepoGroup $repoGroup,
		RevisionStore $revisionStore,
		ParserFactory $parserFactory,
		UserFactory $userFactory,
		?string $userAvatarPropertyName,
		?string $userRealNamePropertyName,
		bool $enableVoting,
		WikiPage $wikiPage,
		int $assocPageId,
		string $commentTitle,
		?string $commentBlockName,
		string $wikitext
	) {
		parent::__construct(
			$smwInterface,
			$socialProfileInterface,
			$linkRenderer,
			$repoGroup,
			$revisionStore,
			$parserFactory,
			$userFactory,
			$userAvatarPropertyName,
			$userRealNamePropertyName,
			$wikiPage,
			$wikitext
		);
		$this->commentStreamsStore = $commentStreamsStore;
		$this->echoInterface = $echoInterface;
		$this->enableVoting = $enableVoting;
		$this->assocPageId = $assocPageId;
		$this->commentTitle = $commentTitle;
		$this->commentBlockName = $commentBlockName;
	}

	/**
	 * @return int page ID for the wiki page this comment is on
	 */
	public function getAssociatedId(): int {
		return $this->assocPageId;
	}

	/**
	 * @return string the title of the comment
	 */
	public function getCommentTitle(): string {
		return $this->commentTitle;
	}

	/**
	 * @return ?string comment block id
	 */
	public function getBlockName(): ?string {
		return $this->commentBlockName;
	}

	/**
	 * @return int number of replies
	 */
	public function getNumReplies(): int {
		return $this->commentStreamsStore->getNumReplies( $this->wikiPage->getId() );
	}

	/**
	 * @param IContextSource $context
	 * @return array get comment data in array suitable for JSON
	 */
	public function getJSON( IContextSource $context ): array {
		$user = $context->getUser();

		$json = [
			'pageid' => $this->wikiPage->getId(),
			'commentblockname' => $this->commentBlockName,
			'associatedid' => $this->assocPageId,
			'commenttitle' => $this->commentTitle,
			'wikitext' => htmlentities( $this->wikitext ),
			'html' => $this->getHTML( $context ),
			'username' => $this->getUsername(),
			'numreplies' => $this->getNumReplies(),
			'userdisplayname' => $this->getUserDisplayName(),
			'avatar' => $this->avatar,
			'moderated' => $this->isLastEditModerated() ? "moderated" : null,
			'created' => $this->getCreationDate( $user ),
			'created_timestamp' => $this->creationTimestamp->format( "U" ),
			'modified' => $this->getModificationDate( $user )
		];

		if ( $this->enableVoting ) {
			$json['numupvotes'] = $this->commentStreamsStore->getNumUpVotes( $this->getId() );
			$json['numdownvotes'] =
				$this->commentStreamsStore->getNumDownVotes( $this->getId() );
			$json['vote'] = $this->getVote( $user );
		}

		if ( $this->echoInterface->isLoaded() ) {
			$json['watching'] = $this->isWatching( $user ) ? 1 : 0;
		}

		return $json;
	}

	/**
	 * record a vote
	 *
	 * @param string $vote 1 for up vote, -1 for down vote, 0 for no vote
	 * @param User $user the user voting on the comment
	 * @return bool database status code
	 */
	public function vote( string $vote, User $user ): bool {
		Assert::parameter( $vote === "-1" || $vote === "0" || $vote === "1", '$vote',
			'must be "-1", "0", or "1"' );
		$result = $this->commentStreamsStore->vote( (int)$vote, $this->getId(), $user->getId() );
		$this->smwInterface->update( $this->getTitle() );
		return $result;
	}

	/**
	 * @param User $user
	 * @return int
	 */
	public function getVote( User $user ): int {
		return $this->commentStreamsStore->getVote( $this->getId(), $user->getId() );
	}

	/**
	 * @param int $userId
	 * @return bool
	 */
	public function watch( int $userId ): bool {
		return $this->commentStreamsStore->watch( $this->getId(), $userId );
	}

	/**
	 * @param int $userId
	 * @return bool
	 */
	public function unwatch( int $userId ): bool {
		return $this->commentStreamsStore->unwatch( $this->getId(), $userId );
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function isWatching( User $user ): bool {
		return $this->commentStreamsStore->isWatching( $this->getId(), $user->getId() );
	}

	/**
	 * update comment in database
	 *
	 * @param string $commentTitle the new title for the comment
	 * @param string $wikitext the wikitext to add
	 * @param User $user the author of the edit
	 * @return bool true if successful
	 * @throws MWException
	 */
	public function update(
		string $commentTitle,
		string $wikitext,
		User $user
	): bool {
		$result = $this->commentStreamsStore->updateComment(
			$this->wikiPage,
			$commentTitle,
			$wikitext,
			$user
		);
		if ( !$result ) {
			return false;
		}
		$this->commentTitle = $commentTitle;
		$this->wikitext = $wikitext;
		$this->modificationTimestamp = null;
		$wikiPage = CommentStreamsUtils::newWikiPageFromId( $this->wikiPage->getId(), 'fromdbmaster' );
		if ( $wikiPage ) {
			$this->wikiPage = $wikiPage;
		}
		$this->smwInterface->update( $this->getTitle() );
		return true;
	}

	/**
	 * delete comment from database
	 *
	 * @param User $deleter
	 * @return bool true if successful
	 * @throws FatalError
	 * @throws MWException
	 */
	public function delete( User $deleter ): bool {
		return $this->commentStreamsStore->deleteComment( $this->wikiPage, $deleter );
	}

	/**
	 * @return WikiPage[]
	 */
	public function getReplies(): array {
		return $this->commentStreamsStore->getReplies( $this->getId() );
	}
}
