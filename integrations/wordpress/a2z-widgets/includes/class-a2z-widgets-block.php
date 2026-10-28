<?php
/**
 * "A2Z Widget" block. block.json + plain-JS editor script (no build step);
 * rendered on the server by the same function as the shortcode.
 *
 * @package A2Z_Widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block registration.
 */
final class A2Z_Widgets_Block {

	const EDITOR_HANDLE = 'a2z-widgets-block-editor';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/**
	 * Register the editor script, then the block from block.json.
	 */
	public static function register() {
		wp_register_script(
			self::EDITOR_HANDLE,
			A2Z_WIDGETS_URL . 'blocks/widget/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' ),
			A2Z_WIDGETS_VERSION,
			true
		);
		wp_set_script_translations( self::EDITOR_HANDLE, 'a2z-widgets' );

		register_block_type(
			A2Z_WIDGETS_DIR . 'blocks/widget',
			array(
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Hand the widget list and allow-lists to the editor script. Runs only in
	 * the block editor, for users who can edit posts; this is the one place a
	 * registry fetch may happen outside the settings screen.
	 */
	public static function editor_data() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$registry = A2Z_Widgets_Registry::get( true );
		$settings = A2Z_Widgets_Options::settings();

		$widgets = array();
		foreach ( $registry['widgets'] as $w ) {
			if ( ! empty( $w['isBadge'] ) ) {
				continue;
			}
			$widgets[] = array(
				'slug'             => $w['slug'],
				'name'             => $w['name'],
				'category'         => $w['category'],
				'height'           => $w['height'],
				'supportsCurrency' => $w['supportsCurrency'],
				'defaultCurrency'  => $w['defaultCurrency'],
				'supportsUnits'    => $w['supportsUnits'],
				'popular'          => $w['popular'],
				'inputs'           => $w['inputs'],
			);
		}
		usort(
			$widgets,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		$data = array(
			'source'          => $registry['source'],
			'widgets'         => $widgets,
			'categories'      => $registry['categories'],
			'currencies'      => A2Z_Widgets_Options::currencies(),
			'defaultTheme'    => $settings['default_theme'],
			'defaultCurrency' => $settings['default_currency'],
			'defaultUnits'    => $settings['default_units'],
			'settingsUrl'     => current_user_can( 'manage_options' ) ? admin_url( 'options-general.php?page=a2z-widgets' ) : '',
		);

		wp_add_inline_script( self::EDITOR_HANDLE, 'window.a2zWidgetsData = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/**
	 * Server-side render.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$a = is_array( $attributes ) ? $attributes : array();

		$html = A2Z_Widgets_Render::widget(
			array(
				'name'     => isset( $a['slug'] ) ? $a['slug'] : '',
				'theme'    => isset( $a['theme'] ) ? $a['theme'] : '',
				'currency' => isset( $a['currency'] ) ? $a['currency'] : '',
				'units'    => isset( $a['units'] ) ? $a['units'] : '',
				'locale'   => isset( $a['locale'] ) ? $a['locale'] : '',
				'height'   => isset( $a['height'] ) ? (string) $a['height'] : '',
				'width'    => isset( $a['width'] ) ? $a['width'] : '',
				'compact'  => ! empty( $a['compact'] ),
				'title'    => isset( $a['showTitle'] ) ? (bool) $a['showTitle'] : true,
				'border'   => isset( $a['border'] ) ? (bool) $a['border'] : true,
				'radius'   => isset( $a['radius'] ) && is_numeric( $a['radius'] ) && $a['radius'] >= 0 ? (string) (int) $a['radius'] : '',
				'accent'   => isset( $a['accent'] ) ? $a['accent'] : '',
				'presets'  => isset( $a['presets'] ) && is_array( $a['presets'] ) ? $a['presets'] : array(),
			)
		);

		if ( '' === $html ) {
			return '';
		}
		return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}
}
