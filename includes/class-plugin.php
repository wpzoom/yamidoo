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
 * Wires up the admin settings and the front-end widget output.
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

		add_filter(
			'plugin_action_links_' . plugin_basename( YAMIDOO_FILE ),
			array( $this, 'action_links' )
		);
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
