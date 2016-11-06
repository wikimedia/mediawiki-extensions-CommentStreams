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

	// MWTimestamp the earliest revision date for this comment
	private $creation_timestamp = null;

	// MWTimestamp the latest revision date for this comment
	private $modification_timestamp = null;

	// number of replies to this comment
	private $num_replies = null;

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
			$title = Title::newFromText( (string) $index, NS_COMMENTSTREAMS );
			if ( !$title->isDeletedQuick() && !$title->exists() ) {
				$wikipage = new WikiPage( $title );
				$status = $wikipage->doEditContent( $content, '', EDIT_NEW, false,
					$user, null );
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
		$result = $dbw-> insert(
			'cs_comment_data',
			[
				'page_id' => $wikipage->getId(),
				'assoc_page_id' => $assoc_page_id,
				'parent_page_id' => $parent_page_id,
				'comment_title' => $comment_title
			],
			__METHOD__
		);
		if ( !$result ) {
			return null;
		}
		$comment->loadFromValues( $assoc_page_id, $parent_page_id, $comment_title );

		$job = new SMWUpdateJob( $title );
		JobQueueGroup::singleton()->push( $job );

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
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->selectRow(
			'cs_comment_data',
			[ 'assoc_page_id', 'parent_page_id', 'comment_title' ],
			[ 'page_id' => $this->getId() ],
			__METHOD__
		);
		if ( $result ) {
			$this->assoc_page_id = (integer) $result->assoc_page_id;
			$this->parent_page_id = $result->parent_page_id;
			if ( !is_null( $this->parent_page_id ) ) {
				$this->parent_page_id = (integer) $this->parent_page_id;
			}
			$this->comment_title = $result->comment_title;
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
		$this->assoc_page_id = (integer) $assoc_page_id;
		$this->parent_page_id = $parent_page_id;
		if ( !is_null( $this->parent_page_id ) ) {
			$this->parent_page_id = (integer) $this->parent_page_id;
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
	 * @return string username of the author of this comment
	 */
	public function getUsername() {
		return $this->getUser()->getName();
	}

	/**
	 * @return string username of the author of this comment
	 */
	public function getUserDisplayName() {
		return self::getDisplayNameFromUser( $this->getUser() );
	}

	/**
	 * @return string the URL of the avatar of the author of this comment
	 */
	public function getAvatar() {
		return self::getAvatarFromUser( $this->getUser() );
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
			$dbr = wfGetDB( DB_SLAVE );
			$this->num_replies = $dbr->selectRowCount(
				'cs_comment_data',
				'*',
				[ 'parent_page_id' => $this->getId() ],
				__METHOD__
			);
		}
		return $this->num_replies;
	}

	/**
	 * @return array get comment data in array suitable for JSON
	 */
	public function getJSON() {
		return
			[
				'commenttitle' => $this->getCommentTitle(),
				'username' => $this->getUsername(),
				'userdisplayname' => $this->getUserDisplayName(),
				'avatar' => $this->getAvatar(),
				'created' => $this->getCreationDate(),
				'modified' => $this->getModificationDate(),
				'wikitext' => $this->getWikiText(),
				'html' => $this->getHTML(),
				'pageid' => $this->getId(),
				'associatedid' => $this->getAssociatedId(),
				'parentid' => $this->getParentId(),
				'numreplies' => $this->getNumReplies()
			];
	}

	/**
	 * update comment in database
	 * NOTE: since only head comments can contain a comment title,
	 * $comment_title may only be non null if this comment has a null parent id
	 * and vice versa
	 *
	 * @param string $comment_title the new title for the comment
	 * @param string $wikitext the wikitext to add
	 * @return boolean true if successful
	 */
	public function update( $comment_title, $wikitext ) {
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
		$status = $this->wikipage->doEditContent( $content, '', EDIT_UPDATE, false,
			$this->getUser(), null );
		if ( !$status->isOK() && !$status->isGood() ) {
			return false;
		}
		$this->wikitext = $wikitext;
		$this->modification_timestamp = null;
		$this->wikipage = WikiPage::newFromID( $this->wikipage->getId() );

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->update(
			'cs_comment_data',
			[ 'comment_title' => $comment_title ],
			[ 'page_id' => $this->getId() ],
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
	 * @return boolean true if successful
	 */
	public function delete() {
		$pageid = $this->getId();

		$status = $this->getWikiPage()->doDeleteArticleReal( 'comment deleted',
			false, 0 );
		if ( !$status->isOK() && !$status->isGood() ) {
			return false;
		}

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->delete(
			'cs_comment_data',
			[ 'page_id' => $pageid ],
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
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select(
			'cs_comment_data',
			[ 'page_id' ],
			[ 'assoc_page_id' => $assoc_page_id ],
			__METHOD__
		);
		$comments = [];
		foreach ( $result as $row ) {
			$page_id = $row->page_id;
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
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select(
			'cs_comment_data',
			[ 'page_id' ],
			[ 'parent_page_id' => $parent_page_id ],
			__METHOD__
		);
		$comments = [];
		foreach ( $result as $row ) {
			$page_id = $row->page_id;
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
	 * @return string display name for user
	 */
	public static function getDisplayNameFromUser( $user ) {
		$displayname = null;
		if ( !is_null( $GLOBALS['wgCommentStreamsUserRealNamePropertyName'] ) ) {
			$displayname = self::getUserProperty( $user,
				$GLOBALS['wgCommentStreamsUserRealNamePropertyName'] );
		}
		if ( is_null( $displayname ) || strlen( $displayname ) == 0 ) {
			$displayname = $user->getRealName();
		}
		if ( is_null( $displayname ) || strlen( $displayname ) == 0 ) {
			$displayname = $user->getName();
		}
		$userpage = $user->getUserPage();
		if ( $userpage->exists() ) {
			$displayname = Linker::link( $userpage, $displayname );
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
						return false;
					}
				}
				if ( !get_class( $avatar ) === 'Title' ) {
					return false;
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
					if ( $value->getDIType() == SMWDataItem::TYPE_STRING
						|| $value->getDIType() == SMWDataItem::TYPE_BLOB ) {
						return $value->getString();
					} elseif ( $value->getDIType() == SMWDataItem::TYPE_WIKIPAGE ) {
						return $value->getTitle();
					}
				}
			}
		}
		return null;
	}
}
