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

use FatalError;
use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\PageProps;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MWException;
use ParserFactory;
use RepoGroup;
use WikiPage;

class Reply extends AbstractComment {
	/**
	 * @var CommentStreamsStore
	 */
	private $commentStreamsStore;

	/**
	 * page ID for the wiki page this comment is in reply to or null
	 * @var ?int
	 */
	private $parentCommentPageId;

	/**
	 * Do not instantiate directly. Use CommentStreamsFactory instead.
	 * @param CommentStreamsStore $commentStreamsStore
	 * @param SMWInterface $smwInterface
	 * @param SocialProfileInterface $socialProfileInterface
	 * @param LinkRenderer $linkRenderer
	 * @param RepoGroup $repoGroup
	 * @param RevisionStore $revisionStore
	 * @param ParserFactory $parserFactory
	 * @param UserFactory $userFactory
	 * @param PageProps $pageProps
	 * @param WikiPageFactory $wikiPageFactory
	 * @param string $timeFormat
	 * @param ?string $userAvatarPropertyName
	 * @param ?string $userRealNamePropertyName
	 * @param WikiPage $wikiPage
	 * @param int $parentCommentPageId
	 * @param string $wikitext
	 */
	public function __construct(
		CommentStreamsStore $commentStreamsStore,
		SMWInterface $smwInterface,
		SocialProfileInterface $socialProfileInterface,
		LinkRenderer $linkRenderer,
		RepoGroup $repoGroup,
		RevisionStore $revisionStore,
		ParserFactory $parserFactory,
		UserFactory $userFactory,
		PageProps $pageProps,
		WikiPageFactory $wikiPageFactory,
		string $timeFormat,
		?string $userAvatarPropertyName,
		?string $userRealNamePropertyName,
		WikiPage $wikiPage,
		int $parentCommentPageId,
		string $wikitext
	) {
		parent::__construct(
			$smwInterface,
			$socialProfileInterface,
			$linkRenderer,
			$repoGroup,
			$revisionStore,
			$parserFactory,
			$userFactory,
			$pageProps,
			$wikiPageFactory,
			$timeFormat,
			$userAvatarPropertyName,
			$userRealNamePropertyName,
			$wikiPage,
			$wikitext
		);
		$this->commentStreamsStore = $commentStreamsStore;
		$this->parentCommentPageId = $parentCommentPageId;
	}

	/**
	 * @return int page ID for the wiki page this comment is in reply to
	 */
	public function getParentCommentPageId(): int {
		return $this->parentCommentPageId;
	}

	/**
	 * @param IContextSource $context
	 * @return array get comment data in array suitable for JSON
	 */
	public function getJSON( IContextSource $context ): array {
		$user = $context->getUser();

		return [
			'pageid' => $this->wikiPage->getId(),
			'parentid' => $this->parentCommentPageId,
			'wikitext' => htmlspecialchars( $this->wikitext ),
			'html' => $this->getHTML( $context ),
			'username' => $this->getUsername(),
			'userdisplayname' => $this->getUserDisplayName(),
			'avatar' => $this->avatar,
			'moderated' => $this->isLastEditModerated() ? "moderated" : null,
			'created' => $this->getCreationDate( $user ),
			'created_timestamp' => $this->creationTimestamp->format( "U" ),
			'modified' => $this->getModificationDate( $user )
		];
	}

	/**
	 * update comment in database
	 *
	 * @param string $wikitext the wikitext to add
	 * @param User $user the author of the edit
	 * @return bool true if successful
	 * @throws MWException
	 */
	public function update(
		string $wikitext,
		User $user
	): bool {
		$this->commentStreamsStore->updateReply(
			$this->wikiPage,
			$wikitext,
			$user
		);
		$this->wikitext = $wikitext;
		$this->modificationTimestamp = null;
		$wikiPage = $this->wikiPageFactory->newFromID( $this->wikiPage->getId(), WikiPage::READ_LATEST );
		if ( $wikiPage ) {
			$this->wikiPage = $wikiPage;
		}
		return true;
	}

	/**
	 * delete comment from database
	 *
	 * @param User $deleter
	 * @return bool true if successful
	 * @throws FatalError
	 * @throws MWException
	 */
	public function delete( User $deleter ): bool {
		return $this->commentStreamsStore->deleteReply( $this->wikiPage, $deleter );
	}
}
