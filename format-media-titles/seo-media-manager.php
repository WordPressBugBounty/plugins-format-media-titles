<?php

/**
 * Plugin Name:       SEO Media Manager
 * Plugin URI:        https://wpgoplugins.com/plugins/seo-media-manager/
 * Description:       Automatically format media titles, alternative text, captions, and descriptions for new uploads and existing media.
 * Version:           1.3.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            David Gwyer
 * Author URI:        https://wpgoplugins.com/
 * Text Domain:       format-media-titles
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 *
 * @package WPGO_SEO_Media_Manager
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'smm_fs' ) ) {
    smm_fs()->set_basename( false, __FILE__ );
} else {
    define( 'WPGO_SEO_MEDIA_MANAGER_VERSION', '1.3.0' );
    define( 'WPGO_SEO_MEDIA_MANAGER_FILE', __FILE__ );
    define( 'WPGO_SEO_MEDIA_MANAGER_NAME', 'SEO Media Manager' );
    define( 'WPGO_SEO_MEDIA_MANAGER_NAME_U', 'seo_media_manager' );
    define( 'WPGO_SEO_MEDIA_MANAGER_NAME_H', 'seo-media-manager' );
    define( 'WPGO_SEO_MEDIA_MANAGER_OPTIONS_DB_NAME', 'seo_media_manager_plugin_options' );
    /**
     * Return the SEO Media Manager Freemius SDK instance.
     *
     * @return Freemius
     */
    function smm_fs() {
        global $smm_fs;
        if ( !isset( $smm_fs ) ) {
            $freemius_start = __DIR__ . '/vendor/freemius/wordpress-sdk/start.php';
            // Support packages built with the pre-Composer 1.0.x layout.
            if ( !file_exists( $freemius_start ) ) {
                $freemius_start = __DIR__ . '/vendor/freemius/start.php';
            }
            require_once $freemius_start;
            $smm_fs = fs_dynamic_init( array(
                'id'               => '36021',
                'slug'             => 'format-media-titles',
                'premium_slug'     => 'seo-media-manager',
                'type'             => 'plugin',
                'public_key'       => 'pk_8a610ef792b7cade9c39844cda782',
                'is_premium'       => false,
                'premium_suffix'   => 'Pro',
                'has_addons'       => false,
                'has_paid_plans'   => true,
                'is_org_compliant' => true,
                'menu'             => array(
                    'slug'       => 'seo-media-manager-home',
                    'first-path' => 'admin.php?page=seo-media-manager-home',
                    'support'    => false,
                ),
                'is_live'          => true,
            ) );
        }
        return $smm_fs;
    }

    /**
     * Use the product mark anywhere Freemius asks for this plugin's icon.
     *
     * @return string Absolute path to the bundled icon.
     */
    function wpgo_smm_freemius_plugin_icon() {
        $plugin_directory = dirname( plugin_basename( WPGO_SEO_MEDIA_MANAGER_FILE ) );
        return trailingslashit( WP_PLUGIN_DIR ) . trailingslashit( $plugin_directory ) . 'assets/seo-media-manager-icon.png';
    }

    smm_fs()->add_filter( 'plugin_icon', 'wpgo_smm_freemius_plugin_icon' );
    smm_fs()->add_filter( 'pricing/show_annual_in_monthly', '__return_false' );
    do_action( 'smm_fs_loaded' );
    require_once __DIR__ . '/classes/introductory-pricing.php';
    WPGO_SEO_Media_Manager_Introductory_Pricing::register();
    /**
     * Determine whether this install may run the paid feature set.
     *
     * The generated Free package cannot return true because Freemius removes the
     * premium directory and rewrites its package flag. A Pro package without an
     * active licence therefore falls back to the same Free runtime and screens.
     *
     * @return bool
     */
    function wpgo_smm_has_premium_features() {
        return smm_fs()->can_use_premium_code__premium_only();
    }

    require_once __DIR__ . '/classes/hooks.php';
    require_once __DIR__ . '/classes/formatter.php';
    require_once __DIR__ . '/classes/plugin-options.php';
    $wpgo_smm_has_premium_features = wpgo_smm_has_premium_features();
    if ( $wpgo_smm_has_premium_features && is_readable( __DIR__ . '/premium/bootstrap.php' ) ) {
        require_once __DIR__ . '/premium/bootstrap.php';
    }
    require_once __DIR__ . '/classes/plugin.php';
    $GLOBALS['wpgo_seo_media_manager_current_plugin'] = new WPGO_SEO_Media_Manager_Current(true, null, $wpgo_smm_has_premium_features);
    unset($wpgo_smm_has_premium_features);
}