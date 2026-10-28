<?php
/**
 * Uninstall: remove the plugin's option and transients (every site on a
 * multisite network). Post content keeps its shortcodes/blocks; with the
 * plugin gone they simply stop rendering.
 *
 * @package A2Z_Widgets
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's data for the current site.
 */
function a2z_widgets_uninstall_site() {
	delete_option( 'a2z_widgets_settings' );
	delete_transient( 'a2z_widgets_registry' );
	delete_transient( 'a2z_widgets_registry_failed' );
}

if ( is_multisite() && function_exists( 'get_sites' ) ) {
	$a2z_widgets_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $a2z_widgets_site_ids as $a2z_widgets_site_id ) {
		switch_to_blog( (int) $a2z_widgets_site_id );
		a2z_widgets_uninstall_site();
		restore_current_blog();
	}
} else {
	a2z_widgets_uninstall_site();
}
