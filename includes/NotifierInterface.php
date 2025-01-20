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

use User;
use WikiPage;

interface NotifierInterface {

	/**
	 * @return bool
	 */
	public function isLoaded(): bool;

	/**
	 * Send Echo notifications if Echo is installed.
	 *
	 * @param Comment $comment the comment to send notifications for
	 * @param WikiPage $associatedPage the associated page for the comment
	 * @param User $user
	 * @param string $commentTitle
	 */
	public function sendCommentNotifications(
		Comment $comment,
		WikiPage $associatedPage,
		User $user,
		string $commentTitle
	);

	/**
	 * Send Echo notifications if Echo is installed.
	 *
	 * @param Reply $reply the comment to send notifications for
	 * @param WikiPage $associatedPage the associated page for the comment
	 * @param User $user
	 * @param Comment $parentComment
	 */
	public function sendReplyNotifications(
		Reply $reply,
		WikiPage $associatedPage,
		User $user,
		Comment $parentComment
	);
}
