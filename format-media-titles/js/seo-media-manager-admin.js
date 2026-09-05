( function ( $, config, previewUtils ) {
	'use strict';

	var $resetForm = $( '#wpgo-smm-reset-form' );
	var $resetStatus = $( '#wpgo-smm-reset-status' );
	var $saveStatus = $( '#wpgo-smm-save-status' );
	var $settingsForm = $( '#wpgo-smm-settings-form' );
	var resetStatusTimer = null;
	var saveStatusTimer = null;

	function optionInput( key ) {
		return $settingsForm.find( '[name$="[' + key + ']"]' );
	}

	function optionValue( key ) {
		var $input = optionInput( key );

		if ( 'radio' === $input.attr( 'type' ) ) {
			return $input.filter( ':checked' ).val() || 'none';
		}

		if ( 'checkbox' === $input.attr( 'type' ) ) {
			return $input.is( ':checked' );
		}

		return $input.val() || '';
	}

	function previewOptions() {
		var options = {};

		[
			'chk_ampersand',
			'chk_curly_brackets',
			'chk_hash',
			'chk_hyphen',
			'chk_number',
			'chk_parse_capitalization',
			'chk_parse_numeric',
			'chk_period',
			'chk_plus',
			'chk_round_brackets',
			'chk_square_brackets',
			'chk_tilde',
			'chk_underscore'
		].forEach( function ( key ) {
			options[ key ] = optionValue( key );
		} );

		options.rdo_cap_options = optionValue( 'rdo_cap_options' );
		options.rdo_case_options = optionValue( 'rdo_case_options' );
		options.txt_chars = optionValue( 'txt_chars' );
		options.txt_replace = optionValue( 'txt_replace' );
		options.txt_uppercase_words = optionValue( 'txt_uppercase_words' );

		return options;
	}

	function updatePreview() {
		var source = $( '#wpgo-smm-preview-source' ).val() || '';
		var result = previewUtils.formatTitle( source, previewOptions() );
		var sourceMethod = optionValue( 'rdo_source_options' );

		$( '#wpgo-smm-preview-source-label' ).text( 'filename' === sourceMethod ? config.strings.sourceFilename : config.strings.sourceTitle );
		$( '#wpgo-smm-preview-before' ).text( previewUtils.stripExtension( source ) || '—' );
		$( '#wpgo-smm-preview-result' ).text( result || '—' );
		$( '[data-preview-value]' ).text( result || '—' );
		[ 'chk_alt', 'chk_caption', 'chk_description' ].forEach( function ( key ) {
			$( '[data-preview-row="' + key + '"]' ).prop( 'hidden', ! optionValue( key ) );
		} );
	}

	function dismissSaveStatus() {
		window.clearTimeout( saveStatusTimer );
		$saveStatus.removeClass( 'is-visible' );
		window.setTimeout( function () {
			$saveStatus.prop( 'hidden', true );
		}, 220 );
	}

	function initialiseSaveStatus() {
		var url;

		if ( ! $saveStatus.hasClass( 'is-visible' ) ) {
			return;
		}

		try {
			url = new URL( window.location.href );
			url.searchParams.delete( 'settings-updated' );
			window.history.replaceState( {}, '', url.toString() );
		} catch ( error ) {
			// The confirmation still fades if URL manipulation is unavailable.
		}

		saveStatusTimer = window.setTimeout( dismissSaveStatus, 3500 );
	}

	function dismissResetStatus() {
		window.clearTimeout( resetStatusTimer );
		$resetStatus.removeClass( 'is-visible' );
		window.setTimeout( function () {
			$resetStatus.prop( 'hidden', true );
		}, 220 );
	}

	function initialiseResetStatus() {
		var url;

		if ( ! $resetStatus.hasClass( 'is-visible' ) ) {
			return;
		}

		try {
			url = new URL( window.location.href );
			url.searchParams.delete( 'smm-reset' );
			window.history.replaceState( {}, '', url.toString() );
		} catch ( error ) {
			// The confirmation still fades if URL manipulation is unavailable.
		}

		resetStatusTimer = window.setTimeout( dismissResetStatus, 3500 );
	}

	function availableTab( tab ) {
		return $( '[data-smm-tab="' + tab + '"]' ).length ? tab : 'settings';
	}

	function showTab( tab ) {
		var safeTab = availableTab( tab );

		$( '[data-smm-tab]' ).each( function () {
			var isActive = $( this ).data( 'smm-tab' ) === safeTab;
			$( this ).toggleClass( 'nav-tab-active', isActive ).attr( 'aria-current', isActive ? 'page' : null );
		} );
		$( '[data-smm-panel]' ).each( function () {
			$( this ).prop( 'hidden', $( this ).data( 'smm-panel' ) !== safeTab );
		} );

		try {
			window.localStorage.setItem( 'wpgoSmmTab', safeTab );
		} catch ( error ) {
			// Storage can be disabled without affecting the settings screen.
		}
	}

	$( '[data-smm-tab]' ).on( 'click', function ( event ) {
		event.preventDefault();
		showTab( $( this ).data( 'smm-tab' ) );
	} );

	$resetForm.on( 'submit', function ( event ) {
		if ( ! window.confirm( config.strings.confirmReset ) ) {
			event.preventDefault();
		}
	} );

	$settingsForm.on( 'input change', ':input:not(#wpgo-smm-preview-source)', function () {
		updatePreview();
		if ( $saveStatus.hasClass( 'is-visible' ) ) {
			dismissSaveStatus();
		}
	} );

	$( '#wpgo-smm-preview-source' ).on( 'input', updatePreview );

	( function restoreTab() {
		var tab = window.location.hash.replace( '#', '' );

		if ( ! tab ) {
			try {
				tab = window.localStorage.getItem( 'wpgoSmmTab' );
			} catch ( error ) {
				tab = 'settings';
			}
		}

		showTab( tab || 'settings' );
		updatePreview();
		initialiseSaveStatus();
		initialiseResetStatus();
	}() );
}( jQuery, wpgoSmmAdmin, wpgoSmmPreview ) );
