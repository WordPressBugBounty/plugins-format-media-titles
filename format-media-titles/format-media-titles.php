<?php
/**
 * Compatibility loader for existing WordPress.org installations.
 *
 * Version 1.2.0 and earlier were activated through
 * format-media-titles/format-media-titles.php. The shared Free/Pro codebase
 * uses seo-media-manager.php as its canonical main file, so this loader keeps
 * an in-place WordPress.org update active and migrates the stored basename.
 *
 * This file intentionally has no plugin header.
 *
 * @package WPGO_SEO_Media_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpgo_smm_legacy_basename    = plugin_basename( __FILE__ );
$wpgo_smm_canonical_basename = plugin_basename( __DIR__ . '/seo-media-manager.php' );

if ( $wpgo_smm_legacy_basename !== $wpgo_smm_canonical_basename ) {
	$wpgo_smm_active_plugins = get_option( 'active_plugins', array() );
	$wpgo_smm_legacy_index   = is_array( $wpgo_smm_active_plugins )
		? array_search( $wpgo_smm_legacy_basename, $wpgo_smm_active_plugins, true )
		: false;

	if ( false !== $wpgo_smm_legacy_index ) {
		$wpgo_smm_active_plugins[ $wpgo_smm_legacy_index ] = $wpgo_smm_canonical_basename;
		update_option( 'active_plugins', array_values( array_unique( $wpgo_smm_active_plugins ) ) );
	}

	if ( is_multisite() ) {
		$wpgo_smm_network_plugins = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $wpgo_smm_network_plugins ) && isset( $wpgo_smm_network_plugins[ $wpgo_smm_legacy_basename ] ) ) {
			$wpgo_smm_network_plugins[ $wpgo_smm_canonical_basename ] = $wpgo_smm_network_plugins[ $wpgo_smm_legacy_basename ];
			unset( $wpgo_smm_network_plugins[ $wpgo_smm_legacy_basename ] );
			update_site_option( 'active_sitewide_plugins', $wpgo_smm_network_plugins );
		}
	}
}

unset(
	$wpgo_smm_active_plugins,
	$wpgo_smm_canonical_basename,
	$wpgo_smm_legacy_basename,
	$wpgo_smm_legacy_index,
	$wpgo_smm_network_plugins
);

require_once __DIR__ . '/seo-media-manager.php';
