<?php
/**
 * Shortcodes:
 *   [a2z_widget name="emi-calculator" theme="auto" currency="INR" height="560"
 *               width="480" compact="1" title="0" border="0" preset_amount="2500000"]
 *   [a2z_badge check="ssl" domain="example.com" style="flat"]
 *
 * @package A2Z_Widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode handlers.
 */
final class A2Z_Widgets_Shortcodes {

	/**
	 * Register shortcodes.
	 */
	public static function init() {
		add_shortcode( 'a2z_widget', array( __CLASS__, 'widget' ) );
		add_shortcode( 'a2z_badge', array( __CLASS__, 'badge' ) );
	}

	/**
	 * [a2z_widget] handler.
	 *
	 * @param array|string $atts Shortcode attributes ('' when none).
	 * @return string
	 */
	public static function widget( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();

		// preset_{field}="value" -> v_{field}=value. Collected before
		// shortcode_atts(), which drops keys it does not know.
		$presets = array();
		foreach ( $atts as $key => $value ) {
			if ( is_string( $key ) && 0 === strpos( $key, 'preset_' ) ) {
				$presets[ substr( $key, 7 ) ] = $value;
			}
		}

		$atts = shortcode_atts(
			array(
				'name'     => '',
				'theme'    => '',
				'currency' => '',
				'units'    => '',
				'locale'   => '',
				'height'   => '',
				'width'    => '',
				'compact'  => '',
				'title'    => '',
				'border'   => '',
				'radius'   => '',
				'accent'   => '',
			),
			$atts,
			'a2z_widget'
		);
		$atts['presets'] = $presets;

		return A2Z_Widgets_Render::widget( $atts );
	}

	/**
	 * [a2z_badge] handler.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function badge( $atts ) {
		$atts = shortcode_atts(
			array(
				'check'  => '',
				'domain' => '',
				'style'  => 'flat',
				'theme'  => 'light',
				'size'   => 'normal',
				'link'   => '1',
			),
			is_array( $atts ) ? $atts : array(),
			'a2z_badge'
		);
		return A2Z_Widgets_Render::badge( $atts );
	}
}
