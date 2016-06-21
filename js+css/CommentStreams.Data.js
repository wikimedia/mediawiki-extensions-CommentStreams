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
	my.Data = {
		isLoggedIn: false,
		userPageURL: "",
		associatedPageId: "",
		associatedPageTitle:"",
		newestStreamsOnTop: false,
		smwInstalled: false,
		semanticTitlePropertyName: "",
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
		initializeConstants: function(isLoggedIn, userPageURL, associatedPageId, associatedPageTitle, smwInstalled, semanticTitlePropertyName, newestStreamsOnTop) {
			this.isLoggedIn = isLoggedIn;
			this.userPageURL = userPageURL;
			this.associatedPageId = associatedPageId;
			this.associatedPageTitle = associatedPageTitle;
			this.smwInstalled = smwInstalled == 1 ? true : false;
			this.semanticTitlePropertyName = semanticTitlePropertyName;
			this.newestStreamsOnTop = newestStreamsOnTop == 1 ? true : false;
		},
		log: function(text) {
			if ( ( window.console !== undefined ) )
				window.console.log( text );
		}
	};

	return my;
}( mediaWiki, jQuery, window.CommentStreams || {} ) );
