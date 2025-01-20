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

	function Block( controller, env, querier, blockName, $commentDiv ) {
		this.controller = controller;
		this.env = env;
		this.querier = querier;
		this.blockName = blockName;

		this.$commentDiv = null;
		this.addButton = null;
		this.$headerDiv = null;
		this.$footerDiv = null;
		this.$editBox = null;
		this.$titleField = null;
		this.$bodyField = null;
		this.submitButton = null;
		this.cancelButton = null;

		this.streams = [];
		this.createBlock( $commentDiv );
	}

	Block.prototype.createBlock = function ( $commentDiv ) {
		const self = this;

		this.$commentDiv = $commentDiv;

		this.$headerDiv = $( '<div>' ).addClass( 'cs-header' );
		this.$commentDiv.append( this.$headerDiv );

		this.$footerDiv = $( '<div>' ).addClass( 'cs-footer' );
		this.$commentDiv.append( this.$footerDiv );

		const params = {
			icon: 'speechBubbleAdd'
		};

		const buttonText = mw.msg( 'commentstreams-buttontext-add' );
		if ( this.env.showLabels ) {
			params.label = buttonText;
		} else {
			params.title = buttonText;
			params.framed = false;
		}
		if ( !this.env.canComment ) {
			params.disabled = true;
		}

		this.addButton = new OO.ui.ButtonWidget( params );

		if ( this.env.newestStreamsOnTop ) {
			this.$headerDiv.append( this.addButton.$element );
		} else {
			this.$footerDiv.append( this.addButton.$element );
		}

		this.addButton.onClick = function () {
			self.showNewCommentStreamBox();
		};

		this.addButton.on( 'click', this.addButton.onClick );
	};

	Block.prototype.addStream = function ( commentData ) {
		const Stream = require( './Stream.js' );
		const stream = new Stream( this.controller, this.env, this, this.querier, commentData );
		if ( this.env.newestStreamsOnTop ) {
			stream.getStream().insertAfter( this.$headerDiv );
		} else {
			stream.getStream().insertBefore( this.$footerDiv );
		}

		if ( this.env.initiallyCollapsed ) {
			stream.collapse();
		}

		this.streams[ commentData.id ] = stream;

		if ( commentData.children !== undefined ) {
			for ( const childComment of commentData.children ) {
				stream.addReply( childComment );
			}
		}

		return stream;
	};

	Block.prototype.enableAllButtons = function () {
		this.addButton.setDisabled( false );
		this.streams.forEach( ( stream ) => {
			stream.enableButtons();
		} );
	};

	Block.prototype.disableAllButtons = function () {
		this.addButton.setDisabled( true );
		this.streams.forEach( ( stream ) => {
			stream.disableButtons();
		} );
	};

	Block.prototype.createEditBox = function ( isStream ) {
		this.$editBox = $( '<div>' )
			.addClass( 'cs-edit-box' );

		if ( isStream ) {
			this.$titleField = $( '<input>' )
				.attr( {
					type: 'text',
					placeholder: mw.msg( 'commentstreams-title-field-placeholder' )
				} )
				.addClass( 'cs-title-edit-field' );
			this.$editBox.append( this.$titleField );
		} else {
			this.$editBox.addClass( 'cs-reply-edit-box' );
		}

		if ( $.fn.applyVisualEditor ) {
			// VEForAll is installed.
			this.$editBox.addClass( 've-area-wrapper' );
		}

		this.$bodyField = $( '<textarea>' )
			.attr( {
				rows: 10,
				placeholder: mw.msg( 'commentstreams-body-field-placeholder' )
			} )
			.addClass( 'cs-body-edit-field' );
		this.$editBox.append( this.$bodyField );

		this.submitButton = new OO.ui.ButtonWidget( {
			icon: 'check',
			flags: 'progressive',
			title: mw.msg( 'commentstreams-buttontooltip-submit' ),
			framed: false
		} );

		this.$editBox.append( this.submitButton.$element );

		this.cancelButton = new OO.ui.ButtonWidget( {
			icon: 'cancel',
			flags: 'destructive',
			title: mw.msg( 'commentstreams-buttontooltip-cancel' ),
			framed: false
		} );

		this.$editBox.append( this.cancelButton.$element );
	};

	Block.prototype.showNewCommentStreamBox = function () {
		const self = this;

		this.createEditBox( true );
		if ( this.env.newestStreamsOnTop ) {
			this.$headerDiv.append( this.$editBox );
		} else {
			this.$footerDiv.prepend( this.$editBox );
		}

		this.$editBox
			.hide()
			.slideDown();

		if ( $.fn.applyVisualEditor ) {
			// VEForAll is installed.
			this.$bodyField.applyVisualEditor();
		}

		this.submitButton.onClick = function () {
			self.postComment( null );
		};
		this.submitButton.on( 'click', this.submitButton.onClick );

		this.cancelButton.onClick = function () {
			self.hideEditBox( true );
		};
		this.cancelButton.on( 'click', this.cancelButton.onClick );

		this.disableAllButtons();

		if ( this.$titleField !== null ) {
			this.$titleField.trigger( 'focus' );
		} else {
			this.$bodyField.trigger( 'focus' );
		}
	};

	Block.prototype.hideEditBox = function ( animated ) {
		const self = this;
		if ( animated ) {
			this.$editBox.slideUp( 'normal', () => {
				self.$editBox.remove();
				self.$editBox = null;
			} );
		} else {
			this.$editBox.remove();
			this.$editBox = null;
		}
		this.enableAllButtons();
	};

	Block.prototype.postComment = function ( parentCommentId ) {
		const self = this;
		if ( this.env.isLoggedIn ) {
			self.postComment2( parentCommentId );
		} else {
			const messageText = mw.msg( 'commentstreams-dialog-anonymous-message' );
			const okText = mw.msg( 'commentstreams-dialog-buttontext-ok' );
			const cancelText = mw.msg( 'commentstreams-dialog-buttontext-cancel' );
			const dialog = new OO.ui.MessageDialog();
			const windowManager = new OO.ui.WindowManager();
			this.$commentDiv.append( windowManager.$element );
			windowManager.addWindows( [ dialog ] );
			windowManager.openWindow( dialog, {
				message: messageText,
				actions: [
					{ label: okText, action: 'ok' },
					{ label: cancelText, flags: 'primary' }
				]
			} ).closed.then( ( data ) => {
				if ( data && data.action && data.action === 'ok' ) {
					self.postComment2( parentCommentId );
				}
			} );
		}
	};

	Block.prototype.postComment2 = function ( parentCommentId ) {
		const self = this;
		if ( this.$bodyField.css( 'display' ) === 'none' ) {
			self.postCommentFromVE( parentCommentId );
		} else {
			const commentText = this.$bodyField.val();
			self.realPostComment( parentCommentId, commentText );
		}
	};

	Block.prototype.postCommentFromVE = function ( parentCommentId ) {
		const self = this;
		const veInstances = this.$bodyField.getVEInstances();
		const curVEEditor = veInstances[ veInstances.length - 1 ];
		new mw.Api().post( {
			action: 'veforall-parsoid-utils',
			from: 'html',
			to: 'wikitext',
			content: curVEEditor.target.getSurface().getHtml(),
			title: mw.config.get( 'wgPageName' ).split( /([\\/])/g ).pop()
		} ).then( ( data ) => {
			const commentText = data[ 'veforall-parsoid-utils' ].content;
			self.realPostComment( parentCommentId, commentText );
		} )
			.fail( () => {
				self.reportError( 'commentstreams-ve-conversion-error' );
			} );
	};

	Block.prototype.realPostComment = function (
		parentCommentId,
		commentText
	) {
		const self = this;

		let commentTitle;
		if ( parentCommentId === null ) {
			if ( this.$titleField !== null ) {
				commentTitle = this.$titleField.val();
				if ( commentTitle === null || commentTitle.trim() === '' ) {
					this.reportError( 'commentstreams-validation-error-nocommenttitle' );
					return;
				}
			}
		} else {
			commentTitle = null;
		}

		if ( commentText === null || commentText.trim() === '' ) {
			this.reportError( 'commentstreams-validation-error-nocommenttext' );
			return;
		}

		this.submitButton.setDisabled( true );
		this.cancelButton.setDisabled( true );

		this.$editBox.fadeTo( 100, 0.2, () => {
			const progressBar = new OO.ui.ProgressBarWidget( {
				progress: false
			} );
			progressBar.$element.insertAfter( self.$editBox );

			if ( parentCommentId ) {
				self.querier.postReply(
					commentText,
					parentCommentId,
					( result ) => {
						progressBar.$element.remove();
						if ( result.error === undefined ) {
							self.hideEditBox( false );
							self.streams[ parentCommentId ].addReply( result );
							self.querier.queryComment( parentCommentId, ( queryResult ) => {
								if ( queryResult.error === undefined ) {
									self.streams[ parentCommentId ]
										.recreateStreamMenu( queryResult );
								}
							} );
						} else {
							self.reportError( result.error );
							self.$editBox.fadeTo( 0.2, 100, () => {
								self.submitButton.setDisabled( false );
								self.cancelButton.setDisabled( false );
							} );
						}
					} );
			} else {
				self.querier.postComment(
					commentTitle,
					commentText,
					self.env.associatedPageId,
					self.blockName,
					( result ) => {
						progressBar.$element.remove();
						if ( result.error === undefined ) {
							self.hideEditBox( false );
							const stream = self.addStream( result );
							stream.adjustCommentOrder( 0, 0, result.created_timestamp );
						} else {
							self.reportError( result.error );
							self.$editBox.fadeTo( 0.2, 100, () => {
								self.submitButton.setDisabled( false );
								self.cancelButton.setDisabled( false );
							} );
						}
					} );
			}
		} );
	};

	Block.prototype.reportError = function ( message ) {
		/* eslint-disable mediawiki/msg-doc */
		let messageText = message;
		const mwmessage = mw.message( message );
		if ( mwmessage.exists() ) {
			messageText = mwmessage.text();
		}
		const okText = mw.msg( 'commentstreams-dialog-buttontext-ok' );
		const dialog = new OO.ui.MessageDialog();
		const windowManager = new OO.ui.WindowManager();
		this.$commentDiv.append( windowManager.$element );
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

	return Block;
}() );
