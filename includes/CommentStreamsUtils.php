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

use CommentStoreComment;
use Content;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\SlotRecord;
use MWException;
use Status;
use WikiPage;
use WikitextContent;

class CommentStreamsUtils {
	/**
	 * @param WikiPage $wikiPage
	 * @param Content $content
	 * @param Authority $authority
	 * @param int $flags
	 * @return Status
	 * @throws MWException
	 */
	public static function doEditContent(
		WikiPage $wikiPage,
		Content $content,
		Authority $authority,
		int $flags
	): Status {
		$updater = $wikiPage->newPageUpdater( $authority );
		$updater->setContent( SlotRecord::MAIN, $content );
		$summary = CommentStoreComment::newUnsavedComment( '' );
		$updater->saveRevision( $summary, $flags );
		return $updater->getStatus();
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param Authority $authority
	 * @return int|null
	 * @throws MWException
	 */
	public static function createEmptyPage(
		WikiPage $wikiPage,
		Authority $authority
	) {
		$result = self::doEditContent( $wikiPage, new WikitextContent( '' ), $authority, EDIT_NEW );
		if ( $result->isOK() ) {
			return $result->getValue()['revision-record']->getId();
		}
		return null;
	}
}
