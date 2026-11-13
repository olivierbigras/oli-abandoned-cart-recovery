/**
 * Oli Abandoned Cart Recovery 1.2 : script d'administration (écrans du plugin seulement).
 * - Confirmation des liens et boutons .oli-acr-confirm (suppression, etc.).
 * - Rejet persistant des avis (Donate, avis de mise à jour 1.2).
 * - Réglages avancés : état ouvert/fermé mémorisé, ouverture sur une ancre #oli-acr-sec-*.
 * - Éditeur : insertion des balises dans TinyMCE ou le champ texte.
 * - Mode « sans case » : la case de confirmation devient obligatoire quand on le choisit.
 */
( function () {
	'use strict';

	var cfg = window.oliAcrAdmin || {};

	function post( action, data ) {
		if ( ! window.fetch || ! window.FormData ) {
			return;
		}
		var body = new window.FormData();
		body.append( 'action', action );
		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );
		window.fetch( cfg.ajaxUrl || window.ajaxurl || '', { method: 'POST', body: body, credentials: 'same-origin' } );
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}

		var confirmEl = target.closest( '.oli-acr-confirm' );
		if ( confirmEl ) {
			var message = confirmEl.getAttribute( 'data-confirm' ) || cfg.confirmDeleteTemplate || '';
			if ( message && ! window.confirm( message ) ) {
				event.preventDefault();
				return;
			}
		}

		if ( target.classList.contains( 'notice-dismiss' ) ) {
			var notice = target.closest( '[data-oli-acr-notice]' );
			if ( notice ) {
				post( 'oli_acr_dismiss', { notice: notice.getAttribute( 'data-oli-acr-notice' ), nonce: cfg.dismissNonce || '' } );
			} else if ( target.closest( '.oli-acr-donate-notice' ) ) {
				post( 'oli_acr_dismiss_donate', { nonce: cfg.donateNonce || '' } );
			}
			return;
		}

		var tag = target.closest( '.oli-acr-insert-tag' );
		if ( tag ) {
			event.preventDefault();
			var text   = tag.getAttribute( 'data-tag' ) || '';
			var editor = window.tinymce && window.tinymce.get ? window.tinymce.get( 'oli_acr_content' ) : null;
			if ( editor && ! editor.isHidden() ) {
				editor.focus();
				editor.execCommand( 'mceInsertContent', false, text );
				return;
			}
			var area = document.getElementById( 'oli_acr_content' );
			if ( area ) {
				var start = area.selectionStart || 0;
				var end   = area.selectionEnd || 0;
				area.value = area.value.slice( 0, start ) + text + area.value.slice( end );
				area.focus();
				area.selectionStart = area.selectionEnd = start + text.length;
			}
		}
	} );

	// Mode « sans case » : la confirmation est exigée (aussi vérifiée côté serveur).
	document.addEventListener( 'change', function ( event ) {
		var input = event.target;
		if ( ! input || 'radio' !== input.type || 's[guest_tracking]' !== input.name ) {
			return;
		}
		var confirmBox = input.form ? input.form.querySelector( 'input[name="s[always_confirm]"]' ) : null;
		if ( confirmBox ) {
			confirmBox.required = input.classList.contains( 'oli-acr-always' ) && input.checked;
		}
	} );

	// Réglages avancés : ouverts si l'URL vise une section, sinon dernier état choisi.
	var details = document.getElementById( 'oli-acr-advanced' );
	if ( details ) {
		var key = 'oliAcrAdvancedOpen';
		var store;
		try {
			store = window.localStorage;
		} catch ( e ) {
			store = null;
		}
		if ( 0 === window.location.hash.indexOf( '#oli-acr-sec-' ) ) {
			details.open = true;
			var section = document.getElementById( window.location.hash.slice( 1 ) );
			if ( section ) {
				section.scrollIntoView();
			}
		} else if ( store && '1' === store.getItem( key ) ) {
			details.open = true;
		}
		details.addEventListener( 'toggle', function () {
			if ( store ) {
				store.setItem( key, details.open ? '1' : '0' );
			}
		} );
		Array.prototype.forEach.call( document.querySelectorAll( 'a[href^="#oli-acr-sec-"]' ), function ( link ) {
			link.addEventListener( 'click', function () {
				details.open = true;
			} );
		} );
	}
}() );
