<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CommentStreams\Log;

use ManualLogEntry;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CommentStreams\Comment;
use MediaWiki\Extension\CommentStreams\Reply;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MWException;

class CommentStreamsLogFactory {

	public const CONSTRUCTOR_OPTIONS = [
		'CommentStreamsSuppressLogsFromRCs'
	];

	private readonly bool $suppressLogsFromRCs;

	public function __construct( ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->suppressLogsFromRCs = (bool)$options->get( 'CommentStreamsSuppressLogsFromRCs' );
	}

	/**
	 * Log an action
	 * @param string $action the name of the action to be logged
	 * @param User $user the user taking the action
	 * @param LinkTarget|PageReference $target the page related to the action
	 * @param array $params log params to add
	 * @throws MWException
	 */
	public function logAction( string $action, User $user, $target, array $params = [] ): void {
		$logEntry = new ManualLogEntry( 'commentstreams', $action );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( $target );
		$logEntry->setParameters( $params );
		$logId = $logEntry->insert();

		if ( !$this->suppressLogsFromRCs ) {
			$logEntry->publish( $logId );
		}
	}

	/**
	 * Log an action related to a comment
	 * @param string $action the name of the action to be logged
	 * @param User $user the user taking the action
	 * @param Comment $comment the comment related to the action
	 * @param array $params additional log params to add
	 * @throws MWException
	 */
	public function logCommentAction( string $action, User $user, Comment $comment, array $params = [] ): void {
		$logTarget = Title::castFromPageIdentity( $comment->getAssociatedPage() );
		$logTarget->setFragment( 'cs-comment-' . $comment->getId() );

		$params = array_merge( $params, [
			'4::commentLinkTarget' => wfEscapeWikiText( $logTarget->getFullText() ),
			'5::commentName' => wfEscapeWikiText( $comment->getTitle() ),
		] );

		$this->logAction( $action, $user, $logTarget, $params );
	}

	/**
	 * Log an action related to a reply
	 * @param string $action the name of the action to be logged
	 * @param User $user the user taking the action
	 * @param Reply $reply the reply related to the action
	 * @throws MWException
	 */
	public function logReplyAction( string $action, User $user, Reply $reply ): void {
		$replyLinkTarget = Title::castFromPageIdentity( $reply->getAssociatedPage() );
		$replyLinkTarget->setFragment( 'cs-comment-' . $reply->getId() );

		$this->logCommentAction( $action, $user, $reply->getParent(), [
			'6::replyLinkTarget' => wfEscapeWikiText( $replyLinkTarget->getFullText() ),
		] );
	}

}
