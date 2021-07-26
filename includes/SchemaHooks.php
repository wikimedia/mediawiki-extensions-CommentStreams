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

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {
	/**
	 * Updates database schema.
	 *
	 * @param DatabaseUpdater $updater database updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql/';
		$updater->addExtensionTable( 'cs_comment_data', $dir . 'commentData.sql' );
		$updater->addExtensionTable( 'cs_votes', $dir . 'votes.sql' );
		$updater->addExtensionTable( 'cs_watchlist', $dir . 'watch.sql' );
		$updater->modifyExtensionField( 'cs_comment_data', 'page_id',
			$dir . 'updateFieldNames.sql' );
		$updater->dropExtensionIndex( 'cs_comment_data', 'assoc_page_id',
			$dir . 'dropForeignKey1.sql' );
		$updater->dropExtensionIndex( 'cs_comment_data', 'cst_assoc_page_id',
			$dir . 'dropForeignKey2.sql' );
		$updater->addExtensionField( 'cs_comment_data', 'cst_id',
			$dir . 'addCommentId.sql' );
		$updater->modifyExtensionField( 'cs_comment_data', 'cst_id',
			$dir . 'cstIdDefault.sql' );
		$updater->addPostDatabaseUpdateMaintenance( 'NullDefaultCommentBlock' );
	}
}
