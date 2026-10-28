=== A2Z Widgets ===
Contributors: a2ztools
Tags: calculator, widget, embed, mortgage calculator, badge
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add free calculators, converters, checkers and website badges from A2Z Tools to any post or page with a block or a shortcode.

== Description ==

A2Z Tools publishes a library of free, ad-free embeddable widgets: finance calculators (EMI, mortgage, SIP, loan, compound interest), business calculators (GST, profit margin, discount), health, time and date, travel, education, design and developer tools, plus live website badges (SSL, security headers, SPF, DMARC, DNSSEC, uptime).

This plugin lets you place them without touching HTML:

* **A2Z Widget block** - pick a widget from the searchable list, choose theme, currency, units, size and starting values, and see a live preview in the editor.
* **[a2z_widget] shortcode** - for the classic editor, widget areas and page builders.
* **[a2z_badge] shortcode** - a status badge image for any domain, linking to its public report.

Every widget is an iframe loaded from a2z.tools. Nothing is copied into your database except your settings and a cached copy of the public widget list.

**Privacy-friendly defaults**

* The plugin sets no cookies and sends no telemetry.
* The plugin never loads JavaScript from a2z.tools into your pages. The optional "auto height" feature uses a small script bundled with the plugin.
* No links are added to your pages unless you switch on the optional "Powered by A2Z Tools" line (nofollow). Never hidden links.
* Pages without a widget or badge are untouched: no scripts, styles or requests are added to them.

= Shortcode examples =

`[a2z_widget name="emi-calculator" theme="auto" currency="INR" height="560" width="480" compact="1" title="0" border="0" preset_amount="2500000"]`

`[a2z_badge check="ssl" domain="example.com" style="flat"]`

Widget options: `name` (required, the widget slug), `theme` (auto, light, dark), `currency` (ISO code such as USD, INR, EUR), `units` (metric, imperial), `locale` (such as en-GB), `height` (80-5000 px), `width` (maximum width such as 480 or 80%), `compact`, `title`, `border` (1 or 0), `radius` (0-24), `accent` (hex colour), and `preset_{field}` to pre-fill a field.

Badge options: `check` (ssl, ssl-expiry, https, hsts, headers, spf, dmarc, dnssec, online), `domain`, `style` (flat, rounded, square), `theme` (light, dark), `size` (normal, compact), `link` (1 or 0).

The full widget list, with field names for each widget, is at https://a2z.tools/embed.

== External services ==

This plugin connects to A2Z Tools (https://a2z.tools), a service operated by Reliable Software Technology. The service is required: the widgets and badges are hosted there and cannot work without it.

* **Widget list** - your server requests `https://a2z.tools/api/widgets` (public JSON) when an administrator opens Settings > A2Z Widgets or a user opens the block editor, at most once every 12 hours, and when an administrator clicks "Refresh widget list". The request sends no personal data and no site identifier beyond what any HTTP request carries (your server's IP address and a "A2Z-Widgets-WordPress/{version}" user agent). If the service cannot be reached a bundled list of popular widgets is used.
* **Widget frames** - when a visitor views a page with a widget, the visitor's browser loads `https://a2z.tools/embed/w/{widget}`. A2Z Tools receives the visitor's IP address, user agent and your site's address (the referrer is limited to the site origin, without the page path), as with any embedded content. Calculator inputs are processed in the visitor's browser. Checker widgets send the domain the visitor enters to a2z.tools. A2Z Tools records aggregate counts (widget, embedding site, country) without storing IP addresses or identifiers. The frames set no cookies and show no ads.
* **Badges** - when a visitor views a page with a badge, the visitor's browser loads `https://a2z.tools/badge/{check}/{domain}.svg` and a2z.tools checks that domain from its server. With `link="1"` (default) the badge links to the public report at `https://a2z.tools/report/...`.

Nothing is sent to a2z.tools from pages that do not contain a widget or a badge.

* A2Z Tools terms of use: https://a2z.tools/terms
* A2Z Tools privacy policy: https://a2z.tools/privacy

== Installation ==

1. Install the plugin from Plugins > Add New, or upload the `a2z-widgets` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Add the "A2Z Widget" block to a post, or use the shortcode.
4. Optional: review Settings > A2Z Widgets (default theme, currency, units, auto height, attribution).

== Frequently Asked Questions ==

= Is it free? =

Yes. The plugin and the widgets are free, with no paid version, no account and no ads in the widgets.

= Does the plugin slow my site down? =

Widgets are iframes with `loading="lazy"`, so they load only when scrolled near. Without auto height the plugin adds no script to your pages, and pages without a widget are not changed at all.

= Why is my widget cut off or too tall? =

Set `height` on the shortcode or block, or turn on auto height in Settings > A2Z Widgets.

= What happens if a2z.tools is down? =

Your pages still load; the widget area stays empty until the service is back. The editor falls back to a bundled list of popular widgets.

= Does the plugin add links to my site? =

Not unless you turn on the visible "Powered by A2Z Tools" line, which is off by default and uses rel="nofollow noopener". Badges link to their public report (turn that off with `link="0"`); that link is also nofollow. The plugin never adds hidden links.

= What does uninstalling remove? =

Deleting the plugin removes its settings and the cached widget list from every site of the network. Shortcodes and blocks stay in your posts and simply stop rendering.

== Screenshots ==

1. The A2Z Widget block with the widget picker and settings sidebar.
2. An EMI calculator embedded in a post (light and dark theme).
3. Settings > A2Z Widgets.
4. SSL and security header badges in a site footer.

== Changelog ==

= 1.0.0 =
* Auto height now uses a small script bundled with the plugin instead of loading https://a2z.tools/embed.js, so no remote JavaScript is ever added to your pages.
* Badges send only your site's origin as the referrer, like widget frames.
* Block uses block API version 3 (iframed editor in WordPress 7.0+).
* Translations are loaded from WordPress.org language packs.
* Tested with WordPress 7.1.

= 0.1.0 =
* Initial release: A2Z Widget block, [a2z_widget] and [a2z_badge] shortcodes, settings screen, cached widget list with offline fallback.

== Upgrade Notice ==

= 1.0.0 =
First public release. Auto height no longer loads a remote script.
