<?php

/*
 * Copyright (c) 2017 The MITRE Corporation
 *
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
use MediaWiki\MediaWikiServices;
use MWException;
use SpecialPage;

class CommentStreamsAllComments extends SpecialPage {
	public function __construct() {
		parent::__construct( 'CommentStreamsAllComments' );
	}

	/**
	 * @inheritDoc
	 * @throws MWException
	 */
	public function execute( $subPage ) {
		$request = $this->getRequest();
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.CommentStreamsAllComments' );

		$commentStreamsStore =
			MediaWikiServices::getInstance()->getService( 'CommentStreamsStore' );

		$offset = $request->getText( 'offset', '0' );
		$limit = 20;
		$pages = $commentStreamsStore->getCommentPages( $limit + 1, $offset );

		if ( !$pages->valid() ) {
			$offset = 0;
			$pages = $commentStreamsStore->getCommentPages( $limit + 1, $offset );
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

		$commentStreamsFactory =
			MediaWikiServices::getInstance()->getService( 'CommentStreamsFactory' );

		$index = 0;
		$more = false;
		foreach ( $pages as $page ) {
			if ( $index < $limit ) {
				$wikipage = CommentStreamsUtils::newWikiPageFromId( $page->page_id );
				$comment = $commentStreamsFactory->newFromWikiPage( $wikipage );
				if ( $comment !== null ) {
					$pagename = $comment->getTitle()->getPrefixedText();
					$associatedpageid = $comment->getAssociatedId();
					$associatedpage = CommentStreamsUtils::newWikiPageFromId( $associatedpageid );
					if ( $associatedpage !== null ) {
						$associatedpagename =
							'[[' . $associatedpage->getTitle()->getPrefixedText() . ']]';
						$author = $comment->getAuthor();
						if ( $author->isAnon() ) {
							$author = '<i>' . wfMessage( 'commentstreams-author-anonymous' )
								. '</i>';
						} else {
							$author = $author->getName();
						}
						$modificationdate = $comment->getModificationDate();
						if ( $modificationdate === null ) {
							$lasteditor = '';
							$modificationdate = '';
						} else {
							$lasteditor = $comment->getLastEditor();
							if ( $lasteditor->isAnon() ) {
								$lasteditor = '<i>' .
									wfMessage( 'commentstreams-author-anonymous' ) . '</i>';
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
						$index++;
					}
				}
			} else {
				$more = true;
			}
		}

		$wikitext .= '|}' . PHP_EOL;
		CommentStreamsUtils::addWikiTextToOutputPage( $wikitext, $this->getOutput() );

		if ( $offset > 0 || $more ) {
			$this->addTableNavigation( $offset, $more, $limit, 'offset' );
		}
	}

	private function displayMessage( $message ) {
		$html = Html::openElement( 'p', [
				'class' => 'csall-message'
			] )
			. $message
			. Html::closeElement( 'p' );
		$this->getOutput()->addHtml( $html );
	}

	private function addTableNavigation( $offset, $more, $limit, $paramname ) {
		$html = Html::openElement( 'table', [
				'class' => 'csall-navigationtable'
			] )
			. Html::openElement( 'tr' )
			. Html::openElement( 'td' );

		if ( $offset > 0 ) {
			$prevurl = $this->getFullTitle()->getFullURL( [ $paramname => ( $offset - $limit ) ] );
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
			$nexturl = $this->getFullTitle()->getFullURL( [ $paramname => ( $offset + $limit ) ] );
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
