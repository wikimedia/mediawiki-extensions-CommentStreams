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
use MWException;
use SpecialPage;

class CommentStreamsAllComments extends SpecialPage {
	/**
	 * @var CommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var CommentFactory
	 */
	private $commentFactory;

	/**
	 * @param CommentStreamsStore $commentStreamsStore
	 * @param CommentFactory $commentFactory
	 */
	public function __construct(
		CommentStreamsStore $commentStreamsStore,
		CommentFactory $commentFactory
	) {
		parent::__construct( 'CommentStreamsAllComments' );
		$this->commentStreamsStore = $commentStreamsStore;
		$this->commentFactory = $commentFactory;
	}

	/**
	 * @inheritDoc
	 * @throws MWException
	 */
	public function execute( $subPage ) {
		$request = $this->getRequest();
		$this->setHeaders();
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

		$wikitext = '{| class="wikitable csall-wikitable"' . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'commentstreams-allcomments-label-page' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'commentstreams-allcomments-label-associatedpage' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'commentstreams-allcomments-label-commenttitle' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'commentstreams-allcomments-label-wikitext' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'commentstreams-allcomments-label-author' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'commentstreams-allcomments-label-lasteditor' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'commentstreams-allcomments-label-created' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'commentstreams-allcomments-label-lastedited' ) . PHP_EOL;
		$wikitext .=
			'!' . wfMessage( 'commentstreams-allcomments-label-blockid' ) . PHP_EOL;

		$index = 0;
		$more = false;
		foreach ( $pages as $page ) {
			if ( $index < $limit ) {
				$wikipage = CommentStreamsUtils::newWikiPageFromId( $page->page_id );
				if ( !$wikipage ) {
					continue;
				}
				$comment = $this->commentFactory->newFromWikiPage( $wikipage );
				if ( $comment !== null ) {
					$pagename = $comment->getTitle()->getPrefixedText();
					$associatedpageid = $comment->getAssociatedId();
					$associatedpage = CommentStreamsUtils::newWikiPageFromId( $associatedpageid );
					if ( $associatedpage !== null ) {
						$associatedpagename =
							'[[' . $associatedpage->getTitle()->getPrefixedText() . ']]';
						$author = $comment->getAuthor();
						if ( $author->getId() === 0 ) {
							$author =
								'<i>' . wfMessage( 'commentstreams-author-anonymous' ) . '</i>';
						} else {
							$author = $author->getName();
						}
						$modificationdate = $comment->getModificationDate();
						if ( $modificationdate === null ) {
							$lasteditor = '';
							$modificationdate = '';
						} else {
							$lasteditor = $comment->getLastEditor();
							if ( $lasteditor->getId() === 0 ) {
								$lasteditor =
									'<i>' . wfMessage( 'commentstreams-author-anonymous' ) .
									'</i>';
							} else {
								$lasteditor = $lasteditor->getName();
							}
						}
						$wikitext .= '|-' . PHP_EOL;
						$wikitext .= '|[[' . $pagename . ']]' . PHP_EOL;
						$wikitext .= '| ' . $associatedpagename . PHP_EOL;
						$wikitext .= '| ' . $comment->getCommentTitle() . PHP_EOL;
						$wikitext .= '| ' . htmlentities( $comment->getWikiText() ) . PHP_EOL;
						$wikitext .= '| ' . $author . PHP_EOL;
						$wikitext .= '| ' . $lasteditor . PHP_EOL;
						$wikitext .= '| ' . $comment->getCreationDate() . PHP_EOL;
						$wikitext .= '| ' . $modificationdate . PHP_EOL;
						$wikitext .= '| ' . $comment->getBlockId() . PHP_EOL;
						$index++;
					}
				}
			} else {
				$more = true;
			}
		}

		$wikitext .= '|}' . PHP_EOL;
		$this->getOutput()->addWikiTextAsInterface( $wikitext );

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
			$prevurl = $this->getFullTitle()->getFullURL( [ 'offset' => ( $offset - $limit ) ] );
			$html .= Html::openElement( 'a', [
					'href' => $prevurl,
					'class' => 'csall-button'
				] )
				. wfMessage( 'commentstreams-allcomments-button-previous' )
				. Html::closeElement( 'a' );
		}

		$html .= Html::closeElement( 'td' )
			. Html::openElement( 'td', [
				'style' => 'text-align:right;'
			] );

		if ( $more ) {
			$nexturl = $this->getFullTitle()->getFullURL( [ 'offset' => ( $offset + $limit ) ] );
			$html .= Html::openElement( 'a', [
					'href' => $nexturl,
					'class' => 'csall-button'
				] )
				. wfMessage( 'commentstreams-allcomments-button-next' )
				. Html::closeElement( 'a' );
		}

		$html .= Html::closeElement( 'td' )
			. Html::closeElement( 'tr' )
			. Html::closeElement( 'table' );
		$this->getOutput()->addHtml( $html );
	}
}
