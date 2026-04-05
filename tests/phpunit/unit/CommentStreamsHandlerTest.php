<?php

namespace MediaWiki\Extension\CommentStreams\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CommentStreams\CommentSerializer;
use MediaWiki\Extension\CommentStreams\CommentStreamsHandler;
use MediaWiki\Extension\CommentStreams\ICommentStreamsStore;
use MediaWiki\Extension\CommentStreams\NotifierInterface;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\NamespaceInfo;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CommentStreams\CommentStreamsHandler
 */
class CommentStreamsHandlerTest extends MediaWikiUnitTestCase {

	public function testGetTitleToShowCommentsFor_NamespacesDisabled() {
		$handler = TestingAccessWrapper::newFromObject( new CommentStreamsHandler(
			new ServiceOptions(
				CommentStreamsHandler::CONSTRUCTOR_OPTIONS,
				[ 'CommentStreamsExportCommentsAutomatically' => true ]
			),
			$this->createNoOpMock( ICommentStreamsStore::class ),
			$this->createNoOpMock( NotifierInterface::class ),
			$this->createNoOpMock( PermissionManager::class ),
			$this->createNoOpMock( CommentSerializer::class ),
			$this->createNoOpMock( NamespaceInfo::class ),
		) );
		/** @var CommentStreamsHandler $handler */

		$outputPage = $this->createMock( OutputPage::class );
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getActionName' )->willReturn( 'view' );
		$outputPage->method( 'getContext' )->willReturn( $context );
		$outputPage->method( 'getConfig' )->willReturn( new HashConfig( [
			// Not using the NAMESPACES_DISABLED constant here to make sure that the value -1
			// will always work
			'CommentStreamsAllowedNamespaces' => -1,
		] ) );

		$parserOutput = $this->createMock( ParserOutput::class );
		$parserOutput->expects( $this->once() )
			->method( 'getExtensionData' )
			->with( CommentStreamsHandler::COMMENTS_DATA_KEY )
			->willReturn( null );

		$this->assertNull( $handler->getTitleToShowCommentsFor( $outputPage, $parserOutput ) );
	}

}
