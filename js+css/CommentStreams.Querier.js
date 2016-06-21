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

window.CommentStreams = ( function( mw, $, my ) {
	my.Querier = {
		postComment: function(commentTitle, commentText, commentedPageId, parentCommentId, reply) {
			var type = parentCommentId ? 'childComment' : 'headComment';
			var api = new mw.Api();
				api.post({
					action: 'csPostComment',
					type: type,
					token: mw.user.tokens.get( 'editToken' ),
					commentText: commentText,
					commentTitle: commentTitle,
					commentedPageId: commentedPageId,
					parent: parentCommentId
				}).done(function(data) {
					reply(data.csPostComment);
				}).fail(function(data) {
					my.Data.log("ERROR: "+data);
					reply({"result" : "error", "data" : data});
				});
		},
		queryForCommentHTML: function(commentPageId, reply) {
			var api = new mw.Api();
			api.get({
				action: 'csQueryComment',
				pageId: commentPageId
			}).done(function(data) {
				reply(data.csQueryComment);
			}).fail(function(data) {
				my.Data.log("ERROR: "+data);
				reply({"result" : "error", "data" : data});
			});
		},
		queryForCommentWikitext: function(commentPageId, reply) {
			var api = new mw.Api();
			api.get({
				action: 'csQueryComment',
				pageId: commentPageId,
				pageFormat: 'wikitext'
			}).done(function(data) {
				reply({"result" : "success", "data" : data.csQueryComment});
			}).fail(function(data) {
				my.Data.log("ERROR: "+data);
				reply({"result" : "error", "data" : data});
			});
		},
		queryForChildrenCount: function(commentPageId, reply) {
			var api = new mw.Api();
			api.get({
				action: 'csQueryDatabase',
				query: 'childrenCount',
				parentCommentId: commentPageId
			}).done(function(data) {
				reply(data.csQueryDatabase.childrenCount);
			}).fail(function(data) {
				my.Data.log("ERROR: "+data);
				reply({"result" : "error", "data" : data});
			})
		},
		deleteComment: function(type, commentPageId, reply) {
			var api = new mw.Api();
			api.post({
				action: 'csDeleteComment',
				pageId: commentPageId,
				type: type,
				token: mw.user.tokens.get( 'editToken' )
			}).done(function(data) {
				reply(data.csDeleteComment);
			}).fail(function(data) {
				my.Data.log("ERROR: "+data);
				reply({"result" : "error", "data" : data.csDeleteComment});
			});
		},
		editComment: function(commentTitle, commentText, pageId, reply) {
			var api = new mw.Api();
			api.post({
				action: 'csEditComment',
				pageId: pageId,
				commentTitle: commentTitle,
				commentText: commentText,
				token: mw.user.tokens.get( 'editToken' )
			}).done(function(data) {
				reply(data.csEditComment);
			}).fail(function(data) {
				my.Data.log("ERROR: "+data);
				reply({"result" : "error", "error" : data});
			});
		}
	};

	return my;
}( mediaWiki, jQuery, window.CommentStreams || {} ) );
