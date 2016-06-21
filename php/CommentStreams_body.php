<?php
/*
 * Copyright (c) 2016 The MITRE Corporation
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

class CommentStreams {

	static function onRegistration() {
		global $wgCommentStreamsSMWinstalled;
		$wgCommentStreamsSMWinstalled = false;
		if ( defined( 'SMW_VERSION' ) ) {
			$wgCommentStreamsSMWinstalled = true;
		}
	}
	
	static function addCommentTableToDatabase(DatabaseUpdater $updater) {
		$updater->addExtensionTable( 'cs_comment_data', dirname(__FILE__) . '/sql/commentData.sql', true );
		$updater->addExtensionTable( 'cs_upvotes', dirname(__FILE__) . '/sql/upvotes.sql', true );
		$updater->addExtensionTable( 'cs_downvotes', dirname(__FILE__) . '/sql/downvotes.sql', true );
		$updater->addExtensionTable( 'cs_next_comment', dirname(__FILE__) . '/sql/nextComment.sql', true);

		return true;
	}

	static function addCommentStreamsNamespaces(array &$namespaces) {
		$namespaces[NS_COMMENTSTREAMS] = 'CommentStreams';
		$namespaces[NS_COMMENTSTREAMS+1] = 'CommentStreams_Talk';
	}
	
	static function onParserSetup( Parser $parser ) {
		$parser->setHook( 'no-comment-streams', 'CommentManager::hideCommentStreams' );
	}
}
