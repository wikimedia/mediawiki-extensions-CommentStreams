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

use ConfigException;
use MediaWiki\MediaWikiServices;
use SMW\SemanticData;
use SMW\Store;

class SMWHooks {
	/**
	 * Initialize extra Semantic MediaWiki properties.
	 * This won't get called unless Semantic MediaWiki is installed.
	 * @throws ConfigException
	 */
	public static function initProperties() {
		MediaWikiServices::getInstance()->get( 'CommentStreamsSMWInterface' )->initProperties();
	}

	/**
	 * Implements Semantic MediaWiki SMWStore::updateDataBefore callback.
	 * This won't get called unless Semantic MediaWiki is installed.
	 * If the comment has not been added to the database yet, which is indicated
	 * by a null associated page id, this function will return early, but it
	 * will be invoked again by an update job.
	 *
	 * @param Store $store semantic data store
	 * @param SemanticData $semanticData semantic data for page
	 * @return bool true to continue
	 * @throws ConfigException
	 */
	public static function updateData( Store $store, SemanticData $semanticData ): bool {
		return MediaWikiServices::getInstance()->get( 'CommentStreamsSMWInterface' )
			->updateData( $store, $semanticData );
	}

	/**
	 * Implements SMW::Settings::BeforeInitializationComplete callback.
	 * See https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/docs/technical/hooks/hook.settings.beforeinitializationcomplete.md
	 * Defines CommentStreams namespace constants.
	 *
	 * @param array &$configuration An array of the configuration options
	 */
	public static function onSMWInitialization( array &$configuration ) {
		MediaWikiServices::getInstance()->get( 'CommentStreamsSMWInterface' )->onSMWInitialization( $configuration );
	}
}
