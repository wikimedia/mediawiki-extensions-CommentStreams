<?php

namespace MediaWiki\Extension\CommentStreams\Tests;

use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;

/**
 * Unit tests for the CommentStreams API module
 *
 * @group CommentStreams
 * @group API
 * @group Database
 */
class ApiCSPostCommentTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Check and create an empty page needed for the tests
		$titleText = "Main Page";
		$title = Title::newFromText( $titleText );
		if ( !$title->exists() ) {
			$this->editPage( $titleText, "", "Setup empty page", NS_MAIN );
			$this->assertNotNull( $title->getArticleID(), "Page creation failed" );
		}
	}

	/**
	 * @dataProvider provideComment
	 * @covers MediaWiki\Extension\CommentStreams\ApiCSPostComment
	 * @covers MediaWiki\Extension\CommentStreams\ApiCSQueryComment
	 */
	public function testCommentLifetime(
		$titleText,
		$summary,
		$body,
		$expectedError
	) {
		$title = Title::newFromText( $titleText );
		$pageid = $title->getArticleID();

		if ( $expectedError ) {
			$this->expectApiErrorCode( $expectedError );
		}
		$ret = $this->doApiRequestWithToken( [
			'action' => 'cspostcomment',
			'associatedid' => $pageid,
			'commenttitle' => $summary,
			'wikitext' => $body
		] );
		$this->assertArrayHasKey( 'cspostcomment', $ret[0], "Posted a comment" );

		$commentid = $ret[0]['cspostcomment'];
		$this->assertIsInt( $commentid, "Got a proper int that we can use as a comment id" );

		$ret = $this->doApiRequestWithToken( [
			'action' => 'csquerycomment',
			'pageid' => $commentid
		] );
		$this->assertArrayHasKey( 'csquerycomment', $ret[0], "Got info on posted comment" );
		$comment = $ret[0]['csquerycomment'];
		$this->assertEquals( $comment['associatedid'], $pageid, "Right pageid stored" );
		$this->assertEquals( $comment['commenttitle'], htmlspecialchars( $summary ), "Right comment title stored" );
		$this->assertEquals( $comment['wikitext'], htmlspecialchars( $body ), "Right comment body stored" );
	}

	public static function provideComment() {
		return [
			'unexist' => [
				"unexist",
				"Plain Title",
				"Body that doesn't matter.",
				'commentstreams-api-error-post-associatedpagedoesnotexist'
			],
			'anon' => [
				"Main Page",
				"\" quot ' #039 < lt > gt & amp",
				"Body with \" quot ' #039 < lt > gt & amp",
				false
			],
		];
	}

}
