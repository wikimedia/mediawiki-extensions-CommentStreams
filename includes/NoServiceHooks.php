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
use MediaWiki\Hook\CanonicalNamespacesHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class NoServiceHooks implements
	CanonicalNamespacesHook,
	LoadExtensionSchemaUpdatesHook
{
	/**
	 * Adds CommentStreams namespaces.
	 *
	 * @param string[] &$namespaces Array of namespace numbers with corresponding canonical names
	 */
	public function onCanonicalNamespaces( &$namespaces ) {
		$namespaces[NS_COMMENTSTREAMS] = 'CommentStreams';
		$namespaces[NS_COMMENTSTREAMS_TALK] = 'CommentStreams_Talk';
	}

	/**
	 * Updates database schema.
	 *
	 * @param DatabaseUpdater $updater database updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql/' . $updater->getDB()->getType();
		$updater->addExtensionTable( 'cs_comments', $dir . '/cs_comments.sql' );
		$updater->addExtensionTable( 'cs_replies', $dir . '/cs_replies.sql' );
		$updater->addExtensionTable( 'cs_votes', $dir . '/cs_votes.sql' );
		$updater->addExtensionTable( 'cs_watchlist', $dir . '/cs_watchlist.sql' );
		$updater->addExtensionTable( 'cs_associated_pages', $dir . '/cs_associated_pages.sql' );

		if ( $updater->fieldExists( 'cs_watchlist', 'cst_wl_page_id' ) ) {
			$updater->modifyExtensionTable( 'cs_watchlist', $dir . '/cs_watchlist_comment_id_patch.sql' );
		}
		if ( $updater->fieldExists( 'cs_votes', 'cst_v_page_id' ) ) {
			$updater->modifyExtensionTable( 'cs_votes', $dir . '/cs_votes_comment_id_patch.sql' );
		}
		$updater->addPostDatabaseUpdateMaintenance( \MigrateToAbstractSchema::class );
	}
}
