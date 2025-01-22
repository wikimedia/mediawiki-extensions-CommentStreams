<?php

namespace MediaWiki\Extension\CommentStreams\Tests;

use MediaWiki\Extension\CommentStreams\Comment;
use MediaWiki\Extension\CommentStreams\Reply;
use MediaWiki\Extension\CommentStreams\Store\TalkPageStore;
use Wikimedia\Timestamp\TimestampException;

/**
 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore
 * @group Database
 */
class TalkPageStoreTest extends \MediaWikiIntegrationTestCase {

	/** @var array */
	protected array $pageData;

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$this->overrideConfigValue( 'CommentStreamsStoreModel', 'talk-page' );
		$this->pageData = $this->insertPage( 'DummyPage', 'Dummy content' );
	}

	/**
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::insertComment
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::insertReply
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::getNumReplies
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::getReplies
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::getReply
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::getComment
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::getWikitext
	 * @return void
	 * @throws TimestampException
	 */
	public function testCreation() {
		$store = $this->getServiceContainer()->getService( 'CommentStreamsStore' );
		$this->assertInstanceOf( TalkPageStore::class, $store );

		$comment = $store->insertComment(
			$this->getTestSysop()->getUser(), 'Foo', $this->pageData['id'], 'Bar', null
		);
		$this->assertInstanceOf( Comment::class, $comment );

		$comment = $store->getComment( $comment->getId() );
		$this->assertInstanceOf( Comment::class, $comment );
		$this->assertSame( $this->getTestSysop()->getUser()->getName(), $comment->getAuthor()->getName() );
		$this->assertSame( $this->pageData['title']->getNamespace(), $comment->getAssociatedPage()->getNamespace() );
		$this->assertSame( $this->pageData['title']->getDBkey(), $comment->getAssociatedPage()->getDBkey() );
		$this->assertSame( 'Bar', $comment->getTitle() );
		$this->assertSame( 'Foo', $store->getWikitext( $comment ) );
		$this->assertSame( null, $comment->getBlockName() );
		// Test if creation timestamp is within same minute
		$this->assertLessThanOrEqual( 60, time() - $comment->getCreated()->getTimestamp() );

		$reply = $store->insertReply( $this->getTestSysop()->getUser(), 'Foo', $comment );
		$this->assertInstanceOf( Reply::class, $reply );
		$this->assertSame( $comment->getId(), $reply->getParent()->getId() );
		$this->assertSame( $this->getTestSysop()->getUser()->getName(), $reply->getAuthor()->getName() );
		$this->assertSame( 'Foo', $store->getWikitext( $reply ) );

		$reply = $store->getReply( $reply->getId() );
		$this->assertInstanceOf( Reply::class, $reply );

		$replies = $store->getReplies( $comment );
		$this->assertCount( 1, $replies );
		$this->assertSame( $reply->getId(), $replies[0]->getId() );
		$this->assertSame( 1, $store->getNumReplies( $comment ) );
	}

	/**
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::updateComment
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::updateReply
	 * @return void
	 */
	public function testEditing() {
		/** @var TalkPageStore $store */
		$store = $this->getServiceContainer()->getService( 'CommentStreamsStore' );
		$comment = $store->insertComment(
			$this->getTestSysop()->getUser(), 'Foo', $this->pageData['id'], 'Bar', null
		);
		$res = $store->updateComment( $comment, 'Dummy', 'Test', $this->getTestSysop()->getUser() );
		$this->assertTrue( $res );

		$comment = $store->getComment( $comment->getId() );
		$this->assertSame( 'Dummy', $comment->getTitle() );
		$this->assertSame( 'Test', $store->getWikitext( $comment ) );
		$this->assertSame( $this->getTestSysop()->getUser()->getName(), $comment->getLastEditor()->getName() );

		$reply = $store->insertReply( $this->getTestSysop()->getUser(), 'Foo', $comment );
		$res = $store->updateReply( $reply, 'Test', $this->getTestSysop()->getUser() );
		$this->assertTrue( $res );

		$reply = $store->getReply( $reply->getId() );
		$this->assertSame( 'Test', $store->getWikitext( $reply ) );
	}

	/**
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::deleteComment
	 * @covers \MediaWiki\Extension\CommentStreams\Store\TalkPageStore::deleteReply
	 * @return void
	 */
	public function testDeletion() {
		/** @var TalkPageStore $store */
		$store = $this->getServiceContainer()->getService( 'CommentStreamsStore' );
		$comment = $store->insertComment(
			$this->getTestSysop()->getUser(), 'Foo', $this->pageData['id'], 'Bar', null
		);
		$reply = $store->insertReply( $this->getTestSysop()->getUser(), 'Foo', $comment );
		$store->insertReply( $this->getTestSysop()->getUser(), 'Bar', $comment );
		$this->assertSame( 2, $store->getNumReplies( $comment ) );
		$res = $store->deleteReply( $reply, $this->getTestSysop()->getUser() );
		$this->assertTrue( $res );
		$this->assertSame( 1, $store->getNumReplies( $comment ) );

		$res = $store->deleteComment( $comment, $this->getTestSysop()->getUser() );
		$this->assertTrue( $res );

		$this->assertNull( $store->getComment( $comment->getId() ) );
	}
}
