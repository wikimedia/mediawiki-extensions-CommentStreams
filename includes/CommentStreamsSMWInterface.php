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
use ExtensionRegistry;
use JobQueueGroup;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\MediaWiki\Jobs\UpdateJob;
use SMW\PropertyRegistry;
use SMW\SemanticData;
use SMW\Store;
use SMW\StoreFactory;
use SMWDataItem;
use SMWDIBlob;
use SMWDINumber;
use Title;

class CommentStreamsSMWInterface {
	/**
	 * @var bool
	 */
	private $isLoaded;

	/**
	 * @param ExtensionRegistry $extensionRegistry
	 */
	public function __construct(
		ExtensionRegistry $extensionRegistry
	) {
		$this->isLoaded = $extensionRegistry->isLoaded( 'SemanticMediaWiki' );
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
		JobQueueGroup::singleton()->push( $job );
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
				if ( ( defined( 'SMWDataItem::TYPE_STRING' ) &&
						$value->getDIType() == SMWDataItem::TYPE_STRING ) ||
					$value->getDIType() == SMWDataItem::TYPE_BLOB ) {
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
	 * @throws ConfigException
	 */
	public static function initProperties() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'CommentStreams' );
		$enableVoting = (bool)$config->get( 'CommentStreamsEnableVoting' );
		$pr = PropertyRegistry::getInstance();
		$pr->registerProperty( '___CS_ASSOCPG', '_wpg', 'Comment on' );
		$pr->registerProperty( '___CS_REPLYTO', '_wpg', 'Reply to' );
		$pr->registerProperty( '___CS_TITLE', '_txt', 'Comment title of' );
		if ( $enableVoting === true ) {
			$pr->registerProperty( '___CS_UPVOTES', '_num', 'Comment up votes' );
			$pr->registerProperty( '___CS_DOWNVOTES', '_num', 'Comment down votes' );
			$pr->registerProperty( '___CS_VOTEDIFF', '_num', 'Comment vote diff' );
		}
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
	 * @noinspection PhpUnusedParameterInspection
	 * @throws ConfigException
	 */
	public static function updateData( Store $store, SemanticData $semanticData ): bool {
		$subject = $semanticData->getSubject();
		if ( $subject !== null && $subject->getTitle() !== null &&
			$subject->getTitle()->getNamespace() === NS_COMMENTSTREAMS ) {
			if ( defined( 'Title::READ_LATEST' ) ) {
				$page_id = $subject->getTitle()->getArticleID( Title::READ_LATEST );
			} else {
				$page_id = $subject->getTitle()->getArticleID( Title::GAID_FOR_UPDATE );
			}
			$wikipage = CommentStreamsUtils::newWikiPageFromId( $page_id );
			$commentFactory = MediaWikiServices::getInstance()->getService( 'CommentFactory' );
			$comment = $commentFactory->newFromWikiPage( $wikipage );

			if ( $comment === null ) {
				return true;
			}

			$assoc_page_id = $comment->getAssociatedId();
			if ( $assoc_page_id !== null ) {
				$assoc_wikipage = CommentStreamsUtils::newWikiPageFromId( $assoc_page_id );
				if ( $assoc_wikipage !== null ) {
					$propertyDI = new DIProperty( '___CS_ASSOCPG' );
					$dataItem =
						DIWikiPage::newFromTitle( $assoc_wikipage->getTitle() );
					$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
				}
			}

			$parent_page_id = $comment->getParentId();
			if ( $parent_page_id !== null ) {
				$parent_wikipage = CommentStreamsUtils::newWikiPageFromId( $parent_page_id );
				if ( $parent_wikipage !== null ) {
					$propertyDI = new DIProperty( '___CS_REPLYTO' );
					$dataItem =
						DIWikiPage::newFromTitle( $parent_wikipage->getTitle() );
					$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
				}
			}

			$commentTitle = $comment->getCommentTitle();
			if ( $commentTitle !== null ) {
				$propertyDI = new DIProperty( '___CS_TITLE' );
				$dataItem = new SMWDIBlob( $comment->getCommentTitle() );
				$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
			}

			$services = MediaWikiServices::getInstance();
			$commentStreamsStore = $services->getService( 'CommentStreamsStore' );
			$config = $services->getConfigFactory()->makeConfig( 'CommentStreams' );
			$enableVoting = (bool)$config->get( 'CommentStreamsEnableVoting' );
			if ( $enableVoting === true ) {
				$upvotes = $commentStreamsStore->getNumUpVotes( $comment->getId() );
				$propertyDI = new DIProperty( '___CS_UPVOTES' );
				$dataItem = new SMWDINumber( $upvotes );
				$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
				$downvotes = $commentStreamsStore->getNumDownVotes( $comment->getId() );
				$propertyDI = new DIProperty( '___CS_DOWNVOTES' );
				$dataItem = new SMWDINumber( $downvotes );
				$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
				$votediff = $upvotes - $downvotes;
				$propertyDI = new DIProperty( '___CS_VOTEDIFF' );
				$dataItem = new SMWDINumber( $votediff );
				$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
			}
		}
		return true;
	}

	/**
	 * Implements SMW::Settings::BeforeInitializationComplete callback.
	 * See https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/docs/technical/hooks/hook.settings.beforeinitializationcomplete.md
	 * Defines CommentStreams namespace constants.
	 *
	 * @param array &$configuration An array of the configuration options
	 */
	public static function onSMWInitialization( array &$configuration ) {
		$namespaceIndex = $GLOBALS['wgCommentStreamsNamespaceIndex'];
		$configuration['smwgNamespacesWithSemanticLinks'][$namespaceIndex] = true;
	}
}
