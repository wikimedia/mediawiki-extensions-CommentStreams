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
	my.Controller = {
		newCommentStreamShowing: false,
		currentNewReplyShowing: null,
		initialize: function(isLoggedIn, userPageURL, associatedPageId, associatedPageTitle, smwInstalled, semanticTitlePropertyName, newestStreamsOnTop) {
			var self = this;

			// Reorganize the content and comments into container divs.
			self.reorganizeContentAndComments();

			if(isLoggedIn) {
				// 4/6/16 NOTE: This only seems to work if CommentStreams.Data.js is listed BEFORE CommentStreams.Controller.js in extension.json.
				// Otherwise, it fails because this initialization is called before CommentStreams.Data.js is loaded.
				// I'm not sure if that's expected behavior for ResourceLoader or not.

				my.Data.initializeConstants(isLoggedIn, userPageURL, associatedPageId, associatedPageTitle, smwInstalled, semanticTitlePropertyName, newestStreamsOnTop);
				// Set up action for the 'Start New Comment Stream' button.
				$('#cs-newStreamButton').click(function() {
					if(!self.newCommentStreamShowing) {
						self.newCommentStreamShowing = true;
						self.hideNewReplyBox(false);
						self.showNewCommentStreamBox();
						$(this).attr('disabled', 'disabled');
					}
				});
			}

			// Set up the rest of the button handlers.
			self.resetButtonHandlers();
		},
		reorganizeContentAndComments : function() {
			var self = this;
			var comments = $('#cs-comments').detach();
			$(comments).insertAfter('#catlinks');

			// Collapse all comments if necessary.
			$('.cs-comment-thread').each(function() {
				if($(this).hasClass('cs-collapsed'))
					self.collapseThread($(this), $(this).find('.cs-toggleButton'));
			});
		},
		resetButtonHandlers: function(isLoggedIn) {
			var self = this;

			$('.cs-toggleButton').unbind();
			$('.cs-toggleButton').click(function() {
				var thread = $(this).closest('.cs-comment-thread');
				if(thread.hasClass('cs-expanded'))
					self.collapseThread(thread, this);
				else
					self.expandThread(thread, this);
			});

			if(my.Data.isLoggedIn) {
				$('.cs-newReplyButton').unbind();
				$('.cs-newReplyButton').click(function() {
					if(self.currentNewReplyShowing)
						self.hideNewReplyBox(false);
					if(self.newCommentStreamShowing)
						self.hideNewStreamBox();

					$(this).attr('disabled', 'disabled');

					var pageId = $(this).attr('data-thread-id');
					self.showNewReplyBox($(this), pageId);
				});

				$('.cs-editCommentButton').unbind();
				$('.cs-editCommentButton').click(function() {
					var comment = $(this).closest('.cs-comment');
					var pageId = $(comment).attr('data-id');
					my.Data.log("Page ID: "+pageId);
					self.editComment($(comment), pageId);
				});

				$('.cs-deleteCommentButton').unbind();
				$('.cs-deleteCommentButton').click(function() {
					var comment = $(this).closest('.cs-comment');
					var pageId = $(comment).attr('data-id');
					var type = $(comment).hasClass('cs-head-comment') ? 'headComment' : 'childComment';
					self.deleteComment($(comment), type, pageId);
				});
			}
		},
		collapseThread: function(thread, button) {
			var allButTitle = thread.find('*').not('.cs-head-comment, .cs-commentTitle, .cs-commentTitle *, .cs-thread-footer');
			allButTitle.css('display', 'none');

			var titleBanner = thread.find('.cs-commentTitle');
			titleBanner.css('background-color', '#BDC3C7');

			var threadFooter = thread.find('.cs-thread-footer');
			threadFooter.append('<img class="cs-collapsed-ellipsis" src="'+mw.config.get("wgServer")+'/'+mw.config.get("wgScriptPath")+'/extensions/CommentStreams/js+css/images/more.png">');

			$(thread).addClass('cs-collapsed');
			$(thread).removeClass('cs-expanded');
			$(button).text("EXPAND");

			$(thread).css('margin-bottom', '0');
		},
		expandThread: function(thread, button) {
			var allButTitle = thread.find('*').not('.cs-head-comment, .cs-commentTitle, .cs-commentTitle *, .cs-thread-footer');
			allButTitle.removeAttr('style');

			var titleBanner = thread.find('.cs-commentTitle');
			titleBanner.removeAttr('style');

			var threadFooterEllipsis = thread.find('.cs-thread-footer > .cs-collapsed-ellipsis');
			threadFooterEllipsis.remove();

			$(thread).addClass('cs-expanded');
			$(thread).removeClass('cs-collapsed');
			$(button).text("COLLAPSE");

			$(thread).removeAttr('style');
		},
		disableAllButtons: function() {
			$('.cs-editCommentButton').attr('disabled', 'disabled');
			$('.cs-newReplyButton').attr('disabled', 'disabled');
			$('#cs-newStreamButton').attr('disabled', 'disabled');
			$('.cs-deleteCommentButton').attr('disabled', 'disabled');
		},
		htmlForCommentType: function(type, createdPageId, commentData, edit) {
			if(commentData.title === null)
				commentData.title = "";

			var deleteButtonHTML = 	'<span class=\'cs-deleteSpan\'>| <button class=\'cs-button cs-deleteCommentButton\'>DELETE</button></span>';
			if(edit && commentData.children > 0)
				deleteButtonHTML = '';

			var newCommentHTML = '\
			<div class=\'cs-commentTitle\'>';
			if(type === 'head')
				newCommentHTML += '<button type="button" class="cs-button cs-toggleButton">COLLAPSE</button>';
			newCommentHTML += '\
				<img src=\''+mw.config.get("wgServer")+'/'+mw.config.get("wgScriptPath")+'/extensions/CommentStreams/js+css/images/user.png\' class=\'cs-userImage\'>\
				<div class=\'cs-commentAuthor\'>\
					'+ my.Data.userPageURL + '\
				</div>\
				<p>' + commentData.title + '</p>\
			</div>\
			<div class=\'cs-commentBody\'>\
				' + commentData.html + '\
			</div>\
			<div class=\'cs-commentFooter\'>\
				<span class=\'cs-commentDetails\'>Posted on ' + commentData.timestamp + '</span>\
				|\
				<button class=\'cs-button cs-editCommentButton\'>EDIT</button>\
				' + deleteButtonHTML + '\
			</div>\
			';

			if(!edit) {
				var commentClass = (type === 'child') ? 'cs-reply-comment' : 'cs-head-comment';

				newCommentHTML = '\
				<div class=\'cs-comment ' + commentClass + '\' data-id=' + createdPageId + '>\
					' + newCommentHTML + '\
				</div>';

				if(type === 'head') {
					newCommentHTML = '<div class=\'cs-comment-thread cs-expanded\'>' + newCommentHTML;

					newCommentHTML += '\
						<div class=\'cs-thread-footer\'>\
							<button class=\'cs-button cs-newReplyButton\' type=\'button\' data-thread-id=' + createdPageId + '>\
								REPLY\
							</button>\
						</div>\
					</div>';
				}
			}
			return newCommentHTML;
		},
		htmlForCommentBoxType: function(type, topCommentId, edit) {
			var outerDiv = (type === 'child') ? 'cs-newReplyBox' : 'cs-newStreamBox';
			var commentDiv = (type === 'child') ? 'cs-comment' : 'cs-comment-thread';
			var streamType = (type === 'child') ? 'cs-newReplyBoxInner' : 'cs-newStreamBoxInner';
			var commentType = (type === 'child') ? 'cs-reply-comment' : 'cs-head-comment';
			var submitButton = (type === 'child') ? '<button class=\'cs-button cs-reply-submitCommentButton\' id=submit-' + topCommentId + '>SUBMIT</button>' :
													'<button class=\'cs-button\' id=\'cs-stream-submitCommentButton\'>SUBMIT</button>';
			var cancelButton = (type === 'child') ? '<button class=\'cs-button cs-reply-cancelCommentButton\' id=cancel-' + topCommentId + '>CANCEL</button>' :
													'<button class=\'cs-button\' id=\'cs-stream-cancelCommentButton\'>CANCEL</button>';

			var commentBoxHTML = '\
			<div class=\'cs-commentTitle\'>\
				<img src=\''+mw.config.get("wgServer")+'/'+mw.config.get("wgScriptPath")+'/extensions/CommentStreams/js+css/images/user.png\' class=\'cs-userImage\'>\
				<div class=\'cs-commentAuthor\' style=\'padding-top:0em;\'>\
					<b>' + my.Data.userPageURL + '</b>\
				</div>\
				<div id=\'cs-titleBoxDiv\'>\
					<input type=\'text\' id=\'cs-titleTextbox\' placeholder=\'Title\'>\
				</div>\
			</div>\
			<div class=\'cs-commentBody\' id=\'cs-newCommentBody\'>\
				<textarea id=\'cs-newCommentTextArea\' rows=6 cols=50 placeholder=\'Enter a new comment...\'></textarea>\
			</div>\
			<div class=\'cs-commentButtons\'>\
				' + submitButton + '\
				' + cancelButton + '\
			</div>\
			';

			if(!edit) {
				commentBoxHTML = '\
				<div id=' + outerDiv + ' class=' + commentDiv + ' style=\'display: none;\'>\
					<div id=' + streamType + '>\
						<div class=' + commentType + '>\
						' + commentBoxHTML + '\
						</div>\
					</div>\
				</div>';
			}

			return commentBoxHTML;
		},
		showNewCommentStreamBox: function() {
			var self = this;

			var textBoxHTML = self.htmlForCommentBoxType('head', null, false);
			if(my.Data.newestStreamsOnTop) {
				$('#cs-comments-header').append(textBoxHTML);
				$('#cs-newStreamBox').slideDown();
			}
			else {
				$('#cs-comments-footer').prepend(textBoxHTML);
				$('#cs-newStreamBox').slideDown();
			}
			$('#cs-stream-cancelCommentButton').click(function() {
				self.hideNewStreamBox(true);
			});
			$('#cs-stream-submitCommentButton').click(function() {
				self.postComment(null);
			});
		},
		showNewReplyBox: function(element, topCommentId) {
			var self = this;
			var newCommentHTML = self.htmlForCommentBoxType('child', topCommentId, false);

			$(newCommentHTML).insertBefore($(element).closest('.cs-thread-footer')).hide().slideDown();
			self.currentNewReplyShowing = $('#cs-newReplyBox');

			$('#cancel-'+topCommentId).click(function() {
				self.hideNewReplyBox(true);
			});
			$('#submit-'+topCommentId).click(function() {
				self.postComment(topCommentId);
			});
		},
		hideNewReplyBox: function(animated) {
			var self = this;
			$('.cs-newReplyButton').attr('disabled', false);
			if(animated) {
				$(self.currentNewReplyShowing).slideUp('normal', function() { 
					$(self.currentNewReplyShowing).remove(); 
					self.currentNewReplyShowing = null;
				});
			}
			else {
				$(self.currentNewReplyShowing).remove(); 
				self.currentNewReplyShowing = null;
			}
		},
		hideNewStreamBox: function(animated) {
			var self = this;
			$('#cs-newStreamButton').attr('disabled', false);

			if(animated) {
				$('#cs-newStreamBox').slideUp('normal', function() { 
					$('#cs-newStreamBox').remove(); 
					self.newCommentStreamShowing = false;
				});
			}
			else {
				$('#cs-newStreamBox').remove(); 
				self.newCommentStreamShowing = false;
			}
		},
		postComment: function(parentCommentId) {
			var self = this;

			var commentElementInner = parentCommentId ? 'cs-newReplyBoxInner' : 'cs-newStreamBoxInner';
			var commentElementOuter = parentCommentId ? 'cs-newReplyBox' : 'cs-newStreamBox';


			// Extract comment title and text from the new comment box.
			var commentTitle = $('#cs-titleTextbox').val();
			var commentText = $('#cs-newCommentTextArea').val();

			if(commentText === null || commentText === "") {
				alert("You must enter comment text.");
				return;
			}

			// Disable the Submit and Cancel buttons.
			$('#cs-submitCommentButton').attr('disabled', 'disabled');
			$('#cs-cancelCommentButton').attr('disabled', 'disabled');

			// Dim new comment box, show spinner, and fire off API call to post new comment. On reply, hide new comment box.
			$('#'+commentElementInner).fadeTo(100, 0.2, function() { 
				new Spinner( my.Data.spinnerOptions ).spin( document.getElementById( commentElementOuter ) ); 

				// If SMW installed, set the associated page title with {{#set:}}.
				if(my.Data.smwInstalled) {
					var associatedTitleSet = "{{#set:CS Associated Page="+my.Data.associatedPageTitle+"}}";
					commentText += associatedTitleSet;
				}

				// If SMW installed, and a value for semantic title property name is set, set the semantic title with {{#set:}}.
				if(my.Data.smwInstalled && my.Data.semanticTitlePropertyName !== '') {
					var semanticTitleSet = "{{#set:" + my.Data.semanticTitlePropertyName+"="+commentTitle+"}}";
					commentText += semanticTitleSet;
				}

				my.Querier.postComment(commentTitle, commentText, my.Data.associatedPageId, parentCommentId, function(postData) {
					if(postData.result === 'success') {
						createdPageId = postData.data.pageIdCreated;
						commentData = postData.data.commentData;

						if(parentCommentId) {
							var newCommentHTML = self.htmlForCommentType('child', createdPageId, commentData, false);
							$('#cs-newReplyBox').closest('.cs-comment-thread').find('.cs-head-comment').find('.cs-deleteSpan').remove();

							var location = $('#cs-newReplyBox').closest('.cs-comment-thread').find('.cs-thread-footer');
							self.hideNewReplyBox(false);
							$(newCommentHTML).insertBefore($(location)).hide().slideDown();

							self.currentNewReplyShowing = null;
							self.resetButtonHandlers();
						}
							
						else {
							var newCommentHTML = self.htmlForCommentType('head', createdPageId, commentData, false);
							self.hideNewStreamBox(false);
							if(my.Data.newestStreamsOnTop) {
								$(newCommentHTML).insertAfter('#cs-comments-header').hide().slideDown();
							}
							else {
								$(newCommentHTML).insertBefore('#cs-comments-footer').hide().slideDown();
							}
							self.resetButtonHandlers();
						}
					}
					else {
						alert("Your post failed, something went wrong.");
						my.Data.log(postData);
						$('#'+commentElementOuter).contents().unwrap();
						$('.spinner').remove();
						self.disableAllButtons();
					}
				});
			});
		},
		deleteComment: function(element, type, pageId) {
			var self = this;
			$(element).children().wrapAll('<div id=\'innerDelete\'>');
			$('#innerDelete').wrap('<div id=\'outerDelete\' style=\'position:relative;\'>');
			$('#innerDelete').fadeTo(100, 0.2, function() { 
				new Spinner( my.Data.spinnerOptions ).spin( document.getElementById( 'outerDelete' ) ); 
				my.Querier.deleteComment(type, pageId, function(deleteResult) {
					if(deleteResult.result === 'success' || deleteResult.error === 'Page does not exist') {
						if(deleteResult.error === 'Page does not exist')
							alert("This comment no longer exists. Perhaps it was already deleted?");
						if(type === 'childComment') {
							var headCommentFooter = $(element).closest('.cs-comment-thread').find('.cs-head-comment').find('.cs-commentFooter');
							var parentId = $(element).closest('.cs-comment-thread').find('.cs-head-comment').attr('data-id');
							my.Querier.queryForChildrenCount(parentId, function(count) {
								if( count === 0 ) {
									$(headCommentFooter).append('\
										<span class=\'cs-deleteSpan\'>\
											|\
											<button class=\'cs-button cs-deleteCommentButton\'>DELETE</button>\
										</span>');
								}
								$(element).slideUp('normal', function() { 
									$(element).remove(); 
									self.resetButtonHandlers();
								});
							})
						}
						else {
							$(element).closest('.cs-comment-thread').slideUp('normal', function() {
								$(element).closest('.cs-comment-thread').remove();
								self.resetButtonHandlers();
							}); 
						}
					}
					else if(deleteResult.error === 'haschildren') {
						alert("Cannot delete thread - it now has replies. Please refresh the page to view them.");
						$('#innerDelete').contents().unwrap();
						$('.spinner').remove();
						self.resetButtonHandlers();
					}
					else {
						alert("Your delete failed, something went wrong.");
						my.Data.log(deleteResult);
						$('#outerDelete').contents().unwrap();
						$('.spinner').remove();
						self.disableAllButtons();
					}
				});
			});
		},
		editComment: function(element, pageId) {
			var self = this;

			var commentType = $(element).hasClass('cs-head-comment') ? 'head' : 'child';
			$(element).children().wrapAll('<div id=\'innerEdit\'>');
			$('#innerEdit').wrap('<div id=\'outerEdit\' style=\'position:relative;\'>');
			$('#innerEdit').fadeTo(100, 0.2, function() {
				new Spinner( my.Data.spinnerOptions ).spin( document.getElementById( 'outerEdit' ) );
				my.Querier.queryForCommentWikitext(pageId, function(wikitextStatus) {
					if(wikitextStatus.result === 'error') {
						alert("Couldn't fetch the wikitext. Perhaps this comment was already deleted?");
						$(element).slideUp('normal', function() { 
							$('#outerEdit').contents().unwrap();

							self.savedEditedComment = null;
							self.resetButtonHandlers();

							$('.cs-editCommentButton').attr('disabled', false);
							$('.cs-newReplyButton').attr('disabled', false);
							$('#cs-newStreamButton').attr('disabled', false);
						});
					}
					commentData = wikitextStatus.data;
					commentData.title = commentData.title.replace(/&amp;/g, '&')
												 .replace(/&lt;/g, '<')
												 .replace(/&gt;/g, '>')
												 .replace(/&quot;/g, '"')
												 .replace(/&#039;/g, '\'');
												 
					// Remove semantic title {{#set:}} from the commentData
					if(my.Data.smwInstalled) {
						var regex = new RegExp("{{#set:CS Associated Page=.*}}");
						commentData.wikitext = commentData.wikitext.replace(regex, "");
					}
					if(my.Data.smwInstalled && my.Data.semanticTitlePropertyName !== '') {
						var regex = new RegExp("{{#set:"+my.Data.semanticTitlePropertyName+"=.*}}");
						commentData.wikitext = commentData.wikitext.replace(regex, "");
					}

					self.savedEditedComment = $('#innerEdit');
					$('#innerEdit').remove();
					$('.spinner').remove();
					$('.cs-editCommentButton').attr('disabled', 'disabled');
					$('.cs-newReplyButton').attr('disabled', 'disabled');
					$('#cs-newStreamButton').attr('disabled', 'disabled');


					var commentBox = self.htmlForCommentBoxType('child', pageId, true);
					$('#outerEdit').append(commentBox);
					$('#cs-titleTextbox').val(commentData.title);
					$('#cs-newCommentTextArea').val(commentData.wikitext);

					$('#cancel-'+pageId).click(function() {
						var parent = $('#outerEdit').parent();
						$('#outerEdit').remove();
						parent.append(self.savedEditedComment);
						$('#innerEdit').contents().unwrap();

						self.savedEditedComment = null;
						self.resetButtonHandlers();

						$('.cs-editCommentButton').attr('disabled', false);
						$('.cs-newReplyButton').attr('disabled', false);
						$('#cs-newStreamButton').attr('disabled', false);
					});
					$('#submit-'+pageId).click(function() {
						var commentTitle = $('#cs-titleTextbox').val();
						var commentText = $('#cs-newCommentTextArea').val();

						if(commentText === null || commentText === "") {
							alert("You must enter comment text.");
							return;
						}
						$('#outerEdit').children().wrapAll('<div id=\'innerEdit\'>');
						$('#innerEdit').fadeTo(100, 0.2, function() {
							new Spinner( my.Data.spinnerOptions ).spin( document.getElementById( 'outerEdit' ) );
							var commentTitle = $('#cs-titleTextbox').val();
							var commentText = $('#cs-newCommentTextArea').val();

							// Put back the {{#set:}} call into the comment
							if(my.Data.smwInstalled) {
								var associatedTitleSet = "{{#set:CS Associated Page="+my.Data.associatedPageTitle+"}}";
								commentText += associatedTitleSet;
							}
							if(my.Data.smwInstalled && my.Data.semanticTitlePropertyName !== '') {
								var semanticTitleSet = "{{#set:" + my.Data.semanticTitlePropertyName+"="+commentTitle+"}}";
								commentText += semanticTitleSet;
							}

							my.Querier.editComment(commentTitle, commentText, pageId, function(editStatus) {
								if(editStatus.result === 'success') {
									var newCommentHTML = self.htmlForCommentType(commentType, pageId, editStatus.data, true);

									$('#outerEdit').empty();
									$('#outerEdit').append(newCommentHTML);

									$('#outerEdit').contents().unwrap();

									self.savedEditedComment = null;
									self.resetButtonHandlers();

									$('.cs-editCommentButton').attr('disabled', false);
									$('.cs-newReplyButton').attr('disabled', false);
									$('#cs-newStreamButton').attr('disabled', false);
								}
								else {
									if(editStatus.error === 'Page does not exist') {
										alert("This comment no longer exists. Perhaps it was already deleted?");
										var parent = $('#outerEdit').parent();
										$('#outerEdit').remove();
										parent.append(self.savedEditedComment);
										$('#innerEdit').contents().unwrap();

										self.savedEditedComment = null;
										self.resetButtonHandlers();

										$('.cs-editCommentButton').attr('disabled', false);
										$('.cs-newReplyButton').attr('disabled', false);
										$('#cs-newStreamButton').attr('disabled', false);
									}
									else {
										alert("Your edit failed, something went wrong.");
										my.Data.log(editStatus);
										$('#outerEdit').contents().unwrap();
										$('.spinner').remove();
										self.disableAllButtons();
									}
								}
							});	
						});
					});
				});
			});
		}
	};

	return my;
}( mediaWiki, jQuery, window.CommentStreams || {} ) );

$( function() {
	if( mw.config.exists('CommentStreams') ) {
		var config = mw.config.get( 'CommentStreams' );
		CommentStreams.Controller.initialize(config.isLoggedIn, 
			config.userPageURL, config.pageId, config.pageTitle, 
			config.smwInstalled, config.semanticTitlePropertyName, 
			config.newestStreamsOnTop);
	}
})
