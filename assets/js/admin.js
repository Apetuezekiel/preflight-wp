/**
 * PreFlight admin JS — keyboard-accessible expand/collapse + AJAX rescan.
 *
 * Vanilla JS. No jQuery. ES5-compatible for broad browser support.
 */

/* global preflight */

document.addEventListener( 'DOMContentLoaded', function () {

	var statusEl  = document.getElementById( 'preflight-status' );
	var dashboard = document.getElementById( 'preflight-dashboard' );

	if ( ! dashboard ) {
		return;
	}

	// -------------------------------------------------------------------------
	// Category expand / collapse — delegated to #preflight-dashboard so it
	// survives innerHTML replacement after each AJAX rescan.
	// Keyboard (Enter / Space) is handled natively by the <button> element;
	// no custom keydown handler needed.
	// -------------------------------------------------------------------------

	function toggleCategory( toggle ) {
		var section  = toggle.closest( '.preflight-category' );
		var expanded = section.classList.contains( 'is-expanded' );
		var bodyId   = toggle.getAttribute( 'aria-controls' );
		var body     = bodyId ? document.getElementById( bodyId ) : null;

		if ( expanded ) {
			section.classList.remove( 'is-expanded' );
			toggle.setAttribute( 'aria-expanded', 'false' );
			if ( body ) {
				body.style.display = 'none';
			}
		} else {
			section.classList.add( 'is-expanded' );
			toggle.setAttribute( 'aria-expanded', 'true' );
			if ( body ) {
				body.style.display = '';
			}
		}
	}

	// -------------------------------------------------------------------------
	// Delegated click handler — covers category toggles AND the AJAX rescan
	// button. Both targets survive innerHTML replacement because the listener
	// is on the stable #preflight-dashboard parent.
	// -------------------------------------------------------------------------

	dashboard.addEventListener( 'click', function ( e ) {

		// Category expand / collapse.
		var toggle = e.target.closest( '.preflight-category__toggle' );
		if ( toggle ) {
			toggleCategory( toggle );
			return;
		}

		// AJAX rescan.
		var btn = e.target.closest( '#preflight-rescan-btn' );
		if ( ! btn ) {
			return;
		}

		if ( typeof fetch === 'undefined' ) {
			return;
		}

		e.preventDefault();

		btn.disabled    = true;
		btn.textContent = ( preflight && preflight.scanning ) ? preflight.scanning : 'Scanning…';

		if ( statusEl ) {
			statusEl.textContent = ( preflight && preflight.scanning ) ? preflight.scanning : 'Scanning…';
		}

		var formData = new FormData();
		formData.append( 'action', 'preflight_rescan' );
		formData.append( 'nonce', ( preflight && preflight.rescanNonce ) ? preflight.rescanNonce : '' );

		fetch( ( preflight && preflight.ajaxUrl ) ? preflight.ajaxUrl : '/wp-admin/admin-ajax.php', {
			method:      'POST',
			credentials: 'same-origin',
			body:        formData,
		} )
		.then( function ( response ) {
			return response.json();
		} )
		.then( function ( data ) {
			if ( data.success && data.data && data.data.html_partial ) {
				dashboard.innerHTML = data.data.html_partial;

				if ( statusEl ) {
					statusEl.textContent = 'Scan complete.';
				}
			} else {
				showError( dashboard );
			}
		} )
		.catch( function () {
			showError( dashboard );
		} )
		.finally( function () {
			var newBtn = document.getElementById( 'preflight-rescan-btn' );
			if ( newBtn ) {
				newBtn.disabled    = false;
				newBtn.textContent = ( preflight && preflight.rescan ) ? preflight.rescan : 'Re-scan';
			}
		} );
	} );

	function showError( container ) {
		var errorMsg = ( preflight && preflight.scanError ) ? preflight.scanError : 'Scan failed. Please try again.';
		var notice   = document.createElement( 'div' );
		notice.className = 'notice notice-error';
		notice.innerHTML = '<p>' + errorMsg + '</p>';

		var existing = container.querySelector( '.notice-error' );
		if ( existing ) {
			existing.remove();
		}
		container.insertBefore( notice, container.firstChild );

		if ( statusEl ) {
			statusEl.textContent = errorMsg;
		}
	}

	// -------------------------------------------------------------------------
	// History table — keyboard navigation (row as link)
	// -------------------------------------------------------------------------

	var historyRows = document.querySelectorAll( '.preflight-history-row' );
	for ( var k = 0; k < historyRows.length; k++ ) {
		( function ( row ) {
			row.addEventListener( 'click', function () {
				var url = row.getAttribute( 'data-drill-url' );
				if ( url ) {
					window.location.href = url;
				}
			} );
			row.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					var url = row.getAttribute( 'data-drill-url' );
					if ( url ) {
						window.location.href = url;
					}
				}
			} );
		} )( historyRows[ k ] );
	}
} );
