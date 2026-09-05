<?php
/**
 * Front-end output: load the hosted Yamidoo widget and (optionally) identify
 * the logged-in WordPress user to it.
 *
 * Page optimizers are the main hazard for a third-party widget. Every
 * delay/defer/combine plugin works by rewriting our <script> tag, and a few
 * rebuild it from `src` alone, dropping the `data-site-id` the widget boots
 * from. So we defend on three fronts:
 *   1. the site id ALSO goes out in an inline stub as `window.yamidooSiteId`,
 *      which survives any tag rewrite (widget.js falls back to it);
 *   2. both tags carry the opt-out attributes the big optimizers honour;
 *   3. we register on the exclusion filters of the ones that expose them.
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

		// Core builds both tags through these since 5.7 (we require 6.4).
		add_filter( 'wp_script_attributes', array( $this, 'script_attributes' ) );
		add_filter( 'wp_inline_script_attributes', array( $this, 'inline_script_attributes' ) );

		// Optimizer exclusion filters. Each only fires if that plugin is active;
		// registering them unconditionally is free.
		// WP Rocket — delay, defer, minify/combine.
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'exclude_patterns' ) );
		add_filter( 'rocket_exclude_defer_js', array( $this, 'exclude_patterns' ) );
		add_filter( 'rocket_exclude_js', array( $this, 'exclude_patterns' ) );
		add_filter( 'rocket_excluded_inline_js', array( $this, 'exclude_inline_patterns' ) );
		// LiteSpeed Cache — defer/delay + minify/combine.
		add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'exclude_patterns' ) );
		add_filter( 'litespeed_optimize_js_excludes', array( $this, 'exclude_patterns' ) );
		// Perfmatters — delay JS.
		add_filter( 'perfmatters_delay_js_exclusions', array( $this, 'exclude_patterns' ) );
		// Autoptimize — comma-separated string, not an array.
		add_filter( 'autoptimize_filter_js_exclude', array( $this, 'exclude_autoptimize' ) );
		// SiteGround Optimizer — matches on script HANDLE.
		add_filter( 'sgo_js_async_exclude', array( $this, 'exclude_handle' ) );
		add_filter( 'sgo_js_minify_exclude', array( $this, 'exclude_handle' ) );
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
	 * Enqueue the hosted widget script and the inline stub.
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
			null, // External, versioned by the service — a ?ver= would only defeat its caching.
			array(
				'strategy'  => 'async',
				'in_footer' => true,
			)
		);

		// Always emitted: the queue stub + site id. Identify is added when enabled.
		// 'before' keeps the async strategy intact ('after' would silently cancel it).
		wp_add_inline_script( self::HANDLE, $this->inline_js( ! empty( $options['identify_logged_in'] ) ), 'before' );
	}

	/**
	 * Build the inline JS: the widget's queue stub (so calls made before
	 * widget.js loads are replayed), the site id fallback, and optionally the
	 * logged-in user's identity (or a reset after logout).
	 *
	 * @param bool $identify Whether to identify the logged-in user.
	 * @return string
	 */
	private function inline_js( $identify ) {
		// Escape for a <script> context so a stray "</script>" in a value can't break out.
		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

		$js  = 'window.yamidoo=window.yamidoo||function(){(window.yamidoo.q=window.yamidoo.q||[]).push(arguments)};';
		$js .= 'window.yamidooSiteId=' . wp_json_encode( $this->site_id, $flags ) . ';';

		if ( ! $identify ) {
			return $js;
		}

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$uid  = (string) $user->ID;
			$data = array(
				// WP stores display names HTML-encoded (e.g. "Rybá&#345;"); decode
				// so the dashboard shows the real characters.
				'name'     => html_entity_decode( $user->display_name, ENT_QUOTES, 'UTF-8' ),
				'email'    => $user->user_email,
				'userId'   => $uid,
				'username' => $user->user_login,
			);

			// Reset identity when the WordPress user changes (User Switching safe).
			$js .= 'try{var u=' . wp_json_encode( $uid, $flags ) . ";if(localStorage.getItem('yamidoo_wp_uid')!==u){window.yamidoo('logout');localStorage.setItem('yamidoo_wp_uid',u);}}catch(e){}";
			$js .= 'window.yamidoo(' . wp_json_encode( 'identify', $flags ) . ',' . wp_json_encode( $data, $flags ) . ');';
			return $js;
		}

		// Logged out: drop any identity left over from a previous login in this browser.
		$js .= "try{if(localStorage.getItem('yamidoo_wp_uid')){window.yamidoo('logout');localStorage.removeItem('yamidoo_wp_uid');}}catch(e){}";
		return $js;
	}

	/**
	 * Attributes that tell optimizers to leave a tag alone. Boolean true renders
	 * as a bare attribute (`nowprocket`), strings as key="value".
	 *
	 * @return array
	 */
	private function optimizer_attributes() {
		return array(
			'nowprocket'       => true,    // WP Rocket: no delay/defer/minify/CDN-rewrite.
			'data-cfasync'     => 'false', // Cloudflare Rocket Loader.
			'data-no-optimize' => '1',     // LiteSpeed Cache.
			'data-noptimize'   => '1',     // Autoptimize.
		);
	}

	/**
	 * Add the site id and optimizer opt-outs to the widget's <script> tag.
	 *
	 * @param array $attributes Tag attributes (core includes 'id' => "{handle}-js").
	 * @return array
	 */
	public function script_attributes( $attributes ) {
		if ( '' === $this->site_id || ! isset( $attributes['id'] ) || self::HANDLE . '-js' !== $attributes['id'] ) {
			return $attributes;
		}
		$attributes['data-site-id'] = $this->site_id;
		return array_merge( $attributes, $this->optimizer_attributes() );
	}

	/**
	 * Same opt-outs on the inline stub, so it runs in place and in order.
	 *
	 * @param array $attributes Tag attributes (core includes 'id' => "{handle}-js-before").
	 * @return array
	 */
	public function inline_script_attributes( $attributes ) {
		if ( '' === $this->site_id || ! isset( $attributes['id'] ) || self::HANDLE . '-js-before' !== $attributes['id'] ) {
			return $attributes;
		}
		return array_merge( $attributes, $this->optimizer_attributes() );
	}

	/**
	 * URL/content patterns for optimizers that take an array of exclusions.
	 * Both tags match: the external one by "widget.js" / its host, the inline
	 * stub by "yamidoo" in its content and id.
	 *
	 * @param array $excluded Existing patterns.
	 * @return array
	 */
	public function exclude_patterns( $excluded ) {
		$excluded   = is_array( $excluded ) ? $excluded : array();
		$excluded[] = 'widget.js';
		$excluded[] = 'yamidoo';
		return $excluded;
	}

	/**
	 * WP Rocket's inline-JS minify/combine exclusion.
	 *
	 * @param array $excluded Excluded inline-JS signatures.
	 * @return array
	 */
	public function exclude_inline_patterns( $excluded ) {
		$excluded   = is_array( $excluded ) ? $excluded : array();
		$excluded[] = 'window.yamidoo';
		return $excluded;
	}

	/**
	 * Autoptimize takes a comma-separated string.
	 *
	 * @param string $excluded Existing exclusions.
	 * @return string
	 */
	public function exclude_autoptimize( $excluded ) {
		$excluded = is_string( $excluded ) ? $excluded : '';
		return ( '' === $excluded ? '' : $excluded . ', ' ) . 'widget.js, yamidoo';
	}

	/**
	 * SiteGround Optimizer matches on script handles.
	 *
	 * @param array $excluded Existing handles.
	 * @return array
	 */
	public function exclude_handle( $excluded ) {
		$excluded   = is_array( $excluded ) ? $excluded : array();
		$excluded[] = self::HANDLE;
		return $excluded;
	}
}
