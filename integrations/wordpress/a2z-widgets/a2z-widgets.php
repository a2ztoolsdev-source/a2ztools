<?php
/**
 * Plugin Name:       A2Z Widgets
 * Plugin URI:        https://a2z.tools/embed
 * Description:       Embed free A2Z Tools calculators, converters, checkers and website badges with a shortcode or a block. Widgets load in an iframe from a2z.tools; the plugin sets no cookies and sends no telemetry.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Reliable Software Technology
 * Author URI:        https://a2z.tools
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       a2z-widgets
 *
 * @package A2Z_Widgets
 */

/*
 * A2Z Widgets is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 2 of the License, or (at your option)
 * any later version.
 *
 * A2Z Widgets is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'A2Z_WIDGETS_VERSION', '1.0.0' );
define( 'A2Z_WIDGETS_FILE', __FILE__ );
define( 'A2Z_WIDGETS_DIR', plugin_dir_path( __FILE__ ) );
define( 'A2Z_WIDGETS_URL', plugin_dir_url( __FILE__ ) );

/*
 * The only remote origin this plugin talks to or points visitors at.
 * It is a constant (not a setting or a filter) on purpose: the security
 * review assumes every iframe, image and link URL starts with it.
 */
define( 'A2Z_WIDGETS_ORIGIN', 'https://a2z.tools' );

require_once A2Z_WIDGETS_DIR . 'includes/class-a2z-widgets-options.php';
require_once A2Z_WIDGETS_DIR . 'includes/class-a2z-widgets-registry.php';
require_once A2Z_WIDGETS_DIR . 'includes/class-a2z-widgets-render.php';
require_once A2Z_WIDGETS_DIR . 'includes/class-a2z-widgets-shortcodes.php';
require_once A2Z_WIDGETS_DIR . 'includes/class-a2z-widgets-block.php';
require_once A2Z_WIDGETS_DIR . 'includes/class-a2z-widgets-settings.php';

/**
 * Wire up the plugin. Translations are loaded by WordPress itself from the
 * WordPress.org language packs (text domain = plugin slug).
 */
function a2z_widgets_init() {
	A2Z_Widgets_Shortcodes::init();
	A2Z_Widgets_Block::init();
	A2Z_Widgets_Settings::init();
}
add_action( 'plugins_loaded', 'a2z_widgets_init' );

/**
 * "Settings" link on the Plugins screen.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function a2z_widgets_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=a2z-widgets' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'a2z-widgets' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'a2z_widgets_action_links' );
