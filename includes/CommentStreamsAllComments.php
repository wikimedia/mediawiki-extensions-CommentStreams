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
use MediaWiki\Linker\LinkRenderer;
use OOUI\ButtonWidget;
use OOUI\IconWidget;
use SpecialPage;

class CommentStreamsAllComments extends SpecialPage {
	/**
	 * @var CommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var CommentStreamsFactory
	 */
	private $commentStreamsFactory;

	/**
	 * @var LinkRenderer
	 */
	private $linkRenderer;

	/**
	 * @param CommentStreamsStore $commentStreamsStore
	 * @param CommentStreamsFactory $commentStreamsFactory
	 * @param LinkRenderer $linkRenderer
	 */
	public function __construct(
		CommentStreamsStore $commentStreamsStore,
		CommentStreamsFactory $commentStreamsFactory,
		LinkRenderer $linkRenderer
	) {
		parent::__construct( 'CommentStreamsAllComments' );
		$this->commentStreamsStore = $commentStreamsStore;
		$this->commentStreamsFactory = $commentStreamsFactory;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		$request = $this->getRequest();
		$this->setHeaders();
		$this->getOutput()->enableOOUI();
		$this->getOutput()->addModuleStyles( [ 'oojs-ui.styles.icons-editing-core' ] );
		$this->getOutput()->addModuleStyles( [ 'oojs-ui.styles.icons-interactions' ] );
		$this->getOutput()->addModuleStyles( [ 'oojs-ui.styles.icons-movement' ] );
		$this->getOutput()->addModuleStyles( 'ext.CommentStreamsAllComments' );

		$offset = (int)$request->getText( 'offset', '0' );
		$limit = 20;
		$pages = $this->commentStreamsStore->getCommentPages( $limit + 1, $offset );

		if ( !$pages->valid() ) {
			$offset = 0;
			$pages = $this->commentStreamsStore->getCommentPages( $limit + 1, $offset );
			if ( !$pages->valid() ) {
				$this->displayMessage(
					wfMessage( 'commentstreams-allcomments-nocommentsfound' )
				);
				return;
			}
		}

		$html = Html::openElement( 'table', [
				'class' => 'wikitable csall-wikitable'
			] )
			. Html::openElement( 'tr' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-wikitext' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-commenttitle' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-reply' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-author' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-lasteditor' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-created' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-lastedited' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-page' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-associatedpage' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'commentstreams-allcomments-label-blockid' )
			. Html::closeElement( 'th' )
			. Html::closeElement( 'tr' );

		$index = 0;
		$more = false;
		foreach ( $pages as $page ) {
			if ( $index < $limit ) {
				$wikiPage = CommentStreamsUtils::newWikiPageFromId( $page->page_id );
				if ( !$wikiPage ) {
					continue;
				}
				$comment = $this->commentStreamsFactory->newCommentFromWikiPage( $wikiPage );
				if ( !$comment ) {
					$reply = $this->commentStreamsFactory->newReplyFromWikiPage( $wikiPage );
					if ( !$reply ) {
						continue;
					}
					$commentWikiPage = CommentStreamsUtils::newWikiPageFromId( $reply->getParentCommentPageId() );
					if ( !$commentWikiPage ) {
						continue;
					}
					$comment = $this->commentStreamsFactory->newCommentFromWikiPage( $commentWikiPage );
					if ( !$comment ) {
						continue;
					}
					$replyCheck = new IconWidget( [
						'icon' => 'check',
						'framed' => false
					] );
					$title = $reply->getTitle();
					$wikitext = htmlentities( $reply->getWikitext() );
					$author = $reply->getAuthor();
					$lastEditor = $reply->getLastEditor();
					$creationDate = $reply->getCreationDate();
					$modificationDate = $reply->getModificationDate();
				} else {
					$replyCheck = '';
					$title = $comment->getTitle();
					$wikitext = htmlentities( $comment->getWikitext() );
					$author = $comment->getAuthor();
					$lastEditor = $comment->getLastEditor();
					$creationDate = $comment->getCreationDate();
					$modificationDate = $comment->getModificationDate();
				}
				$linkButton = new ButtonWidget( [
					'icon' => 'link',
					'framed' => false,
					'href' => $title->getFullURL()
				] );
				$associatedPageId = $comment->getAssociatedId();
				$associatedWikiPage = CommentStreamsUtils::newWikiPageFromId( $associatedPageId );
				if ( $associatedWikiPage ) {
					$associatedPageLink = $this->linkRenderer->makeLink( $associatedWikiPage->getTitle() );
				} else {
					$associatedPageLink = '';
				}
				$commentTitle = htmlentities( $comment->getCommentTitle() );
				$commentBlockName = $comment->getBlockName();
				if ( $commentBlockName ) {
					$commentBlockName = htmlentities( $commentBlockName );
				}
				if ( $author->getId() === 0 ) {
					$author = '<i>' . wfMessage( 'commentstreams-author-anonymous' ) . '</i>';
				} else {
					$author = $author->getName();
				}
				if ( !$modificationDate ) {
					$lastEditor = '';
					$modificationDate = '';
				} else {
					if ( $lastEditor->getId() === 0 ) {
						$lastEditor = '<i>' . wfMessage( 'commentstreams-author-anonymous' ) . '</i>';
					} else {
						$lastEditor = $lastEditor->getName();
					}
				}
				$html .= Html::openElement( 'tr' )
					. Html::openElement( 'td' )
					. $wikitext
					. Html::closeElement( 'td' )
					. Html::openElement( 'td' )
					. $commentTitle
					. Html::closeElement( 'td' )
					. Html::openElement( 'td', [
						'style' => 'text-align:center;'
					] )
					. $replyCheck
					. Html::closeElement( 'td' )
					. Html::openElement( 'td' )
					. $author
					. Html::closeElement( 'td' )
					. Html::openElement( 'td' )
					. $lastEditor
					. Html::closeElement( 'td' )
					. Html::openElement( 'td' )
					. $creationDate
					. Html::closeElement( 'td' )
					. Html::openElement( 'td' )
					. $modificationDate
					. Html::closeElement( 'td' )
					. Html::openElement( 'td', [
						'style' => 'text-align:center;'
					] )
					. $linkButton
					. Html::closeElement( 'td' )
					. Html::openElement( 'td' )
					. $associatedPageLink
					. Html::closeElement( 'td' )
					. Html::openElement( 'td' )
					. $commentBlockName
					. Html::closeElement( 'td' )
					. Html::closeElement( 'tr' );
				$index++;
			} else {
				$more = true;
			}
		}

		$html .= Html::closeElement( 'table' );
		$this->getOutput()->addHtml( $html );

		if ( $offset > 0 || $more ) {
			$this->addTableNavigation( $offset, $more, $limit );
		}
	}

	/**
	 * @param string $message
	 */
	private function displayMessage( string $message ) {
		$html = Html::openElement( 'p', [
				'class' => 'csall-message'
			] )
			. $message
			. Html::closeElement( 'p' );
		$this->getOutput()->addHtml( $html );
	}

	/**
	 * @param int $offset
	 * @param bool $more
	 * @param int $limit
	 */
	private function addTableNavigation( int $offset, bool $more, int $limit ) {
		$html = Html::openElement( 'table', [
				'class' => 'csall-navigationtable'
			] )
			. Html::openElement( 'tr' )
			. Html::openElement( 'td' );

		if ( $offset > 0 ) {
			$prevUrl = $this->getFullTitle()->getFullURL( [ 'offset' => ( $offset - $limit ) ] );
			$html .= new ButtonWidget( [
				'icon' => 'previous',
				'href' => $prevUrl
			] );
		}

		$html .= Html::closeElement( 'td' )
			. Html::openElement( 'td', [
				'style' => 'text-align:right;'
			] );

		if ( $more ) {
			$nextUrl = $this->getFullTitle()->getFullURL( [ 'offset' => ( $offset + $limit ) ] );
			$html .= new ButtonWidget( [
				'icon' => 'next',
				'href' => $nextUrl
			] );
		}

		$html .= Html::closeElement( 'td' )
			. Html::closeElement( 'tr' )
			. Html::closeElement( 'table' );
		$this->getOutput()->addHtml( $html );
	}
}
