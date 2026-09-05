<?php
/**
 * Main plugin runtime.
 *
 * @package WPGO_SEO_Media_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates upload formatting, batch processing, and editor attributes.
 */
class WPGO_SEO_Media_Manager_Current {
	/**
	 * Maximum attachments handled by one Ajax request.
	 */
	const BATCH_SIZE = 20;

	/**
	 * Settings controller.
	 *
	 * @var WPGO_SEO_Media_Manager_Current_Options|null
	 */
	protected $_plugin_options_class;

	/**
	 * Pure title formatter.
	 *
	 * @var WPGO_SEO_Media_Manager_Current_Formatter
	 */
	private $formatter;

	/**
	 * Durable existing-media job manager.
	 *
	 * @var WPGO_SEO_Media_Manager_Background_Jobs|null
	 */
	private $background_jobs;

	/**
	 * Whether paid features are available for this request.
	 *
	 * @var bool
	 */
	private $has_premium_features;

	/**
	 * Construct the runtime.
	 *
	 * @param bool                                          $register_hooks       Whether WordPress hooks should be registered.
	 * @param WPGO_SEO_Media_Manager_Current_Formatter|null $formatter            Optional formatter for tests.
	 * @param bool|null                                     $has_premium_features Optional edition override for tests.
	 */
	public function __construct( $register_hooks = true, $formatter = null, $has_premium_features = null ) {
		$this->has_premium_features = is_bool( $has_premium_features )
			? $has_premium_features
			: ( function_exists( 'wpgo_smm_has_premium_features' ) && wpgo_smm_has_premium_features() );

		$this->formatter = $formatter instanceof WPGO_SEO_Media_Manager_Current_Formatter
			? $formatter
			: ( $this->has_premium_features && class_exists( 'WPGO_SEO_Media_Manager_Premium_Formatter' )
				? new WPGO_SEO_Media_Manager_Premium_Formatter()
				: new WPGO_SEO_Media_Manager_Current_Formatter() );

		if ( ! $register_hooks ) {
			$this->_plugin_options_class = null;
			$this->background_jobs       = null;
			return;
		}

		$this->_plugin_options_class = new WPGO_SEO_Media_Manager_Current_Options(
			array(
				'plugin_name'     => WPGO_SEO_MEDIA_MANAGER_NAME,
				'home_slug'       => WPGO_SEO_MEDIA_MANAGER_NAME_H . '-home',
				'options_group'   => WPGO_SEO_MEDIA_MANAGER_NAME_U . '_plugin_options_group',
				'menu_slug'       => WPGO_SEO_MEDIA_MANAGER_NAME_U . '_admin_options_menu',
				'features_slug'   => WPGO_SEO_MEDIA_MANAGER_NAME_H . '-new-features',
				'options_section' => 'seo_media_manager_default',
				'options_db_name' => WPGO_SEO_MEDIA_MANAGER_OPTIONS_DB_NAME,
				'plugin_root'     => WPGO_SEO_MEDIA_MANAGER_FILE,
				'has_premium'     => $this->has_premium_features,
			)
		);

		add_action( 'plugins_loaded', array( $this, 'localize_plugin' ) );
		add_action( 'plugins_loaded', array( $this, 'disable_format_media_titles_formatter' ), 20 );
		$this->background_jobs = $this->has_premium_features && class_exists( 'WPGO_SEO_Media_Manager_Background_Jobs' )
			? new WPGO_SEO_Media_Manager_Background_Jobs( $this )
			: null;
		add_action( 'add_attachment', array( $this, 'handle_add_attachment' ) );
		add_filter( 'media_send_to_editor', array( $this, 'insert_media_title_attribute' ), 15, 2 );
		add_filter( 'wp_get_attachment_link', array( $this, 'insert_gallery_media_title_attribute' ), 10, 2 );
	}

	/**
	 * Prevent the WordPress.org companion from formatting an upload twice.
	 *
	 * The free plugin remains active and its saved settings remain untouched;
	 * SEO Media Manager simply becomes the formatter while both are installed.
	 *
	 * @return void
	 */
	public function disable_format_media_titles_formatter() {
		if ( ! function_exists( 'fmt_update_media_title' ) ) {
			return;
		}

		remove_action( 'add_attachment', 'fmt_update_media_title' );
		add_action( 'admin_notices', array( $this, 'render_format_media_titles_notice' ) );
	}

	/**
	 * Explain the safe Free-to-Pro handover to administrators.
	 *
	 * @return void
	 */
	public function render_format_media_titles_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible"><p>
			<?php esc_html_e( 'SEO Media Manager imported settings from SEO Media Manager – Format Media Titles and is handling new uploads. You can safely deactivate the free plugin; its settings have not been deleted.', 'format-media-titles' ); ?>
		</p></div>
		<?php
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function localize_plugin() {
		load_plugin_textdomain( 'format-media-titles', false, dirname( plugin_basename( WPGO_SEO_MEDIA_MANAGER_FILE ) ) . '/languages' );
	}

	/**
	 * Format a newly uploaded attachment.
	 *
	 * @param int $id Attachment ID.
	 * @return void
	 */
	public function handle_add_attachment( $id ) {
		$this->update_media_title( $id );
	}

	/**
	 * Add a title attribute to an inserted image when one is not already present.
	 *
	 * @param string $html     Editor HTML.
	 * @param int    $media_id Attachment ID.
	 * @return string
	 */
	public function insert_media_title_attribute( $html, $media_id ) {
		$attachment = get_post( absint( $media_id ) );
		if ( ! $attachment || empty( $attachment->post_title ) || preg_match( '/<img\b[^>]*\btitle\s*=/i', $html ) ) {
			return $html;
		}

		$replacement = '<img title="' . esc_attr( $attachment->post_title ) . '"';
		$updated     = preg_replace( '/<img\b/i', $replacement, $html, 1 );

		return null === $updated ? $html : $updated;
	}

	/**
	 * Add a title attribute to a gallery link when one is not already present.
	 *
	 * @param string $content  Attachment-link HTML.
	 * @param int    $media_id Attachment ID.
	 * @return string
	 */
	public function insert_gallery_media_title_attribute( $content, $media_id ) {
		$title = (string) get_the_title( absint( $media_id ) );
		if ( '' === $title || preg_match( '/<a\b[^>]*\btitle\s*=/i', $content ) ) {
			return $content;
		}

		$replacement = '<a title="' . esc_attr( $title ) . '"';
		$updated     = preg_replace( '/<a\b/i', $replacement, $content, 1 );

		return null === $updated ? $content : $updated;
	}

	/**
	 * Start a durable existing-media job through the historical Ajax method.
	 *
	 * Retained for integrations that invoked the public method directly.
	 *
	 * @return void
	 */
	public function ajax_request() {
		if ( ! $this->has_premium_features || ! class_exists( 'WPGO_SEO_Media_Manager_Background_Jobs' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Batch processing requires SEO Media Manager Pro and an active licence.', 'format-media-titles' ) ),
				403
			);
		}

		if ( ! $this->background_jobs ) {
			$this->background_jobs = new WPGO_SEO_Media_Manager_Background_Jobs( $this, false );
		}

		$this->background_jobs->ajax_start();
	}

	/**
	 * Normalize and deduplicate attachment IDs.
	 *
	 * @param array<int|string, mixed> $ids Raw IDs.
	 * @return int[]
	 */
	public function sanitize_media_ids( $ids ) {
		$normalized = array();

		foreach ( $ids as $id ) {
			if ( is_int( $id ) ) {
				$value = $id;
			} elseif ( is_string( $id ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $id ) ) {
				$value = (int) $id;
			} else {
				continue;
			}

			if ( $value > 0 ) {
				$normalized[ $value ] = $value;
			}
		}

		return array_values( $normalized );
	}

	/**
	 * Format and save an attachment's metadata.
	 *
	 * @param int                       $id               Attachment ID.
	 * @param bool                      $batch_process    Whether this is an existing-item batch operation.
	 * @param array<string, mixed>|null $options_override Optional immutable job settings.
	 * @return array<string, mixed>|null
	 */
	public function update_media_title( $id, $batch_process = false, $options_override = null ) {
		$id = absint( $id );
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			return $batch_process
				? array(
					'id'        => $id,
					'original'  => '',
					'processed' => '',
					'success'   => false,
					'message'   => __( 'The selected item is not an attachment.', 'format-media-titles' ),
				)
				: null;
		}

		$options  = is_array( $options_override ) ? $options_override : WPGO_SEO_Media_Manager_Current_Options::get_plugin_options();
		$original = $this->get_source_title( $id, $batch_process, $options );
		$title    = $this->formatter->format( $original, $options );
		$title    = sanitize_text_field( $title );
		$title    = sanitize_text_field( WPGO_SEO_Media_Manager_Current_Hooks::seo_media_manager_title( $title ) );
		$alt      = sanitize_text_field( WPGO_SEO_Media_Manager_Current_Hooks::seo_media_manager_alt( $title ) );

		if ( '' === $title ) {
			return $batch_process
				? array(
					'id'        => $id,
					'original'  => sanitize_text_field( $original ),
					'processed' => '',
					'success'   => false,
					'message'   => __( 'The formatting rules produced an empty title, so no metadata was changed.', 'format-media-titles' ),
				)
				: null;
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

		$updated = wp_update_post( $post, true );
		$success = ! is_wp_error( $updated );

		if ( $success && ! empty( $options['chk_alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}

		if ( $batch_process ) {
			return array(
				'id'        => $id,
				'original'  => sanitize_text_field( $original ),
				'processed' => $title,
				'success'   => $success,
				'message'   => $success ? '' : $updated->get_error_message(),
			);
		}

		return null;
	}

	/**
	 * Resolve the source title without mutating the attachment.
	 *
	 * Batch jobs intentionally use the stored original filename so rerunning a
	 * job is deterministic and does not repeatedly transform the saved title.
	 *
	 * @param int                  $id            Attachment ID.
	 * @param bool                 $batch_process Whether this is a batch operation.
	 * @param array<string, mixed> $options       Plugin settings.
	 * @return string
	 */
	private function get_source_title( $id, $batch_process, $options ) {
		if ( ! $batch_process && ( ! $this->has_premium_features || 'title' === $options['rdo_source_options'] ) ) {
			$attachment = get_post( $id );
			return $attachment ? (string) $attachment->post_title : '';
		}

		$file = (string) get_attached_file( $id, true );
		$path = pathinfo( $file );
		return (string) $path['filename'];
	}
}
