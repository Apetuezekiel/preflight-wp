/**
 * PreFlight admin JS — keyboard-accessible expand/collapse + AJAX rescan.
 *
 * Vanilla JS. No jQuery. ES5-compatible for broad browser support.
 */

/* global preflight */

document.addEventListener( 'DOMContentLoaded', function () {

	// -------------------------------------------------------------------------
	// Category expand / collapse
	// -------------------------------------------------------------------------

	var headers = document.querySelectorAll( '.preflight-category__header' );

	function toggleCategory( header ) {
		var section  = header.closest( '.preflight-category' );
		var expanded = section.classList.contains( 'is-expanded' );
		var bodyId   = header.getAttribute( 'aria-controls' );
		var body     = bodyId ? document.getElementById( bodyId ) : null;

		if ( expanded ) {
			section.classList.remove( 'is-expanded' );
			header.setAttribute( 'aria-expanded', 'false' );
			if ( body ) {
				body.style.display = 'none';
			}
		} else {
			section.classList.add( 'is-expanded' );
			header.setAttribute( 'aria-expanded', 'true' );
			if ( body ) {
				body.style.display = '';
			}
		}
	}

	for ( var i = 0; i < headers.length; i++ ) {
		( function ( header ) {
			header.addEventListener( 'click', function () {
				toggleCategory( header );
			} );

			header.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					toggleCategory( header );
				}
			} );
		} )( headers[ i ] );
	}

	// -------------------------------------------------------------------------
	// AJAX rescan — delegated to stable #preflight-dashboard parent so the
	// listener survives innerHTML replacement after each scan.
	// -------------------------------------------------------------------------

	var statusEl  = document.getElementById( 'preflight-status' );
	var dashboard = document.getElementById( 'preflight-dashboard' );

	if ( ! dashboard ) {
		return;
	}

	dashboard.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '#preflight-rescan-btn' );
		if ( ! btn ) {
			return;
		}

		// Only intercept if fetch is available.
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

				// Re-attach collapse behaviour to newly injected headers.
				// (The rescan-btn delegation on dashboard survives automatically.)
				var newHeaders = dashboard.querySelectorAll( '.preflight-category__header' );
				for ( var j = 0; j < newHeaders.length; j++ ) {
					( function ( h ) {
						h.addEventListener( 'click', function () { toggleCategory( h ); } );
						h.addEventListener( 'keydown', function ( ev ) {
							if ( ev.key === 'Enter' || ev.key === ' ' ) {
								ev.preventDefault();
								toggleCategory( h );
							}
						} );
					} )( newHeaders[ j ] );
				}

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
			// btn may be gone if the DOM was replaced; re-query by ID.
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
