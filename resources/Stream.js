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

module.exports = ( function () {
	'use strict';

	function Stream( controller, env, block, querier, commentData ) {
		this.controller = controller;
		this.env = env;
		this.block = block;
		this.querier = querier;

		this.$stream = null;
		this.pageId = null;
		this.$headCommentBody = null;
		this.$streamFooter = null;
		this.replyButton = null;
		this.upButton = null;
		this.$upCountSpan = null;
		this.downButton = null;
		this.$downCountSpan = null;
		this.watchButton = null;
		this.collapseButton = null;
		this.streamMenu = null;
		this.replyMenus = [];
		this.collapsed = false;

		this.createStream( commentData );
	}

	Stream.prototype.getStream = function () {
		return this.$stream;
	};

	Stream.prototype.getStreamFooter = function () {
		return this.$streamFooter;
	};

	Stream.prototype.enableButtons = function () {
		this.replyButton.setDisabled( false );
		if ( this.upButton ) {
			this.upButton.setDisabled( false );
		}
		if ( this.downButton ) {
			this.downButton.setDisabled( false );
		}
		this.streamMenu.setDisabled( false );
		this.replyMenus.forEach( function ( replyMenu ) {
			replyMenu.setDisabled( false );
		} );
	};

	Stream.prototype.disableButtons = function () {
		this.replyButton.setDisabled( true );
		if ( this.upButton ) {
			this.upButton.setDisabled( true );
		}
		if ( this.downButton ) {
			this.downButton.setDisabled( true );
		}
		this.streamMenu.setDisabled( true );
		this.replyMenus.forEach( function ( replyMenu ) {
			replyMenu.setDisabled( true );
		} );
	};

	Stream.prototype.createStream = function ( commentData ) {
		const self = this;

		const $headComment = this.createComment( commentData );

		const id = 'cs-comment-' + commentData.pageid;

		this.pageId = commentData.pageid;

		if ( this.env.targetComment === id ) {
			$headComment.addClass( 'cs-target-comment' );
		}

		this.$stream = $( '<div>' )
			.addClass( 'cs-stream' )
			.addClass( 'cs-expanded' )
			.attr( 'data-created-timestamp', commentData.created_timestamp )
			.append( $headComment );

		this.$streamFooter = $( '<div>' )
			.addClass( 'cs-stream-footer' );
		this.$stream.append( this.$streamFooter );

		if ( this.env.canComment ) {
			const params = {
				icon: 'ongoingConversation',
				framed: false
			};

			const buttonText = mw.msg( 'commentstreams-buttontext-reply' );
			if ( this.env.showLabels ) {
				params.label = buttonText;
			} else {
				params.title = buttonText;
			}

			this.replyButton = new OO.ui.ButtonWidget( params );

			this.$streamFooter.append( this.replyButton.$element );

			this.replyButton.onClick = function () {
				self.showNewReplyBox( commentData.pageid );
			};

			this.replyButton.on( 'click', this.replyButton.onClick );
		}
	};

	Stream.prototype.showNewReplyBox = function ( topCommentId ) {
		const self = this;

		this.block.createEditBox( false );
		this.block.$editBox
			.insertBefore( this.$streamFooter )
			.hide()
			.slideDown();

		this.block.submitButton.onClick = function () {
			self.block.postComment( topCommentId );
		};
		this.block.submitButton.on( 'click', this.block.submitButton.onClick );

		this.block.cancelButton.onClick = function () {
			self.block.hideEditBox( true );
		};
		this.block.cancelButton.on( 'click', this.block.cancelButton.onClick );

		self.block.disableAllButtons();

		this.block.$bodyField.trigger( 'focus' );

		if ( $.fn.applyVisualEditor ) {
		// VEForAll is installed.
			this.block.$bodyField.applyVisualEditor();
		}
	};

	Stream.prototype.addReply = function ( commentData ) {
		const $comment = this.createComment( commentData );
		$comment.insertBefore( this.$streamFooter );
		if ( this.collapsed ) {
			$comment.addClass( 'cs-hidden' );
		}
	};

	Stream.prototype.createComment = function ( commentData ) {
		const $commentHeader = $( '<div>' )
			.addClass( 'cs-comment-header' );

		const $leftDiv = $( '<div>' )
			.addClass( 'cs-comment-header-left' );
		if ( commentData.avatar !== null && commentData.avatar.length > 0 ) {
			const $avatar = $( '<img src="' + commentData.avatar + '" alt="' + commentData.username + '">' )
				.addClass( 'cs-avatar' );
			$leftDiv.append( $avatar );
		}
		$commentHeader.append( $leftDiv );

		const $centerDiv = $( '<div>' )
			.addClass( 'cs-comment-header-center' );

		if ( commentData.parentid === undefined ) {
			const $title = $( '<div>' )
				.addClass( 'cs-comment-title' )
				.text( commentData.commenttitle );
			$centerDiv.append( $title );
		}

		const $author = $( '<span>' )
			.addClass( 'cs-comment-author' )
			.html( commentData.userdisplayname );
		$centerDiv.append( $author );

		let dateText = ' | ' + mw.msg( 'commentstreams-datetext-postedon' ) +
		' ' + commentData.created;

		if ( commentData.modified !== null ) {
			dateText += ' | ' + mw.msg( 'commentstreams-datetext-lasteditedon' ) +
			' ' + commentData.modified;
			if ( commentData.moderated ) {
				dateText += ' (' + mw.msg( 'commentstreams-datetext-moderated' ) +
				')';
			}
		}

		const $date = $( '<span>' )
			.addClass( 'cs-comment-details' )
			.text( dateText );
		$centerDiv.append( $date );

		$commentHeader.append( $centerDiv );

		const $rightDiv = $( '<div>' )
			.addClass( 'cs-comment-header-right' );

		if ( commentData.parentid === undefined && this.env.enableVoting ) {
			$rightDiv.append( this.createVotingButtons( commentData ) );
		}

		$rightDiv.append( this.createCommentMenu( commentData ) );

		$commentHeader.append( $rightDiv );

		const $commentBody = $( '<div>' )
			.addClass( 'cs-comment-body' )
			.html( commentData.html );
		const $commentFooter = $( '<div>' )
			.addClass( 'cs-comment-footer' );

		const $comment = $( '<div>' )
			.addClass( 'cs-comment' );

		if ( commentData.parentid === undefined ) {
			$comment.addClass( 'cs-head-comment' );
			this.$headCommentBody = $commentBody;
		} else {
			$comment.addClass( 'cs-reply-comment' );
		}

		$comment.append( [ $commentHeader, $commentBody, $commentFooter ] );

		return $comment;
	};

	Stream.prototype.createVotingButtons = function ( commentData ) {
		const self = this;

		const upParams = {
			icon: 'upTriangle',
			title: mw.msg( 'commentstreams-buttontooltip-upvote' ),
			classes: [ 'cs-vote-up-button' ],
			framed: false
		};

		if ( commentData.vote > 0 ) {
			upParams.flags = 'progressive';
		}

		this.upButton = new OO.ui.ButtonWidget( upParams );

		this.$upCountSpan = $( '<span>' )
			.addClass( 'cs-vote-up-count' )
			.text( commentData.numupvotes );
		this.upButton.$element.append( this.$upCountSpan );

		const downParams = {
			icon: 'downTriangle',
			title: mw.msg( 'commentstreams-buttontooltip-downvote' ),
			classes: [ 'cs-vote-down-button' ],
			framed: false
		};

		if ( commentData.vote < 0 ) {
			downParams.flags = 'progressive';
		}

		this.downButton = new OO.ui.ButtonWidget( downParams );

		this.$downCountSpan = $( '<span>' )
			.addClass( 'cs-vote-down-count' )
			.text( commentData.numdownvotes );
		this.downButton.$element.append( this.$downCountSpan );

		const $votingSpan = $( '<span>' )
			.addClass( 'cs-voting-span' );
		$votingSpan.append( this.upButton.$element );
		$votingSpan.append( this.downButton.$element );

		if ( this.env.isLoggedIn ) {
			this.upButton.onClick = function () {
				self.vote( $votingSpan, commentData.pageid, true,
					commentData.created_timestamp );
			};

			this.upButton.on( 'click', this.upButton.onClick );

			this.downButton.onClick = function () {
				self.vote( $votingSpan, commentData.pageid, false,
					commentData.created_timestamp );
			};

			this.downButton.on( 'click', this.downButton.onClick );
		}

		return $votingSpan;
	};

	Stream.prototype.vote = function ( $votingSpan, pageid, up, createdTimestamp ) {
		const self = this;

		let upCount = parseInt( this.$upCountSpan.text() );
		let downCount = parseInt( this.$downCountSpan.text() );

		let newvote;
		if ( up ) {
			if ( this.upButton.hasFlag( 'progressive' ) ) {
				newvote = 0;
			} else {
				newvote = 1;
			}
		} else {
			if ( self.downButton.hasFlag( 'progressive' ) ) {
				newvote = 0;
			} else {
				newvote = -1;
			}
		}

		this.block.disableAllButtons();
		const progressBar = new OO.ui.ProgressBarWidget( {
			progress: false
		} );
		progressBar.$element.insertBefore( this.$headCommentBody );
		this.querier.vote( pageid, newvote, function ( result ) {
			progressBar.$element.remove();
			if ( result === undefined ) {
				if ( up ) {
					if ( self.upButton.hasFlag( 'progressive' ) ) {
						self.upButton.clearFlags();
						upCount = upCount - 1;
						self.$upCountSpan.text( upCount );
					} else {
						self.upButton.setFlags( 'progressive' );
						upCount = upCount + 1;
						self.$upCountSpan.text( upCount );
						if ( self.downButton.hasFlag( 'progressive' ) ) {
							self.downButton.clearFlags();
							downCount = downCount - 1;
							self.$downCountSpan.text( downCount );
						}
					}
				} else {
					if ( self.downButton.hasFlag( 'progressive' ) ) {
						self.downButton.clearFlags();
						downCount = downCount - 1;
						self.$downCountSpan.text( downCount );
					} else {
						self.downButton.setFlags( 'progressive' );
						downCount = downCount + 1;
						self.$downCountSpan.text( downCount );
						if ( self.upButton.hasFlag( 'progressive' ) ) {
							self.upButton.clearFlags();
							upCount = upCount - 1;
							self.$upCountSpan.text( upCount );
						}
					}
				}
				const voteDiff = upCount - downCount;
				self.adjustCommentOrder( voteDiff, upCount, createdTimestamp );
			} else {
				self.reportError( result.error );
			}
			self.block.enableAllButtons();
		} );
	};

	Stream.prototype.createCommentMenu = function ( commentData ) {
		const self = this;

		const items = this.getCommentMenuItems( commentData );

		const menu = new OO.ui.ButtonMenuSelectWidget( {
			icon: 'ellipsis',
			label: 'More options',
			invisibleLabel: true,
			framed: false,
			classes: [ 'cs-comment-menu' ],
			$overlay: $( document.body ),
			menu: {
				width: 200,
				horizontalPosition: 'end',
				items: items
			}
		} );

		if ( commentData.parentid === undefined ) {
			this.streamMenu = menu;
		} else {
			this.replyMenus.push( menu );
		}

		menu.onChoose = function ( item ) {
			const $comment = menu.$element.closest( '.cs-comment' );
			const pageId = commentData.pageid;
			const id = 'cs-comment-' + pageId;

			switch ( item.getData() ) {
				case 'edit':
					self.editComment( $comment, pageId );
					break;
				case 'delete':
					self.deleteComment( $comment, pageId );
					break;
				case 'watch':
					self.watch( item.$element, pageId );
					break;
				case 'collapse':
					if ( self.collapsed ) {
						self.expand();
					} else {
						self.collapse();
					}
					break;
				case 'link':
					$( '.cs-target-comment' ).removeClass( 'cs-target-comment' );
					self.controller.scrollToElement( $comment );
					$comment.addClass( 'cs-target-comment' );
					window.location.hash = '#' + id;
					break;
			}
		};

		menu.menu.on( 'choose', menu.onChoose );

		return menu.$element;
	};

	Stream.prototype.recreateStreamMenu = function ( commentData ) {
		this.streamMenu.menu.clearItems();
		this.streamMenu.menu.addItems( this.getCommentMenuItems( commentData ) );
	};

	Stream.prototype.getCommentMenuItems = function ( commentData ) {
		const items = [];
		if ( this.canEdit( commentData ) ) {
			items.push( this.createEditButton( commentData.username ) );
		}

		if ( this.canDelete( commentData ) ) {
			items.push( this.createDeleteButton( commentData.username ) );
		}

		if ( commentData.parentid === undefined ) {
			if ( this.env.enableWatchlist && this.env.isLoggedIn ) {
				items.push( this.createWatchButton( commentData ) );
			}

			items.push( this.createCollapseButton() );
		}

		items.push( this.createPermalinkButton( commentData.pageid ) );

		return items;
	};

	Stream.prototype.canEdit = function ( commentData ) {
		return ( this.env.isLoggedIn &&
		( this.env.username === commentData.username || this.env.moderatorEdit ) );
	};

	Stream.prototype.canDelete = function ( commentData ) {
		return ( this.env.isLoggedIn &&
		( this.env.username === commentData.username || this.env.moderatorDelete ) &&
		( commentData.numreplies === 0 || commentData.numreplies === undefined || this.env.moderatorFastDelete ) );
	};

	Stream.prototype.createEditButton = function ( username ) {
		const params = {
			icon: 'edit',
			data: 'edit'
		};

		if ( this.env.username !== username ) {
			params.label = mw.msg( 'commentstreams-buttontooltip-moderator-edit' );
		} else {
			params.label = mw.msg( 'commentstreams-buttontooltip-edit' );
		}

		return new OO.ui.MenuOptionWidget( params );
	};

	Stream.prototype.createDeleteButton = function ( username ) {
		const params = {
			icon: 'trash',
			flags: 'destructive',
			data: 'delete'
		};

		if ( this.env.username !== username ) {
			params.label = mw.msg( 'commentstreams-buttontooltip-moderator-delete' );
		} else {
			params.label = mw.msg( 'commentstreams-buttontooltip-delete' );
		}

		return new OO.ui.MenuOptionWidget( params );
	};

	Stream.prototype.createWatchButton = function ( commentData ) {
		const params = {
			data: 'watch'
		};

		if ( commentData.watching ) {
			params.icon = 'unStar';
			params.flags = 'progressive';
			params.label = mw.msg( 'commentstreams-buttontooltip-unwatch' );
			params.classes = [ 'cs-comment-watching' ];
		} else {
			params.icon = 'star';
			params.label = mw.msg( 'commentstreams-buttontooltip-watch' );
		}

		this.watchButton = new OO.ui.MenuOptionWidget( params );
		return this.watchButton;
	};

	Stream.prototype.createCollapseButton = function () {
		this.collapseButton = new OO.ui.MenuOptionWidget( {
			icon: 'collapse',
			label: mw.msg( 'commentstreams-buttontooltip-collapse' ),
			classes: [ 'cs-collapse-button' ],
			data: 'collapse'
		} );
		return this.collapseButton;
	};

	Stream.prototype.createPermalinkButton = function () {
		return new OO.ui.MenuOptionWidget( {
			icon: 'link',
			label: mw.msg( 'commentstreams-buttontooltip-permalink' ),
			data: 'link'
		} );
	};

	Stream.prototype.watch = function ( button, pageid ) {
		const self = this;

		const watch = this.watchButton.getIcon() === 'star';
		self.block.disableAllButtons();
		const progressBar = new OO.ui.ProgressBarWidget( {
			progress: false
		} );
		progressBar.$element.insertBefore( this.$headCommentBody );
		this.querier.watch( pageid, watch, function ( result ) {
			progressBar.$element.remove();
			if ( result === undefined ) {
				if ( watch ) {
					self.watchButton.setIcon( 'unStar' );
					self.watchButton.setFlags( 'progressive' );
					self.watchButton.setLabel( mw.msg( 'commentstreams-buttontooltip-unwatch' ) );
					self.watchButton.$element.addClass( 'cs-comment-watching' );
				} else {
					self.watchButton.setIcon( 'star' );
					self.watchButton.setLabel( mw.msg( 'commentstreams-buttontooltip-watch' ) );
					self.watchButton.$element.removeClass( 'cs-comment-watching' );
				}
			} else {
				self.reportError( result.error );
			}
			self.block.enableAllButtons();
		} );
	};

	Stream.prototype.collapse = function () {
		this.$stream.find( '.cs-head-comment .cs-comment-body' ).addClass( 'cs-hidden' );
		this.$stream.find( '.cs-reply-comment' ).addClass( 'cs-hidden' );
		this.$streamFooter.addClass( 'cs-hidden' );
		this.collapseButton.setIcon( 'expand' );
		this.collapseButton.setLabel( mw.msg( 'commentstreams-buttontooltip-expand' ) );
		this.$stream.removeClass( 'cs-expanded' );
		this.$stream.addClass( 'cs-collapsed' );
		this.collapsed = true;
	};

	Stream.prototype.expand = function () {
		this.$stream.find( '.cs-head-comment .cs-comment-body' ).removeClass( 'cs-hidden' );
		this.$stream.find( '.cs-reply-comment' ).removeClass( 'cs-hidden' );
		this.$streamFooter.removeClass( 'cs-hidden' );
		this.collapseButton.setIcon( 'collapse' );
		this.collapseButton.setLabel( mw.msg( 'commentstreams-buttontooltip-collapse' ) );
		this.$stream.removeClass( 'cs-collapsed' );
		this.$stream.addClass( 'cs-expanded' );
		this.collapsed = false;
	};

	Stream.prototype.adjustCommentOrder = function (
		voteDiff,
		upCount,
		createdTimestamp
	) {
		const $nextSiblings = this.$stream.nextAll( '.cs-stream' );
		let first = true;
		let index;
		for ( index = 0; index < $nextSiblings.length; index++ ) {
			const $nextSibling = $( $nextSiblings[ index ] );
			const $nextUpCountSpan = $nextSibling.find( '.cs-vote-up-count' );
			const nextUpCount = parseInt( $nextUpCountSpan.text() );
			const $nextDownCountSpan = $nextSibling.find( '.cs-vote-down-count' );
			const nextDownCount = parseInt( $nextDownCountSpan.text() );
			const nextVoteDiff = nextUpCount - nextDownCount;
			if ( nextVoteDiff > voteDiff ) {
			// keeping looking
			} else if ( nextVoteDiff === voteDiff ) {
				if ( nextUpCount > upCount ) {
				// keeping looking
				} else if ( nextUpCount === upCount ) {
					const nextCreatedTimestamp = $nextSibling.attr( 'data-created-timestamp' );
					if ( this.env.newestStreamsOnTop ) {
						if ( nextCreatedTimestamp > createdTimestamp ) {
						// keeping looking
						} else if ( first ) {
						// check previous siblings
							break;
						} else {
							this.moveStream( true, $nextSibling );
							return;
						}
					} else if ( nextCreatedTimestamp < createdTimestamp ) {
					// keep looking
					} else if ( first ) {
					// check previous siblings
						break;
					} else {
						this.moveStream( true, $nextSibling );
						return;
					}
				} else if ( first ) {
				// check previous siblings
					break;
				} else {
					this.moveStream( true, $nextSibling );
					return;
				}
			} else if ( first ) {
			// check previous siblings
				break;
			} else {
				this.moveStream( true, $nextSibling );
				return;
			}
			first = false;
		}
		if ( !first ) {
			this.moveStream( false, $( $nextSiblings[ $nextSiblings.length - 1 ] ) );
			return;
		}
		const $prevSiblings = this.$stream.prevAll( '.cs-stream' );
		first = true;
		for ( index = 0; index < $prevSiblings.length; index++ ) {
			const $prevSibling = $( $prevSiblings[ index ] );
			const $prevUpCountSpan =
			$prevSibling.find( '.cs-vote-up-count' );
			const prevUpCount = parseInt( $prevUpCountSpan.text() );
			const $prevDownCountSpan =
			$prevSibling.find( '.cs-vote-down-count' );
			const prevDownCount = parseInt( $prevDownCountSpan.text() );
			const prevVoteDiff = prevUpCount - prevDownCount;
			if ( prevVoteDiff < voteDiff ) {
			// keeping looking
			} else if ( prevVoteDiff === voteDiff ) {
				if ( prevUpCount < upCount ) {
				// keeping looking
				} else if ( prevUpCount === upCount ) {
					const prevCreatedTimestamp =
					$prevSibling.attr( 'data-created-timestamp' );
					if ( this.env.newestStreamsOnTop ) {
						if ( prevCreatedTimestamp < createdTimestamp ) {
						// keeping looking
						} else if ( first ) {
						// done
							break;
						} else {
							this.moveStream( false, $prevSibling );
							return;
						}
					} else if ( prevCreatedTimestamp > createdTimestamp ) {
					// keeping looking
					} else if ( first ) {
					// done
						break;
					} else {
						this.moveStream( false, $prevSibling );
						return;
					}
				} else if ( first ) {
				// done
					break;
				} else {
					this.moveStream( false, $prevSibling );
					return;
				}
			} else if ( first ) {
			// done
				break;
			} else {
				this.moveStream( false, $prevSibling );
				return;
			}
			first = false;
		}
		if ( !first ) {
			this.moveStream( true, $( $prevSiblings[ $prevSiblings.length - 1 ] )
			);
		}
	// otherwise, the comment was in the correct place already
	};

	Stream.prototype.moveStream = function ( before, $location ) {
		const self = this;
		this.$stream.slideUp( 1000, function () {
			self.$stream.detach();
			self.$stream.hide();
			if ( before ) {
				self.$stream.insertBefore( $location );
			} else {
				self.$stream.insertAfter( $location );
			}
			self.$stream.slideDown( 1000, function () {
				self.block.enableAllButtons();
				self.controller.scrollToElement( self.$stream.find( '.cs-head-comment:first' ) );
			} );
		} );
	};

	Stream.prototype.deleteComment = function ( element, pageId ) {
		const self = this;
		const messageText = mw.msg( 'commentstreams-dialog-delete-message' );
		const yesText = mw.msg( 'commentstreams-dialog-buttontext-yes' );
		const noText = mw.msg( 'commentstreams-dialog-buttontext-no' );
		const dialog = new OO.ui.MessageDialog();
		const windowManager = new OO.ui.WindowManager();
		this.block.$commentDiv.append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog, {
			message: messageText,
			actions: [
				{ label: yesText, action: 'yes' },
				{ label: noText, flags: 'primary' }
			]
		} ).closed.then( function ( data ) {
			if ( data && data.action && data.action === 'yes' ) {
				self.realDeleteComment( element, pageId );
			}
		} );
	};

	Stream.prototype.realDeleteComment = function ( $element, pageId ) {
		const self = this;
		self.block.disableAllButtons();

		let $fadeElement = $element;
		if ( this.pageId === pageId && self.moderatorFastDelete ) {
			$fadeElement = this.$stream;
		}
		$fadeElement.fadeTo( 100, 0.2, function () {
			const progressBar = new OO.ui.ProgressBarWidget( {
				progress: false
			} );
			progressBar.$element.insertAfter( $element );
			if ( $element.hasClass( 'cs-head-comment' ) ) {
				self.querier.deleteComment( pageId, function ( result ) {
					progressBar.$element.remove();
					if ( result === undefined ||
						result.error === 'commentstreams-api-error-commentnotfound' ) {
						self.$stream
							.slideUp( 'normal', function () {
								self.$stream.remove();
								self.block.enableAllButtons();
								delete self.block[ self.pageId ];
							} );
					} else {
						self.reportError( result.error );
						$fadeElement.fadeTo( 0.2, 100, function () {
							self.block.enableAllButtons();
						} );
					}
				} );
			} else {
				self.querier.deleteReply( pageId, function ( result ) {
					progressBar.$element.remove();
					if ( result === undefined ||
						result.error === 'commentstreams-api-error-commentnotfound' ) {
						self.querier.queryComment( self.pageId, function ( queryResult ) {
							if ( queryResult.error === undefined ) {
								self.recreateStreamMenu( queryResult );
							}
							$element.slideUp( 'normal', function () {
								$element.remove();
								self.block.enableAllButtons();
							} );
						} );
					} else {
						self.reportError( result.error );
						$fadeElement.fadeTo( 0.2, 100, function () {
							self.block.enableAllButtons();
						} );
					}
				} );
			}
		} );
	};

	Stream.prototype.editComment = function ( $element, pageId ) {
		const self = this;

		self.block.disableAllButtons();

		let isStream = false;
		if ( $element.hasClass( 'cs-head-comment' ) ) {
			isStream = true;
		}

		$element.fadeTo( 100, 0.2, function () {
			const progressBar = new OO.ui.ProgressBarWidget( {
				progress: false
			} );
			progressBar.$element.insertAfter( $element );

			if ( isStream ) {
				self.querier.queryComment( pageId, function ( result ) {
					progressBar.$element.remove();

					if ( result.error === undefined ) {
						self.block.createEditBox( isStream );
						$element.hide();
						self.block.$editBox
							.insertAfter( $element )
							.hide()
							.slideDown();

						self.block.$bodyField.val( $( '<textarea>' ).html( result.wikitext ).text() );
						if ( isStream ) {
							self.block.$titleField.val( result.commenttitle );
							self.block.$titleField.trigger( 'focus' );
						} else {
							self.block.$bodyField.trigger( 'focus' );
						}
						if ( $.fn.applyVisualEditor ) {
							// VEForAll is installed.
							self.block.$bodyField.applyVisualEditor();
						}

						self.block.submitButton.onClick = function () {
							if ( self.block.$bodyField.css( 'display' ) === 'none' ) {
								self.editCommentFromVE( $element, pageId );
							} else {
								const commentText = self.block.$bodyField.val();
								self.realEditComment( $element, pageId, commentText );
							}
						};
						self.block.submitButton.on( 'click', self.block.submitButton.onClick );

						self.block.cancelButton.onClick = function () {
							self.block.$editBox.slideUp( 'normal', function () {
								$element.fadeTo( 0.2, 100, function () {
									self.block.$editBox.remove();
									self.block.enableAllButtons();
								} );
							} );
						};
						self.block.cancelButton.on( 'click', self.block.cancelButton.onClick );
					} else if ( result.error === 'commentstreams-api-error-commentnotfound' ) {
						self.reportError( result.error );
						self.querier.queryComment( self.pageId, function ( queryResult ) {
							if ( queryResult.error === undefined ) {
								self.recreateStreamMenu( queryResult );
							} else {
								self.reportError( queryResult.error );
							}
							$element.remove();
							self.block.enableAllButtons();
						} );
					} else {
						self.reportError( result.error );
						$element.fadeTo( 0.2, 100, function () {
							self.block.enableAllButtons();
						} );
					}
				} );
			} else {
				self.querier.queryReply( pageId, function ( result ) {
					progressBar.$element.remove();

					if ( result.error === undefined ) {
						self.block.createEditBox( isStream );
						$element.hide();
						self.block.$editBox
							.insertAfter( $element )
							.hide()
							.slideDown();

						self.block.$bodyField.val( $( '<textarea>' ).html( result.wikitext ).text() );
						if ( isStream ) {
							self.block.$titleField.val( result.commenttitle );
							self.block.$titleField.trigger( 'focus' );
						} else {
							self.block.$bodyField.trigger( 'focus' );
						}
						if ( $.fn.applyVisualEditor ) {
							// VEForAll is installed.
							self.block.$bodyField.applyVisualEditor();
						}

						self.block.submitButton.onClick = function () {
							if ( self.block.$bodyField.css( 'display' ) === 'none' ) {
								self.editCommentFromVE( $element, pageId );
							} else {
								const commentText = self.block.$bodyField.val();
								self.realEditComment( $element, pageId, commentText );
							}
						};
						self.block.submitButton.on( 'click', self.block.submitButton.onClick );

						self.block.cancelButton.onClick = function () {
							self.block.$editBox.slideUp( 'normal', function () {
								$element.fadeTo( 0.2, 100, function () {
									self.block.$editBox.remove();
									self.block.enableAllButtons();
								} );
							} );
						};
						self.block.cancelButton.on( 'click', self.block.cancelButton.onClick );
					} else if ( result.error === 'commentstreams-api-error-commentnotfound' ) {
						self.reportError( result.error );
						self.querier.queryReply( self.pageId, function ( queryResult ) {
							if ( queryResult.error === undefined ) {
								self.recreateStreamMenu( queryResult );
							} else {
								self.reportError( queryResult.error );
							}
							$element.remove();
							self.block.enableAllButtons();
						} );
					} else {
						self.reportError( result.error );
						$element.fadeTo( 0.2, 100, function () {
							self.block.enableAllButtons();
						} );
					}
				} );
			}
		} );
	};

	Stream.prototype.editCommentFromVE = function ( element, pageId ) {
		const self = this;
		const veInstances = this.block.$bodyField.getVEInstances();
		const curVEEditor = veInstances[ veInstances.length - 1 ];
		new mw.Api().post( {
			action: 'veforall-parsoid-utils',
			from: 'html',
			to: 'wikitext',
			content: curVEEditor.target.getSurface().getHtml(),
			title: mw.config.get( 'wgPageName' ).split( /([\\/])/g ).pop()
		} ).then( function ( data ) {
			const commentText = data[ 'veforall-parsoid-utils' ].content;
			self.realEditComment( element, pageId, commentText );
		} )
			.fail( function () {
				self.reportError( 'commentstreams-ve-conversion-error' );
			} );
	};

	Stream.prototype.realEditComment = function ( $element, pageId, commentText ) {
		const self = this;

		let isStream = false;
		if ( $element.hasClass( 'cs-head-comment' ) ) {
			isStream = true;
		}

		let commentTitle = null;
		if ( isStream ) {
			commentTitle = self.block.$titleField.val();
			if ( commentTitle === null || commentTitle.trim() === '' ) {
				self.reportError(
					'commentstreams-validation-error-nocommenttitle' );
				return;
			}
		}

		if ( commentText === null || commentText.trim() === '' ) {
			self.reportError(
				'commentstreams-validation-error-nocommenttext' );
			return;
		}

		this.block.submitButton.setDisabled( true );
		this.block.cancelButton.setDisabled( true );

		this.block.$editBox.fadeTo( 100, 0.2, function () {
			const progressBar = new OO.ui.ProgressBarWidget( {
				progress: false
			} );
			progressBar.$element.insertAfter( self.block.$editBox );

			if ( isStream ) {
				self.querier.editComment(
					commentTitle,
					commentText,
					pageId,
					function ( result ) {
						progressBar.$element.remove();
						if ( result.error === undefined ) {
							const $comment = self.createComment( result );
							if ( self.collapsed ) {
								$comment.find( '.cs-comment-body' ).addClass( 'cs-hidden' );
							}
							self.block.$editBox.slideUp( 'normal', function () {
								$comment.insertAfter( self.block.$editBox );
								self.block.$editBox.remove();
								self.block.$editBox = null;
								$element.remove();
								self.block.enableAllButtons();
								self.controller.scrollToElement( $comment );
							} );
						} else if ( result.error === 'commentstreams-api-error-commentnotfound' ) {
							self.reportError( result.error );
							self.querier.queryComment( self.pageId, function ( queryResult ) {
								if ( queryResult.error === undefined ) {
									self.recreateStreamMenu( queryResult );
								} else {
									self.reportError( queryResult.error );
								}
								self.block.$editBox.slideUp( 'normal', function () {
									self.block.$editBox.remove();
									self.block.$editBox = null;
									$element.remove();
									self.block.enableAllButtons();
								} );
							} );
						} else {
							self.reportError( result.error );
							self.block.$editBox.fadeTo( 0.2, 100, function () {
								self.block.submitButton.setDisabled( false );
								self.block.cancelButton.setDisabled( false );
							} );
						}
					} );
			} else {
				self.querier.editReply(
					commentText,
					pageId,
					function ( result ) {
						progressBar.$element.remove();
						if ( result.error === undefined ) {
							const $comment = self.createComment( result );
							if ( self.collapsed ) {
								$comment.find( '.cs-comment-body' ).addClass( 'cs-hidden' );
							}
							self.block.$editBox.slideUp( 'normal', function () {
								$comment.insertAfter( self.block.$editBox );
								self.block.$editBox.remove();
								self.block.$editBox = null;
								$element.remove();
								self.block.enableAllButtons();
								self.controller.scrollToElement( $comment );
							} );
						} else if ( result.error === 'commentstreams-api-error-commentnotfound' ) {
							self.block.$editBox.slideUp( 'normal', function () {
								self.block.$editBox.remove();
								self.block.$editBox = null;
								$element.remove();
								self.block.enableAllButtons();
							} );
						} else {
							self.reportError( result.error );
							self.block.$editBox.fadeTo( 0.2, 100, function () {
								self.block.submitButton.setDisabled( false );
								self.block.cancelButton.setDisabled( false );
							} );
						}
					} );
			}
		} );
	};

	Stream.prototype.reportError = function ( message ) {
	/* eslint-disable mediawiki/msg-doc */
		let messageText = message;
		const mwmessage = mw.message( message );
		if ( mwmessage.exists() ) {
			messageText = mwmessage.text();
		}
		const okText = mw.msg( 'commentstreams-dialog-buttontext-ok' );
		const dialog = new OO.ui.MessageDialog();
		const windowManager = new OO.ui.WindowManager();
		this.block.$commentDiv.append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog, {
			message: messageText,
			actions: [ {
				action: 'accept',
				label: okText,
				flags: 'primary'
			} ]
		} );
	};

	return Stream;
}() );
