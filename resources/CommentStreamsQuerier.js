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

var commentstreams_querier = ( function( mw ) {
	return {
		queryComment: function( pageid, reply ) {
			var self = this;
			var api = new mw.Api();
			api.get( {
					action: 'csQueryComment',
					pageid: pageid
				} )
				.done( function( data ) {
					reply( data.csQueryComment );
				} )
				.fail( function( data ) {
					self.reportError( data, reply );
				} );
		},
		deleteComment: function( pageid, reply ) {
			var self = this;
			var api = new mw.Api();
			api.post( {
					action: 'csDeleteComment',
					pageid: pageid,
					token: mw.user.tokens.get( 'editToken' )
				} )
				.done( function( data ) {
					reply( data );
				} )
				.fail( function( data ) {
					self.reportError( data, reply );
				} );
		},
		postComment: function( commenttitle, wikitext, associatedid, parentid,
			reply ) {
			var self = this;
			var api = new mw.Api();
			var data = {
				action: 'csPostComment',
				wikitext: wikitext,
				associatedid: associatedid,
				token: mw.user.tokens.get( 'editToken' )
			};
			if ( commenttitle !== null ) {
				data.commenttitle = commenttitle;
			}
			if ( parentid !== null ) {
				data.parentid = parentid;
			}
			api.post(
					data
				)
				.done( function( data ) {
					reply( data.csPostComment );
				} )
				.fail( function( data ) {
					self.reportError( data, reply );
				} );
		},
		editComment: function( commenttitle, wikitext, pageid, reply ) {
			var self = this;
			var api = new mw.Api();
			api.post( {
					action: 'csEditComment',
					pageid: pageid,
					commenttitle: commenttitle,
					wikitext: wikitext,
					token: mw.user.tokens.get( 'editToken' )
				} )
				.done( function( data ) {
					reply( data.csEditComment );
				} )
				.fail( function( data ) {
					self.reportError( data, reply );
				} );
		},
		vote: function( pageid, vote, reply ) {
			var self = this;
			var api = new mw.Api();
			api.post( {
					action: 'csVote',
					pageid: pageid,
					vote: vote,
					token: mw.user.tokens.get( 'editToken' )
				} )
				.done( function( data ) {
					reply( data.csVote );
				} )
				.fail( function( data ) {
					self.reportError( data, reply );
				} );
		},
		watch: function( pageid, action, reply ) {
			var self = this;
			var api = new mw.Api();
			api.post( {
					action: action ? 'csWatch' : 'csUnwatch',
					pageid: pageid,
					token: mw.user.tokens.get( 'editToken' )
				} )
				.done( function( data ) {
					if ( action ) {
						reply( data.csWatch );
					} else {
						reply( data.csUnwatch );
					}
				} )
				.fail( function( data ) {
					self.reportError( data, reply );
				} );
		},
		reportError: function( data, reply ) {
			if ( data === 'nosuchpageid' ) {
				reply( {
					'error': 'commentstreams-api-error-commentnotfound'
				} );
			} else if ( data === 'badtoken' ) {
				reply( {
					'error': 'commentstreams-api-error-notloggedin'
				} );
			} else {
				reply( {
					'error': data
				} );
			}
		}
	};
}( mediaWiki ) );

window.CommentStreamsQuerier = commentstreams_querier;
