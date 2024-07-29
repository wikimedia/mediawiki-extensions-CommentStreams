( function () {
	'use strict';

	$( function () {
		// Iterate over each <a> element that has a 'data-search-anchor' attribute
		$( 'a' ).each( function () {
			const $this = $( this );
			const newDataAnchor = $this.data( 'search-anchor' );
			let url = $this.attr( 'href' );

			// Check if the URL already has a hash
			if ( url.indexOf( '#' ) > -1 ) {
				// Replace the existing anchor with the new data anchor
				url = url.replace( /#.*$/, '#' + newDataAnchor );
			} else {
				// Append the new data anchor to the URL
				url += '#' + newDataAnchor;
			}

			// Update the href attribute with the new URL
			$this.attr( 'href', url );
		} );
	} );
}() );
