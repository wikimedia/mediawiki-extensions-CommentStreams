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

class DatabaseQuerier {
	
	public static function commentsForPageId( $contentPageId ) {

		global $wgCommentStreamsUserRealNamePropertyName;
		global $wgCommentStreamsSMWinstalled;

		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select(
			'cs_comment_data',
			array('page_id', 'assoc_page_id', 'parent_page_id', 'comment_title'),
			array(
				'assoc_page_id' => $contentPageId
			),
			__METHOD__
		);

		$databaseResults = array();

		foreach($result as $row) {
			$databaseResults[] = array(
				'page_id' => $row->page_id,
				'assoc_page_id' => $row->assoc_page_id,
				'parent_page_id' => $row->parent_page_id,
				'comment_title' => $row->comment_title
			);
		}

		$comments = array();

		foreach($databaseResults as $row) {
			$title = $row['comment_title'];
			$page_id = $row['page_id'];
			$associated_page_id = $contentPageId;
			$parent_id = $row['parent_page_id'];

			// create WikiPage object to get the page text and username/real name
			$wikipage = WikiPage::newFromId($page_id);
			$pageText = ContentHandler::getContentText( $wikipage->getContent( Revision::RAW ) );
			$user = User::newFromId($wikipage->getOldestRevision()->getUser());
			$username = $user->getName();

			$userTitleObject = Title::newFromText($username, NS_USER);
			if($wgCommentStreamsSMWinstalled && $wgCommentStreamsUserRealNamePropertyName !== null) {
				$userRealName = DatabaseQuerier::queryForUserRealNameProperty($userTitleObject, $wgCommentStreamsUserRealNamePropertyName);
				if($userRealName == null)
					$userRealName = $user->getRealName();
			}
			else
				$userRealName = $user->getRealName();
			// create Title object to get creation date
			$titleObject = Title::newFromID($page_id);
			$timestamp = MWTimestamp::getLocalInstance($titleObject->getEarliestRevTime());
			$creationDate = $timestamp;

			$title = htmlspecialchars($title);
			$comment = new Comment($title, $pageText, $page_id, $associated_page_id, $parent_id, $username, $userRealName, $creationDate);
			$comments[] = $comment;
		}

		return $comments;
	}

	public static function commentTitleForPageId( $pageId ) {
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select(
			'cs_comment_data',
			'comment_title',
			array(
				'page_id' => $pageId
			),
			__METHOD__
		);
		if($result->current())
			return htmlspecialchars($result->current()->comment_title);
		else
			return null;
	}

	public static function numberOfChildCommentsForParentCommentId( $parentId ) {
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select(
			'cs_comment_data',
			array('page_id'),
			array(
				'parent_page_id' => $parentId
			),
			__METHOD__
		);
		$numRows = $result->numRows();
		return is_numeric( $numRows ) ? $numRows : 0;
	}

	public static function getNextCommentNumber() {
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select(
			'cs_next_comment',
			array('next_comment_number')
	 	);

		return $result->current()->next_comment_number;
	}

	public static function incrementNextCommentNumber() {
		$currentCommentNumber = self::getNextCommentNumber();

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'cs_next_comment',
			array('next_comment_number' => $currentCommentNumber+1),
			array('next_comment_number' => $currentCommentNumber)
		);
	}
	public static function setNextCommentNumberOrHigher($newNumber) {
		// Set the comment number to either this value, or the current value +1,
		// whichever is higher. (This is for concurrency issues, in case the current value
		// was incremented since it had been fetched.)
		$currentCommentNumber = self::getNextCommentNumber();

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'cs_next_comment',
			array('next_comment_number' => max($newNumber, $currentCommentNumber+1)),
			array('next_comment_number' => $currentCommentNumber)
		);	
	}
	public static function addCommentDataToDatabase($pageId, $commentedPageId, $commentTitle, $parentCommentId=null) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw-> insert(
			'cs_comment_data',
			array(
				'page_id' => $pageId,
				'assoc_page_id' => $commentedPageId,
				'comment_title' => $commentTitle,
				'parent_page_id' => $parentCommentId
			)
		);
	}
	public static function deleteCommentDataFromDatabase($pageId) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'cs_comment_data',
			array(
				'page_id' => $pageId
			)
		);
	}

	public static function updateCommentTitle($pageId, $title) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'cs_comment_data',
			array('comment_title' => $title),
			array('page_id' => $pageId)
		);
	}
	
	public static function queryForUserRealNameProperty($title, $propertyName) { 
		$store = \SMW\StoreFactory::getStore();

       $subject = SMWDIWikiPage::newFromTitle( $title );
       $data = $store->getSemanticData( $subject );
       $property = SMWDIProperty::newFromUserLabel( $propertyName );
       $values = $data->getPropertyValues( $property );
 
       if(count($values) == 0)
       	return null;

		// this property should only have one value so pick the first one
       $value = $values[0];
		if ( $value->getDIType() == SMWDataItem::TYPE_STRING ||
			$value->getDIType() == SMWDataItem::TYPE_BLOB ) {
			return $value->getString();
		}
		else {
			return null;
		}
	}
}
