<?php
/*
 * Copyright (c) 2016 The MITRE Corporation
 *
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

class Comment {
	// $title - String, title of comment
	// $text - String, wikitext of comment
	// $page_id - int, page ID for this comment's wikipage
	// $associated_page_id - int, page ID for the wikipage this comment is on
	// $username - String, username
	// $creation_date - MWTimestamp, the earliest revision date for this comment
	private $title, $text, $page_id, $associated_page_id, $parent_page_id, $username, $user_real_name, $creation_date;

	public function __construct($title, $text, $page_id, $associated_page_id, $parent_page_id, $username, $user_real_name, $creation_date) {
		$this->title = $title;
		$this->text = $text;
		$this->page_id = $page_id;
		$this->parent_page_id = $parent_page_id;
		$this->associated_page_id = $associated_page_id;
		$this->username = $username;
		$this->user_real_name = $user_real_name;
		$this->creation_date = $creation_date;
	}

	function getTitle() {
		return $this->title;
	}

	function getText() {
		return $this->text;
	}

	function getPageId() {
		return $this->page_id;
	}

	function getAssociatedId() {
		return $this->associated_page_id;
	}

	function getParentId() {
		return $this->parent_page_id;
	}

	function getUsername() {
		return $this->username;
	}

	function getUserRealName() {
		return $this->user_real_name;
	}

	function getDisplayName() {
		if($this->user_real_name != null)
			return $this->user_real_name;
		else
			return $this->username;
	}

	function getCreationDate() {
		return $this->creation_date;
	}
}
