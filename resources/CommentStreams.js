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

const CommentStreams = ( function () {
	'use strict';

	return {
		initialize: function () {
			const config = mw.config.get( 'CommentStreams' );
			const username = mw.config.get( 'wgUserName' );
			const env = {
				username: username,
				isLoggedIn: username !== null,
				associatedPageId: mw.config.get( 'wgArticleId' ),
				canComment: config.canComment,
				moderatorEdit: config.moderatorEdit,
				moderatorDelete: config.moderatorDelete,
				moderatorFastDelete: config.moderatorDelete ? config.moderatorFastDelete : false,
				showLabels: config.showLabels,
				newestStreamsOnTop: config.newestStreamsOnTop,
				initiallyCollapsed: config.initiallyCollapsed,
				enableVoting: config.enableVoting,
				enableWatchlist: config.enableWatchlist,
				targetComment: null
			};

			this.defaultblock = null;
			this.namedblocks = [];

			this.setupDivs( env );

			if ( window.location.hash ) {
				let hash = window.location.hash.substring( 1 );
				const queryIndex = hash.indexOf( '?' );
				if ( queryIndex !== -1 ) {
					hash = hash.substring( 0, queryIndex );
				}
				env.targetComment = hash;
			}

			this.addInitialComments( config.comments );

			if ( env.targetComment ) {
				this.scrollToElement( $( '#' + env.targetComment ) );
			}
		},

		setupDivs: function ( env ) {
			const self = this;

			const Block = require( './Block.js' );
			const Querier = require( './Querier.js' );
			const querier = new Querier();

			const $blocks = $( '.cs-comments' );

			$blocks.each( function () {
				const $blockDiv = $( this );
				const blockName = $blockDiv.attr( 'data-id' );
				if ( blockName === undefined ) {
					$blockDiv
						.detach()
						.insertAfter( '#catlinks' );
					self.defaultblock = new Block( self, env, querier, null, $blockDiv );
				} else {
					self.namedblocks[ blockName ] =
						new Block( self, env, querier, blockName, $blockDiv );
				}
			} );

			if ( $blocks.length === 0 ) {
				const $mainDiv = $( '<div>' )
					.addClass( 'cs-comments' )
					.insertAfter( '#catlinks' );
				this.defaultblock = new Block( this, env, querier, null, $mainDiv );
			}
		},

		addInitialComments: function ( comments ) {
			for ( const parentComment of comments ) {
				const blockName = parentComment.commentblockname;
				if ( blockName === null && this.defaultblock ) {
					this.defaultblock.addStream( parentComment );
				} else if ( blockName in this.namedblocks ) {
					this.namedblocks[ blockName ].addStream( parentComment );
				} else {
					// ignore comments that do not match a block
					// (may be legacy comments from a block that was deleted)
				}
			}
		},

		scrollToElement: function ( $element ) {
			if ( $element.length ) {
				$( 'html,body' ).animate( { scrollTop: $element.offset().top }, 'slow' );
			}
		}
	};
}() );

( function () {
	'use strict';

	$( function () {
		if ( mw.config.exists( 'CommentStreams' ) ) {
			CommentStreams.initialize();
		}
	} );
}() );

window.CommentStreams = module;
