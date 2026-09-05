<?php
/**
 * Admin settings page (connect + widget options).
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
	 * Hook everything up.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'site_id'            => '',
			'enabled'            => 1,
			'identify_logged_in' => 1,
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
		return wp_parse_args( $option, self::defaults() );
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
			'yamidoo_integrations',
			__( 'Integrations', 'yamidoo' ),
			array( $this, 'render_integrations_section' ),
			self::PAGE
		);

		add_settings_field(
			'woocommerce',
			__( 'WooCommerce', 'yamidoo' ),
			array( $this, 'field_soon' ),
			self::PAGE,
			'yamidoo_integrations',
			array( 'label' => __( 'Share a logged-in customer’s recent orders with Yamidoo for order-aware support.', 'yamidoo' ) )
		);

		add_settings_field(
			'edd',
			__( 'Easy Digital Downloads', 'yamidoo' ),
			array( $this, 'field_soon' ),
			self::PAGE,
			'yamidoo_integrations',
			array( 'label' => __( 'Share a customer’s purchases and downloads with Yamidoo.', 'yamidoo' ) )
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

		$out['site_id']            = $site_id;
		$out['enabled']            = empty( $input['enabled'] ) ? 0 : 1;
		$out['identify_logged_in'] = empty( $input['identify_logged_in'] ) ? 0 : 1;

		return $out;
	}

	/**
	 * Section intro text.
	 */
	public function render_section() {
		echo '<p>' . esc_html__( 'Paste the Site ID from your Yamidoo dashboard to link this website and show the chat widget.', 'yamidoo' ) . '</p>';
	}

	/**
	 * Integrations section intro.
	 */
	public function render_integrations_section() {
		echo '<p>' . esc_html__( 'More ways to give your support agents context. These are on the way.', 'yamidoo' ) . '</p>';
	}

	/**
	 * Render a disabled "coming soon" integration row.
	 *
	 * @param array $args Field args (expects 'label').
	 */
	public function field_soon( $args ) {
		$label = isset( $args['label'] ) ? $args['label'] : '';
		printf(
			'<label class="yamidoo-soon-field"><input type="checkbox" disabled /> %1$s</label> <span class="yamidoo-soon-badge">%2$s</span>',
			esc_html( $label ),
			esc_html__( 'Coming soon', 'yamidoo' )
		);
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

			<?php settings_errors(); ?>

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
