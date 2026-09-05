<?php
/**
 * Transparent introductory pricing for the Freemius upgrade screen.
 *
 * @package WPGO_SEO_Media_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add first-year prices and their matching checkout coupons.
 */
class WPGO_SEO_Media_Manager_Introductory_Pricing {
	/**
	 * Register the pricing-page assets.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enqueue only on this product's Freemius pricing page.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'seo-media-manager-home-pricing' !== $page ) {
			return;
		}

		wp_enqueue_style(
			'wpgo-smm-introductory-pricing',
			plugins_url( 'css/introductory-pricing.css', WPGO_SEO_MEDIA_MANAGER_FILE ),
			array(),
			WPGO_SEO_MEDIA_MANAGER_VERSION
		);
		wp_enqueue_script(
			'wpgo-smm-introductory-pricing',
			plugins_url( 'js/introductory-pricing.js', WPGO_SEO_MEDIA_MANAGER_FILE ),
			array(),
			WPGO_SEO_MEDIA_MANAGER_VERSION,
			true
		);
		wp_localize_script(
			'wpgo-smm-introductory-pricing',
			'wpgoIntroductoryPricingConfig',
			array(
				'tiers'  => array(
					'1'  => array(
						'firstYear' => '$29',
						'renewal'   => '$49',
						'coupon'    => 'SMM1SITE10',
					),
					'3'  => array(
						'firstYear' => '$69',
						'renewal'   => '$89',
						'coupon'    => 'SMM3SITE20',
					),
					'25' => array(
						'firstYear' => '$129',
						'renewal'   => '$149',
						'coupon'    => 'SMM25SITE20',
					),
				),
				'labels' => array(
					'firstYear' => __( 'First year', 'format-media-titles' ),
					/* translators: %s: regular annual renewal price. */
					'renews'    => __( 'Renews at %s/year.', 'format-media-titles' ),
				),
			)
		);
	}
}
