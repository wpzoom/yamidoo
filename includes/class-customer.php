<?php
/**
 * Customer data connector.
 *
 * Yamidoo calls POST /wp-json/yamidoo/v1/customer with {email, ts}, signed
 * with the lookup secret you generate in the Yamidoo dashboard and paste into
 * Settings → Yamidoo (X-Yamidoo-Signature: sha256=HMAC_SHA256(secret, "ts.email")).
 * We answer with a small JSON "card" — what this store knows about that email —
 * that the Yamidoo inbox shows next to the conversation, and that the AI uses
 * to answer a logged-in customer's questions about their own orders, licenses
 * and subscriptions.
 *
 * Adapters: Easy Digital Downloads (+ Software Licensing, Recurring) and
 * WooCommerce (+ Subscriptions). Each returns sections; empty ones are dropped.
 * Only ever called with a valid signature, and only for one email at a time.
 * Nothing is pushed to Yamidoo: it fetches on demand and does not store the card.
 *
 * @package Yamidoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Yamidoo_Customer
 */
class Yamidoo_Customer {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'yamidoo/v1';

	/**
	 * Max rows per section.
	 */
	const MAX_ITEMS = 8;

	/**
	 * Max clock skew accepted on a signed request, in seconds.
	 */
	const MAX_SKEW_S = 300;

	/**
	 * Hook the REST route.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register POST /yamidoo/v1/customer.
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/customer',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);
	}

	/**
	 * The endpoint URL Yamidoo should be pointed at (shown on the settings screen).
	 *
	 * @return string
	 */
	public static function endpoint_url() {
		return rest_url( self::REST_NAMESPACE . '/customer' );
	}

	/**
	 * Which supported stores are active on this site.
	 *
	 * @return string[] Human names, e.g. array( 'Easy Digital Downloads' ).
	 */
	public static function detected_stores() {
		$stores = array();
		if ( function_exists( 'edd_get_customer_by' ) ) {
			$stores[] = 'Easy Digital Downloads';
		}
		if ( function_exists( 'wc_get_orders' ) ) {
			$stores[] = 'WooCommerce';
		}
		return $stores;
	}

	/**
	 * Whether sharing is switched on and a secret is set.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$options = Yamidoo_Settings::get();
		return ! empty( $options['share_customer_data'] ) && '' !== (string) $options['lookup_secret'];
	}

	// -------------------------------------------------------------------------
	// Auth
	// -------------------------------------------------------------------------

	/**
	 * HMAC over "ts.email" with the lookup secret; timestamp within 5 minutes.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	public function verify_request( WP_REST_Request $request ) {
		$options = Yamidoo_Settings::get();
		$secret  = (string) $options['lookup_secret'];
		if ( '' === $secret || empty( $options['share_customer_data'] ) ) {
			return new WP_Error( 'yamidoo_disabled', 'Customer data sharing is off.', array( 'status' => 403 ) );
		}
		$ts    = (int) $request->get_header( 'x-yamidoo-timestamp' );
		$sig   = (string) $request->get_header( 'x-yamidoo-signature' );
		$body  = $request->get_json_params();
		$email = isset( $body['email'] ) ? strtolower( trim( (string) $body['email'] ) ) : '';
		if ( ! $ts || '' === $sig || ! is_email( $email ) ) {
			return new WP_Error( 'yamidoo_bad_request', 'Missing signature, timestamp or email.', array( 'status' => 400 ) );
		}
		if ( abs( time() - $ts ) > self::MAX_SKEW_S ) {
			return new WP_Error( 'yamidoo_stale', 'Request timestamp out of range.', array( 'status' => 401 ) );
		}
		$expected = 'sha256=' . hash_hmac( 'sha256', $ts . '.' . $email, $secret );
		if ( ! hash_equals( $expected, $sig ) ) {
			return new WP_Error( 'yamidoo_bad_signature', 'Invalid signature.', array( 'status' => 401 ) );
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Card
	// -------------------------------------------------------------------------

	/**
	 * Build the card for the requested email.
	 *
	 * @param WP_REST_Request $request Incoming (already verified) request.
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$body  = $request->get_json_params();
		$email = strtolower( trim( (string) $body['email'] ) );

		$sections = array();
		$source   = array();
		$url      = '';

		if ( function_exists( 'edd_get_customer_by' ) ) {
			$edd = $this->edd_card( $email );
			if ( $edd ) {
				$sections = array_merge( $sections, $edd['sections'] );
				$source[] = 'Easy Digital Downloads';
				$url      = $url ? $url : $edd['url'];
			}
		}
		if ( function_exists( 'wc_get_orders' ) ) {
			$woo = $this->woo_card( $email );
			if ( $woo ) {
				$sections = array_merge( $sections, $woo['sections'] );
				$source[] = 'WooCommerce';
				$url      = $url ? $url : $woo['url'];
			}
		}

		/**
		 * Filter the customer card before it is sent to Yamidoo. Add sections for
		 * other plugins (memberships, Freemius, a CRM…). Each section is
		 * array( 'title' => …, 'items' => array( array( 'value' => …, 'label' => …, 'badge' => …, 'tone' => …, 'meta' => …, 'url' => … ) ) ).
		 *
		 * @param array  $sections Sections so far.
		 * @param string $email    The customer email being looked up.
		 */
		$sections = apply_filters( 'yamidoo_customer_sections', $sections, $email );

		$card = array( 'sections' => array_values( array_filter( $sections, array( $this, 'section_has_items' ) ) ) );
		if ( $source ) {
			$card['source'] = implode( ' + ', $source );
		}
		if ( $url ) {
			$card['url'] = $url;
		}
		return rest_ensure_response( $card );
	}

	/**
	 * Keep only sections with rows.
	 *
	 * @param array $section Section.
	 * @return bool
	 */
	public function section_has_items( $section ) {
		return ! empty( $section['items'] );
	}

	/**
	 * Build one card row.
	 *
	 * @param string $value Main text.
	 * @param array  $args  Optional label/badge/tone/meta/url.
	 * @return array
	 */
	private function item( $value, $args = array() ) {
		$item = array( 'value' => (string) $value );
		foreach ( array( 'label', 'badge', 'tone', 'meta', 'url' ) as $k ) {
			if ( ! empty( $args[ $k ] ) ) {
				$item[ $k ] = (string) $args[ $k ];
			}
		}
		return $item;
	}

	/**
	 * Map a store status to a badge tone.
	 *
	 * @param string $status Store status.
	 * @return string success|warning|danger|neutral
	 */
	private function tone( $status ) {
		$status = strtolower( (string) $status );
		if ( in_array( $status, array( 'active', 'complete', 'completed', 'publish', 'processing' ), true ) ) {
			return 'success';
		}
		if ( in_array( $status, array( 'pending', 'on-hold', 'trialling', 'trialing', 'expiring', 'inactive' ), true ) ) {
			return 'warning';
		}
		if ( in_array( $status, array( 'expired', 'refunded', 'failed', 'cancelled', 'canceled', 'revoked', 'disabled', 'abandoned' ), true ) ) {
			return 'danger';
		}
		return 'neutral';
	}

	/**
	 * Strip tags and decode entities from a store-formatted amount ("&#36;3,775.76" → "$3,775.76").
	 *
	 * @param string $html Formatted amount.
	 * @return string
	 */
	private function money( $html ) {
		return html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Format a date/timestamp with the site's date format.
	 *
	 * @param mixed $value Timestamp or date string.
	 * @return string
	 */
	private function date( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$ts = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
		return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '';
	}

	// -------------------------------------------------------------------------
	// Easy Digital Downloads
	// -------------------------------------------------------------------------

	/**
	 * EDD sections for an email, or null when the email is not a customer.
	 *
	 * @param string $email Lowercased email.
	 * @return array|null
	 */
	private function edd_card( $email ) {
		$customer = edd_get_customer_by( 'email', $email );
		if ( ! $customer || empty( $customer->id ) ) {
			return null;
		}
		$sections = array();
		$admin    = function_exists( 'edd_get_admin_url' )
			? edd_get_admin_url( array( 'page' => 'edd-customers', 'view' => 'overview', 'id' => (int) $customer->id ) )
			: admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' . (int) $customer->id );

		// Customer.
		$meta = array();
		if ( ! empty( $customer->purchase_count ) ) {
			/* translators: %d: number of purchases */
			$meta[] = sprintf( _n( '%d purchase', '%d purchases', (int) $customer->purchase_count, 'yamidoo' ), (int) $customer->purchase_count );
		}
		if ( function_exists( 'edd_currency_filter' ) && isset( $customer->purchase_value ) ) {
			/* translators: %s: formatted amount */
			$meta[] = sprintf( __( 'lifetime %s', 'yamidoo' ), $this->money( edd_currency_filter( edd_format_amount( (float) $customer->purchase_value ) ) ) );
		}
		if ( ! empty( $customer->date_created ) ) {
			/* translators: %s: date */
			$meta[] = sprintf( __( 'since %s', 'yamidoo' ), $this->date( $customer->date_created ) );
		}
		$sections[] = array(
			'title' => __( 'Customer', 'yamidoo' ),
			'items' => array(
				$this->item(
					trim( $customer->name ? $customer->name : $email ) . ' (#' . (int) $customer->id . ')',
					array( 'meta' => implode( ' · ', $meta ), 'url' => $admin )
				),
			),
		);

		// Licenses (Software Licensing).
		if ( function_exists( 'edd_software_licensing' ) ) {
			$licenses = edd_software_licensing()->licenses_db->get_licenses(
				array( 'customer_id' => (int) $customer->id, 'number' => self::MAX_ITEMS, 'orderby' => 'date_created', 'order' => 'DESC' )
			);
			$items = array();
			foreach ( (array) $licenses as $lic ) {
				$name = '';
				if ( ! empty( $lic->download_id ) ) {
					$name = get_the_title( (int) $lic->download_id );
					if ( ! empty( $lic->price_id ) && function_exists( 'edd_get_price_option_name' ) ) {
						$opt = edd_get_price_option_name( (int) $lic->download_id, (int) $lic->price_id );
						if ( $opt ) {
							$name .= ' · ' . $opt;
						}
					}
				}
				$exp = '';
				if ( ! empty( $lic->is_lifetime ) ) {
					$exp = __( 'lifetime', 'yamidoo' );
				} elseif ( ! empty( $lic->expiration ) ) {
					/* translators: %s: date */
					$exp = sprintf( __( 'expires %s', 'yamidoo' ), $this->date( $lic->expiration ) );
				}
				$sites = '';
				if ( isset( $lic->activation_count ) ) {
					$limit = ! empty( $lic->activation_limit ) ? (int) $lic->activation_limit : '∞';
					/* translators: 1: activations used, 2: activation limit */
					$sites = sprintf( __( 'sites %1$s/%2$s', 'yamidoo' ), (int) $lic->activation_count, $limit );
				}
				$items[] = $this->item(
					$name ? $name : __( 'License', 'yamidoo' ),
					array(
						'badge' => $lic->status,
						'tone'  => $this->tone( $lic->status ),
						'meta'  => implode( ' · ', array_filter( array( $exp, $sites, ! empty( $lic->key ) ? $lic->key : '' ) ) ),
						'url'   => function_exists( 'edd_get_admin_url' )
							? edd_get_admin_url( array( 'page' => 'edd-licenses', 'view' => 'overview', 'license' => (int) $lic->ID ) )
							: '',
					)
				);
			}
			$sections[] = array( 'title' => __( 'Licenses', 'yamidoo' ), 'items' => $items );
		}

		// Orders.
		if ( function_exists( 'edd_get_orders' ) ) {
			$orders = edd_get_orders(
				array( 'customer_id' => (int) $customer->id, 'number' => self::MAX_ITEMS, 'orderby' => 'date_created', 'order' => 'DESC', 'type' => 'sale' )
			);
			$items = array();
			foreach ( (array) $orders as $order ) {
				$products = array();
				if ( method_exists( $order, 'get_items' ) ) {
					foreach ( (array) $order->get_items() as $oi ) {
						$products[] = $oi->product_name;
					}
				}
				$amount = function_exists( 'edd_currency_filter' )
					? $this->money( edd_currency_filter( edd_format_amount( (float) $order->total ), $order->currency ) )
					: (string) $order->total;
				$items[] = $this->item(
					'#' . ( method_exists( $order, 'get_number' ) ? $order->get_number() : $order->id ) . ' · ' . $amount,
					array(
						'badge' => $order->status,
						'tone'  => $this->tone( $order->status ),
						'meta'  => implode( ' · ', array_filter( array( implode( ', ', array_unique( $products ) ), $this->date( $order->date_created ), $order->gateway ? ucfirst( $order->gateway ) : '' ) ) ),
						'url'   => function_exists( 'edd_get_admin_url' )
							? edd_get_admin_url( array( 'page' => 'edd-payment-history', 'view' => 'view-order-details', 'id' => (int) $order->id ) )
							: '',
					)
				);
			}
			$sections[] = array( 'title' => __( 'Orders', 'yamidoo' ), 'items' => $items, 'collapsed' => count( $items ) > 3 );
		}

		// Subscriptions (Recurring).
		if ( class_exists( 'EDD_Recurring_Subscriber' ) ) {
			$subscriber = new EDD_Recurring_Subscriber( (int) $customer->id );
			$subs       = (array) $subscriber->get_subscriptions();
			// Active ones first, then newest; a long-time customer can have dozens.
			usort(
				$subs,
				function ( $a, $b ) {
					$live = array( 'active' => 0, 'trialling' => 0, 'pending' => 1 );
					$ra   = isset( $live[ $a->status ] ) ? $live[ $a->status ] : 2;
					$rb   = isset( $live[ $b->status ] ) ? $live[ $b->status ] : 2;
					if ( $ra !== $rb ) {
						return $ra - $rb;
					}
					return strcmp( (string) $b->created, (string) $a->created );
				}
			);
			$subs  = array_slice( $subs, 0, self::MAX_ITEMS );
			$items = array();
			foreach ( $subs as $sub ) {
				$name = ! empty( $sub->product_id ) ? get_the_title( (int) $sub->product_id ) : __( 'Subscription', 'yamidoo' );
				$meta = array();
				if ( ! empty( $sub->created ) ) {
					/* translators: %s: date */
					$meta[] = sprintf( __( 'created %s', 'yamidoo' ), $this->date( $sub->created ) );
				}
				if ( ! empty( $sub->expiration ) ) {
					/* translators: %s: date */
					$meta[] = sprintf( __( 'renews/expires %s', 'yamidoo' ), $this->date( $sub->expiration ) );
				}
				if ( isset( $sub->recurring_amount ) && function_exists( 'edd_currency_filter' ) ) {
					$meta[] = $this->money( edd_currency_filter( edd_format_amount( (float) $sub->recurring_amount ) ) ) . ( ! empty( $sub->period ) ? ' / ' . $sub->period : '' );
				}
				$items[] = $this->item(
					$name . ' (#' . (int) $sub->id . ')',
					array(
						'badge' => $sub->status,
						'tone'  => $this->tone( $sub->status ),
						'meta'  => implode( ' · ', $meta ),
						'url'   => admin_url( 'edit.php?post_type=download&page=edd-subscriptions&id=' . (int) $sub->id ),
					)
				);
			}
			$sections[] = array(
				'title'     => __( 'Subscriptions', 'yamidoo' ),
				'items'     => $items,
				'collapsed' => count( $items ) > 3,
				'url'       => admin_url( 'edit.php?post_type=download&page=edd-subscriptions&s=' . rawurlencode( $email ) ),
			);
		}

		return array( 'sections' => $sections, 'url' => $admin );
	}

	// -------------------------------------------------------------------------
	// WooCommerce
	// -------------------------------------------------------------------------

	/**
	 * WooCommerce sections for an email, or null when nothing is known.
	 *
	 * @param string $email Lowercased email.
	 * @return array|null
	 */
	private function woo_card( $email ) {
		$orders = wc_get_orders( array( 'customer' => $email, 'limit' => self::MAX_ITEMS, 'orderby' => 'date', 'order' => 'DESC' ) );
		$user   = get_user_by( 'email', $email );
		if ( empty( $orders ) && ! $user ) {
			return null;
		}
		$sections = array();
		$admin    = $user ? admin_url( 'user-edit.php?user_id=' . (int) $user->ID ) : '';

		$total = 0.0;
		$items = array();
		foreach ( (array) $orders as $order ) {
			$total += (float) $order->get_total();
			$products = array();
			foreach ( $order->get_items() as $it ) {
				$products[] = $it->get_name();
			}
			$items[] = $this->item(
				'#' . $order->get_order_number() . ' · ' . $this->money( $order->get_formatted_order_total() ),
				array(
					'badge' => $order->get_status(),
					'tone'  => $this->tone( $order->get_status() ),
					'meta'  => implode( ' · ', array_filter( array( implode( ', ', array_unique( $products ) ), $this->date( $order->get_date_created() ? $order->get_date_created()->getTimestamp() : '' ), $order->get_payment_method_title() ) ) ),
					'url'   => $order->get_edit_order_url(),
				)
			);
		}
		$meta = array();
		if ( $orders ) {
			/* translators: %d: number of orders */
			$meta[] = sprintf( _n( '%d order', '%d orders', count( $orders ), 'yamidoo' ), count( $orders ) );
			/* translators: %s: formatted amount */
			$meta[] = sprintf( __( 'recent total %s', 'yamidoo' ), $this->money( wc_price( $total ) ) );
		}
		if ( $user && ! empty( $user->user_registered ) ) {
			/* translators: %s: date */
			$meta[] = sprintf( __( 'since %s', 'yamidoo' ), $this->date( $user->user_registered ) );
		}
		$sections[] = array(
			'title' => __( 'Customer', 'yamidoo' ),
			'items' => array( $this->item( $user ? $user->display_name : $email, array( 'meta' => implode( ' · ', $meta ), 'url' => $admin ) ) ),
		);
		$sections[] = array( 'title' => __( 'Orders', 'yamidoo' ), 'items' => $items, 'collapsed' => count( $items ) > 3 );

		if ( $user && function_exists( 'wcs_get_users_subscriptions' ) ) {
			$items = array();
			foreach ( (array) wcs_get_users_subscriptions( $user->ID ) as $sub ) {
				$names = array();
				foreach ( $sub->get_items() as $it ) {
					$names[] = $it->get_name();
				}
				$next    = $sub->get_date( 'next_payment' );
				$items[] = $this->item(
					implode( ', ', $names ) . ' (#' . $sub->get_id() . ')',
					array(
						'badge' => $sub->get_status(),
						'tone'  => $this->tone( $sub->get_status() ),
						/* translators: %s: date */
						'meta'  => implode( ' · ', array_filter( array( $this->money( $sub->get_formatted_order_total() ), $next ? sprintf( __( 'next payment %s', 'yamidoo' ), $this->date( $next ) ) : '' ) ) ),
						'url'   => $sub->get_edit_order_url(),
					)
				);
			}
			$sections[] = array( 'title' => __( 'Subscriptions', 'yamidoo' ), 'items' => $items );
		}

		return array( 'sections' => $sections, 'url' => $admin );
	}
}
