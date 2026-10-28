<?php
/**
 * Settings > A2Z Widgets. Settings API (nonce via settings_fields()),
 * manage_options only. The "Refresh widget list" button posts to
 * admin-post.php with its own nonce and capability check.
 *
 * @package A2Z_Widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen.
 */
final class A2Z_Widgets_Settings {

	const PAGE   = 'a2z-widgets';
	const GROUP  = 'a2z_widgets';
	const ACTION = 'a2z_widgets_refresh';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'refresh' ) );
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_options_page(
			__( 'A2Z Widgets', 'a2z-widgets' ),
			__( 'A2Z Widgets', 'a2z-widgets' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Register the single option.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			A2Z_Widgets_Options::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'A2Z_Widgets_Options', 'sanitize_settings' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Suggested text for Settings > Privacy (the site owner decides whether to use it).
	 */
	public static function privacy_policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text if you embed A2Z Tools widgets or badges:', 'a2z-widgets' ) . '</p>';
		$text .= '<p>' . esc_html__( 'Some pages on this site embed calculators, tools or status badges from A2Z Tools (a2z.tools). When you view such a page, your browser connects to a2z.tools to load the widget, which means a2z.tools receives your IP address, browser user agent and the address of this website (as the referrer, without the page path). Values you type into a calculator are processed in your browser. Checker widgets and badges send the domain being checked to a2z.tools. See the A2Z Tools privacy policy at https://a2z.tools/privacy.', 'a2z-widgets' ) . '</p>';
		wp_add_privacy_policy_content( __( 'A2Z Widgets', 'a2z-widgets' ), wp_kses_post( $text ) );
	}

	/**
	 * "Refresh widget list" handler.
	 */
	public static function refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'a2z-widgets' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );
		A2Z_Widgets_Registry::flush();
		$registry = A2Z_Widgets_Registry::get( true );
		$status   = 'api' === $registry['source'] ? 'ok' : 'offline';
		wp_safe_redirect( add_query_arg( 'a2z-refresh', $status, admin_url( 'options-general.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * The settings page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = A2Z_Widgets_Options::settings();
		$registry = A2Z_Widgets_Registry::get( true );
		$name     = A2Z_Widgets_Options::OPTION_NAME;

		// Read-only display flag set by our own redirect; no state changes.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$refresh = isset( $_GET['a2z-refresh'] ) ? sanitize_key( wp_unslash( $_GET['a2z-refresh'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'A2Z Widgets', 'a2z-widgets' ); ?></h1>

			<?php if ( 'ok' === $refresh ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Widget list refreshed.', 'a2z-widgets' ); ?></p></div>
			<?php elseif ( 'offline' === $refresh ) : ?>
				<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'a2z.tools could not be reached. The bundled list of popular widgets is used until the next successful refresh; embedded widgets keep working.', 'a2z-widgets' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'Defaults', 'a2z-widgets' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="a2z-default-theme"><?php esc_html_e( 'Theme', 'a2z-widgets' ); ?></label></th>
						<td>
							<select id="a2z-default-theme" name="<?php echo esc_attr( $name ); ?>[default_theme]">
								<?php
								$themes = array(
									'auto'  => __( 'Auto (follows the visitor\'s light/dark preference)', 'a2z-widgets' ),
									'light' => __( 'Light', 'a2z-widgets' ),
									'dark'  => __( 'Dark', 'a2z-widgets' ),
								);
								foreach ( $themes as $value => $label ) {
									printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $settings['default_theme'], $value, false ), esc_html( $label ) );
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="a2z-default-currency"><?php esc_html_e( 'Currency', 'a2z-widgets' ); ?></label></th>
						<td>
							<select id="a2z-default-currency" name="<?php echo esc_attr( $name ); ?>[default_currency]">
								<option value=""><?php esc_html_e( 'Each widget\'s own default', 'a2z-widgets' ); ?></option>
								<?php
								foreach ( A2Z_Widgets_Options::currencies() as $code ) {
									printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $code ), selected( $settings['default_currency'], $code, false ) );
								}
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Used by money widgets when the shortcode or block does not set a currency.', 'a2z-widgets' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="a2z-default-units"><?php esc_html_e( 'Units', 'a2z-widgets' ); ?></label></th>
						<td>
							<select id="a2z-default-units" name="<?php echo esc_attr( $name ); ?>[default_units]">
								<option value="" <?php selected( $settings['default_units'], '' ); ?>><?php esc_html_e( 'Each widget\'s own default', 'a2z-widgets' ); ?></option>
								<option value="metric" <?php selected( $settings['default_units'], 'metric' ); ?>><?php esc_html_e( 'Metric', 'a2z-widgets' ); ?></option>
								<option value="imperial" <?php selected( $settings['default_units'], 'imperial' ); ?>><?php esc_html_e( 'Imperial', 'a2z-widgets' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Auto height', 'a2z-widgets' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Resize widgets automatically', 'a2z-widgets' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[auto_height]" value="1" <?php checked( $settings['auto_height'] ); ?>>
								<?php esc_html_e( 'Let each widget grow or shrink to fit its content', 'a2z-widgets' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default. When on, pages that contain a widget also load a small script bundled with this plugin (under 3 KB) that listens for height messages from the A2Z iframes on the page and resizes them. It sets no cookies and sends nothing about your page to a2z.tools. When off, the plugin adds no JavaScript to your pages; widgets use the fixed height you set.', 'a2z-widgets' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Attribution', 'a2z-widgets' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( '"Powered by A2Z Tools" line', 'a2z-widgets' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[powered_by]" value="1" <?php checked( $settings['powered_by'] ); ?>>
								<?php esc_html_e( 'Show a visible "Powered by A2Z Tools" line under each widget', 'a2z-widgets' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default and entirely optional. When on, a short visible line is printed under each widget on your page, linking to https://a2z.tools/embed with rel="nofollow noopener". When off, your pages contain no link to A2Z Tools; the only credit is the small link the widget already shows inside its own frame. This plugin never adds hidden links.', 'a2z-widgets' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Widget list', 'a2z-widgets' ); ?></h2>
			<p>
				<?php
				if ( 'api' === $registry['source'] ) {
					printf(
						/* translators: 1: number of widgets, 2: date and time */
						esc_html__( '%1$d widgets loaded from a2z.tools on %2$s. The list is cached for 12 hours.', 'a2z-widgets' ),
						(int) count( $registry['widgets'] ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $registry['fetched'] ) )
					);
				} else {
					printf(
						/* translators: %d: number of widgets in the bundled list */
						esc_html__( 'a2z.tools could not be reached, so the bundled list of %d popular widgets is used. Shortcodes with any valid widget name still work.', 'a2z-widgets' ),
						(int) count( $registry['widgets'] )
					);
				}
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<?php submit_button( __( 'Refresh widget list', 'a2z-widgets' ), 'secondary', 'submit', false ); ?>
			</form>
			<p><a href="<?php echo esc_url( A2Z_WIDGETS_ORIGIN . '/embed' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Browse all widgets on a2z.tools', 'a2z-widgets' ); ?></a></p>

			<h2><?php esc_html_e( 'Privacy', 'a2z-widgets' ); ?></h2>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'Widgets and badges are loaded by your visitors\' browsers directly from a2z.tools, so a2z.tools receives the visitor\'s IP address, user agent and your site\'s address (the referrer is limited to the site origin, without the page path), like any embedded content.', 'a2z-widgets' ); ?></li>
				<li><?php esc_html_e( 'Calculators run in the visitor\'s browser. Checker widgets and badges send the checked domain to a2z.tools, which runs the check on its server.', 'a2z-widgets' ); ?></li>
				<li><?php esc_html_e( 'The widget frames set no cookies and show no ads. A2Z counts widget loads and first use in aggregate (per widget, per site, per country; no IP addresses or identifiers are stored).', 'a2z-widgets' ); ?></li>
				<li><?php esc_html_e( 'This plugin sets no cookies and sends no telemetry. Your server only contacts a2z.tools to download the public widget list (when you open this screen or the block editor, at most every 12 hours).', 'a2z-widgets' ); ?></li>
				<li><?php esc_html_e( 'Suggested privacy policy text is available under Settings > Privacy.', 'a2z-widgets' ); ?></li>
			</ul>

			<h2><?php esc_html_e( 'Shortcodes', 'a2z-widgets' ); ?></h2>
			<p><code>[a2z_widget name="emi-calculator" theme="auto" currency="INR" height="560" width="480" compact="1" title="0" border="0" preset_amount="2500000"]</code></p>
			<p><code>[a2z_badge check="ssl" domain="example.com" style="flat"]</code></p>
			<p class="description"><?php esc_html_e( 'Badge checks: ssl, ssl-expiry, https, hsts, headers, spf, dmarc, dnssec, online. Styles: flat, rounded, square. The block editor has an "A2Z Widget" block with the same options.', 'a2z-widgets' ); ?></p>
		</div>
		<?php
	}
}
