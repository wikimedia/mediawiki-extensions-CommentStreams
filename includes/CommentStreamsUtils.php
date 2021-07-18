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

use Content;
use MediaWiki\MediaWikiServices;
use MWException;
use Status;
use Title;
use User;
use WikiPage;

class CommentStreamsUtils {
	/**
	 * @param int $id Article ID to load
	 * @param string|int $from One of the following values:
	 *        - "fromdb" or WikiPage::READ_NORMAL to select from a replica DB
	 *        - "fromdbmaster" or WikiPage::READ_LATEST to select from the master database
	 * @return WikiPage|null
	 */
	public static function newWikiPageFromId( int $id, $from = 'fromdb' ): ?WikiPage {
		if ( class_exists( '\MediaWiki\Page\WikiPageFactory' ) ) {
			// MW 1.36+
			return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $id, $from );
		}
		return WikiPage::newFromId( $id, $from );
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	public static function hasDeletedEdits( Title $title ): bool {
		if ( method_exists( $title, 'hasDeletedEdits' ) ) {
			// MW 1.36+
			return $title->hasDeletedEdits();
		}
		return $title->isDeletedQuick();
	}

	/**
	 * @param WikiPage $wikipage
	 * @param Content $content
	 * @param User $user
	 * @param int $flags
	 * @return Status
	 * @throws MWException
	 */
	public static function doEditContent(
		WikiPage $wikipage,
		Content $content,
		User $user,
		int $flags
	): Status {
		return $wikipage->doEditContent(
			$content,
			'',
			$flags,
			false,
			$user,
			null
		);
	}
}
