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
use MediaWiki\Linker\LinkRenderer;
use MWException;
use User;
use Wikimedia\Assert\Assert;
use WikiPage;

class CommentStreamsFactory {
	/**
	 * @var \MediaWiki\Config\ServiceOptions|\Config
	 */
	private $options;

	/**
	 * @var CommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * @var CommentStreamsEchoInterface
	 */
	private $echoInterface;

	/**
	 * @var CommentStreamsSMWInterface
	 */
	private $smwInterface;

	/**
	 * @var CommentStreamsSocialProfileInterface
	 */
	private $socialProfileInterface;

	/**
	 * @var LinkRenderer
	 */
	private $linkRenderer;

	/**
	 * @param \MediaWiki\Config\ServiceOptions|\Config $options
	 * @param CommentStreamsStore $commentStreamsStore
	 * @param CommentStreamsEchoInterface $echoInterface
	 * @param CommentStreamsSMWInterface $smwInterface
	 * @param CommentStreamsSocialProfileInterface $socialProfileInterface
	 * @param LinkRenderer $linkRenderer
	 */
	public function __construct(
		$options,
		CommentStreamsStore $commentStreamsStore,
		CommentStreamsEchoInterface $echoInterface,
		CommentStreamsSMWInterface $smwInterface,
		CommentStreamsSocialProfileInterface $socialProfileInterface,
		LinkRenderer $linkRenderer
	) {
		$this->options = $options;
		$this->commentStreamsStore = $commentStreamsStore;
		$this->echoInterface = $echoInterface;
		$this->smwInterface = $smwInterface;
		$this->socialProfileInterface = $socialProfileInterface;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * create a new Comment object from existing wiki page
	 *
	 * @param WikiPage $wikipage WikiPage object corresponding to comment page
	 * @return Comment|null the newly created comment or null if there was an
	 * error
	 * @throws MWException
	 * @throws ConfigException
	 */
	public function newFromWikiPage( WikiPage $wikipage ) : ?Comment {
		if ( $wikipage->getTitle()->getNamespace() !== NS_COMMENTSTREAMS || !$wikipage->exists() ) {
			return null;
		}

		$result = $this->commentStreamsStore->getComment( $wikipage->getId() );
		if ( $result === null ) {
			return null;
		}
		$comment_title = $result['comment_title'];
		$wikitext = $this->commentStreamsStore->getWikiText( $wikipage, $comment_title );
		return new Comment(
			$this->options,
			$this->commentStreamsStore,
			$this->echoInterface,
			$this->smwInterface,
			$this->socialProfileInterface,
			$this->linkRenderer,
			$wikipage,
			$result['comment_block_id'],
			$result['assoc_page_id'],
			$result['parent_page_id'],
			$comment_title,
			$wikitext
		);
	}

	/**
	 * create a new Comment object from values and save to database
	 * NOTE: since only head comments can contain a comment title, either
	 * $comment_title or $parent_page_id must be non null, but not both
	 *
	 * @param ?string $comment_block_id unique id to identify comment block in a page
	 * @param int $assoc_page_id page ID for the wiki page this comment is on
	 * @param ?int $parent_page_id page ID for the wiki page this comment is in
	 * reply to or null
	 * @param ?string $comment_title string title of comment
	 * @param string $wikitext the wikitext to add
	 * @param User $user the user
	 * @return Comment|null new comment object or null if there was a problem
	 * creating it
	 * @throws MWException
	 * @throws ConfigException
	 */
	public function newFromValues(
		?string $comment_block_id,
		int $assoc_page_id,
		?int $parent_page_id,
		?string $comment_title,
		string $wikitext,
		User $user
	) : ?Comment {
		Assert::parameter(
			( $comment_title === null && $parent_page_id !== null ) ||
			( $comment_title !== null && $parent_page_id === null ),
			'$comment_title',
			'must be null if parent page ID is non-null or non-null if parent page ID is null'
		);

		$wikipage = $this->commentStreamsStore->insertComment(
			$user,
			$wikitext,
			$comment_block_id,
			$assoc_page_id,
			$parent_page_id,
			$comment_title
		);

		if ( !$wikipage ) {
			return null;
		}

		$comment = new Comment(
			$this->options,
			$this->commentStreamsStore,
			$this->echoInterface,
			$this->smwInterface,
			$this->socialProfileInterface,
			$this->linkRenderer,
			$wikipage,
			$comment_block_id,
			$assoc_page_id,
			$parent_page_id,
			$comment_title,
			$wikitext
		);

		$this->smwInterface->update( $wikipage->getTitle() );

		if ( $parent_page_id === null ) {
			$this->commentStreamsStore->watch( $wikipage->getId(), $user->getId() );
		} else {
			$this->commentStreamsStore->watch( $parent_page_id, $user->getId() );
		}

		return $comment;
	}
}
