<?php
/**
 * WordPress integration and settings screen.
 *
 * @package Format_Media_Titles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinate settings and new-upload formatting.
 */
class FMT_Plugin {
	/**
	 * Pure formatter.
	 *
	 * @var FMT_Formatter
	 */
	private $formatter;

	/**
	 * Build the runtime.
	 *
	 * @param FMT_Formatter|null $formatter Optional test formatter.
	 */
	public function __construct( $formatter = null ) {
		$this->formatter = $formatter instanceof FMT_Formatter ? $formatter : new FMT_Formatter();
	}

	/**
	 * Register WordPress hooks that are not part of the legacy callback contract.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FMT_FILE ), array( $this, 'settings_link' ) );
	}

	/**
	 * Default options for a new installation.
	 *
	 * @return array<string, string>
	 */
	public static function default_options() {
		return array(
			'chk_hyphen'          => '1',
			'chk_underscore'      => '1',
			'rdo_cap_options'     => 'cap_all',
			'txt_uppercase_words' => '',
		);
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'format-media-titles', false, dirname( plugin_basename( FMT_FILE ) ) . '/languages' );
	}

	/**
	 * Register the established option with strict sanitization.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'fmt_plugin_options',
			FMT_OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'type'              => 'array',
			)
		);
	}

	/**
	 * Sanitize a complete settings submission.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, string>
	 */
	public function sanitize_options( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( array( 'chk_hyphen', 'chk_underscore', 'chk_period', 'chk_tilde', 'chk_plus', 'chk_alt', 'chk_caption', 'chk_description', 'chk_default_options_db' ) as $checkbox ) {
			if ( isset( $input[ $checkbox ] ) && '1' === (string) $input[ $checkbox ] ) {
				$sanitized[ $checkbox ] = '1';
			}
		}

		$capitalization = isset( $input['rdo_cap_options'] ) ? sanitize_key( wp_unslash( $input['rdo_cap_options'] ) ) : 'cap_all';
		if ( ! in_array( $capitalization, array( 'cap_all', 'cap_first', 'all_lower', 'all_upper', 'dont_alter' ), true ) ) {
			$capitalization = 'cap_all';
		}
		$sanitized['rdo_cap_options'] = $capitalization;

		$uppercase_words                  = isset( $input['txt_uppercase_words'] ) ? sanitize_text_field( wp_unslash( $input['txt_uppercase_words'] ) ) : '';
		$sanitized['txt_uppercase_words'] = implode( ', ', FMT_Formatter::normalize_uppercase_words( $uppercase_words ) );

		return $sanitized;
	}

	/**
	 * Register the settings page.
	 *
	 * @return void
	 */
	public function add_options_page() {
		add_options_page(
			__( 'Format Media Titles', 'format-media-titles' ),
			__( 'Format Media Titles', 'format-media-titles' ),
			'manage_options',
			'format-media-titles',
			array( $this, 'render_options_page' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = get_option( FMT_OPTION_NAME, self::default_options() );
		$options = is_array( $options ) ? $options : self::default_options();
		$mode    = isset( $options['rdo_cap_options'] ) ? (string) $options['rdo_cap_options'] : 'cap_all';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Format Media Titles', 'format-media-titles' ); ?></h1>
			<p><?php esc_html_e( 'Apply predictable formatting rules whenever a new attachment is added to the media library.', 'format-media-titles' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'fmt_plugin_options' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Characters to replace', 'format-media-titles' ); ?></th>
						<td>
							<?php $this->render_checkbox( $options, 'chk_hyphen', __( 'Hyphen', 'format-media-titles' ), '-' ); ?>
							<?php $this->render_checkbox( $options, 'chk_underscore', __( 'Underscore', 'format-media-titles' ), '_' ); ?>
							<?php $this->render_checkbox( $options, 'chk_period', __( 'Period', 'format-media-titles' ), '.' ); ?>
							<?php $this->render_checkbox( $options, 'chk_tilde', __( 'Tilde', 'format-media-titles' ), '~' ); ?>
							<?php $this->render_checkbox( $options, 'chk_plus', __( 'Plus', 'format-media-titles' ), '+' ); ?>
							<p class="description"><?php esc_html_e( 'Selected characters are replaced with spaces before capitalization.', 'format-media-titles' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Capitalization', 'format-media-titles' ); ?></th>
						<td>
							<?php $this->render_radio( 'cap_all', $mode, __( 'Capitalize every word', 'format-media-titles' ) ); ?>
							<?php $this->render_radio( 'cap_first', $mode, __( 'Capitalize the first word', 'format-media-titles' ) ); ?>
							<?php $this->render_radio( 'all_lower', $mode, __( 'Lowercase', 'format-media-titles' ) ); ?>
							<?php $this->render_radio( 'all_upper', $mode, __( 'Uppercase', 'format-media-titles' ) ); ?>
							<?php $this->render_radio( 'dont_alter', $mode, __( 'Leave unchanged', 'format-media-titles' ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fmt-uppercase-words"><?php esc_html_e( 'Keep words uppercase', 'format-media-titles' ); ?></label></th>
						<td>
							<input id="fmt-uppercase-words" class="regular-text" type="text" name="<?php echo esc_attr( FMT_OPTION_NAME ); ?>[txt_uppercase_words]" value="<?php echo esc_attr( isset( $options['txt_uppercase_words'] ) ? $options['txt_uppercase_words'] : '' ); ?>">
							<p class="description"><?php esc_html_e( 'Comma-separated words such as SEO, PDF, UK. These words are restored to uppercase after the capitalization rule runs.', 'format-media-titles' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Copy formatted title', 'format-media-titles' ); ?></th>
						<td>
							<?php $this->render_checkbox( $options, 'chk_alt', __( 'Alternative text', 'format-media-titles' ) ); ?>
							<?php $this->render_checkbox( $options, 'chk_caption', __( 'Caption', 'format-media-titles' ) ); ?>
							<?php $this->render_checkbox( $options, 'chk_description', __( 'Description', 'format-media-titles' ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reset behavior', 'format-media-titles' ); ?></th>
						<td>
							<?php $this->render_checkbox( $options, 'chk_default_options_db', __( 'Restore defaults on the next deactivation and reactivation', 'format-media-titles' ) ); ?>
							<p class="description"><?php esc_html_e( 'Leave this off to retain your settings during normal plugin updates and reactivation.', 'format-media-titles' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save formatting rules', 'format-media-titles' ) ); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Need to update existing media?', 'format-media-titles' ); ?></h2>
			<p><?php esc_html_e( 'SEO Media Manager adds safe batch processing, more cleanup rules, filename sources, and direct support.', 'format-media-titles' ); ?></p>
			<p><a class="button button-secondary" href="https://wpgoplugins.com/plugins/seo-media-manager/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Compare SEO Media Manager', 'format-media-titles' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Render one checkbox.
	 *
	 * @param array<string, mixed> $options Saved options.
	 * @param string               $key     Option key.
	 * @param string               $label   Visible label.
	 * @param string               $example Optional example.
	 * @return void
	 */
	private function render_checkbox( $options, $key, $label, $example = '' ) {
		?>
		<label style="display:block;margin-bottom:4px;"><input name="<?php echo esc_attr( FMT_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" type="checkbox" value="1" <?php checked( '1', isset( $options[ $key ] ) ? $options[ $key ] : '0' ); ?>> <?php echo esc_html( $label ); ?><?php echo '' !== $example ? ' (' . esc_html( $example ) . ')' : ''; ?></label>
		<?php
	}

	/**
	 * Render one capitalization radio.
	 *
	 * @param string $value Current option value.
	 * @param string $mode  Selected mode.
	 * @param string $label Visible label.
	 * @return void
	 */
	private function render_radio( $value, $mode, $label ) {
		?>
		<label style="display:block;margin-bottom:4px;"><input name="<?php echo esc_attr( FMT_OPTION_NAME ); ?>[rdo_cap_options]" type="radio" value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $mode ); ?>> <?php echo esc_html( $label ); ?></label>
		<?php
	}

	/**
	 * Add a settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function settings_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=format-media-titles' ) ) . '">' . esc_html__( 'Settings', 'format-media-titles' ) . '</a>' );

		return $links;
	}

	/**
	 * Format a newly uploaded attachment and update requested fields atomically.
	 *
	 * @param int $id Attachment ID.
	 * @return bool Whether the attachment was updated.
	 */
	public function update_media_title( $id ) {
		$id = absint( $id );
		if ( 0 === $id ) {
			return false;
		}

		$attachment = get_post( $id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		$options = get_option( FMT_OPTION_NAME, self::default_options() );
		$options = is_array( $options ) ? $options : self::default_options();
		$title   = $this->formatter->format( (string) $attachment->post_title, $options );
		if ( '' === $title ) {
			return false;
		}

		$post = array(
			'ID'         => $id,
			'post_title' => $title,
		);
		if ( ! empty( $options['chk_description'] ) ) {
			$post['post_content'] = $title;
		}
		if ( ! empty( $options['chk_caption'] ) ) {
			$post['post_excerpt'] = $title;
		}

		$result = wp_update_post( wp_slash( $post ), true );
		if ( is_wp_error( $result ) ) {
			return false;
		}

		if ( ! empty( $options['chk_alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $title );
		}

		return true;
	}
}
