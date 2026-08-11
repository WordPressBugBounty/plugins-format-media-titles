<?php
/**
 * Pure media-title formatting logic.
 *
 * @package Format_Media_Titles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert an attachment title according to the saved free-plugin rules.
 */
class FMT_Formatter {
	/**
	 * Format a media title.
	 *
	 * @param string               $title   Source title.
	 * @param array<string, mixed> $options Saved settings.
	 * @return string
	 */
	public function format( $title, $options ) {
		$title      = (string) $title;
		$characters = $this->characters_to_replace( $options );

		if ( ! empty( $characters ) ) {
			$title = str_replace( $characters, ' ', $title );
		}

		$title = $this->collapse_whitespace( $title );
		$title = $this->apply_capitalization(
			$title,
			isset( $options['rdo_cap_options'] ) ? (string) $options['rdo_cap_options'] : 'cap_all'
		);
		$title = $this->apply_uppercase_words(
			$title,
			isset( $options['txt_uppercase_words'] ) ? (string) $options['txt_uppercase_words'] : ''
		);

		return $this->collapse_whitespace( $title );
	}

	/**
	 * Get selected characters that should become spaces.
	 *
	 * @param array<string, mixed> $options Saved settings.
	 * @return string[]
	 */
	private function characters_to_replace( $options ) {
		$map = array(
			'chk_hyphen'     => '-',
			'chk_underscore' => '_',
			'chk_period'     => '.',
			'chk_tilde'      => '~',
			'chk_plus'       => '+',
		);

		$characters = array();
		foreach ( $map as $option => $character ) {
			if ( ! empty( $options[ $option ] ) ) {
				$characters[] = $character;
			}
		}

		return $characters;
	}

	/**
	 * Apply the established capitalization modes with Unicode support.
	 *
	 * @param string $title  Title.
	 * @param string $method Capitalization method.
	 * @return string
	 */
	private function apply_capitalization( $title, $method ) {
		if ( 'cap_all' === $method ) {
			return function_exists( 'mb_convert_case' )
				? mb_convert_case( $title, MB_CASE_TITLE, 'UTF-8' )
				: ucwords( $title );
		}

		if ( 'cap_first' === $method ) {
			$lower = $this->lowercase( $title );
			if ( '' === $lower ) {
				return '';
			}

			if ( function_exists( 'mb_substr' ) && function_exists( 'mb_strtoupper' ) ) {
				return mb_strtoupper( mb_substr( $lower, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $lower, 1, null, 'UTF-8' );
			}

			return ucfirst( $lower );
		}

		if ( 'all_lower' === $method ) {
			return $this->lowercase( $title );
		}

		if ( 'all_upper' === $method ) {
			return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $title, 'UTF-8' ) : strtoupper( $title );
		}

		return $title;
	}

	/**
	 * Restore selected words as uppercase after other case rules run.
	 *
	 * @param string $title Title.
	 * @param string $words Comma-separated words.
	 * @return string
	 */
	private function apply_uppercase_words( $title, $words ) {
		$protected_words = self::normalize_uppercase_words( $words );

		foreach ( $protected_words as $word ) {
			$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $word, '/' ) . '(?![\p{L}\p{N}])/iu';
			$result  = preg_replace( $pattern, $word, $title );
			if ( null !== $result ) {
				$title = $result;
			}
		}

		return $title;
	}

	/**
	 * Normalize the protected-word setting into unique uppercase tokens.
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
	 * Convert text to lowercase with an mbstring fallback.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private function lowercase( $title ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
	}

	/**
	 * Normalize whitespace and trim the result.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private function collapse_whitespace( $title ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', $title ) );
	}
}
