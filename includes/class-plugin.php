<?php
/**
 * Main plugin controller.
 *
 * @package Yamidoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Yamidoo_Plugin
 *
 * Wires up the admin settings, the front-end widget output and the
 * customer data endpoint.
 */
class Yamidoo_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Yamidoo_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin settings handler.
	 *
	 * @var Yamidoo_Settings
	 */
	public $settings;

	/**
	 * Front-end widget handler.
	 *
	 * @var Yamidoo_Frontend
	 */
	public $frontend;

	/**
	 * Customer data connector (REST endpoint for Yamidoo).
	 *
	 * @var Yamidoo_Customer
	 */
	public $customer;

	/**
	 * Get the singleton instance.
	 *
	 * @return Yamidoo_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Register components and hooks.
	 */
	private function init() {
		$this->settings = new Yamidoo_Settings();
		$this->frontend = new Yamidoo_Frontend();
		$this->customer = new Yamidoo_Customer();

		add_filter(
			'plugin_action_links_' . plugin_basename( YAMIDOO_FILE ),
			array( $this, 'action_links' )
		);
		add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );
	}

	/**
	 * Suggested text for the site's privacy policy (Settings → Privacy → Guide).
	 */
	public function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text, adjust to match how you use Yamidoo.', 'yamidoo' ) . '</p>';
		$content .= '<p>' . esc_html__( 'This site uses Yamidoo (yamidoo.ai) to provide a support chat. When you use the chat, your messages, an anonymous session identifier, the page you are on, basic browser information and any files you choose to upload are sent to Yamidoo so that our team and its AI assistant can answer you. If you are logged in, your name and email address may be shared so that we know who we are talking to.', 'yamidoo' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If you have an account or have made purchases on this site, our support team may look up your orders, licenses and subscriptions while helping you, and the AI assistant may use them to answer questions about your own account when you are logged in. This information is retrieved from this site when needed and is not stored by Yamidoo.', 'yamidoo' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Please do not share passwords, payment details or other sensitive information in the chat. Yamidoo processes this data on our behalf under its privacy policy at https://yamidoo.ai/privacy/.', 'yamidoo' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Yamidoo', 'yamidoo' ), $content );
	}

	/**
	 * Add a "Settings" link on the Plugins list row.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=yamidoo' ) ),
			esc_html__( 'Settings', 'yamidoo' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
