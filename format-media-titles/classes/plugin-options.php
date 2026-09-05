<?php
/**
 * Settings and administration screen.
 *
 * @package WPGO_SEO_Media_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the established seo_media_manager_plugin_options schema.
 */
class WPGO_SEO_Media_Manager_Current_Options {
	/**
	 * WordPress Home-page hook suffix.
	 *
	 * @var string|false
	 */
	protected $_plugin_home_page = false;

	/**
	 * WordPress settings-page hook suffix.
	 *
	 * @var string|false
	 */
	protected $_plugin_options_page = false;

	/**
	 * WordPress New Features-page hook suffix.
	 *
	 * @var string|false
	 */
	protected $_plugin_features_page = false;

	/**
	 * Constructor arguments.
	 *
	 * @var array<string, mixed>
	 */
	protected $_args;

	/**
	 * Construct the settings controller.
	 *
	 * @param array<string, mixed> $args Settings identifiers and edition state.
	 */
	public function __construct( $args ) {
		$args['has_premium'] = ! empty( $args['has_premium'] );
		$this->_args         = $args;
		self::maybe_migrate_format_media_titles_options();

		add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_menu', array( $this, 'reorder_admin_submenu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_wpgo_smm_reset_options', array( $this, 'handle_reset' ) );
		add_filter( 'seo_media_manager_defaults', array( $this, 'add_defaults' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->_args['plugin_root'] ), array( $this, 'plugin_settings_link' ) );
	}

	/**
	 * Import Format Media Titles settings on the first premium activation.
	 *
	 * The legacy option is intentionally retained so customers can return to the
	 * free plugin without losing their previous configuration.
	 *
	 * @return bool Whether an option was imported.
	 */
	public static function maybe_migrate_format_media_titles_options() {
		$current = get_option( WPGO_SEO_MEDIA_MANAGER_OPTIONS_DB_NAME, null );
		if ( is_array( $current ) ) {
			return false;
		}

		$legacy = get_option( 'fmt_options', false );
		if ( ! is_array( $legacy ) ) {
			return false;
		}

		return (bool) update_option(
			WPGO_SEO_MEDIA_MANAGER_OPTIONS_DB_NAME,
			self::translate_format_media_titles_options( $legacy )
		);
	}

	/**
	 * Translate the WordPress.org companion plugin's option schema.
	 *
	 * @param array<string, mixed> $legacy Format Media Titles options.
	 * @return array<string, string>
	 */
	public static function translate_format_media_titles_options( $legacy ) {
		$defaults = self::get_default_plugin_options();
		unset( $defaults['default_on_checkboxes'] );

		$migrated = $defaults;
		foreach ( array( 'chk_hyphen', 'chk_underscore', 'chk_period', 'chk_tilde', 'chk_plus', 'chk_alt', 'chk_caption', 'chk_description' ) as $checkbox ) {
			$migrated[ $checkbox ] = ! empty( $legacy[ $checkbox ] ) ? '1' : '0';
		}
		$migrated['txt_uppercase_words'] = isset( $legacy['txt_uppercase_words'] )
			? implode( ', ', WPGO_SEO_Media_Manager_Current_Formatter::normalize_uppercase_words( (string) $legacy['txt_uppercase_words'] ) )
			: '';

		// Format Media Titles does not remove numbers or use a filename source.
		$migrated['chk_number']         = '0';
		$migrated['rdo_source_options'] = 'title';
		$migrated['rdo_case_options']   = 'none';
		$migrated['rdo_cap_options']    = 'none';

		$capitalization = isset( $legacy['rdo_cap_options'] ) ? (string) $legacy['rdo_cap_options'] : 'dont_alter';
		switch ( $capitalization ) {
			case 'cap_all':
				$migrated['rdo_cap_options'] = 'cap_all';
				break;
			case 'cap_first':
				$migrated['rdo_case_options'] = 'all_lower';
				$migrated['rdo_cap_options']  = 'cap_first';
				break;
			case 'all_lower':
				$migrated['rdo_case_options'] = 'all_lower';
				break;
			case 'all_upper':
				$migrated['rdo_case_options'] = 'all_upper';
				break;
		}

		return $migrated;
	}

	/**
	 * Get filtered default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_plugin_options() {
		$defaults = array( 'default_on_checkboxes' => array() );

		return WPGO_SEO_Media_Manager_Current_Hooks::seo_media_manager_defaults( $defaults );
	}

	/**
	 * Merge stored settings with defaults while retaining unchecked checkboxes.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_plugin_options() {
		$options  = get_option( WPGO_SEO_MEDIA_MANAGER_OPTIONS_DB_NAME );
		$defaults = self::get_default_plugin_options();
		$off      = isset( $defaults['default_on_checkboxes'] ) && is_array( $defaults['default_on_checkboxes'] )
			? $defaults['default_on_checkboxes']
			: array();

		unset( $defaults['default_on_checkboxes'] );

		if ( is_array( $options ) ) {
			$options = array_merge( $off, $options );
		}

		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Register the settings schema.
	 *
	 * @return void
	 */
	public function register_plugin_settings() {
		register_setting(
			$this->_args['options_group'],
			$this->_args['options_db_name'],
			array(
				'sanitize_callback' => array( $this, 'sanitize_plugin_options' ),
				'type'              => 'array',
			)
		);

		add_settings_section( $this->_args['options_section'], '', '__return_false', $this->_args['menu_slug'] );
		add_settings_field(
			'wpgo_support_plugin_option',
			__( 'Support and documentation', 'format-media-titles' ),
			array( $this, 'render_support_fields' ),
			$this->_args['menu_slug'],
			$this->_args['options_section']
		);
		if ( $this->has_premium_features() ) {
			add_settings_field(
				'wpgo_myaccount_plugin_option',
				__( 'WPGO Plugins account', 'format-media-titles' ),
				array( $this, 'render_myaccount_fields' ),
				$this->_args['menu_slug'],
				$this->_args['options_section']
			);
		}
	}

	/**
	 * Sanitize a complete settings submission.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string, string>
	 */
	public function sanitize_plugin_options( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized  = array();
		$checkboxes = array(
			'chk_hyphen',
			'chk_underscore',
			'chk_period',
			'chk_tilde',
			'chk_plus',
			'chk_alt',
			'chk_caption',
			'chk_description',
		);
		if ( $this->has_premium_features() ) {
			$checkboxes = array_merge(
				$checkboxes,
				array(
					'chk_ampersand',
					'chk_square_brackets',
					'chk_round_brackets',
					'chk_curly_brackets',
					'chk_parse_capitalization',
					'chk_parse_numeric',
					'chk_hash',
					'chk_number',
				)
			);
		}

		foreach ( $checkboxes as $checkbox ) {
			if ( isset( $input[ $checkbox ] ) && '1' === (string) $input[ $checkbox ] ) {
				$sanitized[ $checkbox ] = '1';
			}
		}

		$radio_fields = array(
			'rdo_cap_options'  => array( 'cap_all', 'cap_first', 'none' ),
			'rdo_case_options' => array( 'all_lower', 'all_upper', 'none' ),
		);
		if ( $this->has_premium_features() ) {
			$radio_fields['rdo_source_options'] = array( 'title', 'filename' );
		}

		foreach ( $radio_fields as $field => $allowed_values ) {
			if ( isset( $input[ $field ] ) ) {
				$value = sanitize_key( wp_unslash( $input[ $field ] ) );
				if ( in_array( $value, $allowed_values, true ) ) {
					$sanitized[ $field ] = $value;
				}
			}
		}

		if ( $this->has_premium_features() ) {
			foreach ( array( 'txt_chars', 'txt_replace' ) as $text_field ) {
				if ( isset( $input[ $text_field ] ) ) {
					$sanitized[ $text_field ] = sanitize_text_field( wp_unslash( $input[ $text_field ] ) );
				}
			}
		}

		if ( isset( $input['txt_uppercase_words'] ) ) {
			$uppercase_words                  = sanitize_text_field( wp_unslash( $input['txt_uppercase_words'] ) );
			$sanitized['txt_uppercase_words'] = implode( ', ', WPGO_SEO_Media_Manager_Current_Formatter::normalize_uppercase_words( $uppercase_words ) );
		}

		if ( ! $this->has_premium_features() ) {
			$current = get_option( WPGO_SEO_MEDIA_MANAGER_OPTIONS_DB_NAME, array() );
			if ( is_array( $current ) ) {
				foreach ( $this->premium_option_keys() as $premium_option ) {
					if ( array_key_exists( $premium_option, $current ) ) {
						$sanitized[ $premium_option ] = $current[ $premium_option ];
					}
				}
			}
		}

		self::sync_format_media_titles_options( $sanitized );
		return $sanitized;
	}

	/**
	 * Preserve the legacy Free settings as a rollback-safe compatibility copy.
	 *
	 * @param array<string, mixed> $options Canonical settings.
	 * @return void
	 */
	public static function sync_format_media_titles_options( $options ) {
		$legacy = array();
		foreach ( array( 'chk_hyphen', 'chk_underscore', 'chk_period', 'chk_tilde', 'chk_plus', 'chk_alt', 'chk_caption', 'chk_description' ) as $checkbox ) {
			$legacy[ $checkbox ] = ! empty( $options[ $checkbox ] ) ? '1' : '0';
		}

		$case_mode = isset( $options['rdo_case_options'] ) ? (string) $options['rdo_case_options'] : 'none';
		$cap_mode  = isset( $options['rdo_cap_options'] ) ? (string) $options['rdo_cap_options'] : 'none';
		if ( 'all_upper' === $case_mode ) {
			$legacy['rdo_cap_options'] = 'all_upper';
		} elseif ( 'all_lower' === $case_mode && 'cap_first' === $cap_mode ) {
			$legacy['rdo_cap_options'] = 'cap_first';
		} elseif ( 'all_lower' === $case_mode ) {
			$legacy['rdo_cap_options'] = 'all_lower';
		} elseif ( 'cap_all' === $cap_mode ) {
			$legacy['rdo_cap_options'] = 'cap_all';
		} elseif ( 'cap_first' === $cap_mode ) {
			$legacy['rdo_cap_options'] = 'cap_first';
		} else {
			$legacy['rdo_cap_options'] = 'dont_alter';
		}

		$legacy['txt_uppercase_words'] = isset( $options['txt_uppercase_words'] ) ? (string) $options['txt_uppercase_words'] : '';
		update_option( 'fmt_options', $legacy );
	}

	/**
	 * Return settings that must be retained while the licence is inactive.
	 *
	 * @return string[]
	 */
	private function premium_option_keys() {
		return array(
			'chk_ampersand',
			'chk_square_brackets',
			'chk_round_brackets',
			'chk_curly_brackets',
			'chk_parse_capitalization',
			'chk_parse_numeric',
			'chk_hash',
			'chk_number',
			'rdo_source_options',
			'txt_chars',
			'txt_replace',
		);
	}

	/**
	 * Return the current feature entitlement.
	 *
	 * @return bool
	 */
	private function has_premium_features() {
		return ! empty( $this->_args['has_premium'] );
	}

	/**
	 * Add the original option defaults.
	 *
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	public function add_defaults( $defaults ) {
		$defaults = array_merge(
			$defaults,
			array(
				'chk_ampersand'            => '0',
				'chk_square_brackets'      => '0',
				'chk_round_brackets'       => '0',
				'chk_curly_brackets'       => '0',
				'chk_hyphen'               => '1',
				'chk_underscore'           => '1',
				'chk_period'               => '0',
				'chk_parse_capitalization' => '0',
				'chk_parse_numeric'        => '0',
				'chk_tilde'                => '0',
				'chk_plus'                 => '0',
				'chk_hash'                 => '0',
				'chk_number'               => '1',
				'chk_alt'                  => '1',
				'chk_caption'              => '0',
				'chk_description'          => '0',
				'rdo_cap_options'          => 'cap_all',
				'rdo_case_options'         => 'all_lower',
				'rdo_source_options'       => 'title',
				'txt_chars'                => '',
				'txt_replace'              => '',
				'txt_uppercase_words'      => '',
			)
		);

		$defaults['default_on_checkboxes'] = array(
			'chk_number'     => '0',
			'chk_hyphen'     => '0',
			'chk_underscore' => '0',
			'chk_alt'        => '0',
		);

		return $defaults;
	}

	/**
	 * Get a plugin administration URL.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	private function admin_page_url( $slug ) {
		return add_query_arg( 'page', $slug, admin_url( 'admin.php' ) );
	}

	/**
	 * Render support links.
	 *
	 * @return void
	 */
	public function render_support_fields() {
		$support_url = $this->has_premium_features()
			? 'https://wpgoplugins.com/support/'
			: 'https://wordpress.org/support/plugin/format-media-titles/';
		?>
		<p>
			<a class="button button-secondary" href="https://wpgoplugins.com/documentation/seo-media-manager/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'format-media-titles' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'format-media-titles' ); ?></span></a>
			<a class="button button-secondary" href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get support', 'format-media-titles' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'format-media-titles' ); ?></span></a>
		</p>
		<?php
	}

	/**
	 * Render account link.
	 *
	 * @return void
	 */
	public function render_myaccount_fields() {
		?>
		<p><a class="button button-secondary" href="https://wpgoplugins.com/account/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open my account', 'format-media-titles' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'format-media-titles' ); ?></span></a></p>
		<p class="description"><?php esc_html_e( 'Manage licences, billing, downloads, and profile settings.', 'format-media-titles' ); ?></p>
		<?php
	}

	/**
	 * Add the top-level product pages in their shared WPGO order.
	 *
	 * @return void
	 */
	public function add_admin_pages() {
		$this->_plugin_home_page = add_menu_page(
			__( 'SEO Media Manager Home', 'format-media-titles' ),
			__( 'SEO Media Manager', 'format-media-titles' ),
			'manage_options',
			$this->_args['home_slug'],
			array( $this, 'render_home_page' ),
			'dashicons-format-image',
			81
		);

		add_submenu_page(
			$this->_args['home_slug'],
			__( 'SEO Media Manager Home', 'format-media-titles' ),
			__( 'Home', 'format-media-titles' ),
			'manage_options',
			$this->_args['home_slug'],
			array( $this, 'render_home_page' )
		);

		$this->_plugin_options_page = add_submenu_page(
			$this->_args['home_slug'],
			__( 'SEO Media Manager settings', 'format-media-titles' ),
			__( 'Settings', 'format-media-titles' ),
			'manage_options',
			$this->_args['menu_slug'],
			array( $this, 'render_plugin_form' )
		);

		$this->_plugin_features_page = add_submenu_page(
			$this->_args['home_slug'],
			__( 'SEO Media Manager New Features', 'format-media-titles' ),
			__( 'New Features', 'format-media-titles' ),
			'manage_options',
			$this->_args['features_slug'],
			array( $this, 'render_new_features_page' )
		);

		$legacy_hook = add_options_page(
			__( 'SEO Media Manager settings', 'format-media-titles' ),
			__( 'SEO Media Manager', 'format-media-titles' ),
			'manage_options',
			$this->_args['menu_slug'],
			array( $this, 'redirect_legacy_settings_url' )
		);
		if ( $legacy_hook ) {
			add_action( 'load-' . $legacy_hook, array( $this, 'redirect_legacy_settings_url' ) );
			remove_submenu_page( 'options-general.php', $this->_args['menu_slug'] );
		}
	}

	/**
	 * Keep the historical Settings URL working after moving to a top-level menu.
	 *
	 * @return void
	 */
	public function redirect_legacy_settings_url() {
		wp_safe_redirect( $this->admin_page_url( $this->_args['menu_slug'] ) );
		exit;
	}

	/**
	 * Keep Home, Settings and New Features ahead of Freemius account routes.
	 *
	 * @return void
	 */
	public function reorder_admin_submenu() {
		global $submenu;

		$parent = $this->_args['home_slug'];
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		$items   = $submenu[ $parent ];
		$ordered = array();
		foreach ( array( $this->_args['home_slug'], $this->_args['menu_slug'], $this->_args['features_slug'] ) as $slug ) {
			foreach ( $items as $index => $item ) {
				if ( isset( $item[2] ) && $slug === $item[2] ) {
					$ordered[] = $item;
					unset( $items[ $index ] );
					break;
				}
			}
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering this plugin's own submenu.
		$submenu[ $parent ] = array_values( array_merge( $ordered, $items ) );
	}

	/**
	 * Enqueue assets only on this plugin's administration screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		$plugin_pages = array_filter(
			array(
				$this->_plugin_home_page,
				$this->_plugin_options_page,
				$this->_plugin_features_page,
			)
		);
		if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'format-media-titles-admin',
			plugins_url( 'css/seo-media-manager-admin.css', $this->_args['plugin_root'] ),
			array(),
			WPGO_SEO_MEDIA_MANAGER_VERSION
		);
		wp_enqueue_script(
			'format-media-titles-notices',
			plugins_url( 'js/seo-media-manager-notices.js', $this->_args['plugin_root'] ),
			array( 'jquery' ),
			WPGO_SEO_MEDIA_MANAGER_VERSION,
			true
		);

		if ( $this->_plugin_options_page !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'format-media-titles-preview',
			plugins_url( 'js/seo-media-manager-preview.js', $this->_args['plugin_root'] ),
			array(),
			WPGO_SEO_MEDIA_MANAGER_VERSION,
			true
		);
		$admin_dependencies = array( 'jquery', 'format-media-titles-preview' );
		if ( $this->has_premium_features() ) {
			wp_enqueue_script(
				'format-media-titles-preview-pro',
				plugins_url( 'premium/js/seo-media-manager-preview-pro.js', $this->_args['plugin_root'] ),
				array( 'format-media-titles-preview' ),
				WPGO_SEO_MEDIA_MANAGER_VERSION,
				true
			);
			$admin_dependencies[] = 'format-media-titles-preview-pro';
		}
		wp_enqueue_script(
			'format-media-titles-admin',
			plugins_url( 'js/seo-media-manager-admin.js', $this->_args['plugin_root'] ),
			$admin_dependencies,
			WPGO_SEO_MEDIA_MANAGER_VERSION,
			true
		);
		wp_localize_script(
			'format-media-titles-admin',
			'wpgoSmmAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpgo-smm-batch' ),
				'strings' => array(
					'allMedia'          => __( 'All media items', 'format-media-titles' ),
					'backgroundRunning' => __( 'Processing continues in the background. You can leave this page.', 'format-media-titles' ),
					'cancelled'         => __( 'Batch processing cancelled.', 'format-media-titles' ),
					'confirmBatch'      => __( 'This will overwrite metadata for the selected media. Continue?', 'format-media-titles' ),
					'confirmCancel'     => __( 'Stop this background job after its current batch?', 'format-media-titles' ),
					'confirmReset'      => __( 'Reset all SEO Media Manager settings to their defaults?', 'format-media-titles' ),
					'failed'            => __( 'Background processing stopped.', 'format-media-titles' ),
					'failedItems'       => __( 'failed', 'format-media-titles' ),
					'finished'          => __( 'Batch processing complete.', 'format-media-titles' ),
					'gettingReady'      => __( 'Preparing media items…', 'format-media-titles' ),
					'mediaItems'        => __( 'media items', 'format-media-titles' ),
					'oneMediaItem'      => __( 'media item', 'format-media-titles' ),
					'processed'         => __( 'Processed', 'format-media-titles' ),
					'recentResults'     => __( 'Only the 100 most recent item results are shown.', 'format-media-titles' ),
					'selectMedia'       => __( 'Select media items', 'format-media-titles' ),
					'sourceFilename'    => __( 'Original filename', 'format-media-titles' ),
					'sourceTitle'       => __( 'WordPress title', 'format-media-titles' ),
					'succeeded'         => __( 'updated', 'format-media-titles' ),
				),
			)
		);

		if ( $this->has_premium_features() ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'format-media-titles-batch',
				plugins_url( 'premium/js/seo-media-manager-batch.js', $this->_args['plugin_root'] ),
				array(),
				WPGO_SEO_MEDIA_MANAGER_VERSION,
				true
			);
			wp_enqueue_script(
				'format-media-titles-batch-admin',
				plugins_url( 'premium/js/seo-media-manager-batch-admin.js', $this->_args['plugin_root'] ),
				array( 'jquery', 'format-media-titles-admin', 'format-media-titles-batch' ),
				WPGO_SEO_MEDIA_MANAGER_VERSION,
				true
			);
		}
	}

	/**
	 * Reset options through a dedicated nonce-protected admin action.
	 *
	 * @return void
	 */
	public function handle_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to reset these settings.', 'format-media-titles' ) );
		}

		check_admin_referer( 'wpgo_smm_reset_options' );
		$defaults = self::get_default_plugin_options();
		unset( $defaults['default_on_checkboxes'] );
		update_option( $this->_args['options_db_name'], $defaults );
		self::sync_format_media_titles_options( $defaults );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => $this->_args['menu_slug'],
					'smm-reset' => '1',
				),
				admin_url( 'admin.php' )
			) . '#settings'
		);
		exit;
	}

	/**
	 * Render a compatible checkbox.
	 *
	 * @param array<string, mixed> $options Options.
	 * @param string               $key     Option key.
	 * @param string               $label   Label.
	 * @param string               $example Optional code example.
	 * @return void
	 */
	private function render_checkbox( $options, $key, $label, $example = '' ) {
		?>
		<label class="wpgo-smm-choice">
			<input name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[<?php echo esc_attr( $key ); ?>]" type="checkbox" value="1" <?php checked( '1', isset( $options[ $key ] ) ? $options[ $key ] : '0' ); ?>>
			<?php echo esc_html( $label ); ?>
			<?php if ( '' !== $example ) : ?>
				<code><?php echo esc_html( $example ); ?></code>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Render the shared product header.
	 *
	 * @param string $title Visible page title.
	 * @return void
	 */
	private function render_page_header( $title ) {
		$edition = $this->has_premium_features() ? __( 'Pro', 'format-media-titles' ) : __( 'Free', 'format-media-titles' );
		?>
		<header class="wpgo-smm-product-header">
			<img src="<?php echo esc_url( plugins_url( 'assets/seo-media-manager-icon.png', $this->_args['plugin_root'] ) ); ?>" width="64" height="64" alt="">
			<div>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p class="wpgo-smm-product-meta">
					<span class="wpgo-smm-edition-badge<?php echo $this->has_premium_features() ? '' : ' wpgo-smm-edition-badge--free'; ?>"><?php echo esc_html( $edition ); ?></span>
					<a href="<?php echo esc_url( $this->admin_page_url( $this->_args['features_slug'] ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: installed plugin version. */ __( 'Version %s', 'format-media-titles' ), WPGO_SEO_MEDIA_MANAGER_VERSION ) ); ?></a>
					<a href="https://wpgoplugins.com/support/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Send feedback', 'format-media-titles' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'format-media-titles' ); ?></span></a>
				</p>
			</div>
		</header>
		<div class="wpgo-smm-admin-notice-region" role="region" aria-label="<?php esc_attr_e( 'Plugin notices', 'format-media-titles' ); ?>" hidden></div>
		<?php
	}

	/**
	 * Render the first-run product Home page.
	 *
	 * @return void
	 */
	public function render_home_page() {
		$settings_url      = $this->admin_page_url( $this->_args['menu_slug'] );
		$has_premium       = $this->has_premium_features();
		$companion_plugins = array(
			array(
				'icon'        => 'networking',
				'title'       => __( 'Simple Sitemap', 'format-media-titles' ),
				'description' => __( 'Create visitor-friendly HTML sitemaps with blocks and shortcodes.', 'format-media-titles' ),
				'url'         => 'https://wpgoplugins.com/plugins/simple-sitemap/',
				'action'      => __( 'Explore Simple Sitemap', 'format-media-titles' ),
			),
			array(
				'icon'        => 'chart-bar',
				'title'       => __( 'ChartQuill', 'format-media-titles' ),
				'description' => __( 'Create responsive, accessible charts from WordPress or imported data.', 'format-media-titles' ),
				'url'         => 'https://wpgoplugins.com/plugins/chartquill/',
				'action'      => __( 'Explore ChartQuill', 'format-media-titles' ),
			),
		);
		?>
		<div class="wrap wpgo-smm-wrap">
			<?php $this->render_page_header( __( 'SEO Media Manager Home', 'format-media-titles' ) ); ?>

			<div class="wpgo-smm-home-grid">
				<section class="wpgo-smm-card wpgo-smm-start-card" aria-labelledby="wpgo-smm-start-title">
					<h2 id="wpgo-smm-start-title"><?php esc_html_e( 'Start here', 'format-media-titles' ); ?></h2>
					<p><?php esc_html_e( 'Choose how attachment titles are cleaned up and which media fields receive the result. Saving rules does not publish content or change existing Media Library items.', 'format-media-titles' ); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url( $settings_url ); ?>#settings"><?php esc_html_e( 'Configure formatting rules', 'format-media-titles' ); ?></a></p>
				</section>

				<nav class="wpgo-smm-card" aria-labelledby="wpgo-smm-links-title">
					<h2 id="wpgo-smm-links-title"><?php esc_html_e( 'Quick links', 'format-media-titles' ); ?></h2>
					<ul class="wpgo-smm-link-list">
						<?php if ( $has_premium ) : ?>
							<li><a href="<?php echo esc_url( $settings_url ); ?>#batch"><?php esc_html_e( 'Process existing media', 'format-media-titles' ); ?></a></li>
						<?php else : ?>
							<li><a href="<?php echo esc_url( smm_fs()->get_upgrade_url() ); ?>"><?php esc_html_e( 'Compare Free and Pro', 'format-media-titles' ); ?></a></li>
						<?php endif; ?>
						<li><a href="https://wpgoplugins.com/documentation/seo-media-manager/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'format-media-titles' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'format-media-titles' ); ?></span></a></li>
						<li><a href="<?php echo esc_url( $this->admin_page_url( $this->_args['features_slug'] ) ); ?>"><?php esc_html_e( 'Changelog', 'format-media-titles' ); ?></a></li>
						<li><a href="https://wpgoplugins.com/support/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support', 'format-media-titles' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'format-media-titles' ); ?></span></a></li>
					</ul>
				</nav>
			</div>

			<section class="wpgo-smm-home-section" aria-labelledby="wpgo-smm-quick-start-title">
				<h2 id="wpgo-smm-quick-start-title"><?php esc_html_e( 'Quick start', 'format-media-titles' ); ?></h2>
				<div class="wpgo-smm-workflow-grid">
					<article class="wpgo-smm-card">
						<span class="wpgo-smm-step" aria-hidden="true">1</span>
						<img src="<?php echo esc_url( plugins_url( 'assets/admin-settings-preview.png', $this->_args['plugin_root'] ) ); ?>" alt="<?php esc_attr_e( 'Formatting rules screen with a live title preview', 'format-media-titles' ); ?>">
						<h3><?php esc_html_e( 'Set the rules', 'format-media-titles' ); ?></h3>
						<p><?php esc_html_e( 'Choose the source, cleanup, capitalisation and destination fields for future uploads.', 'format-media-titles' ); ?></p>
					</article>
					<article class="wpgo-smm-card">
						<span class="wpgo-smm-step" aria-hidden="true">2</span>
						<?php if ( $has_premium ) : ?>
							<img src="<?php echo esc_url( plugins_url( 'assets/admin-batch-preview.png', $this->_args['plugin_root'] ) ); ?>" alt="<?php esc_attr_e( 'Background-processing screen with saved progress and job controls', 'format-media-titles' ); ?>">
							<h3><?php esc_html_e( 'Update the library safely', 'format-media-titles' ); ?></h3>
							<p><?php esc_html_e( 'Start a durable background job for selected items or the full Media Library, then leave the page if you wish.', 'format-media-titles' ); ?></p>
						<?php else : ?>
							<div class="wpgo-smm-pro-preview"><span class="dashicons dashicons-database" aria-hidden="true"></span><strong><?php esc_html_e( 'Pro', 'format-media-titles' ); ?></strong></div>
							<h3><?php esc_html_e( 'Clean up an existing library', 'format-media-titles' ); ?></h3>
							<p><?php esc_html_e( 'Upgrade to process selected items or the full Media Library in durable background jobs.', 'format-media-titles' ); ?></p>
						<?php endif; ?>
					</article>
					<article class="wpgo-smm-card">
						<span class="wpgo-smm-step" aria-hidden="true">3</span>
						<div class="wpgo-smm-result-preview" aria-hidden="true"><span>coastal-walk-uk.jpg</span><strong>Coastal Walk UK</strong></div>
						<h3><?php esc_html_e( 'Keep uploads consistent', 'format-media-titles' ); ?></h3>
						<p><?php esc_html_e( 'New attachments use the saved rules automatically. Original files are never renamed or modified.', 'format-media-titles' ); ?></p>
					</article>
				</div>
			</section>

			<section class="wpgo-smm-home-section" aria-labelledby="wpgo-smm-features-title">
				<h2 id="wpgo-smm-features-title"><?php echo esc_html( $has_premium ? __( 'Your Pro toolkit', 'format-media-titles' ) : __( 'Included in Free', 'format-media-titles' ) ); ?></h2>
				<div class="wpgo-smm-feature-grid">
					<?php if ( $has_premium ) : ?>
						<article class="wpgo-smm-card"><h3><?php esc_html_e( 'More precise formatting rules', 'format-media-titles' ); ?></h3><p><?php esc_html_e( 'Use original filenames or a fixed source, remove more symbols and phrases, and split joined words.', 'format-media-titles' ); ?></p><a href="<?php echo esc_url( $settings_url ); ?>#settings"><?php esc_html_e( 'Review advanced rules', 'format-media-titles' ); ?></a></article>
						<article class="wpgo-smm-card"><h3><?php esc_html_e( 'Durable background processing', 'format-media-titles' ); ?></h3><p><?php esc_html_e( 'Process large libraries through saved, bounded Action Scheduler jobs with file-type filters, recovery and cancellation.', 'format-media-titles' ); ?></p><a href="<?php echo esc_url( $settings_url ); ?>#batch"><?php esc_html_e( 'Open batch processing', 'format-media-titles' ); ?></a></article>
						<article class="wpgo-smm-card"><h3><?php esc_html_e( 'Targeted file-type filters', 'format-media-titles' ); ?></h3><p><?php esc_html_e( 'Limit a library job to images, SVG files, PDFs, documents, audio or video.', 'format-media-titles' ); ?></p><a href="<?php echo esc_url( $settings_url ); ?>#batch"><?php esc_html_e( 'Choose what to process', 'format-media-titles' ); ?></a></article>
					<?php else : ?>
						<article class="wpgo-smm-card"><h3><?php esc_html_e( 'Automatic upload formatting', 'format-media-titles' ); ?></h3><p><?php esc_html_e( 'Apply one predictable set of title and metadata rules whenever media is uploaded.', 'format-media-titles' ); ?></p><a href="<?php echo esc_url( $settings_url ); ?>#settings"><?php esc_html_e( 'Review rules', 'format-media-titles' ); ?></a></article>
						<article class="wpgo-smm-card"><h3><?php esc_html_e( 'Live rule preview', 'format-media-titles' ); ?></h3><p><?php esc_html_e( 'Try an example filename and see the title and copied metadata before saving.', 'format-media-titles' ); ?></p><a href="<?php echo esc_url( $settings_url ); ?>#settings"><?php esc_html_e( 'Try the preview', 'format-media-titles' ); ?></a></article>
					<?php endif; ?>
					<article class="wpgo-smm-card"><h3><?php esc_html_e( 'Protected uppercase words', 'format-media-titles' ); ?></h3><p><?php esc_html_e( 'Keep abbreviations such as SEO, PDF and UK uppercase after other case rules run.', 'format-media-titles' ); ?></p><a href="<?php echo esc_url( $settings_url ); ?>#settings"><?php esc_html_e( 'Set protected words', 'format-media-titles' ); ?></a></article>
				</div>
			</section>

			<?php if ( ! $has_premium ) : ?>
				<section class="wpgo-smm-pro-journey" aria-labelledby="wpgo-smm-upgrade-title">
					<div class="wpgo-smm-pro-journey__copy">
						<p class="wpgo-smm-eyebrow"><?php esc_html_e( 'When the library needs more', 'format-media-titles' ); ?></p>
						<h2 id="wpgo-smm-upgrade-title"><?php esc_html_e( 'You’ve fixed future uploads. Now fix the backlog!', 'format-media-titles' ); ?></h2>
						<p><?php esc_html_e( 'SEO Media Manager Pro turns a repetitive clean-up project into a saved background job. Choose exactly what to process, leave the page, and return to the result.', 'format-media-titles' ); ?></p>
						<ul class="wpgo-smm-pro-benefits">
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Process a selection or the complete Media Library', 'format-media-titles' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Filter jobs by image, SVG, PDF, document, audio or video', 'format-media-titles' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Use more source, cleanup and parsing controls', 'format-media-titles' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Recover or cancel a saved job without starting again', 'format-media-titles' ); ?></li>
						</ul>
						<div class="wpgo-smm-pro-actions">
							<a class="button button-primary" href="<?php echo esc_url( smm_fs()->get_upgrade_url() ); ?>"><?php esc_html_e( 'Compare Pro plans', 'format-media-titles' ); ?></a>
							<span><?php esc_html_e( '$29 for the first year; then $49/year for one site.', 'format-media-titles' ); ?></span>
						</div>
					</div>
					<figure class="wpgo-smm-pro-journey__preview">
						<span class="wpgo-smm-pro-label"><?php esc_html_e( 'Pro preview', 'format-media-titles' ); ?></span>
						<img src="<?php echo esc_url( plugins_url( 'assets/admin-batch-preview.png', $this->_args['plugin_root'] ) ); ?>" alt="<?php esc_attr_e( 'SEO Media Manager Pro background-processing screen', 'format-media-titles' ); ?>">
						<figcaption><?php esc_html_e( 'Durable batches process 20 attachments at a time and save progress between workers.', 'format-media-titles' ); ?></figcaption>
					</figure>
				</section>
			<?php endif; ?>

			<section class="wpgo-smm-support-callout" aria-labelledby="wpgo-smm-support-title">
				<h2 id="wpgo-smm-support-title"><?php esc_html_e( 'Need a hand?', 'format-media-titles' ); ?></h2>
				<p><?php esc_html_e( 'Send us your support summary and a description of what you expected to happen. The summary deliberately excludes site URLs, content, settings, licence keys and billing data.', 'format-media-titles' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( $settings_url ); ?>#support"><?php esc_html_e( 'Open support tools', 'format-media-titles' ); ?></a></p>
			</section>

			<section class="wpgo-smm-companions" aria-labelledby="wpgo-smm-companions-title">
				<div class="wpgo-smm-companions-heading">
					<p class="wpgo-smm-eyebrow"><?php esc_html_e( 'More from WPGO Plugins', 'format-media-titles' ); ?></p>
					<h2 id="wpgo-smm-companions-title"><?php esc_html_e( 'Keep more of your WordPress content organised', 'format-media-titles' ); ?></h2>
					<p><?php esc_html_e( 'These companion plugins help visitors navigate content and understand data alongside a tidier Media Library.', 'format-media-titles' ); ?></p>
				</div>
				<div class="wpgo-smm-companion-grid">
					<?php foreach ( $companion_plugins as $companion_plugin ) : ?>
						<article class="wpgo-smm-companion-card">
							<span class="wpgo-smm-companion-icon dashicons dashicons-<?php echo esc_attr( $companion_plugin['icon'] ); ?>" aria-hidden="true"></span>
							<div class="wpgo-smm-companion-copy">
								<h3><?php echo esc_html( $companion_plugin['title'] ); ?></h3>
								<p><?php echo esc_html( $companion_plugin['description'] ); ?></p>
							</div>
							<a class="button" href="<?php echo esc_url( $companion_plugin['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $companion_plugin['action'] ); ?> <span aria-hidden="true">↗</span><span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'format-media-titles' ); ?></span></a>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Build a privacy-safe support summary.
	 *
	 * @return string
	 */
	public function get_support_summary() {
		$theme                    = wp_get_theme();
		$action_scheduler_version = $this->has_premium_features() ? __( 'Unavailable', 'format-media-titles' ) : __( 'Not loaded in Free', 'format-media-titles' );
		if ( class_exists( 'ActionScheduler_Versions' ) ) {
			$action_scheduler_version = ActionScheduler_Versions::instance()->latest_version();
		}

		$lines = array(
			sprintf( /* translators: %s: plugin version. */ __( 'SEO Media Manager: %s', 'format-media-titles' ), WPGO_SEO_MEDIA_MANAGER_VERSION ),
			sprintf( /* translators: %s: Free or Pro edition. */ __( 'Edition: %s', 'format-media-titles' ), $this->has_premium_features() ? __( 'Pro', 'format-media-titles' ) : __( 'Free', 'format-media-titles' ) ),
			sprintf( /* translators: %s: WordPress version. */ __( 'WordPress: %s', 'format-media-titles' ), get_bloginfo( 'version' ) ),
			sprintf( /* translators: %s: PHP version. */ __( 'PHP: %s', 'format-media-titles' ), PHP_VERSION ),
			sprintf( /* translators: %s: site locale. */ __( 'Locale: %s', 'format-media-titles' ), get_locale() ),
			sprintf( /* translators: %s: WordPress environment type. */ __( 'Environment: %s', 'format-media-titles' ), wp_get_environment_type() ),
			sprintf( /* translators: %s: yes or no. */ __( 'Multisite: %s', 'format-media-titles' ), is_multisite() ? __( 'Yes', 'format-media-titles' ) : __( 'No', 'format-media-titles' ) ),
			sprintf( /* translators: 1: theme name, 2: theme version. */ __( 'Active theme: %1$s %2$s', 'format-media-titles' ), $theme->get( 'Name' ), $theme->get( 'Version' ) ),
			sprintf( /* translators: %s: Action Scheduler version. */ __( 'Action Scheduler: %s', 'format-media-titles' ), $action_scheduler_version ),
		);

		return implode( PHP_EOL, $lines );
	}

	/**
	 * Render the current customer-facing release highlights.
	 *
	 * @return void
	 */
	public function render_new_features_page() {
		$has_premium  = $this->has_premium_features();
		$settings_url = $this->admin_page_url( $this->_args['menu_slug'] );
		?>
		<div class="wrap wpgo-smm-wrap">
			<?php $this->render_page_header( __( 'SEO Media Manager New Features', 'format-media-titles' ) ); ?>
			<section class="wpgo-smm-card wpgo-smm-release-card">
				<p class="wpgo-smm-release-label"><?php esc_html_e( 'Version 1.3.0', 'format-media-titles' ); ?></p>
				<h2><?php esc_html_e( 'Preview your rules. Take control of the backlog.', 'format-media-titles' ); ?></h2>
				<p><?php esc_html_e( 'Explore what is new across Free and Pro, from a clearer formatting workspace to background jobs for your existing Media Library.', 'format-media-titles' ); ?></p>
			</section>
			<section class="wpgo-smm-card wpgo-smm-release-card">
				<span class="wpgo-smm-feature-edition wpgo-smm-feature-edition--pro"><?php esc_html_e( 'Pro version', 'format-media-titles' ); ?></span>
					<h2><?php esc_html_e( 'Large Media Libraries can now run in the background', 'format-media-titles' ); ?></h2>
					<p><?php esc_html_e( 'Apply your formatting rules to existing media without fixing each item by hand. Background jobs save progress as they work, so you can close the page and return later.', 'format-media-titles' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Process selected attachments or the complete library.', 'format-media-titles' ); ?></li>
						<li><?php esc_html_e( 'Cancel safely after the current worker finishes.', 'format-media-titles' ); ?></li>
						<li><?php esc_html_e( 'Filter a job to images, SVG files, PDFs, documents, audio or video.', 'format-media-titles' ); ?></li>
						<li><?php esc_html_e( 'Recover interrupted work from saved progress.', 'format-media-titles' ); ?></li>
						<li><?php esc_html_e( 'Return later to see saved progress and recent results.', 'format-media-titles' ); ?></li>
					</ul>
					<p><a class="button button-primary" href="<?php echo esc_url( $has_premium ? $settings_url . '#batch' : smm_fs()->get_upgrade_url() ); ?>"><?php echo esc_html( $has_premium ? __( 'Open background processing', 'format-media-titles' ) : __( 'Explore Pro plans', 'format-media-titles' ) ); ?></a></p>
			</section>
			<section class="wpgo-smm-card wpgo-smm-release-card">
				<span class="wpgo-smm-feature-edition"><?php esc_html_e( 'Free and Pro', 'format-media-titles' ); ?></span>
					<h2><?php esc_html_e( 'Preview every rule before you save', 'format-media-titles' ); ?></h2>
					<p><?php esc_html_e( 'Both editions now share a clearer four-step settings screen with an editable live preview. Try your filename and see how each rule changes the result before saving.', 'format-media-titles' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Edit the example filename to check a complete transformation.', 'format-media-titles' ); ?></li>
						<li><?php esc_html_e( 'Preview alternative text, caption and description output.', 'format-media-titles' ); ?></li>
						<li><?php esc_html_e( 'Keep abbreviations such as SEO, PDF and UK uppercase.', 'format-media-titles' ); ?></li>
						<li><?php esc_html_e( 'Retain the original uploaded file unchanged.', 'format-media-titles' ); ?></li>
					</ul>
					<p><a class="button button-primary" href="<?php echo esc_url( $this->admin_page_url( $this->_args['menu_slug'] ) ); ?>#settings"><?php esc_html_e( 'Open formatting rules', 'format-media-titles' ); ?></a> <a class="button" href="https://wpgoplugins.com/documentation/seo-media-manager/changelog/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View full changelog', 'format-media-titles' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'format-media-titles' ); ?></span></a></p>
			</section>
			<section class="wpgo-smm-card wpgo-smm-release-card">
				<span class="wpgo-smm-feature-edition"><?php esc_html_e( 'Free and Pro', 'format-media-titles' ); ?></span>
				<h2><?php esc_html_e( 'A clearer home for your media tools', 'format-media-titles' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Find formatting rules, release highlights and support from the new Home page.', 'format-media-titles' ); ?></li>
					<li><?php esc_html_e( 'See a brief confirmation when settings are saved, with a reset button inside Formatting rules.', 'format-media-titles' ); ?></li>
					<li><?php esc_html_e( 'Keep your existing settings when upgrading from Format Media Titles.', 'format-media-titles' ); ?></li>
					<li><?php esc_html_e( 'Switch an installed Pro package between Free and Pro by deactivating or reactivating its licence. Your paid settings are retained.', 'format-media-titles' ); ?></li>
					<li><?php esc_html_e( 'Share a support summary without including media content or licence keys.', 'format-media-titles' ); ?></li>
				</ul>
			</section>
		</div>
		<?php
	}

	/**
	 * Render the complete settings screen.
	 *
	 * @return void
	 */
	public function render_plugin_form() {
		$options        = self::get_plugin_options();
		$has_premium    = $this->has_premium_features();
		$settings_saved = isset( $_GET['settings-updated'] ) && 'true' === sanitize_key( wp_unslash( $_GET['settings-updated'] ) );
		$settings_reset = isset( $_GET['smm-reset'] ) && '1' === sanitize_key( wp_unslash( $_GET['smm-reset'] ) );
		?>
		<div class="wrap wpgo-smm-wrap">
			<?php $this->render_page_header( __( 'SEO Media Manager', 'format-media-titles' ) ); ?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'SEO Media Manager sections', 'format-media-titles' ); ?>">
				<a href="#settings" class="nav-tab nav-tab-active" data-smm-tab="settings"><?php esc_html_e( 'Formatting rules', 'format-media-titles' ); ?></a>
				<?php if ( $has_premium ) : ?>
					<a href="#batch" class="nav-tab" data-smm-tab="batch"><?php esc_html_e( 'Batch processing', 'format-media-titles' ); ?></a>
				<?php endif; ?>
				<a href="#support" class="nav-tab" data-smm-tab="support"><?php esc_html_e( 'Support', 'format-media-titles' ); ?></a>
			</nav>

			<form id="wpgo-smm-settings-form" method="post" action="options.php">
				<?php settings_fields( $this->_args['options_group'] ); ?>

				<section class="wpgo-smm-panel wpgo-smm-settings-panel" data-smm-panel="settings">
					<?php if ( $settings_reset ) : ?>
						<div id="wpgo-smm-reset-status" class="wpgo-smm-inline-status is-visible" role="status" aria-live="polite">
							<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'Settings reset to defaults.', 'format-media-titles' ); ?>
						</div>
					<?php endif; ?>
					<div class="wpgo-smm-settings-workspace">
						<div class="wpgo-smm-rules-column">
							<section class="wpgo-smm-rule-section" aria-labelledby="wpgo-smm-source-title">
								<div class="wpgo-smm-rule-intro">
									<span class="wpgo-smm-rule-icon dashicons dashicons-format-image" aria-hidden="true"></span>
									<div><h2 id="wpgo-smm-source-title"><?php esc_html_e( '1. Source', 'format-media-titles' ); ?></h2><p><?php echo esc_html( $has_premium ? __( 'Choose where titles come from and set any fixed title.', 'format-media-titles' ) : __( 'Use the title WordPress assigns to each new upload.', 'format-media-titles' ) ); ?></p></div>
								</div>
								<div class="wpgo-smm-rule-controls wpgo-smm-source-grid">
									<?php if ( $has_premium ) : ?>
										<fieldset class="wpgo-smm-fieldset">
											<legend><?php esc_html_e( 'New-upload source', 'format-media-titles' ); ?></legend>
											<div class="wpgo-smm-choice-grid wpgo-smm-choice-grid--two">
												<label class="wpgo-smm-choice"><input name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[rdo_source_options]" type="radio" value="title" <?php checked( 'title', $options['rdo_source_options'] ); ?>> <span><strong><?php esc_html_e( 'WordPress title', 'format-media-titles' ); ?></strong><small><?php esc_html_e( 'Use the title WordPress assigns.', 'format-media-titles' ); ?></small></span></label>
												<label class="wpgo-smm-choice"><input name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[rdo_source_options]" type="radio" value="filename" <?php checked( 'filename', $options['rdo_source_options'] ); ?>> <span><strong><?php esc_html_e( 'Original filename', 'format-media-titles' ); ?></strong><small><?php esc_html_e( 'Use the filename without its extension.', 'format-media-titles' ); ?></small></span></label>
											</div>
										</fieldset>
										<div class="wpgo-smm-field-group">
											<label for="wpgo-smm-fixed-title"><strong><?php esc_html_e( 'Fixed title (optional)', 'format-media-titles' ); ?></strong></label>
											<input id="wpgo-smm-fixed-title" type="text" class="regular-text" name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[txt_replace]" value="<?php echo esc_attr( $options['txt_replace'] ); ?>">
											<p class="description"><?php esc_html_e( 'Optional. This text replaces the source before the remaining rules run.', 'format-media-titles' ); ?></p>
										</div>
									<?php else : ?>
										<div class="wpgo-smm-free-source"><strong><?php esc_html_e( 'WordPress title', 'format-media-titles' ); ?></strong><span><?php esc_html_e( 'The title generated for a new upload is the starting point for the rules below.', 'format-media-titles' ); ?></span></div>
									<?php endif; ?>
								</div>
							</section>

							<section class="wpgo-smm-rule-section" aria-labelledby="wpgo-smm-cleanup-title">
								<div class="wpgo-smm-rule-intro">
									<span class="wpgo-smm-rule-icon dashicons dashicons-editor-removeformatting" aria-hidden="true"></span>
									<div><h2 id="wpgo-smm-cleanup-title"><?php esc_html_e( '2. Clean up', 'format-media-titles' ); ?></h2><p><?php esc_html_e( 'Remove or replace characters and phrases.', 'format-media-titles' ); ?></p></div>
								</div>
								<div class="wpgo-smm-rule-controls wpgo-smm-cleanup-grid">
									<fieldset class="wpgo-smm-fieldset">
										<legend><?php esc_html_e( 'Replace with spaces', 'format-media-titles' ); ?></legend>
										<div class="wpgo-smm-choice-grid wpgo-smm-character-grid">
											<?php
											$this->render_checkbox( $options, 'chk_hyphen', __( 'Hyphen', 'format-media-titles' ), '-' );
											$this->render_checkbox( $options, 'chk_underscore', __( 'Underscore', 'format-media-titles' ), '_' );
											$this->render_checkbox( $options, 'chk_period', __( 'Period', 'format-media-titles' ), '.' );
											$this->render_checkbox( $options, 'chk_tilde', __( 'Tilde', 'format-media-titles' ), '~' );
											$this->render_checkbox( $options, 'chk_plus', __( 'Plus', 'format-media-titles' ), '+' );
											if ( $has_premium ) {
												$this->render_checkbox( $options, 'chk_hash', __( 'Hash', 'format-media-titles' ), '#' );
												$this->render_checkbox( $options, 'chk_ampersand', __( 'At sign', 'format-media-titles' ), '@' );
												$this->render_checkbox( $options, 'chk_number', __( 'All numbers', 'format-media-titles' ), '0–9' );
												$this->render_checkbox( $options, 'chk_square_brackets', __( 'Square brackets', 'format-media-titles' ), '[]' );
												$this->render_checkbox( $options, 'chk_round_brackets', __( 'Round brackets', 'format-media-titles' ), '()' );
												$this->render_checkbox( $options, 'chk_curly_brackets', __( 'Curly brackets', 'format-media-titles' ), '{}' );
											}
											?>
										</div>
									</fieldset>
									<?php if ( $has_premium ) : ?>
										<div class="wpgo-smm-cleanup-side">
											<div class="wpgo-smm-field-group">
												<label for="wpgo-smm-custom-phrases"><strong><?php esc_html_e( 'Case-sensitive phrases to remove', 'format-media-titles' ); ?></strong></label>
												<input id="wpgo-smm-custom-phrases" type="text" class="regular-text" name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[txt_chars]" value="<?php echo esc_attr( $options['txt_chars'] ); ?>">
												<p class="description"><?php esc_html_e( 'Separate phrases with commas.', 'format-media-titles' ); ?></p>
											</div>
											<fieldset class="wpgo-smm-fieldset">
												<legend><?php esc_html_e( 'Parsing', 'format-media-titles' ); ?></legend>
												<div class="wpgo-smm-choice-list">
													<?php $this->render_checkbox( $options, 'chk_parse_capitalization', __( 'Add spaces before capital letters', 'format-media-titles' ) ); ?>
													<?php $this->render_checkbox( $options, 'chk_parse_numeric', __( 'Add spaces before number groups', 'format-media-titles' ) ); ?>
												</div>
											</fieldset>
										</div>
									<?php else : ?>
										<div class="wpgo-smm-pro-note"><strong><?php esc_html_e( 'More cleanup rules in Pro', 'format-media-titles' ); ?></strong><span><?php esc_html_e( 'Remove numbers, brackets, symbols or custom phrases, and split joined words.', 'format-media-titles' ); ?></span><a href="<?php echo esc_url( smm_fs()->get_upgrade_url() ); ?>"><?php esc_html_e( 'Compare editions', 'format-media-titles' ); ?></a></div>
									<?php endif; ?>
								</div>
							</section>

							<section class="wpgo-smm-rule-section" aria-labelledby="wpgo-smm-capitalisation-title">
								<div class="wpgo-smm-rule-intro">
									<span class="wpgo-smm-rule-icon dashicons dashicons-editor-spellcheck" aria-hidden="true"></span>
									<div><h2 id="wpgo-smm-capitalisation-title"><?php esc_html_e( '3. Capitalisation', 'format-media-titles' ); ?></h2><p><?php esc_html_e( 'Control the casing of the formatted title.', 'format-media-titles' ); ?></p></div>
								</div>
								<div class="wpgo-smm-rule-controls wpgo-smm-capitalisation-grid">
										<fieldset class="wpgo-smm-fieldset">
											<legend><?php esc_html_e( 'Letter case', 'format-media-titles' ); ?></legend>
											<div class="wpgo-smm-choice-list">
												<label class="wpgo-smm-choice"><input name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[rdo_case_options]" type="radio" value="all_lower" <?php checked( 'all_lower', $options['rdo_case_options'] ); ?>> <?php esc_html_e( 'Lowercase', 'format-media-titles' ); ?></label>
												<label class="wpgo-smm-choice"><input name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[rdo_case_options]" type="radio" value="all_upper" <?php checked( 'all_upper', $options['rdo_case_options'] ); ?>> <?php esc_html_e( 'Uppercase', 'format-media-titles' ); ?></label>
												<label class="wpgo-smm-choice"><input name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[rdo_case_options]" type="radio" value="none" <?php checked( 'none', $options['rdo_case_options'] ); ?>> <?php esc_html_e( 'Leave unchanged', 'format-media-titles' ); ?></label>
											</div>
										</fieldset>
										<fieldset class="wpgo-smm-fieldset">
											<legend><?php esc_html_e( 'Capitalise', 'format-media-titles' ); ?></legend>
											<div class="wpgo-smm-choice-list">
												<label class="wpgo-smm-choice"><input name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[rdo_cap_options]" type="radio" value="cap_all" <?php checked( 'cap_all', $options['rdo_cap_options'] ); ?>> <?php esc_html_e( 'Every word', 'format-media-titles' ); ?></label>
												<label class="wpgo-smm-choice"><input name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[rdo_cap_options]" type="radio" value="cap_first" <?php checked( 'cap_first', $options['rdo_cap_options'] ); ?>> <?php esc_html_e( 'First word only', 'format-media-titles' ); ?></label>
												<label class="wpgo-smm-choice"><input name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[rdo_cap_options]" type="radio" value="none" <?php checked( 'none', $options['rdo_cap_options'] ); ?>> <?php esc_html_e( 'Do not capitalise', 'format-media-titles' ); ?></label>
											</div>
										</fieldset>
									<div class="wpgo-smm-field-group">
										<label for="wpgo-smm-uppercase-words"><strong><?php esc_html_e( 'Keep these words uppercase', 'format-media-titles' ); ?></strong></label>
										<input id="wpgo-smm-uppercase-words" type="text" class="regular-text" name="<?php echo esc_attr( $this->_args['options_db_name'] ); ?>[txt_uppercase_words]" value="<?php echo esc_attr( $options['txt_uppercase_words'] ); ?>">
										<p class="description"><?php esc_html_e( 'Comma-separated whole words, for example SEO, PDF, UK. They are restored after the other case rules run.', 'format-media-titles' ); ?></p>
									</div>
								</div>
							</section>

							<section class="wpgo-smm-rule-section" aria-labelledby="wpgo-smm-metadata-title">
								<div class="wpgo-smm-rule-intro">
									<span class="wpgo-smm-rule-icon dashicons dashicons-clipboard" aria-hidden="true"></span>
									<div><h2 id="wpgo-smm-metadata-title"><?php esc_html_e( '4. Metadata fields', 'format-media-titles' ); ?></h2><p><?php esc_html_e( 'Copy the formatted title to other metadata fields.', 'format-media-titles' ); ?></p></div>
								</div>
								<div class="wpgo-smm-rule-controls">
									<fieldset class="wpgo-smm-fieldset">
										<legend><?php esc_html_e( 'Also copy to', 'format-media-titles' ); ?></legend>
										<div class="wpgo-smm-choice-grid wpgo-smm-choice-grid--three">
											<?php $this->render_checkbox( $options, 'chk_alt', __( 'Alternative text', 'format-media-titles' ) ); ?>
											<?php $this->render_checkbox( $options, 'chk_caption', __( 'Caption', 'format-media-titles' ) ); ?>
											<?php $this->render_checkbox( $options, 'chk_description', __( 'Description', 'format-media-titles' ) ); ?>
										</div>
										<p class="description"><?php esc_html_e( 'The Media Library title is always updated. Select any additional fields that should receive the same result.', 'format-media-titles' ); ?></p>
									</fieldset>
								</div>
							</section>
						</div>

						<aside class="wpgo-smm-live-preview" aria-labelledby="wpgo-smm-preview-title">
							<h2 id="wpgo-smm-preview-title"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> <?php esc_html_e( 'Live preview', 'format-media-titles' ); ?></h2>
							<p><?php esc_html_e( 'See how your rules transform titles and metadata.', 'format-media-titles' ); ?></p>
							<label for="wpgo-smm-preview-source"><strong><?php esc_html_e( 'Example file', 'format-media-titles' ); ?></strong></label>
							<div class="wpgo-smm-preview-file">
								<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
								<input id="wpgo-smm-preview-source" type="text" value="coastal-walk.jpg" autocomplete="off">
							</div>
							<p class="wpgo-smm-preview-kicker"><span class="dashicons dashicons-edit" aria-hidden="true"></span> <?php esc_html_e( 'Edit the example filename to try your rules.', 'format-media-titles' ); ?></p>
							<p class="wpgo-smm-preview-label"><?php esc_html_e( 'Source', 'format-media-titles' ); ?> (<span id="wpgo-smm-preview-source-label"><?php esc_html_e( 'WordPress title', 'format-media-titles' ); ?></span>)</p>
							<div class="wpgo-smm-preview-value"><code id="wpgo-smm-preview-before">coastal-walk</code></div>
							<p class="wpgo-smm-preview-label wpgo-smm-preview-label--result"><?php esc_html_e( 'Result', 'format-media-titles' ); ?></p>
							<div class="wpgo-smm-preview-value wpgo-smm-preview-value--result"><strong id="wpgo-smm-preview-result">Coastal Walk</strong></div>
							<div class="wpgo-smm-preview-fields" aria-live="polite">
								<p class="wpgo-smm-preview-fields-title"><?php esc_html_e( 'Preview of copied fields', 'format-media-titles' ); ?></p>
								<div data-preview-row="chk_alt"><span><?php esc_html_e( 'Alternative text', 'format-media-titles' ); ?></span><strong data-preview-value></strong></div>
								<div data-preview-row="chk_caption"><span><?php esc_html_e( 'Caption', 'format-media-titles' ); ?></span><strong data-preview-value></strong></div>
								<div data-preview-row="chk_description"><span><?php esc_html_e( 'Description', 'format-media-titles' ); ?></span><strong data-preview-value></strong></div>
							</div>
							<p class="wpgo-smm-preview-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> <?php esc_html_e( 'This preview reflects the rules above. Your original file is never renamed.', 'format-media-titles' ); ?></p>
						</aside>
					</div>
					<div class="wpgo-smm-action-bar">
						<?php submit_button( __( 'Save formatting rules', 'format-media-titles' ), 'primary', 'submit', false ); ?>
						<span id="wpgo-smm-save-status" class="wpgo-smm-save-status<?php echo $settings_saved ? ' is-visible' : ''; ?>" role="status" aria-live="polite"<?php echo $settings_saved ? '' : ' hidden'; ?>><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Settings saved', 'format-media-titles' ); ?></span>
						<button type="submit" class="button wpgo-smm-reset-button" form="wpgo-smm-reset-form"><?php esc_html_e( 'Reset plugin settings', 'format-media-titles' ); ?></button>
					</div>
				</section>

				<?php if ( $has_premium ) : ?>
				<section class="wpgo-smm-panel wpgo-smm-batch-panel" data-smm-panel="batch" hidden>
					<header class="wpgo-smm-batch-intro">
						<p class="wpgo-smm-section-kicker"><?php esc_html_e( 'Background processing', 'format-media-titles' ); ?></p>
						<h2><?php esc_html_e( 'Update a large Media Library without keeping this page open', 'format-media-titles' ); ?></h2>
						<p><?php esc_html_e( 'Choose the complete library or a custom selection. Progress is saved after each small group, so you can leave and return later.', 'format-media-titles' ); ?></p>
					</header>
					<div class="wpgo-smm-batch-grid">
						<div class="wpgo-smm-batch-main">
							<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Back up your database before changing existing media metadata.', 'format-media-titles' ); ?></strong></p></div>
							<section class="wpgo-smm-batch-card" aria-labelledby="wpgo-smm-batch-scope-title">
								<h3 id="wpgo-smm-batch-scope-title"><?php esc_html_e( '1. Choose what to process', 'format-media-titles' ); ?></h3>
								<p><?php esc_html_e( 'Choose the complete Media Library or select individual attachments, then limit the job to one file type if needed.', 'format-media-titles' ); ?></p>
								<input type="hidden" id="wpgo-smm-media-ids" value="">
								<p class="wpgo-smm-selection-summary"><span><?php esc_html_e( 'Current selection', 'format-media-titles' ); ?></span><strong id="wpgo-smm-selection-label"><?php esc_html_e( 'All media items', 'format-media-titles' ); ?></strong></p>
								<div class="wpgo-smm-button-row">
									<button type="button" id="wpgo-smm-select" class="button"><?php esc_html_e( 'Custom selection', 'format-media-titles' ); ?></button>
									<button type="button" id="wpgo-smm-clear" class="button"><?php esc_html_e( 'Use complete library', 'format-media-titles' ); ?></button>
								</div>
								<div class="wpgo-smm-batch-filter">
									<label for="wpgo-smm-media-type"><strong><?php esc_html_e( 'File type', 'format-media-titles' ); ?></strong></label>
									<select id="wpgo-smm-media-type">
										<option value="all"><?php esc_html_e( 'All file types', 'format-media-titles' ); ?></option>
										<option value="image"><?php esc_html_e( 'Images (excluding SVG)', 'format-media-titles' ); ?></option>
										<option value="svg"><?php esc_html_e( 'SVG files', 'format-media-titles' ); ?></option>
										<option value="pdf"><?php esc_html_e( 'PDF files', 'format-media-titles' ); ?></option>
										<option value="document"><?php esc_html_e( 'Documents', 'format-media-titles' ); ?></option>
										<option value="audio"><?php esc_html_e( 'Audio files', 'format-media-titles' ); ?></option>
										<option value="video"><?php esc_html_e( 'Video files', 'format-media-titles' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'The file type is saved with the job and stays the same while it runs.', 'format-media-titles' ); ?></p>
								</div>
							</section>
							<section class="wpgo-smm-batch-card" aria-labelledby="wpgo-smm-batch-run-title">
								<h3 id="wpgo-smm-batch-run-title"><?php esc_html_e( '2. Start or review the job', 'format-media-titles' ); ?></h3>
								<div class="wpgo-smm-button-row">
									<button type="button" id="wpgo-smm-batch" class="button button-primary"><?php esc_html_e( 'Start background processing', 'format-media-titles' ); ?></button>
									<button type="button" id="wpgo-smm-cancel" class="button" hidden disabled><?php esc_html_e( 'Cancel background job', 'format-media-titles' ); ?></button>
								</div>
								<div id="wpgo-smm-progress" class="wpgo-smm-progress" role="status" aria-live="polite"></div>
								<label for="wpgo-smm-log"><strong><?php esc_html_e( 'Recent results', 'format-media-titles' ); ?></strong></label>
								<textarea id="wpgo-smm-log" class="large-text code" rows="10" readonly></textarea>
							</section>
						</div>
						<aside class="wpgo-smm-batch-notes" aria-labelledby="wpgo-smm-batch-notes-title">
							<h3 id="wpgo-smm-batch-notes-title"><?php esc_html_e( 'Built for large libraries', 'format-media-titles' ); ?></h3>
							<ul>
								<li><strong><?php esc_html_e( '20 items at a time', 'format-media-titles' ); ?></strong><span><?php esc_html_e( 'Each worker handles a bounded group instead of loading the whole library into memory.', 'format-media-titles' ); ?></span></li>
								<li><strong><?php esc_html_e( 'Progress is saved', 'format-media-titles' ); ?></strong><span><?php esc_html_e( 'Close the page and return later without losing the job summary.', 'format-media-titles' ); ?></span></li>
								<li><strong><?php esc_html_e( 'Safe cancellation', 'format-media-titles' ); ?></strong><span><?php esc_html_e( 'A cancellation request takes effect after the current worker finishes.', 'format-media-titles' ); ?></span></li>
							</ul>
						</aside>
					</div>
				</section>
				<?php endif; ?>

				<section class="wpgo-smm-panel" data-smm-panel="support" hidden>
					<?php do_settings_sections( $this->_args['menu_slug'] ); ?>
					<h2><?php esc_html_e( 'Support summary', 'format-media-titles' ); ?></h2>
					<p><?php esc_html_e( 'Copy this summary when asking for help. It excludes site URLs, content, option values, licence keys, account details and billing data.', 'format-media-titles' ); ?></p>
					<label class="screen-reader-text" for="wpgo-smm-support-summary"><?php esc_html_e( 'Privacy-safe support summary', 'format-media-titles' ); ?></label>
					<textarea id="wpgo-smm-support-summary" class="large-text code" rows="9" readonly><?php echo esc_textarea( $this->get_support_summary() ); ?></textarea>
				</section>
			</form>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="wpgo-smm-reset-form">
				<input type="hidden" name="action" value="wpgo_smm_reset_options">
				<?php wp_nonce_field( 'wpgo_smm_reset_options' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add Settings to the Plugins list.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function plugin_settings_link( $links ) {
		$url = $this->admin_page_url( $this->_args['menu_slug'] );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'format-media-titles' ) . '</a>' );

		return $links;
	}
}
