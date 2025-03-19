<?php

namespace MediaWiki\Extension\CommentStreams;

use HtmlArmor;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageProps;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
use RepoGroup;
use Wikimedia\ObjectCache\HashBagOStuff;

class CommentSerializer {
	/** @var string|null */
	private ?string $timeFormat;
	/** @var string|null */
	private ?string $userAvatarPropertyName;
	/** @var string|null */
	private ?string $userRealNamePropertyName;
	/** @var bool */
	private bool $enableVoting;
	/** @var ICommentStreamsStore */
	private ICommentStreamsStore $store;
	/** @var ParserFactory */
	private ParserFactory $parserFactory;
	/** @var SMWInterface */
	private SMWInterface $smwInterface;
	/** @var LinkRenderer */
	private LinkRenderer $linkRenderer;
	/** @var UserFactory */
	private UserFactory $userFactory;
	/** @var PageProps */
	private PageProps $pageProps;
	/** @var SocialProfileInterface */
	private SocialProfileInterface $socialProfileInterface;
	/** @var RepoGroup */
	private RepoGroup $repoGroup;
	/** @var HashBagOStuff */
	private HashBagOStuff $cache;

	/**
	 * @param ServiceOptions $options
	 * @param ICommentStreamsStore $store
	 * @param ParserFactory $parserFactory
	 * @param SMWInterface $smwInterface
	 * @param LinkRenderer $linkRenderer
	 * @param UserFactory $userFactory
	 * @param PageProps $pageProps
	 * @param SocialProfileInterface $socialProfileInterface
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		ServiceOptions $options, ICommentStreamsStore $store, ParserFactory $parserFactory,
		SMWInterface $smwInterface, LinkRenderer $linkRenderer, UserFactory $userFactory, PageProps $pageProps,
		SocialProfileInterface $socialProfileInterface, RepoGroup $repoGroup
	) {
		$this->timeFormat = $options->get( 'CommentStreamsTimeFormat' );
		$this->userAvatarPropertyName = $options->get( 'CommentStreamsUserAvatarPropertyName' );
		$this->userRealNamePropertyName = $options->get( 'CommentStreamsUserRealNamePropertyName' );
		$this->enableVoting = (bool)$options->get( 'CommentStreamsEnableVoting' );

		$this->store = $store;
		$this->parserFactory = $parserFactory;
		$this->smwInterface = $smwInterface;
		$this->linkRenderer = $linkRenderer;
		$this->userFactory = $userFactory;
		$this->pageProps = $pageProps;
		$this->socialProfileInterface = $socialProfileInterface;
		$this->repoGroup = $repoGroup;

		$this->cache = new HashBagOStuff();
	}

	/**
	 * @param Comment $comment
	 * @param IContextSource $context
	 * @return array
	 */
	public function serializeComment( Comment $comment, IContextSource $context ): array {
		$user = $context->getUser();
		$wikitext = $this->getWikitext( $comment );

		$json = [
			'id' => $comment->getId(),
			'commentblockname' => $comment->getBlockName(),
			'associatedid' => $comment->getAssociatedPage() ? $comment->getAssociatedPage()->getId() : 0,
			'commenttitle' => htmlspecialchars( $comment->getTitle() ),
			'wikitext' => htmlspecialchars( $wikitext ),
			'html' => $this->getHTML( $context, $wikitext, $comment->getAssociatedPage() ),
			'username' => $comment->getAuthor()->getName(),
			'numreplies' => $this->store->getNumReplies( $comment ),
			'userdisplayname' => $this->getDisplayNameFromUser( $comment->getAuthor(), true ),
			'avatar' => $this->getAvatar( $comment->getAuthor() ),
			'moderated' => $this->isLastEditModerated( $comment ) ? "moderated" : null,
			'created' => $this->formatTimestamp( $comment->getCreated(), $user ),
			'created_timestamp' => $comment->getCreated()->format( "U" ),
			'modified' => $this->formatTimestamp( $comment->getModified(), $user ),
			'watching' => $this->store->isWatching( $comment, $user ) ? 1 : 0
		];

		if ( $this->enableVoting ) {
			$json['numupvotes'] = $this->store->getNumUpVotes( $comment );
			$json['numdownvotes'] = $this->store->getNumDownVotes( $comment );
			$json['vote'] = $this->store->getVote( $comment, $user );
		}

		return $json;
	}

	/**
	 * @param Reply $reply
	 * @param IContextSource $context
	 * @return array
	 */
	public function serializeReply( Reply $reply, IContextSource $context ): array {
		$user = $context->getUser();
		$wikitext = $this->getWikitext( $reply );

		return [
			'id' => $reply->getId(),
			'parentid' => $reply->getParent()->getId(),
			'wikitext' => htmlspecialchars( $wikitext ),
			'html' => $this->getHTML( $context, $wikitext, $reply->getAssociatedPage() ),
			'username' => $reply->getAuthor()->getName(),
			'userdisplayname' => $this->getDisplayNameFromUser( $reply->getAuthor(), true ),
			'avatar' => $this->getAvatar( $reply->getAuthor() ),
			'moderated' => $this->isLastEditModerated( $reply ) ? "moderated" : null,
			'created' => $this->formatTimestamp( $reply->getCreated(), $user ),
			'created_timestamp' => $reply->getCreated()->format( "U" ),
			'modified' => $this->formatTimestamp( $reply->getModified(), $user ),
		];
	}

	/**
	 * @param IContextSource $context
	 * @param string $wikitext
	 * @param PageIdentity|null $associatedPage
	 * @return string parsed HTML of the comment
	 */
	private function getHTML( IContextSource $context, string $wikitext, ?PageIdentity $associatedPage ): string {
		$parser = $this->parserFactory->create();
		$options = ParserOptions::newFromContext( $context );
		$options->setOption( 'enableLimitReport', false );
		$parser->setOptions( $options );
		$output = $parser->parse( $wikitext, $associatedPage ?? Title::newMainPage(), $options );
		$output = $output->runOutputPipeline( $options, [ 'wrapperDivClass' => '' ] );
		return $output->getContentHolderText();
	}

	public function getDisplayNameFromUser(
		UserIdentity $user,
		bool $linked
	): string {
		if ( $user->getId() === 0 ) {
			return Html::rawElement( 'span', [
				'class' => 'cs-comment-author-anonymous'
			], wfMessage( 'commentstreams-author-anonymous' ) );
		}
		$userpage = Title::makeTitle( NS_USER, $user->getName() );
		$displayname = '';

		if ( $this->userRealNamePropertyName !== null ) {
			$displayname = $this->smwInterface->getUserProperty(
				$user,
				$this->userRealNamePropertyName
			);

			// This is not a $displayname === null check to appease Phan
			if ( !is_string( $displayname ) ) {
				$displayname = '';
			}
		}
		if ( $displayname === '' ) {
			$values = $this->pageProps->getProperties( $userpage, 'displaytitle' );
			if ( array_key_exists( $userpage->getArticleID(), $values ) ) {
				// @phan-suppress-next-line SecurityCheck-XSS Core already sanitized this
				$displayname = new HtmlArmor( $values[$userpage->getArticleID()] );
			}
		}
		if ( $displayname === '' ) {
			$displayname = $this->userFactory->newFromUserIdentity( $user )->getRealName();
		}
		if ( $displayname === '' ) {
			$displayname = $user->getName();
		}

		if ( $linked && $userpage->exists() ) {
			$displayname = $this->linkRenderer->makeLink( $userpage, $displayname );
		} elseif ( $displayname instanceof HtmlArmor ) {
			// To satisfy the function return type (plus, this function returns HTML as
			// a string anyway)
			$displayname = HtmlArmor::getHtml( $displayname ) ?? '';
		}
		return $displayname;
	}

	/**
	 * @param AbstractComment $comment
	 * @return string
	 */
	public function getWikitext( AbstractComment $comment ): string {
		return $this->store->getWikitext( $comment );
	}

	/**
	 * @param MWTimestamp|null $timestamp
	 * @param User $user
	 * @return string|null
	 */
	private function formatTimestamp( ?MWTimestamp $timestamp, User $user ): ?string {
		if ( !$timestamp ) {
			return null;
		}
		$timestamp->offsetForUser( $user );
		return $timestamp->format( $this->timeFormat );
	}

	/**
	 * @param AbstractComment $comment
	 * @return bool
	 */
	private function isLastEditModerated( AbstractComment $comment ) {
		return $comment->getAuthor()->getId() !== $comment->getLastEditor()->getId();
	}

	/**
	 * @param UserIdentity $author
	 * @return string|null
	 */
	private function getAvatar( UserIdentity $author ): ?string {
		$cacheKey = $this->cache->makeKey( 'avatar', $author->getId() );
		if ( $this->cache->get( $cacheKey ) ) {
			return $this->cache->get( $cacheKey );
		}

		$avatar = $this->socialProfileInterface->getAvatar( $author );

		if ( $avatar === null && $this->userAvatarPropertyName !== null ) {
			$title = $this->smwInterface->getUserProperty( $author, $this->userAvatarPropertyName );
			if ( $title !== null ) {
				if ( is_string( $title ) ) {
					$title = Title::newFromText( $title );
				}
				if ( $title->isKnown() && $title->getNamespace() === NS_FILE ) {
					$file = $this->repoGroup->findFile( $title->getText() );
					if ( $file ) {
						$avatar = $file->createThumb( 48, 48 );
					}
				}
			}
		}

		$this->cache->set( $cacheKey, $avatar );
		return $avatar;
	}
}
