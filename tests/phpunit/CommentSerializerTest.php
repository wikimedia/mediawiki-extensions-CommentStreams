<?php

namespace MediaWiki\Extension\CommentStreams\Tests;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommentStreams\CommentSerializer;
use MediaWiki\Extension\CommentStreams\Store\TalkPageStore;

/**
 * @covers \MediaWiki\Extension\CommentStreams\CommentSerializer
 * @group Database
 */
class CommentSerializerTest extends \MediaWikiIntegrationTestCase {

	/** @var array */
	protected array $pageData;

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$this->pageData = $this->insertPage( 'DummyPage', 'Dummy content' );
	}

	/**
	 * @covers \MediaWiki\Extension\CommentStreams\CommentSerializer::serializeComment
	 * @covers \MediaWiki\Extension\CommentStreams\CommentSerializer::serializeComment
	 * @return void
	 */
	public function testSerialization() {
		/** @var TalkPageStore $store */
		$store = $this->getServiceContainer()->getService( 'CommentStreamsStore' );
		$comment = $store->insertComment(
			$this->getTestSysop()->getUser(), 'Foo', $this->pageData['id'], 'Bar', null
		);
		$reply = $store->insertReply( $this->getTestSysop()->getUser(), 'Foo', $comment );

		/** @var CommentSerializer $serializer */
		$serializer = $this->getServiceContainer()->getService( 'CommentStreamsSerializer' );
		$serializedComment = $serializer->serializeComment( $comment, RequestContext::getMain() );
		$serializedReply = $serializer->serializeReply( $reply, RequestContext::getMain() );

		$this->assertArrayHasKey( 'avatar', $serializedComment );
		$this->assertArrayHasKey( 'created', $serializedComment );
		$this->assertArrayHasKey( 'html', $serializedComment );
		unset( $serializedComment['avatar'] );
		unset( $serializedComment['created'] );
		unset( $serializedComment['html'] );
		$votingEnabled = $this->getServiceContainer()->getMainConfig()->get( 'CommentStreamsEnableVoting' );

		$expected = [
			'id' => $comment->getId(),
			'commentblockname' => $comment->getBlockName(),
			'associatedid' => $comment->getAssociatedPage()->getId(),
			'commenttitle' => $comment->getTitle(),
			'wikitext' => $store->getWikitext( $comment ),
			'username' => $comment->getAuthor()->getName(),
			'numreplies' => 1,
			'userdisplayname' => $this->getServiceContainer()->getUserFactory()->newFromUserIdentity(
				$comment->getAuthor()
			)->getRealName(),
			'moderated' => null,
			'created_timestamp' => $comment->getCreated()->getTimestamp(),
			'modified' => null,
			'useCustomDateFormat' => true,
			'watching' => 0,
		];
		if ( $votingEnabled ) {
			$expected['numupvotes'] = 0;
			$expected['numdownvotes'] = 0;
			$expected['vote'] = 0;
		}
		$this->assertSame( $expected, $serializedComment );

		$this->assertArrayHasKey( 'avatar', $serializedReply );
		$this->assertArrayHasKey( 'created', $serializedReply );
		$this->assertArrayHasKey( 'html', $serializedReply );
		unset( $serializedReply['avatar'] );
		unset( $serializedReply['created'] );
		unset( $serializedReply['html'] );
		$this->assertSame( [
			'id' => $reply->getId(),
			'parentid' => $comment->getId(),
			'wikitext' => $store->getWikitext( $reply ),
			'username' => $reply->getAuthor()->getName(),
			'userdisplayname' => $this->getServiceContainer()->getUserFactory()->newFromUserIdentity(
				$reply->getAuthor()
			)->getRealName(),
			'moderated' => null,
			'created_timestamp' => $reply->getCreated()->getTimestamp(),
			'modified' => null,

		], $serializedReply );
	}
}
