<?php
/**
 * Admin settings page (connect, widget options, customer data).
 *
 * @package Yamidoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Yamidoo_Settings
 */
class Yamidoo_Settings {

	/**
	 * Settings API group name.
	 */
	const GROUP = 'yamidoo_group';

	/**
	 * Settings page slug.
	 */
	const PAGE = 'yamidoo';

	/**
	 * Transient holding the one-time state nonce of an in-flight connect.
	 */
	const STATE_TRANSIENT = 'yamidoo_connect_state';

	/**
	 * Settings encrypted at rest (see Yamidoo_Secrets).
	 */
	const SECRET_KEYS = array( 'token', 'lookup_secret' );

	/**
	 * Hook everything up.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_connect_return' ) );
		add_action( 'admin_post_yamidoo_connect', array( $this, 'handle_connect_start' ) );
		add_action( 'admin_post_yamidoo_disconnect', array( $this, 'handle_disconnect' ) );
		add_action( 'admin_notices', array( $this, 'connect_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * URL of our settings screen (also the connect return address).
	 *
	 * @return string
	 */
	public static function settings_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'site_id'             => '',
			'enabled'             => 1,
			'identify_logged_in'  => 1,
			// Customer data connector (Easy Digital Downloads / WooCommerce).
			'share_customer_data' => 0,
			'lookup_secret'       => '',
			// Set by the one-click connect; empty when the Site ID was pasted by hand.
			'token'               => '',
			'connected_at'        => 0,
		);
	}

	/**
	 * Get the current settings, merged over the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$option = get_option( YAMIDOO_OPTION, array() );
		if ( ! is_array( $option ) ) {
			$option = array();
		}
		$option = wp_parse_args( $option, self::defaults() );
		foreach ( self::SECRET_KEYS as $key ) {
			$option[ $key ] = Yamidoo_Secrets::decrypt( $option[ $key ] );
		}
		return $option;
	}

	/**
	 * Whether a string looks like a canonical UUID (the Yamidoo Site ID format).
	 *
	 * @param string $value Candidate value.
	 * @return bool
	 */
	public static function is_uuid( $value ) {
		return (bool) preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
			(string) $value
		);
	}

	/**
	 * Register the admin settings submenu under Settings.
	 */
	public function add_menu() {
		add_options_page(
			__( 'Yamidoo', 'yamidoo' ),
			__( 'Yamidoo', 'yamidoo' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the setting, section and fields.
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			YAMIDOO_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'yamidoo_connection',
			__( 'Connect your site', 'yamidoo' ),
			array( $this, 'render_section' ),
			self::PAGE
		);

		add_settings_field(
			'site_id',
			__( 'Site ID', 'yamidoo' ),
			array( $this, 'field_site_id' ),
			self::PAGE,
			'yamidoo_connection',
			array( 'label_for' => 'yamidoo_site_id' )
		);

		add_settings_field(
			'enabled',
			__( 'Widget', 'yamidoo' ),
			array( $this, 'field_enabled' ),
			self::PAGE,
			'yamidoo_connection'
		);

		add_settings_field(
			'identify_logged_in',
			__( 'Logged-in users', 'yamidoo' ),
			array( $this, 'field_identify' ),
			self::PAGE,
			'yamidoo_connection'
		);

		add_settings_section(
			'yamidoo_customer',
			__( 'Customer data', 'yamidoo' ),
			array( $this, 'render_customer_section' ),
			self::PAGE
		);

		add_settings_field(
			'share_customer_data',
			__( 'Customer lookup', 'yamidoo' ),
			array( $this, 'field_share_customer' ),
			self::PAGE,
			'yamidoo_customer'
		);

		add_settings_field(
			'lookup_secret',
			__( 'Lookup secret', 'yamidoo' ),
			array( $this, 'field_lookup_secret' ),
			self::PAGE,
			'yamidoo_customer',
			array( 'label_for' => 'yamidoo_lookup_secret' )
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $input Raw form input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$current = self::get();
		$out     = self::defaults();

		$site_id = isset( $input['site_id'] ) ? trim( sanitize_text_field( $input['site_id'] ) ) : '';
		if ( '' !== $site_id && ! self::is_uuid( $site_id ) ) {
			add_settings_error(
				YAMIDOO_OPTION,
				'invalid_site_id',
				__( 'That doesn’t look like a valid Yamidoo Site ID. It should look like 123e4567-e89b-12d3-a456-426614174000. Your previous value was kept.', 'yamidoo' )
			);
			$site_id = $current['site_id'];
		}

		$out['site_id']             = $site_id;
		// register_setting() runs this on every update_option(), including the
		// connect handshake, which passes the token explicitly. The settings form
		// never posts one: then the token belongs to the site it was minted for.
		if ( isset( $input['token'] ) ) {
			$token               = trim( sanitize_text_field( $input['token'] ) );
			$out['token']        = ( 0 === strpos( $token, 'ycw_' ) ) ? $token : '';
			$out['connected_at'] = '' !== $out['token'] && isset( $input['connected_at'] ) ? (int) $input['connected_at'] : 0;
		} else {
			$out['token']        = ( $site_id === $current['site_id'] ) ? $current['token'] : '';
			$out['connected_at'] = ( $site_id === $current['site_id'] ) ? (int) $current['connected_at'] : 0;
		}
		$out['enabled']             = empty( $input['enabled'] ) ? 0 : 1;
		$out['identify_logged_in']  = empty( $input['identify_logged_in'] ) ? 0 : 1;
		$out['share_customer_data'] = empty( $input['share_customer_data'] ) ? 0 : 1;

		$secret = isset( $input['lookup_secret'] ) ? trim( sanitize_text_field( $input['lookup_secret'] ) ) : '';
		if ( '' !== $secret && ! preg_match( '/^[A-Za-z0-9_\-]{16,128}$/', $secret ) ) {
			add_settings_error(
				YAMIDOO_OPTION,
				'invalid_lookup_secret',
				__( 'That doesn’t look like a Yamidoo lookup secret. Generate one in your dashboard under Integrations → Customer data and paste it as is. Your previous value was kept.', 'yamidoo' )
			);
			$secret = $current['lookup_secret'];
		}
		$out['lookup_secret'] = $secret;

		foreach ( self::SECRET_KEYS as $key ) {
			$out[ $key ] = Yamidoo_Secrets::encrypt( $out[ $key ] );
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// One-click connect
	// -------------------------------------------------------------------------

	/**
	 * "Connect to Yamidoo" → app.yamidoo.ai/connect/wordpress with a one-time
	 * state nonce. The app signs the owner in (or up — email and name are
	 * prefilled from their WordPress profile), creates the project and sends
	 * them back here with the Site ID, a connect token and the customer-lookup
	 * secret. Nothing to paste.
	 */
	public function handle_connect_start() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'yamidoo' ), 403 );
		}
		check_admin_referer( 'yamidoo_connect' );

		$state = wp_generate_password( 24, false, false );
		set_transient( self::STATE_TRANSIENT, $state, 15 * MINUTE_IN_SECONDS );

		$user = wp_get_current_user();
		$name = trim( $user->first_name . ' ' . $user->last_name );
		if ( '' === $name ) {
			$name = html_entity_decode( $user->display_name, ENT_QUOTES, 'UTF-8' );
		}
		$url = add_query_arg(
			array(
				'site'   => rawurlencode( home_url( '/' ) ),
				'return' => rawurlencode( self::settings_url() ),
				'state'  => $state,
				'plugin' => 'yamidoo',
				'email'  => rawurlencode( $user->user_email ),
				'name'   => rawurlencode( $name ),
			),
			yamidoo_app_url() . '/connect/wordpress'
		);
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external app by design.
		exit;
	}

	/**
	 * Back from the app with ?yamidoo_site_id=…&yamidoo_token=…&yamidoo_state=…[&yamidoo_lookup_secret=…].
	 */
	public function handle_connect_return() {
		if ( empty( $_GET['yamidoo_site_id'] ) || empty( $_GET['yamidoo_token'] ) || empty( $_GET['yamidoo_state'] ) ) {
			return;
		}
		// The app returns to the plugin that started the handshake; WPZOOM Connect
		// listens for the same parameters on its own screen.
		if ( empty( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$expected = get_transient( self::STATE_TRANSIENT );
		$state    = sanitize_text_field( wp_unslash( $_GET['yamidoo_state'] ) );
		$site_id  = strtolower( sanitize_text_field( wp_unslash( $_GET['yamidoo_site_id'] ) ) );
		$token    = sanitize_text_field( wp_unslash( $_GET['yamidoo_token'] ) );

		if ( ! $expected || ! hash_equals( (string) $expected, $state ) || ! self::is_uuid( $site_id ) || 0 !== strpos( $token, 'ycw_' ) ) {
			wp_safe_redirect( add_query_arg( 'yamidoo', 'connect_failed', self::settings_url() ) );
			exit;
		}
		delete_transient( self::STATE_TRANSIENT );

		$options                 = self::get();
		$options['site_id']      = $site_id;
		$options['token']        = $token;
		$options['connected_at'] = time();
		if ( ! empty( $_GET['yamidoo_lookup_secret'] ) ) {
			$secret = sanitize_text_field( wp_unslash( $_GET['yamidoo_lookup_secret'] ) );
			if ( 0 === strpos( $secret, 'ycl_' ) ) {
				$options['lookup_secret'] = $secret;
				// Customer data is on by default when there is a store to read from.
				if ( Yamidoo_Customer::detected_stores() ) {
					$options['share_customer_data'] = 1;
				}
			}
		}
		update_option( YAMIDOO_OPTION, $options );

		wp_safe_redirect( add_query_arg( 'yamidoo', 'connected', self::settings_url() ) );
		exit;
	}

	/**
	 * Forget the connection (the project stays in the Yamidoo dashboard).
	 */
	public function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'yamidoo' ), 403 );
		}
		check_admin_referer( 'yamidoo_disconnect' );
		$options                  = self::get();
		$options['site_id']       = '';
		$options['token']         = '';
		$options['connected_at']  = 0;
		$options['lookup_secret'] = '';
		update_option( YAMIDOO_OPTION, $options );
		wp_safe_redirect( add_query_arg( 'yamidoo', 'disconnected', self::settings_url() ) );
		exit;
	}

	/**
	 * Result notices after a connect / disconnect round trip.
	 */
	public function connect_notices() {
		if ( empty( $_GET['page'] ) || self::PAGE !== $_GET['page'] || empty( $_GET['yamidoo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			return;
		}
		$result = sanitize_key( wp_unslash( $_GET['yamidoo'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map    = array(
			'connected'      => array( 'success', __( 'Connected. Your site is linked to Yamidoo and its pages are being indexed.', 'yamidoo' ) ),
			'connect_failed' => array( 'error', __( 'The connection could not be verified. Please click Connect to Yamidoo again.', 'yamidoo' ) ),
			'disconnected'   => array( 'info', __( 'Disconnected. The project is still in your Yamidoo dashboard; connect again any time.', 'yamidoo' ) ),
		);
		if ( ! isset( $map[ $result ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $map[ $result ][0] ),
			esc_html( $map[ $result ][1] )
		);
	}

	/**
	 * The connect card above the settings form.
	 */
	private function render_connect_card() {
		$options   = self::get();
		$connected = '' !== $options['token'] && self::is_uuid( $options['site_id'] );
		?>
		<div class="yamidoo-card yamidoo-connect-card">
			<?php if ( $connected ) : ?>
				<h2><?php esc_html_e( 'Connected to Yamidoo', 'yamidoo' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: date */
							__( 'This site has been linked to your Yamidoo workspace since %s. Widget, customer data and indexing are managed from the dashboard.', 'yamidoo' ),
							date_i18n( get_option( 'date_format' ), (int) $options['connected_at'] )
						)
					);
					?>
				</p>
				<p class="yamidoo-connect-actions">
					<a class="button button-secondary" href="<?php echo esc_url( yamidoo_app_url() . '/dashboard/sites' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the Yamidoo dashboard', 'yamidoo' ); ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yamidoo-inline-form">
						<?php wp_nonce_field( 'yamidoo_disconnect' ); ?>
						<input type="hidden" name="action" value="yamidoo_disconnect" />
						<button type="submit" class="button-link yamidoo-disconnect"><?php esc_html_e( 'Disconnect', 'yamidoo' ); ?></button>
					</form>
				</p>
			<?php else : ?>
				<h2><?php esc_html_e( 'Connect to Yamidoo', 'yamidoo' ); ?></h2>
				<p><?php esc_html_e( 'One click: sign in or create a free account, and this site is linked and indexed — Site ID, customer data and secret all filled in for you.', 'yamidoo' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'yamidoo_connect' ); ?>
					<input type="hidden" name="action" value="yamidoo_connect" />
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Connect to Yamidoo', 'yamidoo' ); ?></button>
				</form>
				<p class="description"><?php esc_html_e( 'Prefer to do it by hand? Paste your Site ID below instead.', 'yamidoo' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Section intro text.
	 */
	public function render_section() {
		echo '<p>' . esc_html__( 'Paste the Site ID from your Yamidoo dashboard to link this website and show the chat widget.', 'yamidoo' ) . '</p>';
	}

	/**
	 * Customer data section intro.
	 */
	public function render_customer_section() {
		$stores = Yamidoo_Customer::detected_stores();
		if ( $stores ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s: store names, e.g. "Easy Digital Downloads" */
					__( 'Let your own Yamidoo inbox show what %s knows about a visitor — orders, licenses, subscriptions — next to their conversation. Nothing is uploaded: your workspace fetches one customer’s record from this site when a conversation is opened, and it is not stored. The AI assistant does not see it unless you switch that on in the dashboard.', 'yamidoo' ),
					implode( ' & ', $stores )
				)
			) . '</p>';
			return;
		}
		echo '<p>' . esc_html__( 'Lets your own Yamidoo inbox show a visitor’s orders and subscriptions. Works with Easy Digital Downloads and WooCommerce — neither is active on this site. Developers can add sections from any plugin with the yamidoo_customer_sections filter.', 'yamidoo' ) . '</p>';
	}

	/**
	 * Share-customer-data checkbox.
	 */
	public function field_share_customer() {
		$options = self::get();
		$stores  = Yamidoo_Customer::detected_stores();
		$label   = $stores
			/* translators: %s: store names, e.g. "Easy Digital Downloads" */
			? sprintf( __( 'Show %s customer data in my Yamidoo inbox', 'yamidoo' ), implode( ' & ', $stores ) )
			: __( 'Show customer data in my Yamidoo inbox', 'yamidoo' );
		printf(
			'<label><input type="checkbox" name="%1$s[share_customer_data]" value="1" %2$s /> %3$s</label>',
			esc_attr( YAMIDOO_OPTION ),
			checked( 1, $options['share_customer_data'], false ),
			esc_html( $label )
		);
		echo '<p class="description">' . esc_html__( 'Only your workspace can request it, and only with the secret below. Your team sees the record next to the conversation. The AI is kept out by default; you can let it answer logged-in customers’ own account questions from the dashboard, under Integrations → Customer data.', 'yamidoo' ) . '</p>';
	}

	/**
	 * Lookup secret field, with the endpoint URL to paste into the dashboard.
	 */
	public function field_lookup_secret() {
		$options  = self::get();
		$endpoint = Yamidoo_Customer::endpoint_url();
		printf(
			'<input type="text" name="%1$s[lookup_secret]" id="yamidoo_lookup_secret" value="%2$s" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="ycl_…" />',
			esc_attr( YAMIDOO_OPTION ),
			esc_attr( $options['lookup_secret'] )
		);
		echo '<p class="description">' . wp_kses(
			sprintf(
				/* translators: 1: link to the Yamidoo dashboard, 2: this site's customer endpoint URL */
				__( 'In your <a href="%1$s" target="_blank" rel="noopener noreferrer">Yamidoo dashboard</a> open your site → Integrations → Customer data, set the Lookup URL to <code class="yamidoo-endpoint">%2$s</code>, click <strong>Generate secret</strong> and paste it here.', 'yamidoo' ),
				esc_url( yamidoo_app_url() . '/dashboard/sites' ),
				esc_html( $endpoint )
			),
			array(
				'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
				'code'   => array( 'class' => array() ),
				'strong' => array(),
			)
		) . '</p>';

		if ( Yamidoo_Customer::is_enabled() ) {
			$status_class = 'is-active';
			$status_label = __( 'On — your Yamidoo inbox can look up customers on this site.', 'yamidoo' );
		} elseif ( ! empty( $options['share_customer_data'] ) ) {
			$status_class = 'is-off';
			$status_label = __( 'Paste the lookup secret from your dashboard to turn this on.', 'yamidoo' );
		} else {
			$status_class = 'is-idle';
			$status_label = __( 'Off — your inbox shows only what the visitor typed.', 'yamidoo' );
		}
		printf( '<p class="yamidoo-status %1$s">%2$s</p>', esc_attr( $status_class ), esc_html( $status_label ) );
	}

	/**
	 * Site ID field.
	 */
	public function field_site_id() {
		$options = self::get();
		printf(
			'<input type="text" name="%1$s[site_id]" id="yamidoo_site_id" value="%2$s" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="123e4567-e89b-12d3-a456-426614174000" />',
			esc_attr( YAMIDOO_OPTION ),
			esc_attr( $options['site_id'] )
		);

		$dashboard_url = yamidoo_app_url() . '/dashboard/sites';
		echo '<p class="description">' . wp_kses(
			sprintf(
				/* translators: %s: link to the Yamidoo dashboard. */
				__( 'Find it in your <a href="%s" target="_blank" rel="noopener noreferrer">Yamidoo dashboard</a> under your site → Integrations → WordPress.', 'yamidoo' ),
				esc_url( $dashboard_url )
			),
			array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
		) . '</p>';

		// Connection status, shown inline right under the field.
		$connected = ( '' !== $options['site_id'] && self::is_uuid( $options['site_id'] ) );
		if ( $connected && $options['enabled'] ) {
			$status_class = 'is-active';
			$status_label = __( 'Connected, the chat widget is active on your site.', 'yamidoo' );
		} elseif ( $connected ) {
			$status_class = 'is-off';
			$status_label = __( 'Connected, the widget is turned off below.', 'yamidoo' );
		} else {
			$status_class = 'is-idle';
			$status_label = __( 'Not connected yet.', 'yamidoo' );
		}
		printf( '<p class="yamidoo-status %1$s">%2$s</p>', esc_attr( $status_class ), esc_html( $status_label ) );
	}

	/**
	 * Enable-widget checkbox.
	 */
	public function field_enabled() {
		$options = self::get();
		printf(
			'<label><input type="checkbox" name="%1$s[enabled]" value="1" %2$s /> %3$s</label>',
			esc_attr( YAMIDOO_OPTION ),
			checked( 1, $options['enabled'], false ),
			esc_html__( 'Show the Yamidoo chat widget on the front end of this site', 'yamidoo' )
		);
	}

	/**
	 * Identify-logged-in-users checkbox.
	 */
	public function field_identify() {
		$options = self::get();
		printf(
			'<label><input type="checkbox" name="%1$s[identify_logged_in]" value="1" %2$s /> %3$s</label>',
			esc_attr( YAMIDOO_OPTION ),
			checked( 1, $options['identify_logged_in'], false ),
			esc_html__( 'Pass a logged-in user’s name and email to Yamidoo so your team knows who they’re talking to', 'yamidoo' )
		);
		echo '<p class="description">' . esc_html__( 'Only their WordPress display name, email, username and user ID are shared, and only while they are logged in.', 'yamidoo' ) . '</p>';
	}

	/**
	 * Enqueue the small admin stylesheet on our page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'yamidoo-admin',
			YAMIDOO_URL . 'assets/admin.css',
			array(),
			YAMIDOO_VERSION
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$customize_url = yamidoo_app_url() . '/dashboard/sites';
		?>
		<div class="wrap yamidoo-wrap">
			<h1><?php esc_html_e( 'Yamidoo', 'yamidoo' ); ?></h1>

			<?php $this->render_connect_card(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<div class="yamidoo-card">
				<h2><?php esc_html_e( 'Customize your widget', 'yamidoo' ); ?></h2>
				<p><?php esc_html_e( 'Colors, welcome message, launcher icon, suggested questions, lead capture and human handoff are all configured in your Yamidoo dashboard. Changes apply automatically — no update needed here.', 'yamidoo' ); ?></p>
				<a class="button button-secondary" href="<?php echo esc_url( $customize_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Open the Yamidoo dashboard', 'yamidoo' ); ?>
					<svg xmlns="http://www.w3.org/2000/svg" height="22" viewBox="0 -960 960 960" width="22" fill="currentColor" aria-hidden="true"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h240q17 0 28.5 11.5T480-800q0 17-11.5 28.5T440-760H200v560h560v-240q0-17 11.5-28.5T800-480q17 0 28.5 11.5T840-440v240q0 33-23.5 56.5T760-120H200Zm560-584L416-360q-11 11-28 11t-28-11q-11-11-11-28t11-28l344-344H600q-17 0-28.5-11.5T560-800q0-17 11.5-28.5T600-840h200q17 0 28.5 11.5T840-800v200q0 17-11.5 28.5T800-560q-17 0-28.5-11.5T760-600v-104Z"/></svg>
				</a>
			</div>
		</div>
		<?php
	}
}
