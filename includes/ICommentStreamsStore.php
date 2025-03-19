<?php
/**
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
 *
 * @author Cindy Cicalese
 */

namespace MediaWiki\Extension\CommentStreams;

use JsonSerializable;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

/**
 * Comment Streams database backend interface
 */
interface ICommentStreamsStore {

	/**
	 * @param int $id
	 * @return Comment|null
	 */
	public function getComment( int $id ): ?Comment;

	/**
	 * @param int $id
	 * @return ?Reply
	 */
	public function getReply( int $id ): ?Reply;

	/**
	 * @param string $action
	 * @param User $user
	 * @param AbstractComment $comment
	 * @return bool
	 */
	public function userCan( string $action, User $user, AbstractComment $comment ): bool;

	/**
	 * @param PageIdentity $page
	 * @return Comment[]
	 */
	public function getAssociatedComments( PageIdentity $page ): array;

	/**
	 * @param Comment $parent
	 * @return Reply[]
	 */
	public function getReplies( Comment $parent ): array;

	/**
	 * @param Comment $comment
	 * @return int
	 */
	public function getNumReplies( Comment $comment ): int;

	/**
	 * @param User $user
	 * @param string $wikitext
	 * @param int $assocPageId
	 * @param string $commentTitle
	 * @param ?string $commentBlockName
	 * @return ?Comment
	 */
	public function insertComment(
		User $user,
		string $wikitext,
		int $assocPageId,
		string $commentTitle,
		?string $commentBlockName
	): ?Comment;

	/**
	 * @param User $user
	 * @param string $wikitext
	 * @param Comment $parent
	 * @return ?Reply
	 */
	public function insertReply(
		User $user,
		string $wikitext,
		Comment $parent
	): ?Reply;

	/**
	 * @param Comment $comment
	 * @param string $commentTitle
	 * @param string $wikitext
	 * @param User $user
	 * @return bool
	 */
	public function updateComment(
		Comment $comment,
		string $commentTitle,
		string $wikitext,
		User $user
	): bool;

	/**
	 * @param Reply $reply
	 * @param string $wikitext
	 * @param User $user
	 * @return bool
	 */
	public function updateReply(
		Reply $reply,
		string $wikitext,
		User $user
	): bool;

	/**
	 * @param Comment $comment
	 * @param Authority $actor
	 * @return bool
	 */
	public function deleteComment( Comment $comment, Authority $actor ): bool;

	/**
	 * @param Reply $reply
	 * @param Authority $actor
	 * @return bool
	 */
	public function deleteReply( Reply $reply, Authority $actor ): bool;

	/**
	 * @param int $pageId
	 * @param int $assocPageId
	 * @param string $commentTitle
	 * @param string|null $blockName
	 */
	public function upsertCommentMetadata(
		int $pageId,
		int $assocPageId,
		string $commentTitle,
		?string $blockName
	): bool;

	/**
	 * @param int $pageId
	 * @param int $commentPageId
	 */
	public function upsertReplyMetadata(
		int $pageId,
		int $commentPageId
	);

	/**
	 * @param Comment $comment
	 * @param UserIdentity $user
	 * @return int -1, 0, or 1
	 */
	public function getVote( AbstractComment $comment, UserIdentity $user ): int;

	/**
	 * @param AbstractComment $comment
	 * @return int
	 */
	public function getNumUpVotes( AbstractComment $comment ): int;

	/**
	 * @param AbstractComment $comment
	 * @return int
	 */
	public function getNumDownVotes( AbstractComment $comment ): int;

	/**
	 * @param Comment $comment
	 * @param int $vote
	 * @param UserIdentity $user
	 * @return bool true for OK, false for error
	 */
	public function vote( AbstractComment $comment, int $vote, UserIdentity $user ): bool;

	/**
	 * @param Comment $comment
	 * @param UserIdentity $user
	 * @return bool true for OK, false for error
	 */
	public function watch( AbstractComment $comment, UserIdentity $user ): bool;

	/**
	 * @param Comment $comment
	 * @param UserIdentity $user
	 * @return bool true for OK, false for error
	 */
	public function unwatch( AbstractComment $comment, UserIdentity $user ): bool;

	/**
	 * @param Comment $comment
	 * @param UserIdentity $user
	 * @param int $fromdb DB_PRIMARY or DB_REPLICA
	 * @return bool database true for OK, false for error
	 */
	public function isWatching( AbstractComment $comment, UserIdentity $user, int $fromdb = DB_REPLICA ): bool;

	/**
	 * @param Comment $comment
	 * @return User[] array of users indexed by user ID
	 */
	public function getWatchers( AbstractComment $comment ): array;

	/**
	 * @param AbstractComment $comment
	 * @return string
	 */
	public function getWikitext( AbstractComment $comment ): string;

	/**
	 * @return JsonSerializable|null
	 */
	public function getHistoryHandler(): ?JsonSerializable;
}
