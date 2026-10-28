<?php
/**
 * Widget registry: GET https://a2z.tools/api/widgets, cached in a transient,
 * with a bundled fallback list when the API cannot be reached.
 *
 * Rules:
 * - Front-end rendering never waits on the network: it reads the cached copy
 *   or the bundled list (A2Z_Widgets_Registry::get( false )).
 * - Admin screens and the block editor may fetch (short timeout). A failed
 *   fetch is remembered for 30 minutes so an offline site does not retry on
 *   every admin page load.
 * - Everything taken from the response is re-validated; nothing from it is
 *   printed without escaping.
 *
 * @package A2Z_Widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry access.
 */
final class A2Z_Widgets_Registry {

	const TRANSIENT      = 'a2z_widgets_registry';
	const FAIL_TRANSIENT = 'a2z_widgets_registry_failed';
	const TTL            = 12 * HOUR_IN_SECONDS;
	const FAIL_TTL       = 30 * MINUTE_IN_SECONDS;
	const TIMEOUT        = 5;
	const MAX_WIDGETS    = 2000;
	const MAX_BODY       = 2097152; // 2 MB.

	/**
	 * Per-request memo.
	 *
	 * @var array|null
	 */
	private static $memo = null;

	/**
	 * Registry URL.
	 *
	 * @return string
	 */
	public static function api_url() {
		return A2Z_WIDGETS_ORIGIN . '/api/widgets';
	}

	/**
	 * The registry.
	 *
	 * @param bool $allow_fetch Whether a remote request may be made on a cache miss.
	 * @return array{source:string,fetched:int,widgets:array<string,array>,categories:array<string,string>}
	 *         source is 'api' (fresh/cached) or 'fallback'.
	 */
	public static function get( $allow_fetch = false ) {
		if ( null !== self::$memo && ( 'api' === self::$memo['source'] || ! $allow_fetch ) ) {
			return self::$memo;
		}

		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['widgets'] ) ) {
			self::$memo = $cached;
			return $cached;
		}

		if ( $allow_fetch && false === get_transient( self::FAIL_TRANSIENT ) ) {
			$fresh = self::fetch();
			if ( null !== $fresh ) {
				set_transient( self::TRANSIENT, $fresh, self::TTL );
				delete_transient( self::FAIL_TRANSIENT );
				self::$memo = $fresh;
				return $fresh;
			}
			set_transient( self::FAIL_TRANSIENT, time(), self::FAIL_TTL );
		}

		self::$memo = self::fallback();
		return self::$memo;
	}

	/**
	 * One widget by slug, or null.
	 *
	 * @param string $slug        Validated slug.
	 * @param bool   $allow_fetch See get().
	 * @return array|null
	 */
	public static function find( $slug, $allow_fetch = false ) {
		$registry = self::get( $allow_fetch );
		return isset( $registry['widgets'][ $slug ] ) ? $registry['widgets'][ $slug ] : null;
	}

	/**
	 * Forget the cached copy (settings "Refresh" button, uninstall).
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT );
		delete_transient( self::FAIL_TRANSIENT );
		self::$memo = null;
	}

	/**
	 * Fetch and normalise the registry. Returns null on any failure.
	 *
	 * @return array|null
	 */
	private static function fetch() {
		$response = wp_remote_get(
			self::api_url(),
			array(
				'timeout'             => self::TIMEOUT,
				'redirection'         => 2,
				'limit_response_size' => self::MAX_BODY,
				'headers'             => array( 'Accept' => 'application/json' ),
				'user-agent'          => 'A2Z-Widgets-WordPress/' . A2Z_WIDGETS_VERSION,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['widgets'] ) || ! is_array( $data['widgets'] ) ) {
			return null;
		}

		$widgets = array();
		foreach ( $data['widgets'] as $raw ) {
			if ( count( $widgets ) >= self::MAX_WIDGETS ) {
				break;
			}
			$w = self::normalise_widget( $raw );
			if ( null !== $w ) {
				$widgets[ $w['slug'] ] = $w;
			}
		}
		if ( empty( $widgets ) ) {
			return null;
		}

		$categories = array();
		if ( ! empty( $data['categories'] ) && is_array( $data['categories'] ) ) {
			foreach ( $data['categories'] as $cat ) {
				if ( ! is_array( $cat ) ) {
					continue;
				}
				$key  = isset( $cat['key'] ) ? self::key( $cat['key'] ) : '';
				$name = isset( $cat['name'] ) ? sanitize_text_field( (string) $cat['name'] ) : '';
				if ( '' !== $key && '' !== $name ) {
					$categories[ $key ] = $name;
				}
			}
		}

		return array(
			'source'     => 'api',
			'fetched'    => time(),
			'widgets'    => $widgets,
			'categories' => $categories,
		);
	}

	/**
	 * Validate one registry entry; keep only the fields the plugin uses.
	 *
	 * @param mixed $raw Entry from the API.
	 * @return array|null
	 */
	private static function normalise_widget( $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$slug = A2Z_Widgets_Options::slug( isset( $raw['slug'] ) ? $raw['slug'] : '' );
		if ( '' === $slug ) {
			return null;
		}
		$name = isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '';

		$inputs = array();
		if ( ! empty( $raw['inputs'] ) && is_array( $raw['inputs'] ) ) {
			foreach ( $raw['inputs'] as $in ) {
				if ( ! is_array( $in ) || empty( $in['id'] ) || ! preg_match( A2Z_Widgets_Options::FIELD_RE, (string) $in['id'] ) ) {
					continue;
				}
				$inputs[] = array(
					'id'      => (string) $in['id'],
					'label'   => isset( $in['label'] ) ? sanitize_text_field( (string) $in['label'] ) : (string) $in['id'],
					'type'    => isset( $in['type'] ) ? self::key( $in['type'] ) : '',
					'example' => isset( $in['example'] ) ? sanitize_text_field( (string) $in['example'] ) : '',
				);
				if ( count( $inputs ) >= A2Z_Widgets_Options::MAX_PRESETS ) {
					break;
				}
			}
		}

		$default_currency = A2Z_Widgets_Options::currency( isset( $raw['defaultCurrency'] ) ? $raw['defaultCurrency'] : '' );

		return array(
			'slug'             => $slug,
			'name'             => '' !== $name ? $name : $slug,
			'category'         => isset( $raw['category'] ) ? self::key( $raw['category'] ) : '',
			'kind'             => isset( $raw['kind'] ) ? self::key( $raw['kind'] ) : '',
			'summary'          => isset( $raw['summary'] ) ? sanitize_text_field( (string) $raw['summary'] ) : '',
			'height'           => A2Z_Widgets_Options::height( isset( $raw['height'] ) ? $raw['height'] : '', A2Z_Widgets_Options::DEFAULT_HEIGHT ),
			'supportsTheme'    => ! empty( $raw['supportsTheme'] ),
			'supportsCurrency' => ! empty( $raw['supportsCurrency'] ),
			'defaultCurrency'  => '' !== $default_currency ? $default_currency : 'USD',
			'supportsUnits'    => ! empty( $raw['supportsUnits'] ),
			'supportsCompact'  => ! empty( $raw['supportsCompact'] ),
			'popular'          => ! empty( $raw['popular'] ),
			'isBadge'          => ! empty( $raw['badgeUrl'] ),
			'inputs'           => $inputs,
		);
	}

	/**
	 * Lowercase key (category, kind, input type).
	 *
	 * @param mixed $value Candidate.
	 * @return string
	 */
	private static function key( $value ) {
		$value = is_scalar( $value ) ? strtolower( (string) $value ) : '';
		return preg_match( '/^[a-z0-9-]{1,40}$/', $value ) ? $value : '';
	}

	/**
	 * Bundled list of popular widgets, used when the API is unreachable.
	 * Snapshot of the A2Z registry (2026-10-01).
	 *
	 * @return array
	 */
	public static function fallback() {
		// slug => [ name, category, height, supportsCurrency, defaultCurrency, supportsUnits ].
		$list = array(
			'emi-calculator'               => array( 'EMI Calculator', 'finance', 560, true, 'INR', false ),
			'mortgage-calculator'          => array( 'Mortgage Calculator', 'finance', 830, true, 'USD', false ),
			'sip-calculator'               => array( 'SIP Calculator', 'finance', 820, true, 'INR', false ),
			'loan-calculator'              => array( 'Loan Calculator', 'finance', 800, true, 'USD', false ),
			'compound-interest-calculator' => array( 'Compound Interest Calculator', 'finance', 830, true, 'USD', false ),
			'currency-converter'           => array( 'Currency Converter', 'finance', 470, true, 'USD', false ),
			'profit-margin-calculator'     => array( 'Profit Margin Calculator', 'business', 540, true, 'USD', false ),
			'discount-calculator'          => array( 'Discount Calculator', 'business', 490, true, 'USD', false ),
			'gst-calculator'               => array( 'GST Calculator (India)', 'business', 570, true, 'INR', false ),
			'tip-calculator'               => array( 'Tip Calculator', 'business', 450, true, 'USD', false ),
			'percentage-calculator'        => array( 'Percentage Calculator', 'education', 460, false, 'USD', false ),
			'scientific-calculator'        => array( 'Scientific Calculator', 'education', 700, false, 'USD', false ),
			'unit-converter'               => array( 'Unit Converter', 'education', 670, false, 'USD', false ),
			'bmi-calculator'               => array( 'BMI Calculator', 'health-and-fitness', 520, false, 'USD', true ),
			'calorie-calculator'           => array( 'Calorie Calculator', 'health-and-fitness', 840, false, 'USD', true ),
			'age-calculator'               => array( 'Age Calculator', 'time-and-date', 470, false, 'USD', false ),
			'time-zone-converter'          => array( 'Time Zone Converter', 'time-and-date', 600, false, 'USD', false ),
			'fuel-cost-calculator'         => array( 'Fuel Cost Calculator', 'travel-and-auto', 690, true, 'USD', true ),
			'car-loan-calculator'          => array( 'Car Loan Calculator', 'travel-and-auto', 880, true, 'USD', false ),
			'qr-code-generator'            => array( 'QR Code Generator', 'generators', 620, false, 'USD', false ),
			'password-generator'           => array( 'Password Generator', 'generators', 660, false, 'USD', false ),
			'word-counter'                 => array( 'Word Counter', 'seo-and-content', 850, false, 'USD', false ),
		);

		$inputs = array(
			'emi-calculator' => array(
				array( 'id' => 'amount', 'label' => 'Loan amount', 'type' => '', 'example' => '2500000' ),
				array( 'id' => 'rate', 'label' => 'Interest rate (% a year)', 'type' => '', 'example' => '8.5' ),
				array( 'id' => 'tenure', 'label' => 'Tenure', 'type' => '', 'example' => '20' ),
			),
		);

		$widgets = array();
		foreach ( $list as $slug => $w ) {
			$widgets[ $slug ] = array(
				'slug'             => $slug,
				'name'             => $w[0],
				'category'         => $w[1],
				'kind'             => '',
				'summary'          => '',
				'height'           => $w[2],
				'supportsTheme'    => true,
				'supportsCurrency' => $w[3],
				'defaultCurrency'  => $w[4],
				'supportsUnits'    => $w[5],
				'supportsCompact'  => true,
				'popular'          => true,
				'isBadge'          => false,
				'inputs'           => isset( $inputs[ $slug ] ) ? $inputs[ $slug ] : array(),
			);
		}

		return array(
			'source'     => 'fallback',
			'fetched'    => 0,
			'widgets'    => $widgets,
			'categories' => array(
				'finance'            => 'Finance',
				'business'           => 'Business & Ecommerce',
				'education'          => 'Education & Math',
				'health-and-fitness' => 'Health & Fitness',
				'time-and-date'      => 'Time & Date',
				'travel-and-auto'    => 'Travel & Auto',
				'generators'         => 'QR & Generators',
				'seo-and-content'    => 'SEO & Content',
			),
		);
	}
}
