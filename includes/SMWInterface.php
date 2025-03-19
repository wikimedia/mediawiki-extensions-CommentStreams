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

use JobQueueGroup;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\MediaWiki\Jobs\UpdateJob;
use SMW\PropertyRegistry;
use SMW\StoreFactory;
use SMWDataItem;

class SMWInterface {
	public const CONSTRUCTOR_OPTIONS = [
		'CommentStreamsEnableVoting'
	];

	/**
	 * @var bool
	 */
	private $isLoaded;

	/**
	 * @var JobQueueGroup
	 */
	private $jobQueueGroup;

	/**
	 * @var bool
	 */
	private $enableVoting;

	/**
	 * @param ServiceOptions $options
	 * @param ExtensionRegistry $extensionRegistry
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		ServiceOptions $options,
		ExtensionRegistry $extensionRegistry,
		JobQueueGroup $jobQueueGroup
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->enableVoting = (bool)$options->get( 'CommentStreamsEnableVoting' );
		$this->isLoaded = $extensionRegistry->isLoaded( 'SemanticMediaWiki' );
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * @return bool
	 */
	public function isLoaded(): bool {
		return $this->isLoaded;
	}

	/**
	 * @param Title $title
	 */
	public function update( Title $title ) {
		if ( !$this->isLoaded ) {
			return;
		}
		$job = new UpdateJob( $title, [] );
		$this->jobQueueGroup->push( $job );
	}

	/**
	 * return the value of a property on a user page
	 *
	 * @param UserIdentity $user the user
	 * @param string $propertyName the name of the property
	 * @return string|Title|null the value of the property
	 */
	public function getUserProperty( UserIdentity $user, string $propertyName ) {
		if ( !$this->isLoaded ) {
			return null;
		}
		$userpage = Title::makeTitle( NS_USER, $user->getName() );
		if ( $userpage->exists() ) {
			$subject = DIWikiPage::newFromTitle( $userpage );
			$store = StoreFactory::getStore();
			$data = $store->getSemanticData( $subject );
			$property = DIProperty::newFromUserLabel( $propertyName );
			$values = $data->getPropertyValues( $property );
			if ( count( $values ) > 0 ) {
				// this property should only have one value so pick the first one
				$value = $values[0];
				if ( $value->getDIType() == SMWDataItem::TYPE_BLOB ) {
					return $value->getString();
				} elseif ( $value->getDIType() == SMWDataItem::TYPE_WIKIPAGE ) {
					return $value->getTitle();
				}
			}
		}
		return null;
	}

	/**
	 * Initialize extra Semantic MediaWiki properties.
	 * This won't get called unless Semantic MediaWiki is installed.
	 * @param PropertyRegistry $propertyRegistry
	 */
	public function initProperties( PropertyRegistry $propertyRegistry ) {
		$propertyRegistry->registerProperty( '___CS_ASSOCPG', '_wpg', 'Comment on' );
		$propertyRegistry->registerProperty( '___CS_REPLYTO', '_wpg', 'Reply to' );
		$propertyRegistry->registerProperty( '___CS_TITLE', '_txt', 'Comment title of' );
		if ( $this->enableVoting ) {
			$propertyRegistry->registerProperty( '___CS_UPVOTES', '_num', 'Comment up votes' );
			$propertyRegistry->registerProperty( '___CS_DOWNVOTES', '_num', 'Comment down votes' );
			$propertyRegistry->registerProperty( '___CS_VOTEDIFF', '_num', 'Comment vote diff' );
		}
	}
}
