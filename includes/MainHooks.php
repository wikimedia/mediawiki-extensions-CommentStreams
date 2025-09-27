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

use Article;
use HtmlArmor;
use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\Extension\CommentStreams\Store\NamespacePageStore;
use MediaWiki\Extension\CommentStreams\Store\TalkPageStore;
use MediaWiki\Hook\AfterImportPageHook;
use MediaWiki\Hook\ImportHandlePageXMLTagHook;
use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\Hook\MovePageIsValidMoveHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SpecialExportGetExtraPagesHook;
use MediaWiki\Hook\XmlDumpWriterOpenPageHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\PageProps;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Search\Hook\ShowSearchHitTitleHook;
use MediaWiki\Specials\SpecialSearch;
use MediaWiki\Status\Status;
use MediaWiki\Title\ForeignTitle;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\Xml\Xml;
use MWException;
use SearchResult;
use Skin;
use stdClass;
use WikiImporter;
use XmlDumpWriter;
use XMLReader;

class MainHooks implements
	MediaWikiPerformActionHook,
	MovePageIsValidMoveHook,
	GetUserPermissionsErrorsHook,
	BeforePageDisplayHook,
	ShowSearchHitTitleHook,
	ParserFirstCallInitHook,
	SpecialExportGetExtraPagesHook,
	XmlDumpWriterOpenPageHook,
	ImportHandlePageXMLTagHook,
	AfterImportPageHook
{
	/**
	 * @var CommentStreamsHandler
	 */
	private $commentStreamsHandler;

	/**
	 * @var ICommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var LinkRenderer
	 */
	private $linkRenderer;

	/**
	 * @var RevisionStore
	 */
	private $revisionStore;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @var PageProps
	 */
	private $pageProps;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @param CommentStreamsHandler $commentStreamsHandler
	 * @param ICommentStreamsStore $commentStreamsStore
	 * @param LinkRenderer $linkRenderer
	 * @param RevisionStore $revisionStore
	 * @param PermissionManager $permissionManager
	 * @param PageProps $pageProps
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		CommentStreamsHandler $commentStreamsHandler,
		ICommentStreamsStore $commentStreamsStore,
		LinkRenderer $linkRenderer,
		RevisionStore $revisionStore,
		PermissionManager $permissionManager,
		PageProps $pageProps,
		WikiPageFactory $wikiPageFactory
	) {
		$this->commentStreamsHandler = $commentStreamsHandler;
		$this->commentStreamsStore = $commentStreamsStore;
		$this->linkRenderer = $linkRenderer;
		$this->revisionStore = $revisionStore;
		$this->permissionManager = $permissionManager;
		$this->pageProps = $pageProps;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Prevents comment pages from being edited or deleted. Displays
	 * comment title and link to associated page when comment is viewed.
	 *
	 * @param OutputPage $output Context output
	 * @param Article $article Article on which the action will be performed
	 * @param Title $title Title on which the action will be performed
	 * @param User $user Context user
	 * @param WebRequest $request Context request
	 * @param ActionEntryPoint $mediaWiki
	 * @return bool|void True or no return value to continue or false to abort
	 * @throws MWException
	 */
	public function onMediaWikiPerformAction(
		$output,
		$article,
		$title,
		$user,
		$request,
		$mediaWiki
	) {
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			// Only applies to NamespacePageStore, where comments are stored as pages in NS_COMMENTSTREAMS
			return;
		}

		$action = $mediaWiki->getAction();
		if ( $action === 'info' || $action === 'history' || $action === 'raw' ) {
			return;
		}
		if ( $action !== 'view' ) {
			$message = wfMessage( 'commentstreams-error-prohibitedaction', $action )->escaped();
			$output->addHTML( '<p class="error">' . $message . '</p>' );
			return false;
		}

		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$comment = $this->commentStreamsStore->getComment( $wikiPage->getId() );
		if ( $comment ) {
			$output->setPageTitle( $comment->getTitle() );
			$associatedTitle = $comment->getAssociatedPage();
			if ( $associatedTitle ) {
				$values = $this->pageProps->getProperties( $associatedTitle, 'displaytitle' );
				if ( array_key_exists( $associatedTitle->getId(), $values ) ) {
					$displaytitle = $values[$associatedTitle->getId()];
				} else {
					$displaytitle = $associatedTitle->getPrefixedText();
				}
				$output->setSubtitle( $this->linkRenderer->makeLink( $associatedTitle, '< ' . $displaytitle ) );
			} else {
				$message = wfMessage( 'commentstreams-error-comment-on-deleted-page' )->escaped();
				$output->addHTML( '<p class="error">' . $message . '</p>' );
			}
		} else {
			$reply = $this->commentStreamsStore->getReply( $wikiPage->getId() );
			if ( $reply ) {
				$parentCommentTitle = Title::newFromId( $reply->getParent()->getId() );
				if ( $parentCommentTitle ) {
					$values = $this->pageProps->getProperties( $parentCommentTitle, 'displaytitle' );
					if ( array_key_exists( $reply->getParent()->getId(), $values ) ) {
						$displaytitle = $values[$reply->getParent()->getId()];
					} else {
						$displaytitle = $parentCommentTitle->getPrefixedText();
					}
					$output->setSubtitle( $this->linkRenderer->makeLink( $parentCommentTitle, '< ' . $displaytitle ) );
				} else {
					$message = wfMessage( 'commentstreams-error-reply-to-deleted-comment' )->escaped();
					$output->addHTML( '<p class="error">' . $message . '</p>' );
				}
			}
		}
	}

	/**
	 * Prevents comment pages from being moved.
	 *
	 * @param Title $oldTitle Current (old) location
	 * @param Title $newTitle New location
	 * @param Status $status Status object to pass error messages to
	 */
	public function onMovePageIsValidMove( $oldTitle, $newTitle, $status ) {
		if ( $oldTitle->getNamespace() === NS_COMMENTSTREAMS || $newTitle->getNamespace() === NS_COMMENTSTREAMS ) {
			$status->fatal( wfMessage( 'commentstreams-error-prohibitedaction', 'move' ) );
		}
	}

	/**
	 * Ensures that only the original author can edit a comment
	 *
	 * @param Title $title Title being checked against
	 * @param User $user Current user
	 * @param string $action Action being checked
	 * @param array &$result Pointer to result, if any
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			return;
		}

		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );

		if ( !$wikiPage->exists() ) {
			return;
		}

		$allowed = true;
		if ( $action === 'cs-comment' ) {
			$revisionRecord = $this->revisionStore->getFirstRevision( $title );
			if ( $revisionRecord ) {
				$author = $revisionRecord->getUser( RevisionRecord::RAW );
				$allowed = ( $user->getId() === $author->getId() )
						 && $this->permissionManager->userHasRight( $user, $action );
			} else {
				$allowed = false;
			}
		} elseif ( in_array( $action, [ 'cs-moderator-edit', 'cs-moderator-delete' ] ) ) {
			$allowed = $this->permissionManager->userHasRight( $user, $action );
		}

		if ( !$allowed ) {
			$result = 'badaccess-group0';
			return false;
		}
	}

	/**
	 * Gets comments for page and initializes variables to be passed to JavaScript.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$this->commentStreamsHandler->init( $out );
	}

	/**
	 * Modifies search results pointing to comment pages to point to the
	 * associated content page instead.
	 *
	 * @param Title &$title Title to link to
	 * @param string|HtmlArmor|null &$titleSnippet Label for the link representing
	 *	 the search result. Typically the article title.
	 * @param SearchResult $result
	 * @param array $terms Array of search terms extracted by SearchDatabase search engines
	 *	 (may not be populated by other search engines)
	 * @param SpecialSearch $specialSearch
	 * @param string[] &$query Array of query string parameters for the link representing the search
	 *	 result
	 * @param string[] &$attributes Array of title link attributes, can be modified by extension
	 */
	public function onShowSearchHitTitle(
		&$title,
		&$titleSnippet,
		$result,
		$terms,
		$specialSearch,
		&$query,
		&$attributes
	) {
		if ( !( $this->commentStreamsStore instanceof ICommentStreamsStore ) ) {
			return;
		}
		if ( $title->getNamespace() !== NS_COMMENTSTREAMS ) {
			return;
		}

		$wikiPage = $this->wikiPageFactory->newFromID( $title->getArticleID() );
		if ( !$wikiPage ) {
			return;
		}

		$reply = $this->commentStreamsStore->getReply( $wikiPage->getId() );
		if ( $reply ) {
			$wikiPage = $this->wikiPageFactory->newFromID( $reply->getParent()->getId() );
			if ( !$wikiPage ) {
				return;
			}
		}

		$comment = $this->commentStreamsStore->getComment( $wikiPage->getId() );
		if ( $comment ) {
			if ( $comment->getAssociatedPage() ) {
				$title = $comment->getAssociatedPage();
			}
		}
	}

	/**
	 * Adds comment-streams, no-comment-streams, and comment-streams-initially-collapsed magic words.
	 *
	 * @param Parser $parser Parser object being initialised
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'comment-streams', [ $this->commentStreamsHandler, 'enableCommentStreams' ] );
		$parser->setHook( 'comment-streams-toc', [ $this->commentStreamsHandler, 'tocTag' ] );
		$parser->setHook( 'no-comment-streams', [ $this->commentStreamsHandler, 'disableCommentStreams' ] );
		$parser->setHook(
			'comment-streams-initially-collapsed',
			[ $this->commentStreamsHandler, 'initiallyCollapseCommentStreams' ]
		);
	}

	/**
	 * Implements extension registration callback.
	 * See https://www.mediawiki.org/wiki/Manual:Extension_registration#Customizing_registration
	 * Sets configuration constants.
	 */
	public static function onRegistration() {
		mwsInitComponents();
		define( 'NS_COMMENTSTREAMS', $GLOBALS['wgCommentStreamsNamespaceIndex'] );
		define( 'NS_COMMENTSTREAMS_TALK', $GLOBALS['wgCommentStreamsNamespaceIndex'] + 1 );
		if ( $GLOBALS['wgCommentStreamsEnableSearch'] ) {
			$GLOBALS['wgNamespacesToBeSearchedDefault'][NS_COMMENTSTREAMS] = true;
		}
		$found = false;
		foreach ( $GLOBALS['wgGroupPermissions'] as $groupperms ) {
			if ( isset( $groupperms['cs-comment'] ) ) {
				$found = true;
				break;
			}
		}
		if ( !$found ) {
			foreach ( $GLOBALS['wgGroupPermissions'] as $group => $groupperms ) {
				if ( isset( $groupperms['edit'] ) ) {
					$GLOBALS['wgGroupPermissions'][$group]['cs-comment'] = $groupperms['edit'];
				}
			}
		}
		if ( !isset( $GLOBALS['wgGroupPermissions']['csmoderator']['cs-moderator-delete'] ) ) {
			$GLOBALS['wgGroupPermissions']['csmoderator']['cs-moderator-delete'] = true;
		}
		if ( !isset( $GLOBALS['wgGroupPermissions']['csmoderator']['cs-moderator-edit'] ) ) {
			$GLOBALS['wgGroupPermissions']['csmoderator']['cs-moderator-edit'] = false;
		}
		$GLOBALS['wgAvailableRights'][] = 'cs-comment';
		$GLOBALS['wgAvailableRights'][] = 'cs-moderator-edit';
		$GLOBALS['wgAvailableRights'][] = 'cs-moderator-delete';
		$GLOBALS['wgLogTypes'][] = 'commentstreams';
		$GLOBALS['wgLogActionsHandlers']['commentstreams/*'] = 'WikitextLogFormatter';

		define( 'SLOT_COMMENTSTREAMS_COMMENTS', 'cs-comments' );
	}

	/**
	 * Add extra pages to the list of pages to export.
	 *
	 * @param string[] $inputPages List of page titles to export
	 * @param PageReference[] &$extraPages List of extra page titles
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSpecialExportGetExtraPages( $inputPages, array &$extraPages ) {
		$pages = $this->commentStreamsHandler->getExtraExportPages( $inputPages );
		foreach ( $pages as $page ) {
			$extraPages[] = $page;
		}
	}

	/**
	 * This hook is called at the end of XmlDumpWriter::openPage, to allow
	 * extra metadata to be added.
	 * For comments, saves the associated page name and the comment title.
	 * For replies, saves the parent comment page name.
	 * Does not save votes, as users may be different between the source and target wiki.
	 *
	 * @param XmlDumpWriter $writer
	 * @param string &$out Output string
	 * @param stdClass $row Database row for the page
	 * @param Title $title Title of the page
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onXmlDumpWriterOpenPage( $writer, &$out, $row, $title ) {
		if ( !( $this->commentStreamsStore instanceof NamespacePageStore ) ) {
			return;
		}
		if ( $title->getNamespace() == NS_COMMENTSTREAMS ) {
			$values = [];
			$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
			$comment = $this->commentStreamsStore->getComment( $wikiPage->getId() );
			if ( $comment ) {
				$metadataTag = 'CommentMetadata';

				$associatedTitle = $comment->getAssociatedPage();
				if ( $associatedTitle ) {
					$values['associatedPageName'] = $writer::canonicalTitle(
						Title::castFromPageIdentity( $associatedTitle )
					);
					$values['commentTitle'] = $comment->getTitle();
					$values['blockName'] = $comment->getBlockName();
				}
			} else {
				$reply = $this->commentStreamsStore->getReply( $wikiPage->getId() );
				if ( $reply ) {
					$metadataTag = 'ReplyMetadata';
					$parentCommentTitle = Title::newFromId( $reply->getParent()->getId() );
					if ( $parentCommentTitle ) {
						$values['parentCommentPageName'] = $writer::canonicalTitle( $parentCommentTitle );
					}
				} else {
					return false;
				}
			}
			if ( $values != [] ) {
				$out .= '    ' . Xml::openElement( $metadataTag ) . "\n";
				foreach ( $values as $key => $value ) {
					if ( $value !== null && $value !== "" ) {
						$out .= '      ' . Xml::element( $key, null, $value ) . "\n";
					}
				}
				$out .= '    ' . Xml::closeElement( $metadataTag ) . "\n";
			}
		}
	}

	/**
	 * This hook is called when parsing an XML tag in a page.
	 *
	 * @param WikiImporter $wikiImporter
	 * @param array &$pageInfo Array of information
	 * @return bool|void True or no return value to continue, or false to stop further
	 *	 processing of the tag
	 */
	public function onImportHandlePageXMLTag( $wikiImporter, &$pageInfo ) {
		if ( !( $this->commentStreamsStore instanceof NamespacePageStore ) ) {
			return;
		}
		$reader = $wikiImporter->getReader();
		$metadataType = $reader->name;
		if ( $metadataType === 'CommentMetadata' ) {
			$fields = [
				'associatedPageName',
				'commentTitle',
				'blockName'
			];
		} elseif ( $metadataType === 'ReplyMetadata' ) {
			$fields = [
				'parentCommentPageName'
			];
		} else {
			return;
		}

		$pageInfo[$metadataType] = [];
		while ( $reader->read() ) {
			if ( $reader->nodeType == XMLReader::END_ELEMENT && $reader->name === $metadataType ) {
				break;
			}
			if ( in_array( $reader->name, $fields ) ) {
				$pageInfo[$metadataType][$reader->name] = $wikiImporter->nodeContents();
			}
		}
	}

	/**
	 * This hook is called when a page import is completed.
	 *
	 * @param Title $title Title under which the revisions were imported
	 * @param ForeignTitle $foreignTitle ForeignTitle object based on data provided by the XML file
	 * @param int $revCount Number of revisions in the XML file
	 * @param int $sRevCount Number of successfully imported revisions
	 * @param array $pageInfo Associative array of page information
	 * @return void True or no return value to continue or false to abort
	 * @throws MWException
	 */
	public function onAfterImportPage(
		$title, $foreignTitle, $revCount, $sRevCount, $pageInfo
	) {
		if ( $this->commentStreamsStore instanceof TalkPageStore ) {
			if ( $title->isTalkPage() ) {
				$this->commentStreamsStore->updateAssociationIndex( $title );
			}
		}
		if ( $this->commentStreamsStore instanceof NamespacePageStore ) {
			$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
			if ( isset( $pageInfo['CommentMetadata'] ) ) {
				$info = $pageInfo['CommentMetadata'];
				$associatedPageName = $info['associatedPageName'];
				$associatedTitle = Title::newFromText( $associatedPageName );
				$associatedPage = $this->wikiPageFactory->newFromTitle( $associatedTitle );
				if ( !$associatedTitle->exists() ) {
					$associatedId = $this->commentStreamsStore->createEmptyPage( $associatedPage, $user );
				} else {
					$associatedId = $associatedTitle->getId();
				}
				$blockName = $info['blockName'] ?? null;
				if ( $associatedId ) {
					$this->commentStreamsStore->upsertCommentMetadata(
						$title->getId(),
						$associatedId,
						$info['commentTitle'],
						$blockName
					);
				}
			} elseif ( isset( $pageInfo['ReplyMetadata'] ) ) {
				$info = $pageInfo['ReplyMetadata'];
				$commentPageName = $info['parentCommentPageName'];
				$commentTitle = Title::newFromText( $commentPageName );
				if ( !$commentTitle->exists() ) {
					$commentPage = $this->wikiPageFactory->newFromTitle( $commentTitle );
					$commentId = $this->commentStreamsStore->createEmptyPage( $commentPage, $user );
				} else {
					$commentId = $commentTitle->getId();
				}
				if ( $commentId ) {
					$this->commentStreamsStore->upsertReplyMetadata(
						$title->getId(),
						$commentId
					);
				}
			}
		}
	}
}
