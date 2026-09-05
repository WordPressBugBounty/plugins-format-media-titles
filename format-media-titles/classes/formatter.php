<?php
/**
 * Pure media-title formatting logic.
 *
 * @package WPGO_SEO_Media_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts a filename or existing title according to the saved rules.
 */
class WPGO_SEO_Media_Manager_Current_Formatter {
	/**
	 * Format a title.
	 *
	 * @param string               $title   Source title.
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string
	 */
	public function format( $title, $options ) {
		$title = $this->transform_source( (string) $title, $options );

		$characters = $this->characters_to_replace( $options );
		if ( ! empty( $characters ) ) {
			$title = str_replace( $characters, ' ', $title );
		}

		$title = $this->collapse_whitespace( $title );
		$title = $this->apply_case( $title, isset( $options['rdo_case_options'] ) ? (string) $options['rdo_case_options'] : 'none' );
		$title = $this->apply_capitalization( $title, isset( $options['rdo_cap_options'] ) ? (string) $options['rdo_cap_options'] : 'none' );
		$title = $this->apply_uppercase_words( $title, isset( $options['txt_uppercase_words'] ) ? (string) $options['txt_uppercase_words'] : '' );

		return $this->collapse_whitespace( $title );
	}

	/**
	 * Allow the premium formatter to transform the source before shared rules.
	 *
	 * @param string               $title   Source title.
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string
	 */
	protected function transform_source( $title, $options ) {
		unset( $options );
		return $title;
	}

	/**
	 * Get selected characters that should become spaces.
	 *
	 * The chk_ampersand option name is retained for compatibility. Historically
	 * it represented the at sign shown by the settings screen.
	 *
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string[]
	 */
	protected function characters_to_replace( $options ) {
		$map        = array(
			'chk_hyphen'     => array( '-' ),
			'chk_underscore' => array( '_' ),
			'chk_period'     => array( '.' ),
			'chk_tilde'      => array( '~' ),
			'chk_plus'       => array( '+' ),
		);
		$characters = array();

		foreach ( $map as $option => $mapped_characters ) {
			if ( ! empty( $options[ $option ] ) ) {
				$characters = array_merge( $characters, $mapped_characters );
			}
		}

		return $characters;
	}

	/**
	 * Apply a lower- or uppercase transform with an mbstring fallback.
	 *
	 * @param string $title Title.
	 * @param string $method Case method.
	 * @return string
	 */
	private function apply_case( $title, $method ) {
		if ( 'all_lower' === $method ) {
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
		}

		if ( 'all_upper' === $method ) {
			return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $title, 'UTF-8' ) : strtoupper( $title );
		}

		return $title;
	}

	/**
	 * Apply word or sentence capitalization.
	 *
	 * @param string $title Title.
	 * @param string $method Capitalization method.
	 * @return string
	 */
	private function apply_capitalization( $title, $method ) {
		if ( 'cap_all' === $method ) {
			if ( function_exists( 'mb_convert_case' ) ) {
				return mb_convert_case( $title, MB_CASE_TITLE, 'UTF-8' );
			}

			return ucwords( $title );
		}

		if ( 'cap_first' === $method ) {
			$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );

			if ( '' === $lower ) {
				return '';
			}

			if ( function_exists( 'mb_substr' ) && function_exists( 'mb_strtoupper' ) ) {
				return mb_strtoupper( mb_substr( $lower, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $lower, 1, null, 'UTF-8' );
			}

			return ucfirst( $lower );
		}

		return $title;
	}

	/**
	 * Restore configured whole words to uppercase after other case rules run.
	 *
	 * @param string $title Title.
	 * @param string $words Comma-separated protected words.
	 * @return string
	 */
	private function apply_uppercase_words( $title, $words ) {
		foreach ( self::normalize_uppercase_words( $words ) as $word ) {
			$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $word, '/' ) . '(?![\p{L}\p{N}])/iu';
			$result  = preg_replace( $pattern, $word, $title );
			if ( null !== $result ) {
				$title = $result;
			}
		}

		return $title;
	}

	/**
	 * Normalize a bounded list of unique protected uppercase words.
	 *
	 * @param string $words Comma-separated words.
	 * @return string[]
	 */
	public static function normalize_uppercase_words( $words ) {
		$normalized = array();
		$items      = array_slice( explode( ',', (string) $words ), 0, 50 );

		foreach ( $items as $item ) {
			$item = trim( wp_strip_all_tags( $item ) );
			if ( '' === $item || ! preg_match( '/^[\p{L}\p{N}][\p{L}\p{N}-]{0,29}$/u', $item ) ) {
				continue;
			}

			$item                = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $item, 'UTF-8' ) : strtoupper( $item );
			$normalized[ $item ] = $item;
		}

		return array_values( $normalized );
	}

	/**
	 * Normalize all whitespace and trim the result.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private function collapse_whitespace( $title ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', $title ) );
	}
}
