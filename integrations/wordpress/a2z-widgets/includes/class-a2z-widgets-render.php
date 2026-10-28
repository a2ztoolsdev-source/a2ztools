<?php
/**
 * The one place that turns validated options into markup. The shortcode and
 * the block's server-side render both call A2Z_Widgets_Render::widget().
 *
 * Output contract:
 * - A plain <iframe> (works with JavaScript disabled). If the site owner
 *   enabled "auto height", the plugin's own assets/js/a2z-widgets-autoheight.js
 *   is enqueued and resizes the plugin's A2Z iframes; nothing else changes.
 *   No script from a2z.tools is ever loaded into the host page.
 * - Every URL is built from A2Z_WIDGETS_ORIGIN + allow-listed values and
 *   passed through esc_url(); every attribute through esc_attr().
 * - No links are added unless the site owner switched on "Powered by".
 *
 * @package A2Z_Widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer.
 */
final class A2Z_Widgets_Render {

	const LOADER_HANDLE = 'a2z-widgets-loader';
	const STYLE_HANDLE  = 'a2z-widgets';

	/**
	 * Normalise raw options (shortcode atts or block attributes) against the
	 * allow-lists. Unknown or invalid values are dropped, never passed on.
	 *
	 * @param array $raw Raw options. Keys: name|slug, theme, currency, units,
	 *                   locale, height, width, compact, title, border, radius,
	 *                   accent, presets (array id => value).
	 * @return array|WP_Error
	 */
	public static function normalise( array $raw ) {
		$settings = A2Z_Widgets_Options::settings();

		$slug = A2Z_Widgets_Options::slug( isset( $raw['name'] ) ? $raw['name'] : ( isset( $raw['slug'] ) ? $raw['slug'] : '' ) );
		if ( '' === $slug ) {
			return new WP_Error( 'a2z_widgets_slug', __( 'A2Z widget: the name must be a widget slug such as "emi-calculator".', 'a2z-widgets' ) );
		}

		$registry = A2Z_Widgets_Registry::get( false );
		$widget   = isset( $registry['widgets'][ $slug ] ) ? $registry['widgets'][ $slug ] : null;

		// With the live registry cached we know the full list; reject unknown
		// slugs instead of rendering a frame that would 404. With the bundled
		// fallback we only know ~20 slugs, so any well-formed slug is allowed.
		if ( null === $widget && 'api' === $registry['source'] ) {
			/* translators: %s: widget slug */
			return new WP_Error( 'a2z_widgets_unknown', sprintf( __( 'A2Z widget: "%s" is not in the A2Z widget registry.', 'a2z-widgets' ), $slug ) );
		}
		if ( null !== $widget && ! empty( $widget['isBadge'] ) ) {
			return new WP_Error( 'a2z_widgets_badge', __( 'A2Z widget: badges are images, use the [a2z_badge] shortcode instead.', 'a2z-widgets' ) );
		}

		$theme = A2Z_Widgets_Options::pick( isset( $raw['theme'] ) ? $raw['theme'] : '', A2Z_Widgets_Options::themes(), $settings['default_theme'] );

		$currency = A2Z_Widgets_Options::currency( isset( $raw['currency'] ) ? $raw['currency'] : '' );
		if ( '' === $currency ) {
			$currency = $settings['default_currency'];
		}
		if ( null !== $widget && empty( $widget['supportsCurrency'] ) ) {
			$currency = '';
		}

		$units = A2Z_Widgets_Options::pick( isset( $raw['units'] ) ? $raw['units'] : '', A2Z_Widgets_Options::units(), $settings['default_units'] );
		if ( null !== $widget && empty( $widget['supportsUnits'] ) ) {
			$units = '';
		}

		// Preset field ids: shortcode attribute names are lowercased by
		// WordPress, so restore the registry's casing when we know it.
		$presets = A2Z_Widgets_Options::presets( isset( $raw['presets'] ) && is_array( $raw['presets'] ) ? $raw['presets'] : array() );
		if ( null !== $widget && ! empty( $widget['inputs'] ) && ! empty( $presets ) ) {
			$by_lower = array();
			foreach ( $widget['inputs'] as $in ) {
				$by_lower[ strtolower( $in['id'] ) ] = $in['id'];
			}
			$fixed = array();
			foreach ( $presets as $id => $value ) {
				$key = strtolower( $id );
				if ( isset( $by_lower[ $key ] ) ) {
					$fixed[ $by_lower[ $key ] ] = $value;
				}
			}
			$presets = $fixed;
		}

		return array(
			'slug'     => $slug,
			'name'     => null !== $widget ? $widget['name'] : ucwords( str_replace( '-', ' ', $slug ) ),
			'theme'    => $theme,
			'currency' => $currency,
			'units'    => $units,
			'locale'   => A2Z_Widgets_Options::locale( isset( $raw['locale'] ) ? $raw['locale'] : '' ),
			'height'   => A2Z_Widgets_Options::height( isset( $raw['height'] ) ? $raw['height'] : '', null !== $widget ? $widget['height'] : A2Z_Widgets_Options::DEFAULT_HEIGHT ),
			'width'    => A2Z_Widgets_Options::width( isset( $raw['width'] ) ? $raw['width'] : '' ),
			'compact'  => A2Z_Widgets_Options::flag( isset( $raw['compact'] ) ? $raw['compact'] : '', false ),
			'title'    => A2Z_Widgets_Options::flag( isset( $raw['title'] ) ? $raw['title'] : '', true ),
			'border'   => A2Z_Widgets_Options::flag( isset( $raw['border'] ) ? $raw['border'] : '', true ),
			'radius'   => A2Z_Widgets_Options::radius( isset( $raw['radius'] ) ? $raw['radius'] : '' ),
			'accent'   => A2Z_Widgets_Options::accent( isset( $raw['accent'] ) ? $raw['accent'] : '' ),
			'presets'  => $presets,
		);
	}

	/**
	 * Frame URL: https://a2z.tools/embed/w/{slug}?theme=..&v_{id}=..
	 *
	 * @param array $o Normalised options.
	 * @return string Unescaped URL (escape at output).
	 */
	public static function frame_url( array $o ) {
		$query = array();
		if ( 'auto' !== $o['theme'] ) {
			$query['theme'] = $o['theme'];
		}
		if ( $o['compact'] ) {
			$query['style'] = 'compact';
		}
		if ( ! $o['title'] ) {
			$query['title'] = '0';
		}
		if ( ! $o['border'] ) {
			$query['border'] = '0';
		}
		if ( null !== $o['radius'] ) {
			$query['radius'] = (string) $o['radius'];
		}
		if ( '' !== $o['accent'] ) {
			$query['accent'] = $o['accent'];
		}
		if ( '' !== $o['currency'] ) {
			$query['currency'] = $o['currency'];
		}
		if ( '' !== $o['units'] ) {
			$query['units'] = $o['units'];
		}
		if ( '' !== $o['locale'] ) {
			$query['locale'] = $o['locale'];
		}
		foreach ( $o['presets'] as $id => $value ) {
			$query[ 'v_' . $id ] = $value;
		}

		$url = A2Z_WIDGETS_ORIGIN . '/embed/w/' . rawurlencode( $o['slug'] );
		if ( ! empty( $query ) ) {
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}
		return $url;
	}

	/**
	 * Widget markup, or '' (with an HTML comment for editors) on invalid input.
	 *
	 * @param array $raw Raw options, see normalise().
	 * @return string
	 */
	public static function widget( array $raw ) {
		$o = self::normalise( $raw );
		if ( is_wp_error( $o ) ) {
			return self::error( $o );
		}

		$settings = A2Z_Widgets_Options::settings();

		$style = 'border:0;width:100%;display:block;height:' . (int) $o['height'] . 'px';
		if ( '' !== $o['width'] ) {
			$style .= ';max-width:' . $o['width'];
		}

		/* translators: %s: widget name, e.g. "EMI Calculator" */
		$title = sprintf( __( '%s by A2Z Tools', 'a2z-widgets' ), $o['name'] );

		$html  = '<div class="a2z-widgets-embed">';
		$html .= '<iframe'
			. ' src="' . esc_url( self::frame_url( $o ) ) . '"'
			. ' title="' . esc_attr( $title ) . '"'
			. ' width="100%"'
			. ' height="' . esc_attr( (string) $o['height'] ) . '"'
			. ' style="' . esc_attr( $style ) . '"'
			. ' loading="lazy"'
			. ' allow="clipboard-write"'
			. ' referrerpolicy="strict-origin-when-cross-origin"'
			. '></iframe>';

		if ( $settings['powered_by'] ) {
			wp_enqueue_style( self::STYLE_HANDLE, A2Z_WIDGETS_URL . 'assets/css/a2z-widgets.css', array(), A2Z_WIDGETS_VERSION );
			$html .= '<p class="a2z-widgets-attribution">'
				. esc_html__( 'Powered by', 'a2z-widgets' ) . ' '
				. '<a href="' . esc_url( A2Z_WIDGETS_ORIGIN . '/embed' ) . '" rel="nofollow noopener" target="_blank">'
				. esc_html__( 'A2Z Tools', 'a2z-widgets' ) . '</a></p>';
		}
		$html .= '</div>';

		if ( $settings['auto_height'] ) {
			self::enqueue_loader();
		}

		return $html;
	}

	/**
	 * Badge markup: <a rel="nofollow noopener" href=report><img src=badge></a>.
	 *
	 * @param array $raw Keys: check, domain, style, theme, size, link.
	 * @return string
	 */
	public static function badge( array $raw ) {
		$check = A2Z_Widgets_Options::pick( isset( $raw['check'] ) ? $raw['check'] : '', A2Z_Widgets_Options::badge_checks(), '' );
		if ( '' === $check ) {
			return self::error( new WP_Error( 'a2z_widgets_check', __( 'A2Z badge: check must be one of ssl, ssl-expiry, https, hsts, headers, spf, dmarc, dnssec, online.', 'a2z-widgets' ) ) );
		}
		$domain = A2Z_Widgets_Options::domain( isset( $raw['domain'] ) ? $raw['domain'] : '' );
		if ( '' === $domain ) {
			return self::error( new WP_Error( 'a2z_widgets_domain', __( 'A2Z badge: domain must be a full domain name such as example.com.', 'a2z-widgets' ) ) );
		}
		$style   = A2Z_Widgets_Options::pick( isset( $raw['style'] ) ? $raw['style'] : '', A2Z_Widgets_Options::badge_styles(), 'flat' );
		$theme   = A2Z_Widgets_Options::pick( isset( $raw['theme'] ) ? $raw['theme'] : '', array( 'light', 'dark' ), 'light' );
		$compact = 'compact' === A2Z_Widgets_Options::pick( isset( $raw['size'] ) ? $raw['size'] : '', array( 'compact', 'normal' ), 'normal' );
		$link    = A2Z_Widgets_Options::flag( isset( $raw['link'] ) ? $raw['link'] : '', true );

		$query = array();
		if ( 'flat' !== $style ) {
			$query['style'] = $style;
		}
		if ( 'dark' === $theme ) {
			$query['theme'] = 'dark';
		}
		if ( $compact ) {
			$query['size'] = 'compact';
		}
		$src = A2Z_WIDGETS_ORIGIN . '/badge/' . rawurlencode( $check ) . '/' . rawurlencode( $domain ) . '.svg';
		if ( ! empty( $query ) ) {
			$src .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}
		$report = A2Z_WIDGETS_ORIGIN . '/report/' . A2Z_Widgets_Options::report_for( $check ) . '/' . rawurlencode( $domain );

		/* translators: 1: check name such as "ssl", 2: domain */
		$alt = sprintf( __( '%1$s status of %2$s, checked by A2Z Tools', 'a2z-widgets' ), strtoupper( $check ), $domain );

		$img = '<img class="a2z-widgets-badge" src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" height="' . ( $compact ? '18' : '20' ) . '" loading="lazy" decoding="async" referrerpolicy="strict-origin-when-cross-origin">';
		if ( ! $link ) {
			return $img;
		}
		return '<a href="' . esc_url( $report ) . '" rel="nofollow noopener" target="_blank">' . $img . '</a>';
	}

	/**
	 * Enqueue the optional auto-height script, bundled with the plugin. Only
	 * called while rendering a widget and only when the site owner enabled
	 * "Auto height" in Settings > A2Z Widgets, so pages without a widget
	 * never load it.
	 */
	public static function enqueue_loader() {
		if ( wp_script_is( self::LOADER_HANDLE, 'enqueued' ) ) {
			return;
		}
		// WP 6.3+ reads the args array (footer + defer); 6.0-6.2 treat the
		// truthy array as $in_footer = true.
		wp_enqueue_script(
			self::LOADER_HANDLE,
			A2Z_WIDGETS_URL . 'assets/js/a2z-widgets-autoheight.js',
			array(),
			A2Z_WIDGETS_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Visitors see nothing for an invalid shortcode; users who can edit posts
	 * see an HTML comment explaining why (visible in page source only).
	 *
	 * @param WP_Error $error Error.
	 * @return string
	 */
	private static function error( WP_Error $error ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		// Block editor preview (ServerSideRender): show the reason visibly.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="a2z-widgets-error">' . esc_html( $error->get_error_message() ) . '</p>';
		}
		return '<!-- ' . esc_html( str_replace( '--', '- -', $error->get_error_message() ) ) . ' -->';
	}
}
