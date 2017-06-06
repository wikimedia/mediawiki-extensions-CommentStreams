﻿/*
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
		isLoggedIn: false,
		moderatorEdit: false,
		moderatorDelete: false,
		moderatorFastDelete: false,
		userDisplayName: null,
		newestStreamsOnTop: false,
		initiallyCollapsed: false,
		enableVoting: false,
		comments: [],
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
			this.isLoggedIn = mw.config.get( 'wgUserName' ) !== null;
			var config = mw.config.get( 'CommentStreams' );
			this.moderatorEdit = config.moderatorEdit;
			this.moderatorDelete = config.moderatorDelete;
			this.moderatorFastDelete = this.moderatorDelete ?
				config.moderatorFastDelete : false;
			this.userDisplayName = config.userDisplayName;
			this.newestStreamsOnTop = config.newestStreamsOnTop;
			this.initiallyCollapsed = config.initiallyCollapsed;
			this.enableVoting = config.enableVoting;
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
			$( '.cs-vote-button' ).attr( 'disabled', 'disabled' );
		},
		enableAllButtons: function() {
			$( '.cs-edit-button' ).attr( 'disabled', false );
			$( '.cs-reply-button' ).attr( 'disabled', false );
			$( '#cs-add-button' ).attr( 'disabled', false );
			$( '.cs-delete-button' ).attr( 'disabled', false );
			$( '.cs-toggle-button' ).attr( 'disabled', false );
			$( '.cs-vote-button' ).attr( 'disabled', false );
		},
		formatComment: function( commentData ) {
			var self = this;
			var comment = this.formatCommentInner( commentData );

			if ( commentData.parentid === null ) {
				comment = $( '<div>' )
					.addClass( 'cs-stream' )
					.addClass( 'cs-expanded' )
					.attr( 'data-created-timestamp', commentData.created_timestamp )
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
				var text = mw.message( 'commentstreams-datetext-lasteditedon' ) +
					' ' + commentData.modified;
				if ( commentData.moderated ) {
					text += ' (' + mw.message( 'commentstreams-datetext-moderated' ) +
						')';
				}
				var modified = $( '<span>' )
					.addClass( 'cs-comment-details' )
					.text( text );
				commentHeader.append( this.createDivider() );
				commentHeader.append( modified );
			}

			if ( this.canEdit( commentData ) ) {
				commentHeader.append( this.createEditButton( commentData.username) );
			}

			if ( this.canDelete( commentData ) ) {
				commentHeader.append( this.createDeleteButton( commentData.username) );
			}

			if ( commentData.parentid === null && this.enableVoting ) {
				commentHeader.append( this.createVotingButtons( commentData ) );
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
		createEditButton: function( username ) {
			var self = this;
			var editSpan = $( '<span>' )
				.addClass( 'cs-edit-span' );
			var divider = this.createDivider();
			editSpan.append( divider );
			var editButton = $( '<button>' )
				.addClass( 'cs-button' )
				.addClass( 'cs-edit-button' )
				.attr( 'type', 'button' )
				.text( mw.message( 'commentstreams-buttontext-edit' ) );
			if ( mw.user.getName() !== username ) {
				editButton
					.addClass( 'cs-moderator-button' )
			}
			editSpan.append( editButton );
			editButton.click( function() {
				var comment = $( this ).closest( '.cs-comment' );
				var pageId = $( comment ).attr( 'data-id' );
				self.editComment( $( comment ), pageId );
			} );
			return editSpan;
		},
		createDeleteButton: function( username ) {
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
			if ( mw.user.getName() !== username ) {
				deleteButton
					.addClass( 'cs-moderator-button' )
			}
			deleteSpan.append( deleteButton );
			deleteButton.click( function() {
				var comment = $( this ).closest( '.cs-comment' );
				var pageId = $( comment ).attr( 'data-id' );
				self.deleteComment( $( comment ), pageId );
			} );
			return deleteSpan;
		},
		createVotingButtons( commentData ) {
			var self = this;
			var path = mw.config.get( 'wgExtensionAssetsPath' ) +
				'/CommentStreams/images/';

			var upButton;
			if ( mw.user.isAnon() ) {
				upButton = $( '<span>' )
					.addClass( 'cs-button' );
			} else {
				upButton = $( '<button>' )
					.addClass( 'cs-button' )
					.addClass( 'cs-vote-button' )
					.click( function() {
						self.vote( $( this ), commentData.pageid, path, true,
							commentData.created_timestamp );
					});
			}
			var upimage = $( '<img>' )
				.addClass( 'cs-vote-upimage' );
			if ( commentData.vote > 0 ) {
				upimage.attr( 'src', path + 'upvote-enabled.png');
				upimage.addClass( 'cs-vote-enabled' );
			} else {
				upimage.attr( 'src', path + 'upvote-disabled.png');
			}
			var upcountspan = $( '<span>' )
				.addClass( 'cs-comment-details' )
				.addClass( 'cs-vote-upcount' )
				.text( commentData.numupvotes );
			upButton.append( upimage );
			upButton.append( upcountspan );

			var downButton;
			if ( mw.user.isAnon() ) {
				downButton = $( '<span>' )
					.addClass( 'cs-button' );
			} else {
				downButton = $( '<button>' )
					.addClass( 'cs-button' )
					.addClass( 'cs-vote-button' )
					.click( function() {
						self.vote( $( this ), commentData.pageid, path, false,
							commentData.created_timestamp );
					});
			}
			var downimage = $( '<img>' )
				.addClass( 'cs-vote-downimage' );
			if ( commentData.vote < 0 ) {
				downimage.attr( 'src', path + 'downvote-enabled.png');
				downimage.addClass( 'cs-vote-enabled' );
			} else {
				downimage.attr( 'src', path + 'downvote-disabled.png');
			}
			var downcountspan = $( '<span>' )
				.addClass( 'cs-comment-details' )
				.addClass( 'cs-vote-downcount' )
				.text( commentData.numdownvotes );
			downButton.append( downimage );
			downButton.append( downcountspan );

			var votingSpan = $( '<span>' )
				.addClass( 'cs-voting-span' );
			var divider = this.createDivider();
			votingSpan.append( divider );
			votingSpan.append( upButton );
			votingSpan.append( downButton );
			return votingSpan;
		},
		vote: function( button, pageid, path, up, created_timestamp ) {

			var self = this;
			var votespan = button.closest( '.cs-voting-span' );
			var upcountspan = votespan.find( '.cs-vote-upcount' );
			var upcount = parseInt(upcountspan.text());
			var upimage = votespan.find( '.cs-vote-upimage' );
			var downcountspan = votespan.find( '.cs-vote-downcount' );
			var downcount = parseInt(downcountspan.text());
			var downimage = votespan.find( '.cs-vote-downimage' );

			var newvote;
			var oldvote;
			if ( up ) {
				if ( upimage.hasClass( 'cs-vote-enabled' ) ) {
					newvote = 0;
					oldvote = 1;
				} else {
					newvote = 1;
					if ( downimage.hasClass( 'cs-vote-enabled' ) ) {
						oldvote = -1;
					} else {
						oldvote = 0;
					}
				}
			} else {
				if ( downimage.hasClass( 'cs-vote-enabled' ) ) {
					newvote = 0;
					oldvote = -1;
				} else {
					newvote = -1;
					if ( upimage.hasClass( 'cs-vote-enabled' ) ) {
						oldvote = 1;
					} else {
						oldvote = 0;
					}
				}
			}

			var comment = button.closest( '.cs-comment' );
			this.disableAllButtons();
			new Spinner( self.spinnerOptions )
				.spin( document.getElementById( comment.attr( 'id' ) ) );
			CommentStreamsQuerier.vote( pageid, newvote, function( result ) {
				$( '.spinner' ).remove();
				if ( result.error === undefined ) {
					if ( up ) {
						if ( upimage.hasClass( 'cs-vote-enabled' ) ) {
							upimage.attr( 'src', path + 'upvote-disabled.png');
							upimage.removeClass( 'cs-vote-enabled' );
							upcount = upcount - 1;
							upcountspan.text( upcount );
						} else {
							upimage.attr( 'src', path + 'upvote-enabled.png');
							upimage.addClass( 'cs-vote-enabled' );
							upcount = upcount + 1;
							upcountspan.text( upcount );
							if ( downimage.hasClass( 'cs-vote-enabled' ) ) {
								downimage.attr( 'src', path + 'downvote-disabled.png');
								downimage.removeClass( 'cs-vote-enabled' );
								downcount = downcount - 1;
								downcountspan.text( downcount );
							}
						}
					} else {
						if ( downimage.hasClass( 'cs-vote-enabled' ) ) {
							downimage.attr( 'src', path + 'downvote-disabled.png');
							downimage.removeClass( 'cs-vote-enabled' );
							downcount = downcount - 1;
							downcountspan.text( downcount );
						} else {
							downimage.attr( 'src', path + 'downvote-enabled.png');
							downimage.addClass( 'cs-vote-enabled' );
							downcount = downcount + 1;
							downcountspan.text( downcount );
							if ( upimage.hasClass( 'cs-vote-enabled' ) ) {
								upimage.attr( 'src', path + 'upvote-disabled.png');
								upimage.removeClass( 'cs-vote-enabled' );
								upcount = upcount - 1;
								upcountspan.text( upcount );
							}
						}
					}
					var votediff = upcount - downcount;
					var stream = comment.closest( '.cs-stream' );
					self.adjustCommentOrder( stream, votediff, upcount,
						created_timestamp );
				} else {
					self.reportError( result.error );
					self.enableAllButtons();
				}
			});
		},
		adjustCommentOrder: function( stream, votediff, upcount,
			created_timestamp ) {
			var nextSiblings = stream.nextAll( '.cs-stream' );
			var first = true;
			var index;
			for ( index = 0; index < nextSiblings.length; index++ ) {
				var sibling = nextSiblings[index];
				var nextupcountspan =
					$( sibling ).find( '.cs-vote-upcount' );
				var nextupcount = parseInt(nextupcountspan.text());
				var nextdowncountspan =
					$( sibling ).find( '.cs-vote-downcount' );
				var nextdowncount = parseInt(nextdowncountspan.text());
				var nextvotediff = nextupcount - nextdowncount;
				if ( nextvotediff > votediff ) {
					// keeping looking
				} else if ( nextvotediff === votediff ) {
					if ( nextupcount > upcount ) {
						// keeping looking
					} else if ( nextupcount === upcount ) {
						var nextcreated_timestamp =
							$( sibling ).attr( 'data-created-timestamp' );
						if ( this.newestStreamsOnTop ) {
							if ( nextcreated_timestamp > created_timestamp ) {
								// keeping looking
							} else if ( first ) {
								// check previous siblings
								break;
							} else {
								this.moveComment( stream, true, $( sibling ) );
								return;
							}
						} else if ( nextcreated_timestamp < created_timestamp ) {
							// keep looking
						} else if ( first ) {
							// check previous siblings
							break;
						} else {
							this.moveComment( stream, true, $( sibling ) );
							return;
						}
					} else if ( first ) {
						// check previous siblings
						break;
					} else {
						this.moveComment( stream, true, $( sibling ) );
						return;
					}
				} else if ( first ) {
					// check previous siblings
					break;
				} else {
					this.moveComment( stream, true, $( sibling ) );
					return;
				}
				first = false;
			}
			if ( !first ) {
				this.moveComment( stream, false,
					$( nextSiblings[nextSiblings.length - 1] ) );
				return;
			}
			var prevSiblings = stream.prevAll( '.cs-stream' );
			first = true;
			for ( index = 0; index < prevSiblings.length; index++ ) {
				var sibling = prevSiblings[index];
				var prevupcountspan =
					$( sibling ).find( '.cs-vote-upcount' );
				var prevupcount = parseInt(prevupcountspan.text());
				var prevdowncountspan =
					$( sibling ).find( '.cs-vote-downcount' );
				var prevdowncount = parseInt(prevdowncountspan.text());
				var prevvotediff = prevupcount - prevdowncount;
				if ( prevvotediff < votediff ) {
					// keeping looking
				} else if ( prevvotediff === votediff ) {
					if ( prevupcount < upcount ) {
						// keeping looking
					} else if ( prevupcount === upcount ) {
						var prevcreated_timestamp =
							$( sibling ).attr( 'data-created-timestamp' );
						if ( this.newestStreamsOnTop ) {
							if ( prevcreated_timestamp < created_timestamp ) {
								// keeping looking
							} else if ( first ) {
								// done
								break;
							} else {
								this.moveComment( stream, false, $( sibling ) );
								return;
							}
						} else if ( prevcreated_timestamp > created_timestamp ) {
							// keeping looking
						} else if ( first ) {
							// done
							break;
						} else {
							this.moveComment( stream, false, $( sibling ) );
							return;
						}
					} else if ( first ) {
						// done
						break;
					} else {
						this.moveComment( stream, false, $( sibling ) );
						return;
					}
				} else if ( first ) {
					// done
					break;
				} else {
					this.moveComment( stream, false, $( sibling ) );
					return;
				}
				first = false;
			}
			if ( !first ) {
				this.moveComment( stream, true,
					$( prevSiblings[prevSiblings.length - 1] ) );
				return;
			}
			// otherwise, the comment was in the correct place already
			this.enableAllButtons();
		},
		moveComment: function( stream, before, location ) {
			var self = this;
			stream.slideUp( 1000, function() {
				stream.detach();
				stream.hide();
				if ( before ) {
					stream.insertBefore( location );
				} else {
					stream.insertAfter( location );
				}
				stream.slideDown( 1000, function() {
					self.enableAllButtons();
				} );
			} );
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

			var commentTitle;
			if ( parentPageId === null ) {
				var titleField = $( '#cs-title-edit-field' );
				if ( titleField !== null ) {
					commentTitle = titleField .val();
					if ( commentTitle === null || commentTitle.trim() === "" ) {
						this.reportError( 'commentstreams-validation-error-nocommenttitle' );
						return;
					}
				}
			} else {
				commentTitle = null;
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
							if ( !self.moderatorFastDelete ) {
								var deleteSpan = $( '#cs-edit-box' )
									.closest( '.cs-stream' )
									.find( '.cs-head-comment' )
									.find( '.cs-comment-header' )
									.find( '.cs-delete-span' );
								deleteSpan.remove();
							}
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
							self.adjustCommentOrder( comment, 0, 0,
								result.created_timestamp );
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
			var message_text =
				mw.message( 'commentstreams-dialog-delete-message' ).text();
			var yes_text =
				mw.message( 'commentstreams-dialog-buttontext-yes' ).text();
			var no_text =
				mw.message( 'commentstreams-dialog-buttontext-no' ).text();
			var dialog = new OO.ui.MessageDialog();
			var window_manager = new OO.ui.WindowManager();
			$( '#cs-comments' ).append( window_manager.$element );
			window_manager.addWindows( [ dialog ] );
			window_manager.openWindow( dialog, {
				message: message_text,
				actions: [
					{ label: yes_text, action: 'yes' },
					{ label: no_text, flags: 'primary' }
				]
			} ).then( function ( opened ) {
				opened.then( function ( closing, data ) {
					if ( data && data.action ) {
						if ( data.action === 'yes' ) {
							self.realDeleteComment( element, pageId );
						}
					}
				} );
			} );
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
								if ( result.error === undefined && self.canDelete( result ) &&
									!self.moderatorFastDelete ) {
									self.createDeleteButton( result.username )
										.insertAfter ( element
											.closest( '.cs-stream' )
											.find( '.cs-head-comment' )
											.find( '.cs-comment-header' )
											.find( '.cs-edit-span' ) );
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
							if ( element.hasClass( 'cs-head-comment' ) ) {
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
												self.canDelete( result ) &&
												!self.moderatorFastDelete ) {
												self.createDeleteButton( result.username )
													.insertAfter ( element
														.closest( '.cs-stream' )
														.find( '.cs-head-comment' )
														.find( '.cs-comment-header' )
														.find( '.cs-edit-span' ) );
											}
											commentBox.slideUp( 'normal', function() {
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
					} else if ( result.error === 'commentstreams-api-error-commentnotfound' ) {
						self.reportError( result.error );
						var parentId = element
							.closest( '.cs-stream' )
							.find( '.cs-head-comment' )
							.attr( 'data-id' );
						CommentStreamsQuerier.queryComment( parentId, function( result ) {
							if ( result.error === undefined &&
								self.canDelete( result ) &&
								!self.moderatorFastDelete ) {
								self.createDeleteButton( result.username )
									.insertAfter ( element
										.closest( '.cs-stream' )
										.find( '.cs-head-comment' )
										.find( '.cs-comment-header' )
										.find( '.cs-edit-span' ) );
							}
							element.remove();
							self.enableAllButtons();
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
		canEdit: function( comment ) {
			var username = comment.username;
			if ( !mw.user.isAnon() && ( mw.user.getName() === username ||
				this.moderatorEdit ) ) {
				return true;
			}
			return false;
		},
		canDelete: function( comment ) {
			var username = comment.username;
			if ( !mw.user.isAnon() &&
				( mw.user.getName() === username || this.moderatorDelete ) &&
				( comment.numreplies === 0 || this.moderatorFastDelete ) ) {
				return true;
			}
			return false;
		},
		reportError: function( message ) {
			var message_text = mw.message( message ).text();
			var ok_text = mw.message( 'commentstreams-dialog-buttontext-ok' ).text();
			var dialog = new OO.ui.MessageDialog();
			var window_manager = new OO.ui.WindowManager();
			$( '#cs-comments' ).append( window_manager.$element );
			window_manager.addWindows( [ dialog ] );
			window_manager.openWindow( dialog, {
				message: message_text,
				actions: [ {
					action: 'accept',
					label: ok_text,
					flags: 'primary'
				} ]
			} );
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
