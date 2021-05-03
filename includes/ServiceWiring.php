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

use ExtensionRegistry;
use MediaWiki\MediaWikiServices;

return [
	'CommentStreamsHandler' =>
		static function ( MediaWikiServices $services ) : CommentStreamsHandler {
			return new CommentStreamsHandler(
				$services->getService( 'CommentStreamsFactory' ),
				$services->getService( 'CommentStreamsStore' ),
				$services->getService( 'CommentStreamsEchoInterface' ),
				$services->getService( 'CommentStreamsSMWInterface' ),
				$services->getService( 'CommentStreamsSocialProfileInterface' )
			);
		},
	'CommentStreamsStore' =>
		static function ( MediaWikiServices $services ) : CommentStreamsStore {
			return new CommentStreamsStore(
				$services->getDBLoadBalancer()
			);
		},
	'CommentStreamsFactory' =>
		static function ( MediaWikiServices $services ) : CommentStreamsFactory {
			$config = $services->getConfigFactory()->makeConfig( 'CommentStreams' );
			if ( class_exists( '\MediaWiki\Config\ServiceOptions' ) ) {
				$config = new \MediaWiki\Config\ServiceOptions(
					Comment::CONSTRUCTOR_OPTIONS,
					$config
				);
			}
			return new CommentStreamsFactory(
				$config,
				$services->getService( 'CommentStreamsStore' ),
				$services->getService( 'CommentStreamsEchoInterface' ),
				$services->getService( 'CommentStreamsSMWInterface' ),
				$services->getService( 'CommentStreamsSocialProfileInterface' ),
				$services->getLinkRenderer()
			);
		},
	'CommentStreamsEchoInterface' =>
		static function ( MediaWikiServices $services ) : CommentStreamsEchoInterface {
			return new CommentStreamsEchoInterface(
				ExtensionRegistry::getInstance()
			);
		},
	'CommentStreamsSMWInterface' =>
		static function ( MediaWikiServices $services ) : CommentStreamsSMWInterface {
			return new CommentStreamsSMWInterface(
				ExtensionRegistry::getInstance()
			);
		},
	'CommentStreamsSocialProfileInterface' =>
		static function ( MediaWikiServices $services ) : CommentStreamsSocialProfileInterface {
			$config = $services->getConfigFactory()->makeConfig( 'CommentStreams' );
			if ( class_exists( '\MediaWiki\Config\ServiceOptions' ) ) {
				$config = new \MediaWiki\Config\ServiceOptions(
					CommentStreamsSocialProfileInterface::CONSTRUCTOR_OPTIONS,
					$config
				);
			}
			return new CommentStreamsSocialProfileInterface(
				$config
			);
		},
];
