/**
 * Oli Abandoned Cart Recovery : script d'administration (écrans du plugin seulement).
 * Confirmation avant les liens de suppression marqués .oli-acr-confirm (remplace les attributs onclick).
 * Mémorise le rejet de l'avis Donate (remplace le script inline).
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var link = event.target && event.target.closest ? event.target.closest( 'a.oli-acr-confirm' ) : null;
		if ( ! link ) {
			return;
		}
		var cfg     = window.oliAcrAdmin || {};
		var message = link.getAttribute( 'data-confirm' ) || cfg.confirmDeleteTemplate || '';
		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	var notice = document.querySelector( '.oli-acr-donate-notice' );
	if ( ! notice ) {
		return;
	}
	notice.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.classList || ! target.classList.contains( 'notice-dismiss' ) ) {
			return;
		}
		var cfg  = window.oliAcrAdmin || {};
		var body = new window.FormData();
		body.append( 'action', 'oli_acr_dismiss_donate' );
		body.append( 'nonce', cfg.donateNonce || '' );
		if ( window.fetch ) {
			window.fetch( cfg.ajaxUrl || ( typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '' ), {
				method: 'POST',
				body: body,
				credentials: 'same-origin'
			} );
		}
	} );
}() );
