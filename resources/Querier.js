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

	class Querier {
		constructor() {
		}

		queryComment( pageid, reply ) {
			const self = this;
			new mw.Api()
				.get( {
					action: 'csquerycomment',
					pageid: pageid
				} )
				.done( function ( data ) {
					if ( data.csquerycomment === undefined ) {
						self.reportError( 'invalid', reply );
					}
					reply( data.csquerycomment );
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		queryReply( pageid, reply ) {
			const self = this;
			new mw.Api()
				.get( {
					action: 'csqueryreply',
					pageid: pageid
				} )
				.done( function ( data ) {
					if ( data.csqueryreply === undefined ) {
						self.reportError( 'invalid', reply );
					}
					reply( data.csqueryreply );
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		deleteComment( pageid, reply ) {
			const self = this;
			new mw.Api()
				.post( {
					action: 'csdeletecomment',
					pageid: pageid,
					token: mw.user.tokens.get( 'csrfToken' )
				} )
				.done( function () {
					reply();
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		deleteReply( pageid, reply ) {
			const self = this;
			new mw.Api()
				.post( {
					action: 'csdeletereply',
					pageid: pageid,
					token: mw.user.tokens.get( 'csrfToken' )
				} )
				.done( function () {
					reply();
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		postComment( commenttitle,
			wikitext,
			associatedid,
			commentblockname,
			reply ) {
			const self = this;
			const data = {
				action: 'cspostcomment',
				wikitext: wikitext,
				associatedid: associatedid,
				commenttitle: commenttitle,
				token: mw.user.tokens.get( 'csrfToken' )
			};
			if ( commentblockname !== null ) {
				data.commentblockname = commentblockname;
			}
			new mw.Api()
				.post( data )
				.done( function ( postData ) {
					if ( postData.cspostcomment === undefined ) {
						self.reportError( 'invalid', reply );
					}
					self.queryComment( postData.cspostcomment, reply );
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		postReply( wikitext,
			parentid,
			reply ) {
			const self = this;
			const data = {
				action: 'cspostreply',
				parentid: parentid,
				wikitext: wikitext,
				token: mw.user.tokens.get( 'csrfToken' )
			};
			new mw.Api()
				.post( data )
				.done( function ( postData ) {
					if ( postData.cspostreply === undefined ) {
						self.reportError( 'invalid', reply );
					}
					self.queryReply( postData.cspostreply, reply );
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		editComment( commenttitle, wikitext, pageid, reply ) {
			const self = this;
			const params = {
				action: 'cseditcomment',
				pageid: pageid,
				wikitext: wikitext,
				commenttitle: commenttitle,
				token: mw.user.tokens.get( 'csrfToken' )
			};
			new mw.Api()
				.post( params )
				.done( function () {
					self.queryComment( pageid, reply );
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		editReply( wikitext, pageid, reply ) {
			const self = this;
			const params = {
				action: 'cseditreply',
				pageid: pageid,
				wikitext: wikitext,
				token: mw.user.tokens.get( 'csrfToken' )
			};
			new mw.Api()
				.post( params )
				.done( function () {
					self.queryReply( pageid, reply );
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		vote( pageid, vote, reply ) {
			const self = this;
			new mw.Api()
				.post( {
					action: 'csvote',
					pageid: pageid,
					vote: vote,
					token: mw.user.tokens.get( 'csrfToken' )
				} )
				.done( function () {
					reply();
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		watch( pageid, action, reply ) {
			const self = this;
			new mw.Api()
				.post( {
					action: action ? 'cswatch' : 'csunwatch',
					pageid: pageid,
					token: mw.user.tokens.get( 'csrfToken' )
				} )
				.done( function () {
					reply();
				} )
				.fail( function ( _code, error ) {
					self.reportError( error, reply );
				} );
		}

		reportError( error, reply ) {
			if ( error === 'invalid' ||
                error.error === undefined ||
                error.error.code === undefined ||
                error.error[ '*' ] === undefined ) {
				reply( {
					error: 'commentstreams-api-error-invalid'
				} );
			} else if ( error.error.code === 'nosuchpageid' ) {
				reply( {
					error: 'commentstreams-api-error-commentnotfound'
				} );
			} else if ( error.error.code === 'badtoken' ) {
				reply( {
					error: 'commentstreams-api-error-notloggedin'
				} );
			} else {
				// These types of errors should never happen, but in the case of install errors,
				// syntax errors during development, or conflicting extensions, they could happen.
				// Since there is no other good way of debugging them, they will be displayed.
				reply( {
					error: error.error[ '*' ]
				} );
			}
		}
	}

	return Querier;
}() );
