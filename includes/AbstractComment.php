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

use Html;
use IContextSource;
use IDBAccessObject;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MWTimestamp;
use PageProps;
use ParserFactory;
use RepoGroup;
use Title;
use WikiPage;

abstract class AbstractComment {
	/**
	 * @var SMWInterface
	 */
	protected $smwInterface;

	/**
	 * @var SocialProfileInterface
	 */
	protected $socialProfileInterface;

	/**
	 * @var LinkRenderer
	 */
	protected $linkRenderer;

	/**
	 * @var RepoGroup
	 */
	protected $repoGroup;

	/**
	 * @var ParserFactory
	 */
	protected $parserFactory;

	/**
	 * @var UserFactory
	 */
	protected $userFactory;

	/**
	 * @var PageProps
	 */
	protected $pageProps;

	/**
	 * @var ?string
	 */
	protected $userAvatarPropertyName;

	/**
	 * @var ?string
	 */
	protected $userRealNamePropertyName;

	/**
	 * wiki page object for this comment wiki page
	 * @var WikiPage
	 */
	protected $wikiPage;

	/**
	 * wikitext of comment
	 * @var ?string
	 */
	protected $wikitext;

	/**
	 * user object for the author of this comment
	 * @var UserIdentity
	 */
	protected $author;

	/**
	 * user object for the last editor of this comment
	 * @var UserIdentity
	 */
	protected $lastEditor;

	/**
	 * Avatar for author of this comment
	 * @var ?string
	 */
	protected $avatar;

	/**
	 * @var array
	 */
	protected static $avatarCache = [];

	/**
	 * the earliest revision date for this comment
	 * @var MWTimestamp
	 */
	protected $creationTimestamp;

	/**
	 * the latest revision date for this comment
	 * @var ?MWTimestamp
	 */
	protected $modificationTimestamp;

	/**
	 * @param SMWInterface $smwInterface
	 * @param SocialProfileInterface $socialProfileInterface
	 * @param LinkRenderer $linkRenderer
	 * @param RepoGroup $repoGroup
	 * @param RevisionStore $revisionStore
	 * @param ParserFactory $parserFactory
	 * @param UserFactory $userFactory
	 * @param PageProps $pageProps
	 * @param ?string $userAvatarPropertyName
	 * @param ?string $userRealNamePropertyName
	 * @param WikiPage $wikiPage
	 * @param string $wikitext
	 */
	public function __construct(
		SMWInterface $smwInterface,
		SocialProfileInterface $socialProfileInterface,
		LinkRenderer $linkRenderer,
		RepoGroup $repoGroup,
		RevisionStore $revisionStore,
		ParserFactory $parserFactory,
		UserFactory $userFactory,
		PageProps $pageProps,
		?string $userAvatarPropertyName,
		?string $userRealNamePropertyName,
		WikiPage $wikiPage,
		string $wikitext
	) {
		$this->smwInterface = $smwInterface;
		$this->socialProfileInterface = $socialProfileInterface;
		$this->linkRenderer = $linkRenderer;
		$this->repoGroup = $repoGroup;
		$this->parserFactory = $parserFactory;
		$this->userFactory = $userFactory;
		$this->pageProps = $pageProps;
		$this->userAvatarPropertyName = $userAvatarPropertyName;
		$this->userRealNamePropertyName = $userRealNamePropertyName;
		$this->wikiPage = $wikiPage;
		$this->wikitext = $wikitext;

		$title = $wikiPage->getTitle();
		$firstRevision = $revisionStore->getFirstRevision( $title );
		$latestRevision = $revisionStore->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );

		$this->author = $firstRevision->getUser( RevisionRecord::RAW );
		$this->setAvatar();
		$this->lastEditor = $latestRevision->getUser( RevisionRecord::RAW );
		$this->creationTimestamp = MWTimestamp::getLocalInstance( $firstRevision->getTimestamp() );
		if ( $firstRevision->getId() !== $latestRevision->getId() ) {
			$this->modificationTimestamp = MWTimestamp::getLocalInstance( $latestRevision->getTimestamp() );
		}
	}

	/**
	 * @return int page ID of the comment's wiki page
	 */
	public function getId(): int {
		return $this->wikiPage->getId();
	}

	/**
	 * @return Title Title object associated with this comment page
	 */
	public function getTitle(): Title {
		return $this->wikiPage->getTitle();
	}

	/**
	 * @return string wikitext of the comment
	 */
	public function getWikitext(): string {
		return $this->wikitext;
	}

	/**
	 * @param IContextSource $context
	 * @return string parsed HTML of the comment
	 */
	public function getHTML( IContextSource $context ): string {
		$parser = $this->parserFactory->create();
		$parserOptions = $this->wikiPage->makeParserOptions( $context );
		$parserOptions->setOption( 'enableLimitReport', false );
		return $parser
			->parse( $this->wikitext, $this->wikiPage->getTitle(), $parserOptions )
			->getText( [ 'wrapperDivClass' => '' ] );
	}

	/**
	 * @param IContextSource $context
	 * @return array get comment data in array suitable for JSON
	 */
	abstract public function getJSON( IContextSource $context ): array;

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
		return $this->creationTimestamp;
	}

	/**
	 * @return string
	 */
	public function getCreationDate(): string {
		return $this->creationTimestamp->format( "M j \a\\t g:i a" );
	}

	/**
	 * @return ?string
	 */
	public function getModificationDate(): ?string {
		return $this->modificationTimestamp ?
			$this->modificationTimestamp->format( "M j \a\\t g:i a" ) : null;
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
			$values = $this->pageProps->getProperties( $userpage,
				'displaytitle' );
			if ( array_key_exists( $userpage->getArticleID(), $values ) ) {
				$displayname = $values[$userpage->getArticleID()];
			}
		}
		if ( $displayname === null || strlen( $displayname ) == 0 ) {
			$displayname = $this->userFactory->newFromUserIdentity( $user )->getRealName();
		}
		if ( strlen( $displayname ) == 0 ) {
			$displayname = $user->getName();
		}
		if ( $linked && $userpage->exists() ) {
			$displayname = $this->linkRenderer->makeLink( $userpage, $displayname );
		}
		return $displayname;
	}
}
