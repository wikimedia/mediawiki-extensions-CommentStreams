window.cs = window.cs || {};
window.window.cs.talkPageStore = {
	historyHandler: {
		init: function ( id ) {
			const dialog = new window.cs.talkPageStore.historyHandler.Dialog( {
				commentId: id,
				size: 'large'
			} );
			const windowManager = new OO.ui.WindowManager();
			$( 'body' ).append( windowManager.$element );
			windowManager.addWindows( [ dialog ] );
			windowManager.openWindow( dialog );
		}
	}
};

window.cs.talkPageStore.historyHandler.Dialog = function ( cfg ) {
	window.cs.talkPageStore.historyHandler.Dialog.parent.call( this, cfg );
	this.commentId = cfg.commentId;
};

OO.inheritClass( window.cs.talkPageStore.historyHandler.Dialog, OO.ui.ProcessDialog );

window.cs.talkPageStore.historyHandler.Dialog.static.name = 'csTalkPageStoreHistoryDialog';
window.cs.talkPageStore.historyHandler.Dialog.static.title = mw.msg( 'commentstreams-history-title' );
window.cs.talkPageStore.historyHandler.Dialog.static.actions = [
	{
		action: 'close',
		label: mw.msg( 'commentstreams-ui-close' ),
		flags: [ 'safe' ]
	}
];

window.cs.talkPageStore.historyHandler.Dialog.prototype.getReadyProcess = function ( data ) {
	return window.cs.talkPageStore.historyHandler.Dialog.parent.prototype.getReadyProcess
		.call( this, data )
		.next( function () {
			this.pushPending();
			this.load().done( ( history ) => {
				this.render( history );
				this.popPending();
			} ).fail( () => {
				this.showErrors( [] );
				this.updateSize();
				this.popPending();
			} );
		}, this );
};

window.cs.talkPageStore.historyHandler.Dialog.prototype.initialize = function () {
	window.cs.talkPageStore.historyHandler.Dialog.parent.prototype.initialize.call( this );

	this.panel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: false
	} );

	this.$body.append( this.panel.$element );
};

window.cs.talkPageStore.historyHandler.Dialog.prototype.load = function () {
	const dfd = $.Deferred();
	const response = $.ajax( {
		url: mw.util.wikiScript( 'rest' ) + '/commentstreams/v1/comments/' + this.commentId + '/history',
		method: 'GET'
	} );

	response.done( ( data ) => {
		dfd.resolve( data );
	} );
	response.fail( () => {
		dfd.reject();
	} );

	return dfd.promise();
};

window.cs.talkPageStore.historyHandler.Dialog.prototype.render = function ( data ) {
	const $table = $( '<table>' )
		.addClass( 'commentstreams-history-table' )
		.append( $( '<thead>' )
			.append( $( '<tr>' ).append(
				$( '<th>' ).text( mw.msg( 'commentstreams-history-timestamp' ) ),
				$( '<th>' ).text( mw.msg( 'commentstreams-history-actor' ) ),
				$( '<th>' ).text( mw.msg( 'commentstreams-history-text' ) )
			) ) );
	for ( const cid in data ) {
		const item = data[ cid ];
		$table.append( $( '<tr>' ).append(
			$( '<td>' ).text( item.timestamp ),
			$( '<td>' ).text( item.actor ),
			$( '<td>' ).text( item.text )
		) );
	}
	this.panel.$element.append( $table );
	this.updateSize();
};

window.cs.talkPageStore.historyHandler.Dialog.prototype.getActionProcess = function ( action ) {
	return window.cs.talkPageStore.historyHandler.Dialog.parent.prototype.getActionProcess
		.call( this, action )
		.next( function () {
			this.close();
		}, this );
};

window.cs.talkPageStore.historyHandler.Dialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}
	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight + 10;
};
