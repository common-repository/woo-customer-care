jQuery( document ).ready( function() {
	// assign to
	jQuery( 'body' ).on( 'click', '#woocc_assign_to', function( e ) {
		var order = jQuery( this ).attr( 'data-order' );
		var user = jQuery( '#woocc_assign_user' ).val();
		var data = {
			action: 'woocc_assign',
			nonce: woocc_vars.nonce,
			order: order,
			user: user,
		};
		jQuery( this ).html( '...' );
		jQuery.post( woocc_vars.url, data, function( response ) {
			jQuery( '#woocc_metabox' ).html( response );
		} );
		e.preventDefault();
	} );

	// assign me
	jQuery( 'body' ).on( 'click', '#woocc_assign_me', function( e ) {
		var order = jQuery( this ).attr( 'data-order' );
		var data = {
			action: 'woocc_assign',
			nonce: woocc_vars.nonce,
			order: order,
		};
		jQuery( this ).html( '...' );
		jQuery.post( woocc_vars.url, data, function( response ) {
			jQuery( '#woocc_metabox' ).html( response );
		} );
		e.preventDefault();
	} );

	// remove me
	jQuery( 'body' ).on( 'click', '#woocc_remove_me', function( e ) {
		var order = jQuery( this ).attr( 'data-order' );
		var data = {
			action: 'woocc_remove',
			nonce: woocc_vars.nonce,
			order: order,
		};
		jQuery( this ).html( '...' );
		jQuery.post( woocc_vars.url, data, function( response ) {
			jQuery( '#woocc_metabox' ).html( response );
		} );
		e.preventDefault();
	} );

	// remove user
	jQuery( 'body' ).on( 'click', '#woocc_remove_user', function( e ) {
		var order = jQuery( this ).attr( 'data-order' );
		var user = jQuery( this ).attr( 'data-user' );
		var data = {
			action: 'woocc_remove',
			nonce: woocc_vars.nonce,
			order: order,
			user: user,
		};
		jQuery( this ).html( '...' );
		jQuery.post( woocc_vars.url, data, function( response ) {
			jQuery( '#woocc_metabox' ).html( response );
		} );
		e.preventDefault();
	} );

	// delete note
	jQuery( 'body' ).on( 'click', '.woocc-delete-note', function( e ) {
		if ( window.confirm( 'Are you sure you wish to delete this note? This action cannot be undone.' ) ) {
			var order = jQuery( this ).attr( 'data-order' );
			var note = jQuery( this ).attr( 'data-note' );
			var data = {
				action: 'woocc_delete_note',
				nonce: woocc_vars.nonce,
				order: order,
				note: note,
			};
			jQuery( this ).html( '...' );
			jQuery.post( woocc_vars.url, data, function( response ) {
				jQuery( '#woocc_metabox' ).html( response );
			} );
		}
		e.preventDefault();
	} );

	// take over
	jQuery( 'body' ).on( 'click', '#woocc_take_over', function( e ) {
		jQuery( '#post-lock-dialog' ).remove();
		e.preventDefault();
	} );
} );