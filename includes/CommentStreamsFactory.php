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

use ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MWException;
use PageProps;
use ParserFactory;
use RepoGroup;
use User;
use WikiPage;

class CommentStreamsFactory {
	public const CONSTRUCTOR_OPTIONS = [
		'CommentStreamsTimeFormat',
		'CommentStreamsUserAvatarPropertyName',
		'CommentStreamsUserRealNamePropertyName',
		'CommentStreamsEnableVoting'
	];

	/**
	 * @var string
	 */
	private $timeFormat;

	/**
	 * @var string
	 */
	private $userAvatarPropertyName;

	/**
	 * @var string
	 */
	private $userRealNamePropertyName;

	/**
	 * @var bool
	 */
	private $enableVoting;

	/**
	 * @var CommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var EchoInterface
	 */
	private $echoInterface;

	/**
	 * @var SMWInterface
	 */
	private $smwInterface;

	/**
	 * @var SocialProfileInterface
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
	 * @var PageProps
	 */
	private $pageProps;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @param ServiceOptions $options
	 * @param CommentStreamsStore $commentStreamsStore
	 * @param EchoInterface $echoInterface
	 * @param SMWInterface $smwInterface
	 * @param SocialProfileInterface $socialProfileInterface
	 * @param LinkRenderer $linkRenderer
	 * @param RepoGroup $repoGroup
	 * @param RevisionStore $revisionStore
	 * @param ParserFactory $parserFactory
	 * @param UserFactory $userFactory
	 * @param PageProps $pageProps
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ServiceOptions $options,
		CommentStreamsStore $commentStreamsStore,
		EchoInterface $echoInterface,
		SMWInterface $smwInterface,
		SocialProfileInterface $socialProfileInterface,
		LinkRenderer $linkRenderer,
		RepoGroup $repoGroup,
		RevisionStore $revisionStore,
		ParserFactory $parserFactory,
		UserFactory $userFactory,
		PageProps $pageProps,
		WikiPageFactory $wikiPageFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->timeFormat = $options->get( 'CommentStreamsTimeFormat' );
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
		$this->pageProps = $pageProps;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * create a new Comment object from existing wiki page
	 *
	 * @param WikiPage $wikiPage WikiPage object corresponding to comment page
	 * @return Comment|null the newly created comment or null if there was an error
	 * @throws ConfigException
	 */
	public function newCommentFromWikiPage( WikiPage $wikiPage ): ?Comment {
		if ( $wikiPage->getTitle()->getNamespace() !== NS_COMMENTSTREAMS || !$wikiPage->exists() ) {
			return null;
		}

		$result = $this->commentStreamsStore->getComment( $wikiPage->getId() );
		if ( $result === null ) {
			return null;
		}

		$commentTitle = $result['comment_title'];
		$wikitext = $this->commentStreamsStore->getWikitext( $wikiPage, $commentTitle );
		return new Comment(
			$this->commentStreamsStore,
			$this->echoInterface,
			$this->smwInterface,
			$this->socialProfileInterface,
			$this->linkRenderer,
			$this->repoGroup,
			$this->revisionStore,
			$this->parserFactory,
			$this->userFactory,
			$this->pageProps,
			$this->wikiPageFactory,
			$this->timeFormat,
			$this->userAvatarPropertyName,
			$this->userRealNamePropertyName,
			$this->enableVoting,
			$wikiPage,
			$result['assoc_page_id'],
			$commentTitle,
			$result['block_name'],
			$wikitext
		);
	}

	/**
	 * create a new Reply object from existing wiki page
	 *
	 * @param WikiPage $wikiPage WikiPage object corresponding to comment page
	 * @return Reply|null the newly created comment or null if there was an error
	 * @throws ConfigException
	 */
	public function newReplyFromWikiPage( WikiPage $wikiPage ): ?Reply {
		if ( $wikiPage->getTitle()->getNamespace() !== NS_COMMENTSTREAMS || !$wikiPage->exists() ) {
			return null;
		}

		$result = $this->commentStreamsStore->getReply( $wikiPage->getId() );
		if ( $result === null ) {
			return null;
		}

		$wikitext = $this->commentStreamsStore->getWikitext( $wikiPage, null );
		return new Reply(
			$this->commentStreamsStore,
			$this->smwInterface,
			$this->socialProfileInterface,
			$this->linkRenderer,
			$this->repoGroup,
			$this->revisionStore,
			$this->parserFactory,
			$this->userFactory,
			$this->pageProps,
			$this->wikiPageFactory,
			$this->timeFormat,
			$this->userAvatarPropertyName,
			$this->userRealNamePropertyName,
			$wikiPage,
			$result['comment_page_id'],
			$wikitext
		);
	}

	/**
	 * create a new Comment object from values and save to database
	 *
	 * @param int $assocPageId page ID for the wiki page this comment is on
	 * @param string $commentTitle string title of comment
	 * @param ?string $commentBlockName unique id to identify comment block in a page
	 * @param string $wikitext the wikitext to add
	 * @param User $user the user
	 * @return Comment|null new comment object or null if there was a problem creating it
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function newCommentFromValues(
		int $assocPageId,
		string $commentTitle,
		?string $commentBlockName,
		string $wikitext,
		User $user
	): ?Comment {
		$wikiPage = $this->commentStreamsStore->insertComment(
			$user,
			$wikitext,
			$assocPageId,
			$commentTitle,
			$commentBlockName
		);

		if ( !$wikiPage ) {
			return null;
		}

		$comment = new Comment(
			$this->commentStreamsStore,
			$this->echoInterface,
			$this->smwInterface,
			$this->socialProfileInterface,
			$this->linkRenderer,
			$this->repoGroup,
			$this->revisionStore,
			$this->parserFactory,
			$this->userFactory,
			$this->pageProps,
			$this->wikiPageFactory,
			$this->timeFormat,
			$this->userAvatarPropertyName,
			$this->userRealNamePropertyName,
			$this->enableVoting,
			$wikiPage,
			$assocPageId,
			$commentTitle,
			$commentBlockName,
			$wikitext
		);

		$this->smwInterface->update( $wikiPage->getTitle() );

		$this->commentStreamsStore->watch( $wikiPage->getId(), $user->getId() );

		return $comment;
	}

	/**
	 * create a new Reply object from values and save to database
	 *
	 * @param int $parentCommentPageId page ID for the wiki page this comment is in reply to
	 * @param string $wikitext the wikitext to add
	 * @param User $user the user
	 * @return Reply|null new reply object or null if there was a problem creating it
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function newReplyFromValues(
		int $parentCommentPageId,
		string $wikitext,
		User $user
	): ?Reply {
		$wikiPage = $this->commentStreamsStore->insertReply(
			$user,
			$wikitext,
			$parentCommentPageId
		);

		if ( !$wikiPage ) {
			return null;
		}

		$reply = new Reply(
			$this->commentStreamsStore,
			$this->smwInterface,
			$this->socialProfileInterface,
			$this->linkRenderer,
			$this->repoGroup,
			$this->revisionStore,
			$this->parserFactory,
			$this->userFactory,
			$this->pageProps,
			$this->wikiPageFactory,
			$this->timeFormat,
			$this->userAvatarPropertyName,
			$this->userRealNamePropertyName,
			$wikiPage,
			$parentCommentPageId,
			$wikitext
		);

		$this->smwInterface->update( $wikiPage->getTitle() );

		$this->commentStreamsStore->watch( $parentCommentPageId, $user->getId() );

		return $reply;
	}
}
