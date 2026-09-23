<?php
/**
 * Plugin Name:       Yamidoo – AI Support Chat
 * Plugin URI:        https://yamidoo.ai/docs/getting-started/installation/
 * Description:       Connect your website to Yamidoo and add the AI support chat widget. Answers visitors from your own content, knows your Easy Digital Downloads and WooCommerce customers, and hands off to a human when needed.
 * Version:           1.1.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Yamidoo
 * Author URI:        https://yamidoo.ai/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       yamidoo
 *
 * @package Yamidoo
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'YAMIDOO_VERSION', '1.1.2' );
define( 'YAMIDOO_FILE', __FILE__ );
define( 'YAMIDOO_DIR', plugin_dir_path( __FILE__ ) );
define( 'YAMIDOO_URL', plugin_dir_url( __FILE__ ) );

// Single option that stores all plugin settings.
define( 'YAMIDOO_OPTION', 'yamidoo_settings' );

// Base URL of the hosted Yamidoo app that serves widget.js and the widget API.
// Overridable via `define( 'YAMIDOO_APP_URL', 'http://localhost:3000' );`
// in wp-config.php (handy for local development) or the `yamidoo_app_url` filter.
if ( ! defined( 'YAMIDOO_APP_URL' ) ) {
	define( 'YAMIDOO_APP_URL', 'https://app.yamidoo.ai' );
}

/**
 * Resolve the Yamidoo app base URL (no trailing slash).
 *
 * @return string
 */
function yamidoo_app_url() {
	return untrailingslashit( apply_filters( 'yamidoo_app_url', YAMIDOO_APP_URL ) );
}

/**
 * Signature that proves a logged-in user's email came from WordPress, for
 * themes and builders that print the widget themselves instead of letting the
 * plugin do it. Pass it as `signature` in `yamidoo('identify', {...})` and the
 * AI may answer that user's account questions from the customer data
 * connector. Compute it server-side only — the secret must never reach the browser.
 *
 *     yamidoo('identify', { email: ..., signature: <?php echo wp_json_encode( yamidoo_identity_signature( $user->user_email ) ); ?> });
 *
 * @param string $email The logged-in user's email.
 * @return string Hex HMAC-SHA256, or '' when customer data sharing is off.
 */
function yamidoo_identity_signature( $email ) {
	$options = Yamidoo_Settings::get();
	if ( empty( $options['share_customer_data'] ) || '' === (string) $options['lookup_secret'] ) {
		return '';
	}
	return hash_hmac( 'sha256', strtolower( trim( (string) $email ) ), (string) $options['lookup_secret'] );
}

require_once YAMIDOO_DIR . 'includes/class-secrets.php';
require_once YAMIDOO_DIR . 'includes/class-settings.php';
require_once YAMIDOO_DIR . 'includes/class-frontend.php';
require_once YAMIDOO_DIR . 'includes/class-customer.php';
require_once YAMIDOO_DIR . 'includes/class-plugin.php';

/**
 * Main plugin instance.
 *
 * @return Yamidoo_Plugin
 */
function yamidoo() {
	return Yamidoo_Plugin::instance();
}
add_action( 'plugins_loaded', 'yamidoo' );

/**
 * Seed default settings on activation.
 */
register_activation_hook( __FILE__, 'yamidoo_activate' );
function yamidoo_activate() {
	if ( false === get_option( YAMIDOO_OPTION ) ) {
		add_option( YAMIDOO_OPTION, Yamidoo_Settings::defaults() );
	}
}
