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

use Content;
use FatalError;
use File;
use IContextSource;
use IDBAccessObject;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use MWException;
use MWNamespace;
use OutputPage;
use Parser;
use Status;
use Title;
use User;
use WikiPage;

// @phan-file-suppress PhanUndeclaredMethod
// @phan-file-suppress PhanTypeMismatchArgumentReal
// @phan-file-suppress UnusedPluginFileSuppression
class CommentStreamsUtils {
	/**
	 * @param string $action
	 * @param User $user
	 * @param Title $title
	 * @return bool
	 */
	public static function userCan( string $action, User $user, Title $title ) : bool {
		if ( class_exists( '\MediaWiki\Permissions\PermissionManager' ) ) {
			// MW 1.33+
			return MediaWikiServices::getInstance()->getPermissionManager()->
				userCan( $action, $user, $title );
		}
		return $title->userCan( $action, $user );
	}

	/**
	 * @param User $user
	 * @param string $right
	 * @return bool
	 */
	public static function userHasRight( User $user, string $right ) : bool {
		if ( class_exists( '\MediaWiki\Permissions\PermissionManager' ) &&
			method_exists( '\MediaWiki\Permissions\PermissionManager', 'userHasRight' ) ) {
			// MW 1.34+
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			if ( !$permissionManager->userHasRight( $user, $right ) ||
				$user->getBlock() !== null ) {
				// This is not quite right, since it will prevent a user from commenting if they
				// are blocked from any action, which may be overly broad
				return false;
			}
		} elseif ( !in_array( $right, $user->getRights() ) || $user->isBlocked() ) {
			return false;
		}
		return true;
	}

	/**
	 * @param int $id Article ID to load
	 * @param string|int $from One of the following values:
	 *        - "fromdb" or WikiPage::READ_NORMAL to select from a replica DB
	 *        - "fromdbmaster" or WikiPage::READ_LATEST to select from the master database
	 * @return WikiPage|null
	 */
	public static function newWikiPageFromId( int $id, $from = 'fromdb' ) : ?WikiPage {
		if ( class_exists( '\MediaWiki\Page\WikiPageFactory' ) ) {
			// MW 1.36+
			return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $id, $from );
		}
		return WikiPage::newFromId( $id, $from );
	}

	/**
	 * @param string $wikitext
	 * @param OutputPage $outputPage
	 * @throws MWException
	 */
	public static function addWikiTextToOutputPage( string $wikitext, OutputPage $outputPage ) {
		if ( method_exists( 'OutputPage', 'addWikiTextAsInterface' ) ) {
			// MW 1.32+
			$outputPage->addWikiTextAsInterface( $wikitext );
		} else {
			$outputPage->addWikiText( $wikitext );
		}
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	public static function hasDeletedEdits( Title $title ) : bool {
		if ( method_exists( $title, 'hasDeletedEdits' ) ) {
			// MW 1.36+
			return $title->hasDeletedEdits();
		}
		return $title->isDeletedQuick();
	}

	/**
	 * @param string $filename
	 * @return bool|File
	 */
	public static function findFile( string $filename ) {
		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			return MediaWikiServices::getInstance()->getRepoGroup()->findFile( $filename );
		} else {
			return wfFindFile( $filename, [ 'latest' => true ] );
		}
	}

	/**
	 * @param int $id
	 * @param Title $title
	 * @return bool|string
	 */
	public static function getTimestampFromId( int $id, Title $title ) {
		$revStore = MediaWikiServices::getInstance()->getRevisionStore();
		if ( version_compare( MW_VERSION, '1.34', '<' ) ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
			return $revStore->getTimestampFromId( $title, $id );
		} else {
			return $revStore->getTimestampFromId( $id );
		}
	}

	/**
	 * @param Title $title
	 * @return ?string
	 */
	public static function getCreationTimestamp( Title $title ) : ?string {
		if ( class_exists( '\MediaWiki\Revision\RevisionStore' ) &&
			method_exists( '\MediaWiki\Revision\RevisionStore', 'getFirstRevision' ) ) {
			// MW 1.35+
			return MediaWikiServices::getInstance()->getRevisionStore()->
				getFirstRevision( $title )->getTimestamp();
		}
		return $title->getEarliestRevTime();
	}

	/**
	 * @param Title $title
	 * @return ?int
	 */
	public static function getFirstRevisionId( Title $title ) : ?int {
		if ( class_exists( '\MediaWiki\Revision\RevisionStore' ) &&
			method_exists( '\MediaWiki\Revision\RevisionStore', 'getFirstRevision' ) ) {
			// MW 1.35+
			$revisionRecord =
				MediaWikiServices::getInstance()->getRevisionStore()->getFirstRevision( $title );
			if ( $revisionRecord !== null ) {
				return $revisionRecord->getId();
			}
		} else {
			$revision = $title->getFirstRevision();
			if ( $revision !== null ) {
				return $revision->getId();
			}
		}
		return null;
	}

	/**
	 * @param Title $title
	 * @return ?User
	 */
	public static function getAuthor( Title $title ) : ?User {
		if ( class_exists( '\MediaWiki\Revision\RevisionStore' ) &&
			method_exists( '\MediaWiki\Revision\RevisionStore', 'getFirstRevision' ) ) {
			// MW 1.35+
			$revisionRecord =
				MediaWikiServices::getInstance()->getRevisionStore()->getFirstRevision( $title );
			if ( $revisionRecord !== null ) {
				return User::newFromId( $revisionRecord->getUser(
					\MediaWiki\Revision\RevisionRecord::RAW )->getId() );
			}
		} else {
			$revision = $title->getFirstRevision( Title::GAID_FOR_UPDATE );
			if ( $revision !== null ) {
				return User::newFromId( $revision->getUser() );
			}
		}
		return null;
	}

	/**
	 * @param WikiPage $wikipage
	 * @return ?UserIdentity
	 */
	public static function getLastEditor( WikiPage $wikipage ) : ?UserIdentity {
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$latestRevision = $revisionStore->getRevisionByTitle( $wikipage->getTitle(),
			0, IDBAccessObject::READ_LATEST );
		if ( $latestRevision !== null ) {
			if ( class_exists( '\MediaWiki\Revision\RevisionRecord' ) ) {
				return $latestRevision->getUser( \MediaWiki\Revision\RevisionRecord::RAW );
			} else {
				return $latestRevision->getUser( \MediaWiki\Storage\RevisionRecord::RAW );
			}
		}
		return null;
	}

	/**
	 * @param WikiPage $wikipage
	 * @param Content $content
	 * @param User $user
	 * @param int $flags
	 * @return Status
	 * @throws MWException
	 */
	public static function doEditContent(
		WikiPage $wikipage,
		Content $content,
		User $user,
		int $flags
	) : Status {
		return $wikipage->doEditContent(
			$content,
			'',
			$flags,
			false,
			$user,
			null );
	}

	/**
	 * @param WikiPage $wikipage
	 * @param string $reason
	 * @param User $deleter
	 * @return Status
	 * @throws FatalError
	 * @throws MWException
	 */
	public static function deDeleteArticle(
		WikiPage $wikipage,
		string $reason,
		User $deleter
	) : Status {
		if ( version_compare( MW_VERSION, '1.35', '<' ) ) {
			return $wikipage->doDeleteArticleReal( $reason, true );
		}
		return $wikipage->doDeleteArticleReal( $reason, $deleter, true );
	}

	/**
	 * @param int $namespace
	 * @return int
	 */
	public static function getSubjectNamespace( int $namespace ) : int {
		if ( class_exists( 'NamespaceInfo' ) ) {
			// MW 1.34+
			return MediaWikiServices::getInstance()->getNamespaceInfo()->getSubject( $namespace );
		}
		return MWNamespace::getSubject( $namespace );
	}

	/**
	 * @param string $wikitext
	 * @param WikiPage $wikipage
	 * @param IContextSource $context
	 * @return string
	 */
	public static function parse(
		string $wikitext,
		WikiPage $wikipage,
		IContextSource $context
	) : string {
		if ( class_exists( '\ParserFactory' ) ) {
			// MW 1.32+
			$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		} else {
			$parser = new Parser();
		}
		$parserOptions = $wikipage->makeParserOptions( $context );
		$parserOptions->setOption( 'enableLimitReport', false );
		return $parser
			->parse( $wikitext, $wikipage->getTitle(), $parserOptions )
			->getText( [ 'wrapperDivClass' => '' ] );
	}

	/**
	 * @param WikiPage $wikipage
	 * @return Content
	 */
	public static function getContent( WikiPage $wikipage ) : Content {
		if ( class_exists( '\MediaWiki\Revision\RevisionRecord' ) ) {
			return $wikipage->getContent( \MediaWiki\Revision\RevisionRecord::RAW );
		}
		return $wikipage->getContent( \MediaWiki\Storage\RevisionRecord::RAW );
	}
}
