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

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommentStreams\Log\CommentStreamsLogFactory;
use MediaWiki\Extension\CommentStreams\Notifier\NullNotifier;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use Psr\Log\LoggerInterface;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'CommentStreamsHandler' =>
		static function ( MediaWikiServices $services ): CommentStreamsHandler {
			return new CommentStreamsHandler(
				new ServiceOptions( CommentStreamsHandler::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
				$services->getService( 'CommentStreamsStore' ),
				$services->getService( 'CommentStreamsNotifierInterface' ),
				$services->getPermissionManager(),
				$services->getService( 'CommentStreamsSerializer' ),
				$services->getNamespaceInfo()
			);
		},
	'CommentStreamsLogFactory' =>
		static function ( MediaWikiServices $services ): CommentStreamsLogFactory {
			return new CommentStreamsLogFactory(
				new ServiceOptions(
					CommentStreamsLogFactory::CONSTRUCTOR_OPTIONS,
					$services->getMainConfig()
				),
			);
		},
	'CommentStreamsStore' =>
		static function ( MediaWikiServices $services ): ICommentStreamsStore {
			$attribute = ExtensionRegistry::getInstance()->getAttribute( 'CommentStreamsStore' );
			$selectedStoreModel = $services->getMainConfig()->get( 'CommentStreamsStoreModel' );
			if ( !$selectedStoreModel || !isset( $attribute[$selectedStoreModel] ) ) {
				throw new ConfigException( 'Invalid CommentStreamsStoreModel' );
			}
			$object = $services->getObjectFactory()->createObject( $attribute[$selectedStoreModel] );
			if ( !( $object instanceof ICommentStreamsStore ) ) {
				throw new \RuntimeException( 'Invalid CommentStreamsStoreModel object' );
			}
			return $object;
		},
	'CommentStreamsNotifierInterface' =>
		static function ( MediaWikiServices $services ): NotifierInterface {
			$config = $services->getMainConfig();
			$notifierName = $config->get( 'CommentStreamsNotifier' );
			$specs = ExtensionRegistry::getInstance()->getAttribute( 'CommentStreamsNotifier' );
			foreach ( $specs as $key => $spec ) {
				$notifier = $services->getObjectFactory()->createObject( $spec );
				if ( !( $notifier instanceof NotifierInterface ) ) {
					continue;
				}
				if ( $notifierName && $notifierName === $key ) {
					return $notifier;
				}
				if ( !$notifierName && $notifier->isLoaded() ) {
					return $notifier;
				}
			}
			return new NullNotifier();
		},
	'CommentStreamsSMWInterface' =>
		static function ( MediaWikiServices $services ): SMWInterface {
			return new SMWInterface(
				new ServiceOptions( SMWInterface::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
				ExtensionRegistry::getInstance(),
				$services->getJobQueueGroup()
			);
		},
	'CommentStreamsSocialProfileInterface' =>
		static function ( MediaWikiServices $services ): SocialProfileInterface {
			return new SocialProfileInterface(
				new ServiceOptions(
					SocialProfileInterface::CONSTRUCTOR_OPTIONS,
					$services->getMainConfig()
				)
			);
		},
	'CommentStreamsLogger' => static function ( MediaWikiServices $services ): LoggerInterface {
		return LoggerFactory::getInstance( 'CommentStreams' );
	},
	'CommentStreamsSerializer' => static function ( MediaWikiServices $services ): CommentSerializer {
		$optionKeys = [
			'CommentStreamsTimeFormat',
			'CommentStreamsUserAvatarPropertyName',
			'CommentStreamsUserRealNamePropertyName',
			'CommentStreamsEnableVoting'
		];
		$options = new ServiceOptions( $optionKeys, $services->getMainConfig() );
		$options->assertRequiredOptions( $optionKeys );
		return new CommentSerializer(
			$options,
			$services->getService( 'CommentStreamsStore' ),
			$services->getParserFactory(),
			$services->getService( 'CommentStreamsSMWInterface' ),
			$services->getLinkRenderer(),
			$services->getUserFactory(),
			$services->getPageProps(),
			$services->getService( 'CommentStreamsSocialProfileInterface' ),
			$services->getRepoGroup()
		);
	},
];

// @codeCoverageIgnoreEnd
