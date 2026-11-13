( function () {
	'use strict';

	var source = document.querySelector( '#source' );
	var nameFormat = document.querySelector( '#zone_name_format' );

	function updateSourcePanels() {
		if ( ! source ) {
			return;
		}

		document.querySelectorAll( '[data-source-panel]' ).forEach( function ( panel ) {
			panel.hidden = panel.dataset.sourcePanel !== source.value;
		} );
	}

	function updateNameTemplate() {
		if ( ! nameFormat ) {
			return;
		}

		document.querySelectorAll( '[data-zone-template]' ).forEach( function ( field ) {
			field.hidden = 'custom' !== nameFormat.value;
		} );
	}

	if ( source ) {
		source.addEventListener( 'change', updateSourcePanels );
		updateSourcePanels();
	}

	if ( nameFormat ) {
		nameFormat.addEventListener( 'change', updateNameTemplate );
		updateNameTemplate();
	}

	document.querySelectorAll( '.shipbridge-dropzone' ).forEach( function ( dropzone ) {
		var input = dropzone.querySelector( 'input[type="file"]' );
		var label = dropzone.querySelector( '.shipbridge-file-name' );

		if ( ! input || ! label ) {
			return;
		}

		input.addEventListener( 'change', function () {
			label.textContent = input.files.length ? input.files[ 0 ].name : 'No file selected';
		} );

		[ 'dragenter', 'dragover' ].forEach( function ( eventName ) {
			dropzone.addEventListener( eventName, function () {
				dropzone.classList.add( 'is-dragging' );
			} );
		} );

		[ 'dragleave', 'drop' ].forEach( function ( eventName ) {
			dropzone.addEventListener( eventName, function () {
				dropzone.classList.remove( 'is-dragging' );
			} );
		} );
	} );

	document.querySelectorAll( '.shipbridge-confirm-form' ).forEach( function ( form ) {
		var confirmation = form.querySelector( '[data-confirm-value]' );
		var submit = form.querySelector( 'button[type="submit"]' );
		var unavailable = submit && submit.disabled;

		if ( ! confirmation || ! submit ) {
			return;
		}

		function updateButton() {
			var requiredChecks = Array.prototype.slice.call( form.querySelectorAll( 'input[type="checkbox"][required]' ) );
			var checksComplete = requiredChecks.every( function ( checkbox ) { return checkbox.checked; } );
			var textComplete = confirmation.value.trim().toUpperCase() === confirmation.dataset.confirmValue;
			var isReady = ! unavailable && checksComplete && textComplete;
			var card = form.closest( '.shipbridge-card' );
			submit.disabled = ! isReady;
			if ( card && form.dataset.readyClass ) {
				card.classList.toggle( form.dataset.readyClass, isReady );
			}
		}

		confirmation.addEventListener( 'input', updateButton );
		form.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( checkbox ) {
			checkbox.addEventListener( 'change', updateButton );
		} );
		form.addEventListener( 'submit', function ( event ) {
			if ( ! window.confirm( form.dataset.confirmMessage || 'Continue?' ) ) {
				event.preventDefault();
			}
		} );
		updateButton();
	} );

	document.querySelectorAll( '.shipbridge-clear-import-form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			if ( ! window.confirm( form.dataset.confirmMessage || 'Remove the current import?' ) ) {
				event.preventDefault();
			}
		} );
	} );
}() );
