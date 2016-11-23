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

var commentstreams_controller = ( function( mw, $ ) {
	'use strict';

	return {
		username: null,
		isLoggedIn: false,
		userDisplayName: null,
		newestStreamsOnTop: false,
		initiallyCollapsed: false,
		comments: [],
		newCommentStreamShowing: false,
		spinnerOptions: {
			lines: 11, // The number of lines to draw
			length: 8, // The length of each line
			width: 4, // The line thickness
			radius: 8, // The radius of the inner circle
			corners: 1, // Corner roundness (0..1)
			rotate: 0, // The rotation offset
			direction: 1, // 1: clockwise, -1: counterclockwise
			color: '#000', // #rgb or #rrggbb or array of colors
			speed: 1, // Rounds per second
			trail: 60, // ƒfterglow percentage
			shadow: false, // Whether to render a shadow
			hwaccel: false, // Whether to use hardware acceleration
			className: 'spinner', // The CSS class to assign to the spinner
			zIndex: 2e9, // The z-index (defaults to 2000000000)
			top: '50%', // Top position relative to parent
			left: '50%' // Left position relative to parent
		},
		initialize: function() {
			var self = this;
			this.username = mw.config.get( 'wgUserName' );
			this.isLoggedIn = mw.config.get( 'wgUserName' ) !== null;
			var config = mw.config.get( 'CommentStreams' );
			this.userDisplayName = config.userDisplayName;
			this.newestStreamsOnTop = config.newestStreamsOnTop === 1 ? true : false;
			this.initiallyCollapsed = config.initiallyCollapsed;
			this.comments = config.comments;
			this.setupDivs();
			this.addInitialComments();
		},
		setupDivs: function() {
			var self = this;

			var mainDiv = $( '<div>' ).attr( 'id', 'cs-comments' );

			var headerDiv = $( '<div> ').attr( 'id', 'cs-header');
			mainDiv.append( headerDiv );

			var footerDiv = $( '<div> ').attr( 'id', 'cs-footer');
			mainDiv.append( footerDiv );

			if ( this.isLoggedIn ) {
				var addButton = $( '<button>' )
					.attr({
						type: 'button',
						id: 'cs-add-button'
					})
					.addClass( 'cs-button' )
					.text( mw.message( 'commentstreams-buttontext-add' ) );

				if ( this.newestStreamsOnTop ) {
					headerDiv.append( addButton );
				} else {
					footerDiv.append( addButton );
				}

				addButton.click( function() {
					self.showNewCommentStreamBox();
				} );
			}

			mainDiv.insertAfter( '#catlinks' );
		},
		addInitialComments: function() {
			var self = this;
			var parentIndex;
			for ( parentIndex in this.comments ) {
				var parentComment = this.comments[ parentIndex ];
				var commenthtml = this.formatComment( parentComment );
				var location = $( commenthtml )
					.insertBefore( '#cs-footer' );
				var childIndex;
				for ( childIndex in parentComment.children ) {
					var childComment = parentComment.children[ childIndex ];
					commenthtml = this.formatComment( childComment );
					$( commenthtml ).insertBefore(
						$( location ).find( '.cs-stream-footer' ) );
				}
			}

			if ( this.initiallyCollapsed ) {
				$( '.cs-stream' ).each( function() {
					self.collapseStream( $( this ), $( this )
						.find( '.cs-toggle-button' ) );
				} );
			}
		},
		collapseStream: function( stream, button ) {
			stream.find( '.cs-reply-comment' ).hide();
			stream.find( '.cs-head-comment .cs-comment-body' ).hide();
			stream.find( '.cs-stream-footer .cs-reply-button' ).hide();
			stream.find( '.cs-comment-header .cs-edit-button' )
				.attr( 'disabled', 'disabled' );
			stream.find( '.cs-comment-header .cs-delete-button' )
				.attr( 'disabled', 'disabled' );
			$( stream ).addClass( 'cs-collapsed' );
			$( stream ).removeClass( 'cs-expanded' );
			$( button ).text( mw.message( 'commentstreams-buttontext-expand' ) );
		},
		expandStream: function( stream, button ) {
			stream.find( '.cs-reply-comment' ).show();
			stream.find( '.cs-head-comment .cs-comment-body' ).show();
			stream.find( '.cs-stream-footer .cs-reply-button' ).show();
			stream.find( '.cs-comment-header .cs-edit-button' )
				.attr( 'disabled', false );
			stream.find( '.cs-comment-header .cs-delete-button' )
				.attr( 'disabled', false );
			$( stream ).addClass( 'cs-expanded' );
			$( stream ).removeClass( 'cs-collapsed' );
			$( button ).text( mw.message( 'commentstreams-buttontext-collapse' ) );
		},
		disableAllButtons: function() {
			$( '.cs-edit-button' ).attr( 'disabled', 'disabled' );
			$( '.cs-reply-button' ).attr( 'disabled', 'disabled' );
			$( '#cs-add-button' ).attr( 'disabled', 'disabled' );
			$( '.cs-delete-button' ).attr( 'disabled', 'disabled' );
			$( '.cs-toggle-button' ).attr( 'disabled', 'disabled' );
		},
		enableAllButtons: function() {
			$( '.cs-edit-button' ).attr( 'disabled', false );
			$( '.cs-reply-button' ).attr( 'disabled', false );
			$( '#cs-add-button' ).attr( 'disabled', false );
			$( '.cs-delete-button' ).attr( 'disabled', false );
			$( '.cs-toggle-button' ).attr( 'disabled', false );
		},
		formatComment: function( commentData ) {
			var self = this;
			var comment = this.formatCommentInner( commentData );

			if ( commentData.parentid === null ) {
				comment = $( '<div>' )
					.addClass( 'cs-stream' )
					.addClass( 'cs-expanded' )
					.append( comment );

				var streamFooter = $( '<div>' )
					.addClass( 'cs-stream-footer' );
				comment.append( streamFooter );

				if ( this.isLoggedIn ) {
					var replyButton = $( '<button>' )
						.addClass( 'cs-button' )
						.addClass( 'cs-reply-button' )
						.attr({
							type: 'button',
							'data-stream-id': commentData.pageid
						})
						.text( mw.message( 'commentstreams-buttontext-reply' ) );
					streamFooter.append( replyButton );
					replyButton.click( function() {
						var pageId = $( this ).attr( 'data-stream-id' );
						self.showNewReplyBox( $( this ), pageId );
					} );
				}
			}

			return comment;
		},
		formatCommentInner: function( commentData ) {
			var self = this;
			var commentHeader = $( '<div>' )
				.addClass( 'cs-comment-header' );

			if ( commentData.parentid === null ) {
				var collapseButton = $( '<button>' )
					.addClass( 'cs-button' )
					.addClass( 'cs-toggle-button' )
					.attr( 'type', 'button' )
					.text( mw.message( 'commentstreams-buttontext-collapse' ) );
				commentHeader.append( collapseButton );
				collapseButton.click( function() {
					var stream = $( this ).closest( '.cs-stream' );
					if ( stream.hasClass( 'cs-expanded' ) ) {
						self.collapseStream( stream, this );
					} else {
						self.expandStream( stream, this );
					}
				} );

				var title = $( '<div>' )
					.addClass( 'cs-comment-title' )
					.text( commentData.commenttitle );
				commentHeader.append( title );
			}

			if ( commentData.avatar !== null && commentData.avatar.length > 0 ) {
				var avatar = $( '<img>' )
					.addClass( 'cs-avatar' )
					.attr( 'src', commentData.avatar );
				commentHeader.append( avatar );
			}

			var author = $( '<span>' )
				.addClass( 'cs-comment-author' )
				.html( commentData.userdisplayname );
			commentHeader.append( author );

			var created = $( '<span>' )
				.addClass( 'cs-comment-details' )
				.text( mw.message( 'commentstreams-datetext-postedon' ) +
				' ' + commentData.created );
			commentHeader.append( this.createDivider() );
			commentHeader.append( created );

			if ( commentData.modified !== null ) {
				var modified = $( '<span>' )
					.addClass( 'cs-comment-details' )
					.text( mw.message( 'commentstreams-datetext-lasteditedon' ) +
					' ' + commentData.modified );
				commentHeader.append( this.createDivider() );
				commentHeader.append( modified );
			}

			if ( this.username === commentData.username ) {
				var editButton = $( '<button>' )
					.addClass( 'cs-button' )
					.addClass( 'cs-edit-button' )
					.attr( 'type', 'button' )
					.text( mw.message( 'commentstreams-buttontext-edit' ) );
				commentHeader.append( this.createDivider() );
				commentHeader.append( editButton );
				editButton.click( function() {
					var comment = $( this ).closest( '.cs-comment' );
					var pageId = $( comment ).attr( 'data-id' );
					self.editComment( $( comment ), pageId );
				} );

				if ( commentData.numreplies === 0 ) {
					commentHeader.append( this.createDeleteButton() );
				}
			}

			var commentBody = $( '<div>' )
				.addClass( 'cs-comment-body' )
				.html( commentData.html );
			var commentFooter = $( '<div>' )
				.addClass( 'cs-comment-footer' );

			var commentClass;
			if ( commentData.parentid !== null ) {
				commentClass = 'cs-reply-comment';
			} else {
				commentClass = 'cs-head-comment';
			}
			var comment = $( '<div>' )
				.addClass( 'cs-comment' )
				.addClass( commentClass )
				.attr({
					'id': 'cs-comment-' + commentData.pageid,
					'data-id': commentData.pageid
				})
				.append( [ commentHeader, commentBody, commentFooter ] );

			return comment;
		},
		createDeleteButton: function() {
			var self = this;
			var deleteSpan = $( '<span>' )
				.addClass( 'cs-delete-span' );
			var divider = this.createDivider();
			deleteSpan.append( divider );
			var deleteButton = $( '<button>' )
				.addClass( 'cs-button' )
				.addClass( 'cs-delete-button' )
				.attr( 'type', 'button' )
				.text( mw.message( 'commentstreams-buttontext-delete' ) );
			deleteSpan.append( deleteButton );
			deleteButton.click( function() {
				var comment = $( this ).closest( '.cs-comment' );
				var pageId = $( comment ).attr( 'data-id' );
				self.deleteComment( $( comment ), pageId );
			} );
			return deleteSpan;
		},
		createDivider: function() {
			return $( '<span>' )
				.addClass( 'cs-comment-details' )
				.text('|');
		},
		formatEditBox: function( is_stream ) {
			var commentBox = $( '<div>' )
				.addClass( 'cs-edit-box' )
				.attr( 'id', 'cs-edit-box' );

			if ( is_stream ) {
				var titleField = $( '<input>' )
					.attr({
						'id': 'cs-title-edit-field',
						'type': 'text',
						'placeholder': mw.message( 'commentstreams-title-field-placeholder' )
					});
				commentBox.append( titleField );
			} else {
				commentBox.addClass( 'cs-reply-edit-box' );
			}

			var bodyField = $( '<textarea>' )
				.attr({
					'id': 'cs-body-edit-field',
					'rows': 10,
					'placeholder': mw.message( 'commentstreams-body-field-placeholder' )
				});
			commentBox.append( bodyField );

			var submitButton = $( '<button>' )
				.addClass( 'cs-button' )
				.addClass( 'cs-submit-button' )
				.attr({
					'id': 'cs-submit-button',
					'type': 'button'
				})
				.text( mw.message( 'commentstreams-buttontext-submit' ) );
			commentBox.append( submitButton );

			commentBox.append( this.createDivider() );

			var cancelButton = $( '<button>' )
				.addClass( 'cs-button' )
				.addClass( 'cs-cancel-button' )
				.attr({
					'id': 'cs-cancel-button',
					'type': 'button'
				})
				.text( mw.message( 'commentstreams-buttontext-cancel' ) );
			commentBox.append( cancelButton );

			return commentBox;
		},
		showNewCommentStreamBox: function() {
			var self = this;
			var editBox = this.formatEditBox( true );
			if ( this.newestStreamsOnTop ) {
				$( '#cs-header' ).append( editBox );
				$( '#cs-edit-box' )
					.hide()
					.slideDown();
			} else {
				$( '#cs-footer' ).prepend( editBox );
				$( '#cs-edit-box' )
					.hide()
					.slideDown();
			}
			$( '#cs-submit-button' ).click( function() {
				self.postComment( null );
			} );
			$( '#cs-cancel-button' ).click( function() {
				self.hideEditBox( true );
			} );
			this.disableAllButtons();
			var titleField = $( '#cs-title-edit-field' );
			if ( titleField !== null ) {
				titleField.focus();
			}
		},
		showNewReplyBox: function( element, topCommentId ) {
			var self = this;
			var editBox = this.formatEditBox( false );
			$( editBox )
				.insertBefore( element.closest( '.cs-stream-footer' ) )
				.hide()
				.slideDown();

			$( '#cs-submit-button' ).click( function() {
					self.postComment( topCommentId );
			} );
			$( '#cs-cancel-button' ).click( function() {
				self.hideEditBox( true );
			} );
			this.disableAllButtons();
			var editField = $( '#cs-body-edit-field' );
			if ( editField !== null ) {
				editField.focus();
			}
		},
		hideEditBox: function( animated ) {
			var self = this;
			if ( animated ) {
				$( '#cs-edit-box' ).slideUp( 'normal', function() {
					$( '#cs-edit-box' ).remove();
				} );
			} else {
				$( '#cs-edit-box' ).remove();
			}
			this.enableAllButtons();
		},
		postComment: function( parentPageId ) {
			var self = this;

			var titleField = $( '#cs-title-edit-field' );
			if ( titleField !== null ) {
				var commentTitle = titleField .val();
				if ( commentTitle === null || commentTitle.trim() === "" ) {
					this.reportError( 'commentstreams-validation-error-nocommenttitle' );
					return;
				}
			}

			var commentText = $( '#cs-body-edit-field' ).val();
			if ( commentText === null || commentText.trim() === "" ) {
				this.reportError( 'commentstreams-validation-error-nocommenttext' );
				return;
			}

			$( '#cs-submit-button' ).attr( 'disabled', 'disabled' );
			$( '#cs-cancel-button' ).attr( 'disabled', 'disabled' );

			$( '#cs-edit-box' ).fadeTo( 100, 0.2, function() {
				new Spinner( self.spinnerOptions )
					.spin( document.getElementById( 'cs-edit-box' ) );

				var associatedPageId = mw.config.get( 'wgArticleId' );
				CommentStreamsQuerier.postComment( commentTitle, commentText,
					associatedPageId, parentPageId, function( result ) {
					$( '.spinner' ).remove();
					if ( result.error === undefined ) {
						var comment = self.formatComment( result );
						if ( parentPageId ) {
							var deleteSpan = $( '#cs-edit-box' )
								.closest( '.cs-stream' )
								.find( '.cs-head-comment' )
								.find( '.cs-comment-header' )
								.find( '.cs-delete-span' );
							deleteSpan.remove();
							var location = $( '#cs-edit-box' )
								.closest( '.cs-stream' )
								.find( '.cs-stream-footer' );
							self.hideEditBox( false );
							comment.insertBefore( $( location ) )
								.hide()
								.slideDown();
						} else {
							self.hideEditBox( false );
							if ( self.newestStreamsOnTop ) {
								comment.insertAfter( '#cs-header' )
									.hide()
									.slideDown();
							} else {
								comment.insertBefore( '#cs-footer' )
									.hide()
									.slideDown();
							}
						}
					} else {
						self.reportError( result.error );
						$( '#cs-edit-box').fadeTo( 0.2, 100, function() {
							$( '#cs-submit-button' ).attr( 'disabled', false );
							$( '#cs-cancel-button' ).attr( 'disabled', false );
						} );
					}
				} );
			} );
		},
		deleteComment: function( element, pageId ) {
			var self = this;
			var title_string = mw.message( 'commentstreams-dialog-delete-title' );
			var message_string = mw.message( 'commentstreams-dialog-delete-message' );
			var dialog = $( '<div>' )
				.attr( 'title', title_string )
				.text( message_string );
			$( '#cs-comments' ).append( dialog );
			var dialog_attrs = {
				resizable: false,
				height: 'auto',
				width: 400,
				modal: true,
				buttons: {}
			};
			var yes_string =
				mw.message( 'commentstreams-dialog-buttontext-yes' ).text();
			dialog_attrs.buttons[yes_string] = function () {
				$( this ).dialog( 'close' );
				$( dialog ).remove();
				self.realDeleteComment( element, pageId );
			};
			var no_string =
				mw.message( 'commentstreams-dialog-buttontext-no' ).text();
			dialog_attrs.buttons[no_string] = function () {
				$( this ).dialog( 'close' );
				$( dialog ).remove();
			};
			dialog.dialog( dialog_attrs );
		},
		realDeleteComment: function( element, pageId ) {
			var self = this;
			this.disableAllButtons();
			element.fadeTo( 100, 0.2, function() {
				new Spinner( self.spinnerOptions )
					.spin( document.getElementById( element.attr( 'id' ) ) );
				CommentStreamsQuerier.deleteComment( pageId, function( result ) {
					$( '.spinner' ).remove();
					if ( result.error === undefined ||
						result.error === 'commentstreams-api-error-commentnotfound' ) {
						if ( element.hasClass( 'cs-head-comment' ) ) {
							element.closest( '.cs-stream' )
								.slideUp( 'normal', function() {
									element.closest( '.cs-stream' ).remove();
									self.enableAllButtons();
								} );
						} else {
							var parentId = element
								.closest( '.cs-stream' )
								.find( '.cs-head-comment' )
								.attr( 'data-id' );
							CommentStreamsQuerier.queryComment( parentId, function( result ) {
								if ( result.error === undefined &&
									self.username === result.username &&
									result.numreplies === 0 ) {
									element
										.closest( '.cs-stream' )
										.find( '.cs-head-comment' )
										.find( '.cs-comment-header' )
										.append( self.createDeleteButton() );
								}
								element.slideUp( 'normal', function() {
									element.remove();
									self.enableAllButtons();
								} );
							} );
						}
					} else {
						self.reportError( result.error );
						element.fadeTo( 0.2, 100, function() {
							self.enableAllButtons();
						} );
					}
				} );
			} );
		},
		editComment: function( element, pageId ) {
			var self = this;
			this.disableAllButtons();
			element.fadeTo( 100, 0.2, function() {
				new Spinner( self.spinnerOptions )
					.spin( document.getElementById( element.attr( 'id' ) ) );
				CommentStreamsQuerier.queryComment( pageId, function( result ) {
					$( '.spinner' ).remove();

					if ( result.error === undefined ) {
						var is_stream = element.hasClass( 'cs-head-comment' );
						var commentBox = self.formatEditBox( is_stream );
						commentBox.insertAfter( element );
						element.hide();
						commentBox.slideDown();

						var editField = $( '#cs-body-edit-field' );
						editField.val( result.wikitext );
						if ( is_stream ) {
							var titleField = $( '#cs-title-edit-field' );
							titleField.val( result.commenttitle );
							titleField.focus();
						} else {
							editField.focus();
						}

						$( '#cs-cancel-button' ).click( function() {
							commentBox.slideUp( 'normal', function() {
								element.fadeTo( 0.2, 100, function() {
									commentBox.remove();
									self.enableAllButtons();
								} );
							} );
						} );

						$( '#cs-submit-button' ).click( function() {
							var titleField = $( '#cs-title-edit-field' );
							if ( titleField !== null ) {
								var commentTitle = $( '#cs-title-edit-field' ).val();
								if ( commentTitle === null || commentTitle.trim() === "" ) {
									self.reportError(
										'commentstreams-validation-error-nocommenttitle' );
									return;
								}
							}

							var commentText = $( '#cs-body-edit-field' ).val();
							if ( commentText === null || commentText.trim() === "" ) {
								self.reportError(
									'commentstreams-validation-error-nocommenttext' );
								return;
							}

							$( '#cs-submit-button' ).attr( 'disabled', 'disabled' );
							$( '#cs-cancel-button' ).attr( 'disabled', 'disabled' );

							commentBox.fadeTo( 100, 0.2, function() {
								new Spinner( self.spinnerOptions )
									.spin( document.getElementById( 'cs-edit-box' ) );

								CommentStreamsQuerier.editComment( commentTitle, commentText,
									pageId, function( result ) {
									$( '.spinner' ).remove();
									if ( result.error === undefined ) {
										var comment = self.formatCommentInner( result );
										commentBox.slideUp( 'normal', function() {
											comment.insertAfter( commentBox );
											commentBox.remove();
											element.remove();
											self.enableAllButtons();
										} );
									} else if ( result.error === 'commentstreams-api-error-commentnotfound' ) {
										self.reportError( result.error );
										var parentId = element
											.closest( '.cs-stream' )
											.find( '.cs-head-comment' )
											.attr( 'data-id' );
										CommentStreamsQuerier.queryComment( parentId, function( result ) {
											if ( result.error === undefined &&
												self.username === result.username &&
												result.numreplies === 0 ) {
												element
													.closest( '.cs-stream' )
													.find( '.cs-head-comment' )
													.find( '.cs-comment-header' )
													.append( self.createDeleteButton() );
											}
											commentBox.slideUp( 'normal', function() {
												comment.insertAfter( commentBox );
												commentBox.remove();
												element.remove();
												self.enableAllButtons();
											} );
										} );
									} else {
										self.reportError( result.error );
										commentBox.fadeTo( 0.2, 100, function() {
											$( '#cs-submit-button' ).attr( 'disabled', false );
											$( '#cs-cancel-button' ).attr( 'disabled', false );
										} );
									}
								} );
							} );
						} );
					} else {
						self.reportError( result.error );
						element.fadeTo( 0.2, 100, function() {
							self.enableAllButtons();
						} );
					}
				} );
			} );
		},
		reportError: function( message ) {
			var title_string = mw.message( 'commentstreams-dialog-error-title' );
			var message_string = mw.message( message );
			var dialog = $( '<div>' )
				.attr( 'title', title_string )
				.text( message_string );
			$( '#cs-comments' ).append( dialog );
			var dialog_attrs = {
				resizable: false,
				height: 'auto',
				width: 400,
				modal: true,
				buttons: {}
			};
			var ok_string =
				mw.message( 'commentstreams-dialog-buttontext-ok' ).text();
			dialog_attrs.buttons[ok_string] = function () {
				$( this ).dialog( 'close' );
				$( dialog ).remove();
			};
			dialog.dialog( dialog_attrs );
			if ( ( window.console !== undefined ) )
				window.console.log( message_string );
		}
	};
}( mediaWiki, jQuery ) );

window.CommentStreamsController = commentstreams_controller;

( function( mw, $ ) {
	$( document )
		.ready( function() {
			if ( mw.config.exists( 'CommentStreams' ) ) {
				window.CommentStreamsController.initialize();
			}
		} );
}( mediaWiki, jQuery ) );
