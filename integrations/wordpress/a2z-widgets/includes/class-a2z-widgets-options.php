<?php
/**
 * Allow-lists and validators. Every value that ends up in an iframe, image or
 * link URL passes through one of these functions. They mirror the checks the
 * A2Z server and runtime apply (EmbedFrameOptions.cs, a2z-embed-core.js,
 * embed.js, EmbedBadgeSvg.cs, EmbedCheckService.cs, DomainInputValidator.cs),
 * so the plugin never builds a URL the server would silently rewrite.
 *
 * @package A2Z_Widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static validation helpers.
 */
final class A2Z_Widgets_Options {

	const OPTION_NAME = 'a2z_widgets_settings';

	/** Widget slug, as accepted by the plugin (the spec's /^[a-z0-9-]{2,80}$/). */
	const SLUG_RE = '/^[a-z0-9-]{2,80}$/';

	/** Preset field id, as accepted by the frame (v_{id}). */
	const FIELD_RE = '/^[A-Za-z0-9]{1,32}$/';

	/** Locale, as accepted by the frame runtime. */
	const LOCALE_RE = '/^[a-z]{2,3}(?:-[A-Za-z]{2,4})?(?:-[A-Z]{2})?$/';

	/** Accent colour: six hex digits, with or without '#'. */
	const HEX_RE = '/^#?[0-9a-fA-F]{6}$/';

	/** Width: 2-4 digits, optional px or % (same rule as embed.js data-width). */
	const WIDTH_RE = '/^\d{2,4}(px|%)?$/';

	/** DNS label (DomainInputValidator.LabelPattern). */
	const LABEL_RE = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

	const MIN_HEIGHT     = 80;
	const MAX_HEIGHT     = 5000;
	const DEFAULT_HEIGHT = 560;
	const MAX_PRESET_LEN = 4000;
	const MAX_PRESETS    = 40;

	/**
	 * Themes the frame accepts.
	 *
	 * @return string[]
	 */
	public static function themes() {
		return array( 'auto', 'light', 'dark' );
	}

	/**
	 * Unit systems the frame accepts.
	 *
	 * @return string[]
	 */
	public static function units() {
		return array( 'metric', 'imperial' );
	}

	/**
	 * Currencies the frame runtime accepts (a2z-embed-core.js CURRENCIES).
	 *
	 * @return string[]
	 */
	public static function currencies() {
		return array(
			'USD', 'EUR', 'GBP', 'INR', 'JPY', 'CNY', 'AUD', 'CAD', 'CHF', 'SGD', 'HKD', 'NZD', 'SEK', 'NOK', 'DKK',
			'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'TRY', 'RUB', 'UAH', 'ZAR', 'NGN', 'KES', 'EGP', 'MAD', 'AED', 'SAR',
			'QAR', 'KWD', 'BHD', 'OMR', 'JOD', 'ILS', 'PKR', 'BDT', 'LKR', 'NPR', 'IDR', 'MYR', 'THB', 'PHP', 'VND',
			'KRW', 'TWD', 'BRL', 'MXN', 'ARS', 'CLP', 'COP', 'PEN', 'UYU',
		);
	}

	/**
	 * Badge checks the server knows (EmbedCheckService.Checks).
	 *
	 * @return string[]
	 */
	public static function badge_checks() {
		return array( 'ssl', 'ssl-expiry', 'https', 'hsts', 'headers', 'spf', 'dmarc', 'dnssec', 'online' );
	}

	/**
	 * Badge styles (EmbedBadgeSvg.Styles).
	 *
	 * @return string[]
	 */
	public static function badge_styles() {
		return array( 'flat', 'rounded', 'square' );
	}

	/**
	 * Report page a badge links to (EmbedController.ReportFor).
	 *
	 * @param string $check A validated check.
	 * @return string
	 */
	public static function report_for( $check ) {
		switch ( $check ) {
			case 'ssl':
			case 'ssl-expiry':
				return 'ssl';
			case 'headers':
			case 'hsts':
				return 'headers';
			case 'spf':
			case 'dmarc':
			case 'dnssec':
				return 'email-security';
			default:
				return 'security';
		}
	}

	/**
	 * Plugin settings with defaults applied.
	 *
	 * @return array{auto_height:bool,powered_by:bool,default_theme:string,default_currency:string,default_units:string}
	 */
	public static function settings() {
		$defaults = array(
			'auto_height'      => false,
			'powered_by'       => false,
			'default_theme'    => 'auto',
			'default_currency' => '',
			'default_units'    => '',
		);
		$saved = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return self::sanitize_settings( array_merge( $defaults, $saved ) );
	}

	/**
	 * Settings sanitiser (also the register_setting sanitize_callback).
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'auto_height'      => ! empty( $input['auto_height'] ),
			'powered_by'       => ! empty( $input['powered_by'] ),
			'default_theme'    => self::pick( isset( $input['default_theme'] ) ? $input['default_theme'] : '', self::themes(), 'auto' ),
			'default_currency' => self::currency( isset( $input['default_currency'] ) ? $input['default_currency'] : '' ),
			'default_units'    => self::pick( isset( $input['default_units'] ) ? $input['default_units'] : '', self::units(), '' ),
		);
	}

	/**
	 * Return $value if it is in $allowed, else $fallback.
	 *
	 * @param mixed    $value    Candidate.
	 * @param string[] $allowed  Allow-list.
	 * @param string   $fallback Fallback.
	 * @return string
	 */
	public static function pick( $value, array $allowed, $fallback ) {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Validated slug or '' if invalid.
	 *
	 * @param mixed $slug Candidate.
	 * @return string
	 */
	public static function slug( $slug ) {
		$slug = is_scalar( $slug ) ? strtolower( trim( (string) $slug ) ) : '';
		return preg_match( self::SLUG_RE, $slug ) ? $slug : '';
	}

	/**
	 * Validated ISO currency or ''.
	 *
	 * @param mixed $value Candidate.
	 * @return string
	 */
	public static function currency( $value ) {
		$value = is_scalar( $value ) ? strtoupper( trim( (string) $value ) ) : '';
		return in_array( $value, self::currencies(), true ) ? $value : '';
	}

	/**
	 * Validated locale or ''.
	 *
	 * @param mixed $value Candidate.
	 * @return string
	 */
	public static function locale( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return preg_match( self::LOCALE_RE, $value ) ? $value : '';
	}

	/**
	 * Accent as six lowercase hex digits without '#', or ''.
	 *
	 * @param mixed $value Candidate.
	 * @return string
	 */
	public static function accent( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return preg_match( self::HEX_RE, $value ) ? strtolower( ltrim( $value, '#' ) ) : '';
	}

	/**
	 * Radius 0-24, or null when absent/invalid.
	 *
	 * @param mixed $value Candidate.
	 * @return int|null
	 */
	public static function radius( $value ) {
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) || ! preg_match( '/^\d{1,2}$/', trim( (string) $value ) ) ) {
			return null;
		}
		$r = (int) $value;
		return ( $r >= 0 && $r <= 24 ) ? $r : null;
	}

	/**
	 * Height in px clamped to the loader's range, or $fallback.
	 *
	 * @param mixed $value    Candidate.
	 * @param int   $fallback Fallback.
	 * @return int
	 */
	public static function height( $value, $fallback ) {
		if ( is_scalar( $value ) && preg_match( '/^\d{2,4}(px)?$/', trim( (string) $value ) ) ) {
			$h = (int) $value;
			if ( $h >= self::MIN_HEIGHT && $h <= self::MAX_HEIGHT ) {
				return $h;
			}
		}
		$fallback = (int) $fallback;
		return ( $fallback >= self::MIN_HEIGHT && $fallback <= self::MAX_HEIGHT ) ? $fallback : self::DEFAULT_HEIGHT;
	}

	/**
	 * CSS max-width value ("480px", "90%") or '' for full width.
	 *
	 * @param mixed $value Candidate.
	 * @return string
	 */
	public static function width( $value ) {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		if ( ! preg_match( self::WIDTH_RE, $value ) ) {
			return '';
		}
		if ( '%' === substr( $value, -1 ) ) {
			$pct = (int) $value;
			return ( $pct >= 10 && $pct < 100 ) ? $pct . '%' : '';
		}
		$px = (int) $value;
		return ( $px >= 200 && $px <= 2000 ) ? $px . 'px' : '';
	}

	/**
	 * Boolean-ish attribute: 1/true/yes/on => true, 0/false/no/off => false, else $fallback.
	 *
	 * @param mixed $value    Candidate.
	 * @param bool  $fallback Fallback.
	 * @return bool
	 */
	public static function flag( $value, $fallback ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}
		return (bool) $fallback;
	}

	/**
	 * Preset field values: id => value. Ids must match FIELD_RE, values are
	 * plain text capped at MAX_PRESET_LEN (the frame's own cap) and are only
	 * ever URL-encoded into the query string.
	 *
	 * @param mixed $presets Candidate map.
	 * @return array<string,string>
	 */
	public static function presets( $presets ) {
		$out = array();
		if ( ! is_array( $presets ) ) {
			return $out;
		}
		foreach ( $presets as $id => $value ) {
			if ( count( $out ) >= self::MAX_PRESETS ) {
				break;
			}
			$id = (string) $id;
			if ( ! preg_match( self::FIELD_RE, $id ) || ! is_scalar( $value ) ) {
				continue;
			}
			$value = sanitize_textarea_field( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$out[ $id ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_PRESET_LEN ) : substr( $value, 0, self::MAX_PRESET_LEN );
		}
		return $out;
	}

	/**
	 * Normalise a domain like DomainInputValidator.TryNormalize: strip a
	 * pasted scheme/path, lowercase, at least two valid DNS labels.
	 *
	 * @param mixed $input Candidate.
	 * @return string Domain, or '' when invalid.
	 */
	public static function domain( $input ) {
		$value = is_scalar( $input ) ? trim( (string) $input ) : '';
		if ( '' === $value ) {
			return '';
		}
		if ( false !== strpos( $value, '://' ) ) {
			$host  = wp_parse_url( $value, PHP_URL_HOST );
			$value = is_string( $host ) ? $host : '';
		}
		$value = rtrim( $value, '/' );
		$slash = strpos( $value, '/' );
		if ( false !== $slash ) {
			$value = substr( $value, 0, $slash );
		}
		$value = strtolower( trim( $value, '.' ) );
		if ( '' === $value || strlen( $value ) > 253 ) {
			return '';
		}
		$labels = explode( '.', $value );
		if ( count( $labels ) < 2 ) {
			return '';
		}
		foreach ( $labels as $label ) {
			if ( ! preg_match( self::LABEL_RE, $label ) ) {
				return '';
			}
		}
		return $value;
	}
}
