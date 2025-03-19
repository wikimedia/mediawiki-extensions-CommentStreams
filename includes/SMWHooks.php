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

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\PropertyRegistry;
use SMW\SemanticData;
use SMW\Store;
use SMWDIBlob;
use SMWDINumber;
use Wikimedia\Rdbms\IDBAccessObject;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
class SMWHooks {

	/** @var ICommentStreamsStore */
	private $commentStreamsStore;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var Config */
	private $config;

	/**
	 * @param ICommentStreamsStore $commentStreamsStore
	 * @param WikiPageFactory $wikiPageFactory
	 * @param Config $config
	 */
	public function __construct(
		ICommentStreamsStore $commentStreamsStore,
		WikiPageFactory $wikiPageFactory,
		Config $config,
	) {
		$this->commentStreamsStore = $commentStreamsStore;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->config = $config;
	}

	/**
	 * Initialize extra Semantic MediaWiki properties.
	 * This won't get called unless Semantic MediaWiki is installed.
	 * @param PropertyRegistry $propertyRegistry
	 */
	public function onSMW__Property__initProperties( PropertyRegistry $propertyRegistry ) {
		MediaWikiServices::getInstance()->get( 'CommentStreamsSMWInterface' )->initProperties( $propertyRegistry );
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
	public function onSMWStore__updateDataBefore( Store $store, SemanticData $semanticData ) {
		$subject = $semanticData->getSubject();
		if ( !$subject || !$subject->getTitle() || $subject->getTitle()->getNamespace() !== NS_COMMENTSTREAMS ) {
			return true;
		}

		$pageId = $subject->getTitle()->getArticleID( IDBAccessObject::READ_LATEST );

		$comment = $this->commentStreamsStore->getComment( $pageId );
		if ( !$comment ) {
			$reply = $this->commentStreamsStore->getReply( $pageId );
			if ( !$reply ) {
				return true;
			}

			$parentWikiPage = $this->wikiPageFactory->newFromID( $reply[ 'comment_page_id' ] );
			if ( $parentWikiPage ) {
				$propertyDI = new DIProperty( '___CS_REPLYTO' );
				$dataItem = DIWikiPage::newFromTitle( $parentWikiPage->getTitle() );
				$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
			}

			return true;
		}

		$assocWikiPage = $this->wikiPageFactory->newFromID(
			$comment->getAssociatedPage() ? $comment->getAssociatedPage()->getId() : 0
		);
		if ( $assocWikiPage ) {
			$propertyDI = new DIProperty( '___CS_ASSOCPG' );
			$dataItem = DIWikiPage::newFromTitle( $assocWikiPage->getTitle() );
			$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
		}

		$propertyDI = new DIProperty( '___CS_TITLE' );
		$dataItem = new SMWDIBlob( $comment->getTitle() );
		$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );

		if (
			$this->config->has( 'CommentStreamsEnableVoting' ) &&
			$this->config->get( 'CommentStreamsEnableVoting' )
		) {
			$upvotes = $this->commentStreamsStore->getNumUpVotes( $comment );
			$propertyDI = new DIProperty( '___CS_UPVOTES' );
			$dataItem = new SMWDINumber( $upvotes );
			$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
			$downvotes = $this->commentStreamsStore->getNumDownVotes( $comment );
			$propertyDI = new DIProperty( '___CS_DOWNVOTES' );
			$dataItem = new SMWDINumber( $downvotes );
			$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
			$votediff = $upvotes - $downvotes;
			$propertyDI = new DIProperty( '___CS_VOTEDIFF' );
			$dataItem = new SMWDINumber( $votediff );
			$semanticData->addPropertyObjectValue( $propertyDI, $dataItem );
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
	public static function onSMW__Settings__BeforeInitializationComplete( array &$configuration ) {
		$namespaceIndex = $GLOBALS['wgCommentStreamsNamespaceIndex'];
		$configuration['smwgNamespacesWithSemanticLinks'][$namespaceIndex] = true;
	}
}
