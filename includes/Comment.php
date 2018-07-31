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

	// wiki page object for this comment wiki page
	private $wikipage = null;

	// data for this comment has been loaded from the database
	private $loaded = false;

	// int page ID for the wikipage this comment is on
	private $assoc_page_id;

	// int page ID for the wikipage this comment is in reply to or null
	private $parent_page_id;

	// string title of comment
	private $comment_title;

	// string wikitext of comment
	private $wikitext = null;

	// string HTML of comment
	private $html = null;

	// User user object for the author of this comment
	private $user = null;

	// Avatar for author of this comment
	private $avatar = null;

	// MWTimestamp the earliest revision date for this comment
	private $creation_timestamp = null;

	// MWTimestamp the latest revision date for this comment
	private $modification_timestamp = null;

	// number of replies to this comment
	private $num_replies = null;

	// number of up votes for this comment
	private $num_up_votes = null;

	// number of dow votes for this comment
	private $num_down_votes = null;

	/**
	 * create a new Comment object from existing wiki page
	 *
	 * @param WikiPage $wikipage WikiPage object corresponding to comment page
	 * @return Comment|null the newly created comment or null if there was an
	 * error
	 */
	public static function newFromWikiPage( $wikipage ) {
		if ( !is_null( $wikipage ) &&
			$wikipage->getTitle()->getNamespace() === NS_COMMENTSTREAMS ) {
			$comment = new Comment( $wikipage );
			if ( $wikipage->exists() ) {
				$comment->loadFromDatabase();
			}
			return $comment;
		}
		return null;
	}

	/**
	 * create a new Comment object from values and save to database
	 * NOTE: since only head comments can contain a comment title, either
	 * $comment_title or $parent_page_id must be non null, but not both
	 *
	 * @param int $assoc_page_id page ID for the wikipage this comment is on
	 * @param int $parent_page_id page ID for the wikipage this comment is in
	 * reply to or null
	 * @param string $comment_title string title of comment
	 * @param string $wikitext the wikitext to add
	 * @param User $user the user
	 * @return Comment|null new comment object or null if there was a problem
	 * creating it
	 */
	public static function newFromValues( $assoc_page_id, $parent_page_id,
		$comment_title, $wikitext, $user ) {
		if ( is_null( $comment_title ) && is_null( $parent_page_id ) ) {
			return null;
		}
		if ( !is_null( $comment_title ) && !is_null( $parent_page_id ) ) {
			return null;
		}
		$annotated_wikitext = self::addAnnotations( $wikitext, $comment_title,
			$assoc_page_id );
		$content = new WikitextContent( $annotated_wikitext );
		$success = false;
		while ( !$success ) {
			$index = wfRandomString();
			$title = Title::newFromText( (string)$index, NS_COMMENTSTREAMS );
			if ( !$title->isDeletedQuick() && !$title->exists() ) {
				if ( !$title->userCan( 'cs-comment' ) ) {
					return null;
				}
				$wikipage = new WikiPage( $title );
				$status = $wikipage->doEditContent( $content, '',
					EDIT_NEW | EDIT_SUPPRESS_RC, false, $user, null );
				if ( !$status->isOK() && !$status->isGood() ) {
					if ( $status->getMessage()->getKey() == 'edit-already-exists' ) {
						$index = wfRandomString();
					} else {
						return null;
					}
				} else {
					$success = true;
				}
			} else {
				$index = wfRandomString();
			}
		}
		$comment = new Comment( $wikipage );
		$comment->wikitext = $wikitext;

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->insert(
			'cs_comment_data',
			[
				'cst_page_id' => $wikipage->getId(),
				'cst_assoc_page_id' => $assoc_page_id,
				'cst_parent_page_id' => $parent_page_id,
				'cst_comment_title' => $comment_title
			],
			__METHOD__
		);
		if ( !$result ) {
			return null;
		}
		$comment->loadFromValues( $assoc_page_id, $parent_page_id, $comment_title );

		if ( is_null( $parent_page_id ) ) {
			$comment->watch( $user );
		} else {
			self::watchComment( $parent_page_id, $user );
		}

		if ( defined( 'SMW_VERSION' ) ) {
			$job = new SMWUpdateJob( $title );
			JobQueueGroup::singleton()->push( $job );
		}

		return $comment;
	}

	/**
	 * constructor
	 *
	 * @param WikiPage $wikipage WikiPage object corresponding to comment page
	 */
	private function __construct( $wikipage ) {
		$this->wikipage = $wikipage;
	}

	/**
	 * load comment data from database
	 */
	private function loadFromDatabase() {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->selectRow(
			'cs_comment_data',
			[
				'cst_assoc_page_id',
				'cst_parent_page_id',
				'cst_comment_title'
			],
			[
				'cst_page_id' => $this->getId()
			],
			__METHOD__
		);
		if ( $result ) {
			$this->assoc_page_id = (int)$result->cst_assoc_page_id;
			$this->parent_page_id = $result->cst_parent_page_id;
			if ( !is_null( $this->parent_page_id ) ) {
				$this->parent_page_id = (int)$this->parent_page_id;
			}
			$this->comment_title = $result->cst_comment_title;
			$this->loaded = true;
		}
	}

	/**
	 * load comment data from values
	 *
	 * @param int $assoc_page_id page ID for the wikipage this comment is on
	 * @param int $parent_page_id page ID for the wikipage this comment is in
	 * reply to or null
	 * @param string $comment_title string title of comment
	 */
	private function loadFromValues( $assoc_page_id, $parent_page_id,
		$comment_title ) {
		$this->assoc_page_id = (int)$assoc_page_id;
		$this->parent_page_id = $parent_page_id;
		if ( !is_null( $this->parent_page_id ) ) {
			$this->parent_page_id = (int)$this->parent_page_id;
		}
		$this->comment_title = $comment_title;
		$this->loaded = true;
	}

	/**
	 * @return int page ID of the comment's wikipage
	 */
	public function getId() {
		return $this->wikipage->getId();
	}

	/**
	 * @return WikiPage wiki page object associate with this comment page
	 */
	public function getWikiPage() {
		return $this->wikipage;
	}

	/**
	 * @return int page ID for the wikipage this comment is on
	 */
	public function getAssociatedId() {
		if ( $this->loaded === false ) {
			$this->loadFromDatabase();
		}
		return $this->assoc_page_id;
	}

	/**
	 * @return int|null page ID for the wikipage this comment is in reply to or
	 * null if this comment is a discussion, not a reply
	 */
	public function getParentId() {
		if ( $this->loaded === false ) {
			$this->loadFromDatabase();
		}
		return $this->parent_page_id;
	}

	/**
	 * @return string the title of the comment
	 */
	public function getCommentTitle() {
		if ( $this->loaded === false ) {
			$this->loadFromDatabase();
		}
		return $this->comment_title;
	}

	/**
	 * @return string wikitext of the comment
	 */
	public function getWikiText() {
		if ( is_null( $this->wikitext ) ) {
			$wikitext = ContentHandler::getContentText( $this->wikipage->getContent(
				Revision::RAW ) );
			$wikitext = $this->removeAnnotations( $wikitext );
			$this->wikitext = $wikitext;
		}
		return $this->wikitext;
	}

	/**
	 * @return string parsed HTML of the comment
	 */
	public function getHTML() {
		if ( is_null( $this->html ) ) {
			$this->getWikiText();
			if ( !is_null( $this->wikitext ) ) {
				$parser = new Parser;
				$this->html = $parser->parse( $this->wikitext,
					$this->wikipage->getTitle(), new ParserOptions )->getText();
			}
		}
		return $this->html;
	}

	/**
	 * @return User the author of this comment
	 */
	public function getUser() {
		if ( is_null( $this->user ) ) {
			$user_id = $this->wikipage->getOldestRevision()->getUser();
			$this->user = User::newFromId( $user_id );
		}
		return $this->user;
	}

	/**
	 * @return bool true if the last edit to this comment was not done by the
	 * original author
	 */
	public function isLastEditModerated() {
		$author = $this->wikipage->getOldestRevision()->getUser();
		$lastEditor = $this->wikipage->getRevision()->getUser();
		return $author !== $lastEditor;
	}

	/**
	 * @return string username of the author of this comment
	 */
	public function getUsername() {
		return $this->getUser()->getName();
	}

	/**
	 * @return string display name of the author of this comment linked to
	 * the user's user page if it exists
	 */
	public function getUserDisplayName() {
		return self::getDisplayNameFromUser( $this->getUser() );
	}

	/**
	 * @return string display name of the author of this comment
	 */
	public function getUserDisplayNameUnlinked() {
		return self::getDisplayNameFromUser( $this->getUser(), false );
	}

	/**
	 * @return string the URL of the avatar of the author of this comment
	 */
	public function getAvatar() {
		if ( is_null( $this->avatar ) ) {
			if ( class_exists( 'wAvatar' ) ) {
				// from Extension:SocialProfile
				$avatar = new wAvatar( $this->getUser()->getId(), 'l' );
				$this->avatar = $GLOBALS['wgUploadPath'] . '/avatars/' .
					$avatar->getAvatarImage();
			} else {
				$this->avatar = self::getAvatarFromUser( $this->getUser() );
			}
		}
		return $this->avatar;
	}

	/**
	 * @return MWTimestamp the earliest revision date for this
	 */
	public function getCreationTimestamp() {
		if ( is_null( $this->creation_timestamp ) ) {
			$this->creation_timestamp = MWTimestamp::getLocalInstance(
				$this->wikipage->getTitle()->getEarliestRevTime() );
		}
		return $this->creation_timestamp;
	}

	/**
	 * @return MWTimestamp the earliest revision date for this
	 */
	public function getCreationDate() {
		if ( !is_null( $this->getCreationTimestamp() ) ) {
			return $this->creation_timestamp->format( "M j \a\\t g:i a" );
		}
		return "";
	}

	/**
	 * @return MWTimestamp the latest revision date for this
	 */
	public function getModificationTimestamp() {
		if ( is_null( $this->modification_timestamp ) ) {
			$title = $this->wikipage->getTitle();
			if ( $title->getFirstRevision()->getId() === $title->getLatestRevID() ) {
				return null;
			}
			$timestamp = Revision::getTimestampFromId( $title,
				$title->getLatestRevID() );
			$this->modification_timestamp = MWTimestamp::getLocalInstance(
				$timestamp );
		}
		return $this->modification_timestamp;
	}

	/**
	 * @return MWTimestamp the earliest revision date for this
	 */
	public function getModificationDate() {
		if ( !is_null( $this->getModificationTimestamp() ) ) {
			return $this->modification_timestamp->format( "M j \a\\t g:i a" );
		}
		return null;
	}

	/**
	 * @return int number of replies
	 */
	public function getNumReplies() {
		if ( is_null( $this->num_replies ) ) {
			$dbr = wfGetDB( DB_REPLICA );
			$this->num_replies = $dbr->selectRowCount(
				'cs_comment_data',
				'*',
				[
					'cst_parent_page_id' => $this->getId()
				],
				__METHOD__
			);
		}
		return $this->num_replies;
	}

	/**
	 * @return array get comment data in array suitable for JSON
	 */
	public function getJSON() {
		$json = [
			'commenttitle' => $this->getCommentTitle(),
			'username' => $this->getUsername(),
			'userdisplayname' => $this->getUserDisplayName(),
			'avatar' => $this->getAvatar(),
			'created' => $this->getCreationDate(),
			'created_timestamp' => $this->getCreationTimestamp()->format( "U" ),
			'modified' => $this->getModificationDate(),
			'moderated' => $this->isLastEditModerated() ? "moderated" : null,
			'wikitext' => htmlentities( $this->getWikiText() ),
			'html' => $this->getHTML(),
			'pageid' => $this->getId(),
			'associatedid' => $this->getAssociatedId(),
			'parentid' => $this->getParentId(),
			'numreplies' => $this->getNumReplies(),
		];
		if ( $GLOBALS['wgCommentStreamsEnableVoting'] ) {
			$json['numupvotes'] = $this->getNumUpVotes();
			$json['numdownvotes'] = $this->getNumDownVotes();
		}
		return $json;
	}

	/**
	 * get vote for user
	 *
	 * @param User $user the author of the edit
	 * @return +1 for up vote, -1 for down vote, 0 for no vote
	 */
	public function getVote( $user ) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->selectRow(
			'cs_votes',
			[
				'cst_v_vote'
			],
			[
				'cst_v_page_id' => $this->getId(),
				'cst_v_user_id' => $user->getId()
			],
			__METHOD__
		);
		if ( $result ) {
			$vote = (int)$result->cst_v_vote;
			if ( $vote > 0 ) {
				return 1;
			}
			if ( $vote < 0 ) {
				return -1;
			}
		}
		return 0;
	}

	/**
	 * @return int number of up votes
	 */
	public function getNumUpVotes() {
		if ( is_null( $this->num_up_votes ) ) {
			$dbr = wfGetDB( DB_REPLICA );
			$this->num_up_votes = $dbr->selectRowCount(
				'cs_votes',
				'*',
				[
					'cst_v_page_id' => $this->getId(),
					'cst_v_vote' => 1
				],
				__METHOD__
			);
		}
		return $this->num_up_votes;
	}

	/**
	 * @return int number of down votes
	 */
	public function getNumDownVotes() {
		if ( is_null( $this->num_down_votes ) ) {
			$dbr = wfGetDB( DB_REPLICA );
			$this->num_down_votes = $dbr->selectRowCount(
				'cs_votes',
				'*',
				[
					'cst_v_page_id' => $this->getId(),
					'cst_v_vote' => -1
				],
				__METHOD__
			);
		}
		return $this->num_down_votes;
	}

	/**
	 * record a vote
	 *
	 * @param string $vote 1 for up vote, -1 for down vote, 0 for no vote
	 * @param User $user the user voting on the comment
	 * @return database status code
	 */
	public function vote( $vote, $user ) {
		if ( $vote !== "-1" && $vote !== "0" && $vote !== "1" ) {
			return false;
		}
		$vote = (int)$vote;
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->selectRow(
			'cs_votes',
			[
				'cst_v_vote'
			],
			[
				'cst_v_page_id' => $this->getId(),
				'cst_v_user_id' => $user->getId()
			],
			__METHOD__
		);
		if ( $result ) {
			if ( $vote === (int)$result->cst_v_vote ) {
				return true;
			}
			if ( $vote === 1 || $vote === -1 ) {
				$dbw = wfGetDB( DB_MASTER );
				$result = $dbw->update(
					'cs_votes',
					[
						'cst_v_vote' => $vote
					],
					[
						'cst_v_page_id' => $this->getId(),
						'cst_v_user_id' => $user->getId()
					],
					__METHOD__
				);
			} else {
				$dbw = wfGetDB( DB_MASTER );
				$result = $dbw->delete(
					'cs_votes',
					[
						'cst_v_page_id' => $this->getId(),
						'cst_v_user_id' => $user->getId()
					],
					__METHOD__
				);
			}
		} else {
			if ( $vote === 0 ) {
				return true;
			}
			$dbw = wfGetDB( DB_MASTER );
			$result = $dbw->insert(
				'cs_votes',
				[
					'cst_v_page_id' => $this->getId(),
					'cst_v_user_id' => $user->getId(),
					'cst_v_vote' => $vote
				],
				__METHOD__
			);
		}
		return $result;
	}

	/**
	 * watch a comment (get page ID from this comment)
	 *
	 * @param User $user the user watching the comment
	 * @return database true for OK, false for error
	 */
	public function watch( $user ) {
		return self::watchComment( $this->getID(), $user );
	}

	/**
	 * watch a comment (get page ID from parameter)
	 *
	 * @param $pageid the page ID of the comment to watch
	 * @param User $user the user watching the comment
	 * @return database true for OK, false for error
	 */
	private static function watchComment( $pageid, $user ) {
		if ( self::isWatchingComment( $pageid, $user ) ) {
			return true;
		}
		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->insert(
			'cs_watchlist',
			[
				'cst_wl_page_id' => $pageid,
				'cst_wl_user_id' => $user->getId()
			],
			__METHOD__
		);
		return $result;
	}

	/**
	 * unwatch a comment
	 *
	 * @param User $user the user unwatching the comment
	 * @return database true for OK, false for error
	 */
	public function unwatch( $user ) {
		if ( !$this->isWatching( $user ) ) {
			return true;
		}
		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->delete(
			'cs_watchlist',
			[
				'cst_wl_page_id' => $this->getId(),
				'cst_wl_user_id' => $user->getId()
			],
			__METHOD__
		);
		return $result;
	}

	/**
	 * Check if a particular user is watching this comment
	 *
	 * @param User $user the user watching the comment
	 * @return database true for OK, false for error
	 */
	public function isWatching( $user ) {
		return self::isWatchingComment( $this->getId(), $user );
	}

	/**
	 * Check if a particular user is watching a comment
	 *
	 * @param $pageid the page ID of the comment to check
	 * @param User $user the user watching the comment
	 * @return database true for OK, false for error
	 */
	private static function isWatchingComment( $pageid, $user ) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->selectRow(
			'cs_watchlist',
			[
				'cst_wl_page_id'
			],
			[
				'cst_wl_page_id' => $pageid,
				'cst_wl_user_id' => $user->getId()
			],
			__METHOD__
		);
		if ( $result ) {
			return true;
		}
		return false;
	}

	/**
	 * Get an array of watchers for this comment
	 *
	 * @return array of user IDs
	 */
	public function getWatchers() {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->select(
			'cs_watchlist',
			[
				'cst_wl_user_id'
			],
			[
				'cst_wl_page_id' => $this->getId()
			],
			__METHOD__
		);
		$users = [];
		foreach ( $result as $row ) {
			$user_id = $row->cst_wl_user_id;
			$user = User::newFromId( $user_id );
			$users[$user_id] = $user;
		}
		return $users;
	}

	/**
	 * update comment in database
	 * NOTE: since only head comments can contain a comment title,
	 * $comment_title may only be non null if this comment has a null parent id
	 * and vice versa
	 *
	 * @param string $comment_title the new title for the comment
	 * @param string $wikitext the wikitext to add
	 * @param User $user the author of the edit
	 * @return bool true if successful
	 */
	public function update( $comment_title, $wikitext, $user ) {
		if ( is_null( $comment_title ) && is_null( $this->getParentId() ) ) {
			return false;
		}
		if ( !is_null( $comment_title ) && !is_null( $this->getParentId() ) ) {
			return false;
		}
		$annotated_wikitext =
			self::addAnnotations( $wikitext, $comment_title,
				$this->getAssociatedId() );
		$content = new WikitextContent( $annotated_wikitext );
		$status = $this->wikipage->doEditContent( $content, '',
			EDIT_UPDATE | EDIT_SUPPRESS_RC, false, $user, null );
		if ( !$status->isOK() && !$status->isGood() ) {
			return false;
		}
		$this->wikitext = $wikitext;
		$this->modification_timestamp = null;
		$this->wikipage = WikiPage::newFromID( $this->wikipage->getId() );

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->update(
			'cs_comment_data',
			[
				'cst_comment_title' => $comment_title
			],
			[
				'cst_page_id' => $this->getId()
			],
			__METHOD__
		);
		if ( !$result ) {
			return false;
		}
		$this->comment_title = $comment_title;

		return true;
	}

	/**
	 * delete comment from database
	 *
	 * @return bool true if successful
	 */
	public function delete() {
		$pageid = $this->getId();

		$status = $this->getWikiPage()->doDeleteArticleReal( 'comment deleted',
			true, 0 );
		if ( !$status->isOK() && !$status->isGood() ) {
			return false;
		}

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->delete(
			'cs_comment_data',
			[
				'cst_page_id' => $pageid
			],
			__METHOD__
		);
		return $result;
	}

	/**
	 * add extra information to wikitext before storage
	 *
	 * @param string $wikitext the wikitext to which to add
	 * @param string $comment_title string title of comment
	 * @param int $assoc_page_id page ID for the wikipage this comment is on
	 * @return string annotated wikitext
	 */
	public static function addAnnotations( $wikitext, $comment_title,
		$assoc_page_id ) {
		if ( !is_null( $comment_title ) ) {
			$wikitext .= <<<EOT
{{DISPLAYTITLE:
$comment_title
}}
EOT;
		}
		return $wikitext;
	}

	/**
	 * add extra information to wikitext before storage
	 *
	 * @param string $wikitext the wikitext to which to add
	 * @return string wikitext without annotations
	 */
	public function removeAnnotations( $wikitext ) {
		$comment_title = $this->getCommentTitle();
		if ( !is_null( $comment_title ) ) {
			$strip = <<<EOT
{{DISPLAYTITLE:
$comment_title
}}
EOT;
			$wikitext = str_replace( $strip, '', $wikitext );
		}
		return $wikitext;
	}

	/**
	 * get comments for the given page
	 *
	 * @param int $assoc_page_id ID of page to get comments for
	 * @return array array of comments for the given page
	 */
	public static function getAssociatedComments( $assoc_page_id ) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->select(
			'cs_comment_data',
			[
				'cst_page_id'
			],
			[
				'cst_assoc_page_id' => $assoc_page_id
			],
			__METHOD__
		);
		$comments = [];
		foreach ( $result as $row ) {
			$page_id = $row->cst_page_id;
			$wikipage = WikiPage::newFromId( $page_id );
			$comment = self::newFromWikiPage( $wikipage );
			if ( !is_null( $comment ) ) {
				$comments[] = $comment;
			}
		}
		return $comments;
	}

	/**
	 * get replies for the given comment
	 *
	 * @param int $parent_page_id ID of page to get comments for
	 * @return array array of comments for the given page
	 */
	public static function getReplies( $parent_page_id ) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->select(
			'cs_comment_data',
			[
				'cst_page_id'
			],
			[
				'cst_parent_page_id' => $parent_page_id
			],
			__METHOD__
		);
		$comments = [];
		foreach ( $result as $row ) {
			$page_id = $row->cst_page_id;
			$wikipage = WikiPage::newFromId( $page_id );
			$comment = self::newFromWikiPage( $wikipage );
			if ( !is_null( $comment ) ) {
				$comments[] = $comment;
			}
		}
		return $comments;
	}

	/**
	 * return the text to use to represent the user at the top of a comment
	 *
	 * @param User $user the user
	 * @param bool $linked whether to link the display name to the user page,
	 * if it exists
	 * @return string display name for user
	 */
	public static function getDisplayNameFromUser( $user, $linked = true ) {
		if ( $user->isAnon() ) {
			$html = Html::openElement( 'span', [
					'class' => 'cs-comment-author-anonymous'
				] )
				. wfMessage( 'commentstreams-author-anonymous' )
				. Html::closeElement( 'span' );
			return $html;
		}
		$userpage = $user->getUserPage();
		$displayname = null;
		if ( !is_null( $GLOBALS['wgCommentStreamsUserRealNamePropertyName'] ) ) {
			$displayname = self::getUserProperty( $user,
				$GLOBALS['wgCommentStreamsUserRealNamePropertyName'] );
		}
		if ( is_null( $displayname ) || strlen( $displayname ) == 0 ) {
			if ( class_exists( 'PageProps' ) ) {
				$values = PageProps::getInstance()->getProperties( $userpage,
					'displaytitle' );
				if ( array_key_exists( $userpage->getArticleID(), $values ) ) {
					$displayname = $values[$userpage->getArticleID()];
				}
			}
		}
		if ( is_null( $displayname ) || strlen( $displayname ) == 0 ) {
			$displayname = $user->getRealName();
		}
		if ( is_null( $displayname ) || strlen( $displayname ) == 0 ) {
			$displayname = $user->getName();
		}
		if ( $linked && $userpage->exists() ) {
			$displayname = CommentStreamsUtils::link( $userpage, $displayname );
		}
		return $displayname;
	}

	/**
	 * return the name of the file page containing the user's avatar
	 *
	 * @param User $user the user
	 * @return string URL of avatar
	 */
	public static function getAvatarFromUser( $user ) {
		$avatar = null;
		if ( !is_null( $GLOBALS['wgCommentStreamsUserAvatarPropertyName'] ) ) {
			$avatar = self::getUserProperty( $user,
				$GLOBALS['wgCommentStreamsUserAvatarPropertyName'] );
			if ( !is_null( $avatar ) ) {
				if ( gettype( $avatar ) === 'string' ) {
					$avatar = Title::newFromText( $avatar );
					if ( is_null( $avatar ) ) {
						return null;
					}
				}
				if ( !get_class( $avatar ) === 'Title' ) {
					return null;
				}
				if ( $avatar->isKnown() && $avatar->getNamespace() === NS_FILE ) {
					$file = wfFindFile( $avatar );
					if ( $file ) {
						return $file->getFullUrl();
					}
				}
			}
		}
		return null;
	}

	/**
	 * return the value of a property on a user page
	 *
	 * @param User $user the user
	 * @param string $propertyName the name of the property
	 * @return string|null the value of the property
	 */
	private static function getUserProperty( $user, $propertyName ) {
		if ( defined( 'SMW_VERSION' ) ) {
			$userpage = $user->getUserPage();
			if ( $userpage->exists() ) {
				$store = \SMW\StoreFactory::getStore();
				$subject = SMWDIWikiPage::newFromTitle( $userpage );
				$data = $store->getSemanticData( $subject );
				$property = SMWDIProperty::newFromUserLabel( $propertyName );
				$values = $data->getPropertyValues( $property );
				if ( count( $values ) > 0 ) {
					// this property should only have one value so pick the first one
					$value = $values[0];
					if ( ( defined( 'SMWDataItem::TYPE_STRING' ) &&
						$value->getDIType() == SMWDataItem::TYPE_STRING ) ||
						$value->getDIType() == SMWDataItem::TYPE_BLOB ) {
						return $value->getString();
					} elseif ( $value->getDIType() == SMWDataItem::TYPE_WIKIPAGE ) {
						return $value->getTitle();
					}
				}
			}
		}
		return null;
	}

	/**
	 * Used by Echo to locate the users watching a comment being replied to.
	 * @param EchoEvent $event the Echo event
	 * @return array array mapping user id to User object
	 */
	public static function locateUsersWatchingComment( $event ) {
		$id = $event->getExtraParam( 'parent_id' );
		$wikipage = WikiPage::newFromId( $id );
		if ( !is_null( $wikipage ) ) {
			$comment = self::newFromWikiPage( $wikipage );
			if ( !is_null( $comment ) ) {
				return $comment->getWatchers();
			}
		}
		return [];
	}
}
