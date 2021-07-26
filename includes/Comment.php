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
use Html;
use IContextSource;
use IDBAccessObject;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MWException;
use MWTimestamp;
use PageProps;
use ParserFactory;
use RepoGroup;
use Title;
use User;
use Wikimedia\Assert\Assert;
use WikiPage;

class Comment {
	public const CONSTRUCTOR_OPTIONS = [
		'CommentStreamsUserAvatarPropertyName',
		'CommentStreamsUserRealNamePropertyName',
		'CommentStreamsEnableVoting'
	];

	/**
	 * @var CommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var CommentStreamsEchoInterface
	 */
	private $echoInterface;

	/**
	 * @var CommentStreamsSMWInterface
	 */
	private $smwInterface;

	/**
	 * @var CommentStreamsSocialProfileInterface
	 */
	private $socialProfileInterface;

	/**
	 * @var LinkRenderer
	 */
	private $linkRenderer;

	/**
	 * @var RepoGroup
	 */
	private $repoGroup;

	/**
	 * @var RevisionStore
	 */
	private $revisionStore;

	/**
	 * @var ParserFactory
	 */
	private $parserFactory;

	/**
	 * @var UserFactory
	 */
	private $userFactory;

	/**
	 * @var string
	 */
	private $userAvatarPropertyName;

	/**
	 * @var string
	 */
	private $userRealNamePropertyName;

	/**
	 * @var mixed
	 */
	private $enableVoting;

	/**
	 * wiki page object for this comment wiki page
	 * @var WikiPage
	 */
	private $wikipage;

	/**
	 * unique id to identify comment block on a page; null if comment is in default comment block
	 * or is a reply
	 * @var ?string
	 */
	private $comment_block_id;

	/**
	 * page ID for the wiki page this comment is on
	 * @var int
	 */
	private $assoc_page_id;

	/**
	 * page ID for the wiki page this comment is in reply to or null
	 * @var ?int
	 */
	private $parent_page_id;

	/**
	 * title of comment
	 * @var ?string
	 */
	private $comment_title;

	/**
	 * wikitext of comment
	 * @var ?string
	 */
	private $wikitext;

	/**
	 * number of replies to this comment
	 * @var ?int
	 */
	private $num_replies;

	/**
	 * user object for the author of this comment
	 * @var UserIdentity
	 */
	private $author;

	/**
	 * user object for the last editor of this comment
	 * @var ?UserIdentity
	 */
	private $lastEditor;

	/**
	 * Avatar for author of this comment
	 * @var ?string
	 */
	private $avatar;

	/**
	 * @var array
	 */
	private static $avatarCache = [];

	/**
	 * the earliest revision date for this comment
	 * @var ?MWTimestamp
	 */
	private $creation_timestamp;

	/**
	 * the latest revision date for this comment
	 * @var ?MWTimestamp
	 */
	private $modification_timestamp;

	/**
	 * Do not instantiate directly. Use CommentStreamsFactory instead.
	 * @param ServiceOptions $options
	 * @param CommentStreamsStore $commentStreamsStore
	 * @param CommentStreamsEchoInterface $echoInterface
	 * @param CommentStreamsSMWInterface $smwInterface
	 * @param CommentStreamsSocialProfileInterface $socialProfileInterface
	 * @param LinkRenderer $linkRenderer
	 * @param RepoGroup $repoGroup
	 * @param RevisionStore $revisionStore
	 * @param ParserFactory $parserFactory
	 * @param UserFactory $userFactory
	 * @param WikiPage $wikipage
	 * @param ?string $comment_block_id
	 * @param int $assoc_page_id
	 * @param ?int $parent_page_id
	 * @param ?string $comment_title
	 * @param string $wikitext
	 */
	public function __construct(
		ServiceOptions $options,
		CommentStreamsStore $commentStreamsStore,
		CommentStreamsEchoInterface $echoInterface,
		CommentStreamsSMWInterface $smwInterface,
		CommentStreamsSocialProfileInterface $socialProfileInterface,
		LinkRenderer $linkRenderer,
		RepoGroup $repoGroup,
		RevisionStore $revisionStore,
		ParserFactory $parserFactory,
		UserFactory $userFactory,
		WikiPage $wikipage,
		?string $comment_block_id,
		int $assoc_page_id,
		?int $parent_page_id,
		?string $comment_title,
		string $wikitext
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->userAvatarPropertyName = $options->get( 'CommentStreamsUserAvatarPropertyName' );
		$this->userRealNamePropertyName = $options->get( 'CommentStreamsUserRealNamePropertyName' );
		$this->enableVoting = (bool)$options->get( 'CommentStreamsEnableVoting' );
		$this->commentStreamsStore = $commentStreamsStore;
		$this->echoInterface = $echoInterface;
		$this->smwInterface = $smwInterface;
		$this->socialProfileInterface = $socialProfileInterface;
		$this->linkRenderer = $linkRenderer;
		$this->repoGroup = $repoGroup;
		$this->revisionStore = $revisionStore;
		$this->parserFactory = $parserFactory;
		$this->userFactory = $userFactory;
		$this->wikipage = $wikipage;
		$this->assoc_page_id = $assoc_page_id;
		$this->parent_page_id = $parent_page_id;
		if ( $this->comment_title === null ) {
			$this->comment_block_id = $comment_block_id;
		} else {
			$this->comment_block_id = null;
		}
		$this->comment_title = $comment_title;
		$this->wikitext = $wikitext;
		$this->num_replies = $commentStreamsStore->getNumReplies( $wikipage->getId() );

		$title = $wikipage->getTitle();
		$firstRevision = $this->revisionStore->getFirstRevision( $title );
		$latestRevision = $this->revisionStore->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );

		$this->author = $firstRevision->getUser( RevisionRecord::RAW );
		$this->setAvatar();
		$this->lastEditor = $latestRevision->getUser( RevisionRecord::RAW );
		$this->creation_timestamp = MWTimestamp::getLocalInstance( $firstRevision->getTimestamp() );
		if ( $firstRevision->getId() !== $latestRevision->getId() ) {
			$this->modification_timestamp = MWTimestamp::getLocalInstance( $latestRevision->getTimestamp() );
		}
	}

	/**
	 * @return int page ID of the comment's wiki page
	 */
	public function getId(): int {
		return $this->wikipage->getId();
	}

	/**
	 * @return Title Title object associated with this comment page
	 */
	public function getTitle(): Title {
		return $this->wikipage->getTitle();
	}

	/**
	 * @return ?string comment block id
	 */
	public function getBlockId(): ?string {
		return $this->comment_block_id;
	}

	/**
	 * @return int page ID for the wiki page this comment is on
	 */
	public function getAssociatedId(): int {
		return $this->assoc_page_id;
	}

	/**
	 * @return int|null page ID for the wiki page this comment is in reply to or
	 * null if this comment is a discussion, not a reply
	 */
	public function getParentId(): ?int {
		return $this->parent_page_id;
	}

	/**
	 * @return ?string the title of the comment
	 */
	public function getCommentTitle(): ?string {
		return $this->comment_title;
	}

	/**
	 * @return string wikitext of the comment
	 */
	public function getWikiText(): string {
		return $this->wikitext;
	}

	/**
	 * @param IContextSource $context
	 * @return string parsed HTML of the comment
	 */
	public function getHTML( IContextSource $context ): string {
		$parser = $this->parserFactory->create();
		$parserOptions = $this->wikipage->makeParserOptions( $context );
		$parserOptions->setOption( 'enableLimitReport', false );
		return $parser
			->parse( $this->wikitext, $this->wikipage->getTitle(), $parserOptions )
			->getText( [ 'wrapperDivClass' => '' ] );
	}

	/**
	 * @return int number of replies
	 */
	public function getNumReplies(): int {
		return $this->num_replies;
	}

	/**
	 * @return ?UserIdentity the author of this comment
	 */
	public function getAuthor(): ?UserIdentity {
		return $this->author;
	}

	/**
	 * @return string username of the author of this comment
	 */
	public function getUsername(): string {
		return $this->author->getName();
	}

	/**
	 * @return string display name of the author of this comment linked to
	 * the user's user page if it exists
	 */
	public function getUserDisplayName(): string {
		return $this->getDisplayNameFromUser( $this->author, true );
	}

	/**
	 * @return string display name of the author of this comment
	 */
	public function getUserDisplayNameUnlinked(): string {
		return $this->getDisplayNameFromUser( $this->author, false );
	}

	/**
	 * @return UserIdentity the last editor of this comment
	 */
	public function getLastEditor(): UserIdentity {
		return $this->lastEditor;
	}

	/**
	 * @return bool true if the last edit to this comment was not done by the
	 * original author
	 */
	public function isLastEditModerated(): bool {
		return $this->author->getId() !== $this->lastEditor->getId();
	}

	/**
	 * @return MWTimestamp
	 */
	public function getCreationTimestamp(): MWTimestamp {
		return $this->creation_timestamp;
	}

	/**
	 * @return string
	 */
	public function getCreationDate(): string {
		return $this->creation_timestamp->format( "M j \a\\t g:i a" );
	}

	/**
	 * @return ?string
	 */
	public function getModificationDate(): ?string {
		return $this->modification_timestamp ?
			$this->modification_timestamp->format( "M j \a\\t g:i a" ) : null;
	}

	/**
	 * @param IContextSource $context
	 * @return array get comment data in array suitable for JSON
	 */
	public function getJSON( IContextSource $context ): array {
		$json = [
			'pageid' => $this->wikipage->getId(),
			'commentblockid' => $this->comment_block_id,
			'associatedid' => $this->assoc_page_id,
			'parentid' => $this->parent_page_id,
			'commenttitle' => $this->comment_title,
			'wikitext' => htmlentities( $this->wikitext ),
			'html' => $this->getHTML( $context ),
			'username' => $this->getUsername(),
			'numreplies' => $this->num_replies,
			'userdisplayname' => $this->getUserDisplayName(),
			'avatar' => $this->avatar,
			'moderated' => $this->isLastEditModerated() ? "moderated" : null,
			'created' => $this->getCreationDate(),
			'created_timestamp' => $this->creation_timestamp->format( "U" ),
			'modified' => $this->getModificationDate()
		];

		$user = $context->getUser();
		if ( $this->parent_page_id === null ) {
			if ( $this->enableVoting ) {
				$json['numupvotes'] = $this->commentStreamsStore->getNumUpVotes( $this->getId() );
				$json['numdownvotes'] =
					$this->commentStreamsStore->getNumDownVotes( $this->getId() );
				$json['vote'] = $this->getVote( $user );
			}
			if ( $this->echoInterface->isLoaded() ) {
				$json['watching'] = $this->isWatching( $user ) ? 1 : 0;
			}
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
	 * @param int $user_id
	 * @return bool
	 */
	public function watch( int $user_id ): bool {
		return $this->commentStreamsStore->watch( $this->getId(), $user_id );
	}

	/**
	 * @param int $user_id
	 * @return bool
	 */
	public function unwatch( int $user_id ): bool {
		return $this->commentStreamsStore->unwatch( $this->getId(), $user_id );
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
	 * NOTE: since only head comments can contain a comment title,
	 * $comment_title may only be non null if this comment has a null parent id
	 * and vice versa
	 *
	 * @param ?string $comment_title the new title for the comment
	 * @param string $wikitext the wikitext to add
	 * @param User $user the author of the edit
	 * @return bool true if successful
	 * @throws MWException
	 */
	public function update(
		?string $comment_title,
		string $wikitext,
		User $user
	): bool {
		Assert::parameter(
			( $comment_title === null && $this->parent_page_id !== null ) ||
			( $comment_title !== null && $this->parent_page_id === null ),
			'$comment_title',
			'must be null if parent page ID is non-null or non-null if parent page ID is null'
		);
		$result = $this->commentStreamsStore->updateComment(
			$this->wikipage,
			$comment_title,
			$wikitext,
			$user
		);
		if ( !$result ) {
			return false;
		}
		$this->comment_title = $comment_title;
		$this->wikitext = $wikitext;
		$this->modification_timestamp = null;
		$wikipage = CommentStreamsUtils::newWikiPageFromId( $this->wikipage->getId(),
			'fromdbmaster' );
		if ( $wikipage !== null ) {
			$this->wikipage = $wikipage;
		}
		if ( $this->parent_page_id === null ) {
			$this->smwInterface->update( $this->getTitle() );
		}
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
		return $this->commentStreamsStore->deleteComment( $this->wikipage, $deleter );
	}

	private function setAvatar() {
		if ( array_key_exists( $this->author->getId(), self::$avatarCache ) ) {
			$this->avatar = self::$avatarCache[ $this->author->getId() ];
			return;
		}

		$this->avatar = $this->socialProfileInterface->getAvatar( $this->author );

		if ( $this->avatar === null && $this->userAvatarPropertyName !== null ) {
			$title = $this->smwInterface->getUserProperty( $this->author,
				$this->userAvatarPropertyName );
			if ( $title !== null ) {
				if ( is_string( $title ) ) {
					$title = Title::newFromText( $title );
				}
				if ( $title->isKnown() && $title->getNamespace() === NS_FILE ) {
					$file = $this->repoGroup->findFile( $title->getText() );
					if ( $file ) {
						$this->avatar = $file->createThumb( 48, 48 );
					}
				}
			}
		}

		self::$avatarCache[ $this->author->getId() ] = $this->avatar;
	}

	/**
	 * return the text to use to represent the user at the top of a comment
	 *
	 * @param UserIdentity $user the user
	 * @param bool $linked whether to link the display name to the user page,
	 *        if it exists
	 * @return string display name for user
	 */
	private function getDisplayNameFromUser(
		UserIdentity $user,
		bool $linked
	): string {
		if ( $user->getId() === 0 ) {
			return Html::openElement( 'span', [
					'class' => 'cs-comment-author-anonymous'
				] )
				. wfMessage( 'commentstreams-author-anonymous' )
				. Html::closeElement( 'span' );
		}
		$userpage = Title::makeTitle( NS_USER, $user->getName() );
		$displayname = null;
		if ( $this->userRealNamePropertyName !== null ) {
			$displayname = $this->smwInterface->getUserProperty(
				$user,
				$this->userRealNamePropertyName
			);
		}
		if ( $displayname === null || strlen( $displayname ) == 0 ) {
			$values = PageProps::getInstance()->getProperties( $userpage,
				'displaytitle' );
			if ( array_key_exists( $userpage->getArticleID(), $values ) ) {
				$displayname = $values[$userpage->getArticleID()];
			}
		}
		if ( $displayname === null || strlen( $displayname ) == 0 ) {
			$displayname = $this->userFactory->newFromUserIdentity( $user )->getRealName();
		}
		if ( $displayname === null || strlen( $displayname ) == 0 ) {
			$displayname = $user->getName();
		}
		if ( $linked && $userpage->exists() ) {
			$displayname = $this->linkRenderer->makeLink( $userpage, $displayname );
		}
		return $displayname;
	}
}
