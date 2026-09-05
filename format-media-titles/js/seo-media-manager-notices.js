( function ( $ ) {
	'use strict';

	var $productHeader = $( '.wpgo-smm-product-header' ).first();
	var $noticeRegion = $( '.wpgo-smm-admin-notice-region' ).first();

	function relocateHeaderNotices() {
		if ( ! $productHeader.length || ! $noticeRegion.length ) {
			return;
		}

		$productHeader.find( '.fs-notice, .notice' ).each( function () {
			$noticeRegion.append( this );
		} );

		$noticeRegion.prop( 'hidden', 0 === $noticeRegion.children().length );
	}

	relocateHeaderNotices();

	if ( window.MutationObserver && $productHeader.length ) {
		new window.MutationObserver( relocateHeaderNotices ).observe( $productHeader.get( 0 ), {
			childList: true,
			subtree: true
		} );
	}
}( jQuery ) );
