( function () {
	'use strict';

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
			submit.disabled = unavailable || ! checksComplete || ! textComplete;
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
}() );
