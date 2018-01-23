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

class CommentStreamsAllComments extends SpecialPage {

	public function __construct() {
		parent::__construct( 'CommentStreamsAllComments' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$request = $this->getRequest();
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.CommentStreamsAllComments' );

		$offset = $request->getText( 'offset', 0 );
		$limit = 20;
		$pages = self::getCommentPages( $limit + 1, $offset );

		if ( !$pages->valid() ) {
			$offset = 0;
			$pages = self::getCommentPages( $limit + 1, $offset );
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

		$index = 0;
		$more = false;
		foreach ( $pages as $page ) {
			if ( $index < $limit ) {
				$wikipage = WikiPage::newFromId( $page->page_id );
				$comment = Comment::newFromWikiPage( $wikipage );
				if ( !is_null( $comment ) ) {
					$pagename = $comment->getWikiPage()->getTitle()->getPrefixedText();
					$associatedpageid = $comment->getAssociatedId();
					$associatedpage = WikiPage::newFromId( $associatedpageid );
					if ( !is_null( $associatedpage ) ) {
						$associatedpagename =
							'[[' . $associatedpage->getTitle()->getPrefixedText() . ']]';
						$author = $comment->getUser();
						if ( $author->isAnon() ) {
							$author = '<i>' . wfMessage( 'commentstreams-author-anonymous' )
								. '</i>';
						} else {
							$author = $author->getName();
						}
						$modificationdate = $comment->getModificationDate();
						if ( is_null( $modificationdate ) ) {
							$lasteditor = '';
						} else {
							$lasteditor =
								User::newFromId( $wikipage->getRevision()->getUser() );
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
						$wikitext .= '| ' . $comment->getWikiText() . PHP_EOL;
						$wikitext .= '| ' . $author . PHP_EOL;
						$wikitext .= '| ' . $lasteditor . PHP_EOL;
						$wikitext .= '| ' . $comment->getCreationDate() . PHP_EOL;
						$wikitext .= '| ' . $modificationdate . PHP_EOL;
						$index ++;
					}
				}
			} else {
				$more = true;
			}
		}

		$wikitext .= '|}' . PHP_EOL;
		$this->getOutput()->addWikiText( $wikitext );

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
		$title = Title::newFromText( 'Special:' . __CLASS__ );
		$url = $title->getFullURL();

		$html = Html::openElement( 'table', [
				'class' => 'csall-navigationtable'
			] )
			. Html::openElement( 'tr' )
			. Html::openElement( 'td' );

		if ( $offset > 0 ) {
			$prevurl = $url . '?' . $paramname . '=' . ( $offset - $limit );
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
			$nexturl = $url . '?' . $paramname . '=' . ( $offset + $limit );
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

	private static function getCommentPages( $limit, $offset ) {
		$dbr = wfGetDB( DB_REPLICA );
		$pages = $dbr->select(
			[
				'cs_comment_data',
				'page',
				'revision'
			],
			[
				'page_id'
			],
			[
				'cst_page_id = page_id',
				'page_latest = rev_id'
			],
			__METHOD__,
			[
				'ORDER BY' => 'rev_timestamp DESC' ,
				'LIMIT' => $limit,
				'OFFSET' => $offset
			]
		);
		return $pages;
	}
}
