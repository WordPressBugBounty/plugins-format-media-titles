<?php
/**
 * Plugin Name: Format Media Titles
 * Plugin URI: https://wpgoplugins.com/plugins/seo-media-manager/
 * Description: Formats titles for new media uploads and can copy the result to common attachment fields.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: David Gwyer
 * Author URI: https://wpgoplugins.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: format-media-titles
 * Domain Path: /languages
 *
 * @package Format_Media_Titles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FMT_VERSION', '1.1.0' );
define( 'FMT_FILE', __FILE__ );
define( 'FMT_OPTION_NAME', 'fmt_options' );

require_once __DIR__ . '/includes/class-fmt-formatter.php';
require_once __DIR__ . '/includes/class-fmt-plugin.php';

/**
 * Get the shared plugin runtime.
 *
 * @return FMT_Plugin
 */
function fmt_plugin() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new FMT_Plugin();
	}

	return $plugin;
}

/**
 * Add defaults without overwriting established settings.
 *
 * The legacy reset-on-reactivation option remains supported for compatibility.
 *
 * @return void
 */
function fmt_add_defaults() {
	$current = get_option( FMT_OPTION_NAME );
	$reset   = is_array( $current ) && ! empty( $current['chk_default_options_db'] );

	if ( ! is_array( $current ) || $reset ) {
		update_option( FMT_OPTION_NAME, FMT_Plugin::default_options() );
	}
}

/**
 * Remove plugin settings only when WordPress uninstalls the plugin.
 *
 * @return void
 */
function fmt_delete_plugin_options() {
	delete_option( FMT_OPTION_NAME );
}

/**
 * Preserve the historical public callback used by SEO Media Manager handover.
 *
 * @param int $id Attachment ID.
 * @return void
 */
function fmt_update_media_title( $id ) {
	fmt_plugin()->update_media_title( $id );
}

register_activation_hook( __FILE__, 'fmt_add_defaults' );
register_uninstall_hook( __FILE__, 'fmt_delete_plugin_options' );
add_action( 'add_attachment', 'fmt_update_media_title' );

fmt_plugin()->register_hooks();
