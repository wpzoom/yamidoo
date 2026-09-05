<?php
/**
 * Front-end output: load the hosted Yamidoo widget and (optionally) identify
 * the logged-in WordPress user to it.
 *
 * @package Yamidoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Yamidoo_Frontend
 */
class Yamidoo_Frontend {

	/**
	 * Script handle for the hosted widget.
	 */
	const HANDLE = 'yamidoo-widget';

	/**
	 * Sanitized Site ID for the current request (empty when not connected).
	 *
	 * @var string
	 */
	private $site_id = '';

	/**
	 * Hook front-end output.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 10, 3 );

		// Keep page optimizers from breaking the widget (only fire if WP Rocket is active).
		add_filter( 'rocket_excluded_inline_js', array( $this, 'rocket_exclude_inline' ) );
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'rocket_exclude_delay' ) );
	}

	/**
	 * Whether the widget should load on the current request.
	 *
	 * @return bool
	 */
	private function should_load() {
		$options = Yamidoo_Settings::get();
		if ( empty( $options['enabled'] ) ) {
			return false;
		}
		if ( '' === $options['site_id'] || ! Yamidoo_Settings::is_uuid( $options['site_id'] ) ) {
			return false;
		}
		/**
		 * Filter whether to output the Yamidoo widget on this request.
		 *
		 * @param bool $load Whether to load the widget.
		 */
		return (bool) apply_filters( 'yamidoo_should_load', true );
	}

	/**
	 * Enqueue the hosted widget script and the identify snippet.
	 */
	public function enqueue() {
		if ( ! $this->should_load() ) {
			return;
		}

		$options       = Yamidoo_Settings::get();
		$this->site_id = $options['site_id'];

		wp_enqueue_script(
			self::HANDLE,
			yamidoo_app_url() . '/widget.js',
			array(),
			null,
			array(
				'strategy'  => 'async',
				'in_footer' => true,
			)
		);

		if ( ! empty( $options['identify_logged_in'] ) ) {
			$inline = $this->identify_js();
			if ( '' !== $inline ) {
				wp_add_inline_script( self::HANDLE, $inline, 'before' );
			}
		}
	}

	/**
	 * Build the inline JS that identifies the logged-in user (or clears a stale
	 * identity after logout). Uses the widget's queue-stub so calls made before
	 * widget.js finishes loading are replayed.
	 *
	 * @return string
	 */
	private function identify_js() {
		// Escape for a <script> context so a stray "</script>" in a value can't break out.
		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
		$stub  = 'window.yamidoo=window.yamidoo||function(){(window.yamidoo.q=window.yamidoo.q||[]).push(arguments)};';

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$uid  = (string) $user->ID;
			$data = array(
				'name'     => $user->display_name,
				'email'    => $user->user_email,
				'userId'   => $uid,
				'username' => $user->user_login,
			);

			$js  = $stub;
			// Reset identity when the WordPress user changes (User Switching safe).
			$js .= 'try{var u=' . wp_json_encode( $uid, $flags ) . ";if(localStorage.getItem('yamidoo_wp_uid')!==u){window.yamidoo('logout');localStorage.setItem('yamidoo_wp_uid',u);}}catch(e){}";
			$js .= 'window.yamidoo(' . wp_json_encode( 'identify', $flags ) . ',' . wp_json_encode( $data, $flags ) . ');';
			return $js;
		}

		// Logged out: drop any identity left over from a previous login in this browser.
		$js  = $stub;
		$js .= "try{if(localStorage.getItem('yamidoo_wp_uid')){window.yamidoo('logout');localStorage.removeItem('yamidoo_wp_uid');}}catch(e){}";
		return $js;
	}

	/**
	 * Add the widget's data attributes and optimizer-safe hints to its script tag.
	 *
	 * @param string $tag    The full <script> tag markup.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 * @return string
	 */
	public function filter_script_tag( $tag, $handle, $src ) {
		if ( self::HANDLE !== $handle || '' === $this->site_id ) {
			return $tag;
		}

		$attrs = sprintf(
			' data-site-id="%s" data-cfasync="false" nowprocket',
			esc_attr( $this->site_id )
		);

		return str_replace( ' src=', $attrs . ' src=', $tag );
	}

	/**
	 * Tell WP Rocket not to combine/optimize our inline identify script.
	 *
	 * @param array $excluded Excluded inline-JS signatures.
	 * @return array
	 */
	public function rocket_exclude_inline( $excluded ) {
		$excluded[] = 'window.yamidoo';
		return $excluded;
	}

	/**
	 * Tell WP Rocket not to delay our widget/identify JS.
	 *
	 * @param array $excluded Excluded JS patterns.
	 * @return array
	 */
	public function rocket_exclude_delay( $excluded ) {
		$excluded[] = 'widget.js';
		$excluded[] = 'yamidoo';
		return $excluded;
	}
}
