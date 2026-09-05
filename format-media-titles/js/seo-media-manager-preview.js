( function ( root, factory ) {
	'use strict';

	if ( 'object' === typeof module && module.exports ) {
		module.exports = factory();
	} else {
		root.wpgoSmmPreview = factory();
	}
}( 'undefined' !== typeof self ? self : this, function () {
	'use strict';

	var characterMap = {
		chk_hyphen: [ '-' ],
		chk_underscore: [ '_' ],
		chk_period: [ '.' ],
		chk_tilde: [ '~' ],
		chk_plus: [ '+' ]
	};
	var premiumTransforms = null;

	function collapseWhitespace( value ) {
		return String( value ).replace( /\s+/gu, ' ' ).trim();
	}

	function escapeRegExp( value ) {
		return value.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	}

	function stripExtension( value ) {
		return String( value ).replace( /\.[^.\s/\\]+$/u, '' );
	}

	function titleCase( value ) {
		return value.replace( /\p{L}[\p{L}\p{M}]*/gu, function ( word ) {
			var characters = Array.from( word );

			return characters.shift().toUpperCase() + characters.join( '' ).toLowerCase();
		} );
	}

	function normalizeUppercaseWords( value ) {
		var unique = {};

		return String( value )
			.split( ',' )
			.slice( 0, 50 )
			.map( function ( word ) {
				return word.trim();
			} )
			.filter( function ( word ) {
				return /^[\p{L}\p{N}][\p{L}\p{N}-]{0,29}$/u.test( word );
			} )
			.map( function ( word ) {
				return word.toUpperCase();
			} )
			.filter( function ( word ) {
				if ( unique[ word ] ) {
					return false;
				}

				unique[ word ] = true;
				return true;
			} );
	}

	function formatTitle( source, options ) {
		var title = premiumTransforms && premiumTransforms.source
			? premiumTransforms.source( source, options, stripExtension )
			: stripExtension( source );
		var characters = [];

		Object.keys( characterMap ).forEach( function ( option ) {
			if ( options[ option ] ) {
				characters = characters.concat( characterMap[ option ] );
			}
		} );
		if ( premiumTransforms && premiumTransforms.characters ) {
			characters = characters.concat( premiumTransforms.characters( options ) );
		}

		if ( characters.length ) {
			characters.forEach( function ( character ) {
				title = title.split( character ).join( ' ' );
			} );
		}

		if ( premiumTransforms && premiumTransforms.afterCharacters ) {
			title = premiumTransforms.afterCharacters( title, options );
		}

		title = collapseWhitespace( title );

		if ( 'all_lower' === options.rdo_case_options ) {
			title = title.toLowerCase();
		} else if ( 'all_upper' === options.rdo_case_options ) {
			title = title.toUpperCase();
		}

		if ( 'cap_all' === options.rdo_cap_options ) {
			title = titleCase( title );
		} else if ( 'cap_first' === options.rdo_cap_options ) {
			title = title.toLowerCase();
			if ( title ) {
				title = Array.from( title ).shift().toUpperCase() + Array.from( title ).slice( 1 ).join( '' );
			}
		}

		normalizeUppercaseWords( options.txt_uppercase_words ).forEach( function ( word ) {
			var pattern = new RegExp( '(?<![\\p{L}\\p{N}])' + escapeRegExp( word ) + '(?![\\p{L}\\p{N}])', 'giu' );

			title = title.replace( pattern, word );
		} );

		return collapseWhitespace( title );
	}

	return {
		formatTitle: formatTitle,
		normalizeUppercaseWords: normalizeUppercaseWords,
		registerPremiumTransforms: function ( transforms ) {
			premiumTransforms = transforms || null;
		},
		stripExtension: stripExtension
	};
} ) );
