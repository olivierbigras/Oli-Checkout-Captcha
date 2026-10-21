<?php
/**
 * Main plugin class.
 *
 * @package Olic_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers settings, checkout assets, and reCAPTCHA validation.
 *
 * @since 1.0.0
 */
class Olic_Checkout_Captcha {

	/**
	 * Store API / script namespace.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const EXTENSION_NAMESPACE = 'oli-checkout-captcha';

	/**
	 * Legacy Store API namespace used before the rename.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const LEGACY_EXTENSION_NAMESPACE = 'recaptcha-block-checkout';

	/**
	 * Singleton instance.
	 *
	 * @var Olic_Checkout_Captcha|null
	 */
	private static $instance = null;

	/**
	 * Google reCAPTCHA site key.
	 *
	 * @var string
	 */
	private $site_key = '';

	/**
	 * Google reCAPTCHA secret key.
	 *
	 * @var string
	 */
	private $secret_key = '';

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Olic_Checkout_Captcha
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook admin, settings, and checkout integrations.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->site_key   = $this->get_stored_api_key( 'olic_site_key' );
		$this->secret_key = $this->get_stored_api_key( 'olic_secret_key' );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'option_page_capability_olic_settings', array( $this, 'get_settings_capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( OLIC_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
			return;
		}

		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		if ( $this->has_keys() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'woocommerce_blocks_loaded', array( $this, 'register_endpoint_data' ) );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_checkout' ), 10, 2 );
		}
	}

	/**
	 * Load translations from the languages directory.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'oli-checkout-captcha',
			false,
			dirname( plugin_basename( OLIC_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Capability required to manage plugin settings.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function get_settings_capability() {
		return 'manage_options';
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string>|mixed $links Existing plugin action links.
	 * @return array<string, string>
	 */
	public function add_settings_link( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return $links;
		}

		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'options-general.php?page=oli-checkout-captcha' ) ),
			esc_html__( 'Settings', 'oli-checkout-captcha' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Register the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Oli Checkout Captcha', 'oli-checkout-captcha' ),
			__( 'Oli Checkout Captcha', 'oli-checkout-captcha' ),
			$this->get_settings_capability(),
			'oli-checkout-captcha',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings with capability-restricted sanitization callbacks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_settings() {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return;
		}

		register_setting(
			'olic_settings',
			'olic_site_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'olic_settings',
			'olic_secret_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'olic_settings',
			'olic_theme',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_theme' ),
				'default'           => 'light',
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'olic_settings_section',
			__( 'API keys', 'oli-checkout-captcha' ),
			array( $this, 'render_settings_section' ),
			'oli-checkout-captcha'
		);

		add_settings_field(
			'olic_site_key',
			__( 'Site Key', 'oli-checkout-captcha' ),
			array( $this, 'render_site_key_field' ),
			'oli-checkout-captcha',
			'olic_settings_section'
		);

		add_settings_field(
			'olic_secret_key',
			__( 'Secret Key', 'oli-checkout-captcha' ),
			array( $this, 'render_secret_key_field' ),
			'oli-checkout-captcha',
			'olic_settings_section'
		);

		add_settings_field(
			'olic_theme',
			__( 'Theme', 'oli-checkout-captcha' ),
			array( $this, 'render_theme_field' ),
			'oli-checkout-captcha',
			'olic_settings_section'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * Nonces are output by settings_fields() and verified by options.php.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'oli-checkout-captcha' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<p>' . esc_html__( 'Add Google reCAPTCHA v2 to your WooCommerce block checkout to prevent spam orders.', 'oli-checkout-captcha' ) . '</p>';

		if ( $this->has_keys() ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'reCAPTCHA is active and protecting your checkout.', 'oli-checkout-captcha' ) . '</strong></p></div>';
		} else {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Please configure your reCAPTCHA API keys below.', 'oli-checkout-captcha' ) . '</strong></p></div>';
		}

		echo '<form method="post" action="options.php">';
		settings_fields( 'olic_settings' );
		do_settings_sections( 'oli-checkout-captcha' );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the settings section description.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Keys are stored in the WordPress options table and used only to verify checkout submissions with Google reCAPTCHA.', 'oli-checkout-captcha' ) . '</p>';
	}

	/**
	 * Render the site key field.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_site_key_field() {
		printf(
			'<input type="text" id="olic_site_key" name="olic_site_key" value="%s" class="regular-text" autocomplete="off" />',
			esc_attr( $this->get_stored_api_key( 'olic_site_key' ) )
		);

		$link = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( 'https://www.google.com/recaptcha/admin/create' ),
			esc_html__( 'Google reCAPTCHA Admin', 'oli-checkout-captcha' )
		);

		echo '<p class="description">';
		echo wp_kses(
			sprintf(
				/* translators: %s: link to the Google reCAPTCHA admin console */
				__( 'Get your keys from %s. Use reCAPTCHA v2 "I\'m not a robot" Checkbox.', 'oli-checkout-captcha' ),
				$link
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		);
		echo '</p>';
	}

	/**
	 * Render the secret key field.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_secret_key_field() {
		printf(
			'<input type="text" id="olic_secret_key" name="olic_secret_key" value="%s" class="regular-text" autocomplete="off" />',
			esc_attr( $this->get_stored_api_key( 'olic_secret_key' ) )
		);
	}

	/**
	 * Render the theme field.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_theme_field() {
		$theme = $this->get_theme();

		echo '<select id="olic_theme" name="olic_theme">';
		printf(
			'<option value="light"%s>%s</option>',
			selected( $theme, 'light', false ),
			esc_html__( 'Light', 'oli-checkout-captcha' )
		);
		printf(
			'<option value="dark"%s>%s</option>',
			selected( $theme, 'dark', false ),
			esc_html__( 'Dark', 'oli-checkout-captcha' )
		);
		echo '</select>';
	}

	/**
	 * Sanitize a reCAPTCHA API key.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_api_key( $value ) {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return '';
		}

		$value = sanitize_text_field( wp_unslash( (string) $value ) );
		$clean = preg_replace( '/[^A-Za-z0-9_-]/', '', $value );

		return is_string( $clean ) ? $clean : '';
	}

	/**
	 * Sanitize the widget theme.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_theme( $value ) {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return 'light';
		}

		$value   = sanitize_key( wp_unslash( (string) $value ) );
		$allowed = array( 'light', 'dark' );

		return in_array( $value, $allowed, true ) ? $value : 'light';
	}

	/**
	 * Show an admin notice when WooCommerce is missing.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Oli Checkout Captcha requires WooCommerce to be installed and active.', 'oli-checkout-captcha' );
		echo '</p></div>';
	}

	/**
	 * Enqueue checkout CSS and JavaScript from files (no inline assets).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'olic-checkout',
			plugins_url( 'css/checkout.css', OLIC_PLUGIN_FILE ),
			array(),
			OLIC_VERSION
		);

		wp_register_script(
			'olic-checkout',
			plugins_url( 'js/checkout.js', OLIC_PLUGIN_FILE ),
			array( 'wp-data' ),
			OLIC_VERSION,
			true
		);

		wp_localize_script(
			'olic-checkout',
			'olicConfig',
			array(
				'siteKey'   => $this->site_key,
				'theme'     => $this->get_theme(),
				'namespace' => self::EXTENSION_NAMESPACE,
			)
		);

		wp_enqueue_script( 'olic-checkout' );

		wp_enqueue_script(
			'olic-google-recaptcha',
			'https://www.google.com/recaptcha/api.js?onload=olicRecaptchaOnLoad&render=explicit',
			array( 'olic-checkout' ),
			OLIC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Register checkout Store API extension data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoint_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => self::EXTENSION_NAMESPACE,
				'schema_callback' => array( $this, 'get_checkout_endpoint_schema' ),
			)
		);
	}

	/**
	 * Schema for the checkout extension token.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_checkout_endpoint_schema() {
		return array(
			'token' => array(
				'description' => __( 'reCAPTCHA token', 'oli-checkout-captcha' ),
				'type'        => 'string',
				'readonly'    => false,
				'context'     => array( 'view', 'edit' ),
			),
		);
	}

	/**
	 * Validate the reCAPTCHA token when the Store API creates an order.
	 *
	 * This is a WooCommerce Store API checkout callback, not a custom REST route.
	 * Guests must be able to check out, so a manage_options capability check is not applied.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order        $order   Order being updated.
	 * @param WP_REST_Request $request Store API request.
	 * @return WC_Order
	 * @throws \Exception When verification fails.
	 */
	public function validate_checkout( $order, $request ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_method' ) || 'POST' !== $request->get_method() ) {
			return $order;
		}

		$token = $this->get_token_from_request( $request );

		if ( '' === $token ) {
			throw new \Exception( esc_html__( 'Please complete the reCAPTCHA verification.', 'oli-checkout-captcha' ) );
		}

		$body = array(
			'secret'   => $this->secret_key,
			'response' => $token,
		);

		$remote_ip = $this->get_remote_ip();
		if ( '' !== $remote_ip ) {
			$body['remoteip'] = $remote_ip;
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html__( 'reCAPTCHA verification failed. Please try again.', 'oli-checkout-captcha' ) );
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $payload['success'] ) ) {
			throw new \Exception( esc_html__( 'reCAPTCHA verification failed. Please complete the challenge again.', 'oli-checkout-captcha' ) );
		}

		return $order;
	}

	/**
	 * Declare compatibility with WooCommerce High-Performance Order Storage.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility() {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', OLIC_PLUGIN_FILE, true );
	}

	/**
	 * Whether both API keys are configured.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private function has_keys() {
		return ( '' !== $this->site_key && '' !== $this->secret_key );
	}

	/**
	 * Get a stored API key option.
	 *
	 * @since 1.1.0
	 *
	 * @param string $option_name Option name.
	 * @return string
	 */
	private function get_stored_api_key( $option_name ) {
		$value = get_option( $option_name, '' );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Get the sanitized widget theme.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function get_theme() {
		$theme   = get_option( 'olic_theme', 'light' );
		$theme   = is_string( $theme ) ? $theme : 'light';
		$allowed = array( 'light', 'dark' );

		return in_array( $theme, $allowed, true ) ? $theme : 'light';
	}

	/**
	 * Read the reCAPTCHA token from the Store API request.
	 *
	 * Accepts the current namespace and the pre-rename namespace so cached
	 * checkout scripts continue to work after the slug change.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request Store API request.
	 * @return string
	 */
	private function get_token_from_request( $request ) {
		if ( ! method_exists( $request, 'get_param' ) ) {
			return '';
		}

		$extensions = $request->get_param( 'extensions' );
		if ( ! is_array( $extensions ) ) {
			return '';
		}

		$namespaces = array( self::EXTENSION_NAMESPACE, self::LEGACY_EXTENSION_NAMESPACE );

		foreach ( $namespaces as $namespace ) {
			if ( empty( $extensions[ $namespace ]['token'] ) || ! is_string( $extensions[ $namespace ]['token'] ) ) {
				continue;
			}

			return sanitize_text_field( wp_unslash( $extensions[ $namespace ]['token'] ) );
		}

		return '';
	}

	/**
	 * Sanitize the visitor IP used for Google siteverify.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function get_remote_ip() {
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		if ( function_exists( 'rest_is_ip_address' ) && ! rest_is_ip_address( $ip ) ) {
			return '';
		}

		return $ip;
	}
}
