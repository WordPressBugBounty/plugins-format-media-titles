<?php
/**
 * Public filter helpers.
 *
 * @package WPGO_SEO_Media_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the plugin's established filter contract in one place.
 */
class WPGO_SEO_Media_Manager_Current_Hooks {
	/**
	 * Filter the default option values.
	 *
	 * @param array<string, mixed> $defaults Default values.
	 * @return array<string, mixed>
	 */
	public static function seo_media_manager_defaults( $defaults ) {
		return apply_filters( 'seo_media_manager_defaults', $defaults );
	}

	/**
	 * Filter a formatted media title before it is saved.
	 *
	 * @param string $title Formatted title.
	 * @return string
	 */
	public static function seo_media_manager_title( $title ) {
		return apply_filters( 'seo_media_manager_title', $title );
	}

	/**
	 * Filter alternative text before it is saved.
	 *
	 * @param string $alt Alternative text.
	 * @return string
	 */
	public static function seo_media_manager_alt( $alt ) {
		return apply_filters( 'seo_media_manager_alt', $alt );
	}
}
