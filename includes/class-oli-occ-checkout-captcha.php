<?php
/**
 * Main plugin class.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers settings, checkout assets, and captcha validation.
 *
 * @since 1.0.0
 */
class Oli_OCC_Checkout_Captcha {

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
	 * reCAPTCHA v3 action sent with execute() and checked server-side.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const CAPTCHA_ACTION = 'checkout';

	/**
	 * Singleton instance.
	 *
	 * @var Oli_OCC_Checkout_Captcha|null
	 */
	private static $instance = null;

	/**
	 * Token verifier.
	 *
	 * @since 1.2.0
	 * @var Oli_OCC_Verifier
	 */
	private $verifier;

	/**
	 * Failed-attempt limiter.
	 *
	 * @since 1.2.0
	 * @var Oli_OCC_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Oli_OCC_Checkout_Captcha
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
		$this->verifier     = new Oli_OCC_Verifier();
		$this->rate_limiter = new Oli_OCC_Rate_Limiter();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'option_page_capability_oli_occ_settings', array( $this, 'get_settings_capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( OLI_OCC_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
			return;
		}

		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		if ( $this->has_keys() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_classic_widget' ) );
			add_action( 'woocommerce_checkout_process', array( $this, 'validate_classic_checkout' ) );
			add_action( 'woocommerce_blocks_loaded', array( $this, 'register_endpoint_data' ) );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_store_api_checkout' ), 10, 2 );
		}
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

		$this->register_string_setting( 'oli_occ_provider', array( $this, 'sanitize_provider' ), 'recaptcha_v2' );
		$this->register_string_setting( 'oli_occ_site_key', array( $this, 'sanitize_api_key' ), '' );
		$this->register_string_setting( 'oli_occ_secret_key', array( $this, 'sanitize_api_key' ), '' );
		$this->register_string_setting( 'oli_occ_theme', array( $this, 'sanitize_theme' ), 'light' );
		$this->register_string_setting( 'oli_occ_score_threshold', array( $this, 'sanitize_score_threshold' ), '0.5' );
		$this->register_string_setting( 'oli_occ_fail_closed', array( $this, 'sanitize_checkbox' ), '1' );
		$this->register_string_setting( 'oli_occ_rate_limit_enabled', array( $this, 'sanitize_checkbox' ), '0' );
		$this->register_string_setting( 'oli_occ_rate_limit_max', array( $this, 'sanitize_rate_limit_max' ), '10' );
		$this->register_string_setting( 'oli_occ_rate_limit_window', array( $this, 'sanitize_rate_limit_window' ), '900' );

		add_settings_section(
			'oli_occ_provider_section',
			__( 'Provider', 'oli-checkout-captcha' ),
			array( $this, 'render_provider_section' ),
			'oli-checkout-captcha'
		);

		add_settings_field(
			'oli_occ_provider',
			__( 'Captcha provider', 'oli-checkout-captcha' ),
			array( $this, 'render_provider_field' ),
			'oli-checkout-captcha',
			'oli_occ_provider_section'
		);

		add_settings_section(
			'oli_occ_settings_section',
			__( 'API keys', 'oli-checkout-captcha' ),
			array( $this, 'render_settings_section' ),
			'oli-checkout-captcha'
		);

		add_settings_field(
			'oli_occ_site_key',
			__( 'Site Key', 'oli-checkout-captcha' ),
			array( $this, 'render_site_key_field' ),
			'oli-checkout-captcha',
			'oli_occ_settings_section'
		);

		add_settings_field(
			'oli_occ_secret_key',
			__( 'Secret Key', 'oli-checkout-captcha' ),
			array( $this, 'render_secret_key_field' ),
			'oli-checkout-captcha',
			'oli_occ_settings_section'
		);

		add_settings_section(
			'oli_occ_verification_section',
			__( 'Verification', 'oli-checkout-captcha' ),
			array( $this, 'render_verification_section' ),
			'oli-checkout-captcha'
		);

		add_settings_field(
			'oli_occ_score_threshold',
			__( 'reCAPTCHA v3 score threshold', 'oli-checkout-captcha' ),
			array( $this, 'render_score_threshold_field' ),
			'oli-checkout-captcha',
			'oli_occ_verification_section'
		);

		add_settings_field(
			'oli_occ_fail_closed',
			__( 'Fail closed', 'oli-checkout-captcha' ),
			array( $this, 'render_fail_closed_field' ),
			'oli-checkout-captcha',
			'oli_occ_verification_section'
		);

		add_settings_section(
			'oli_occ_rate_limit_section',
			__( 'Rate limiting', 'oli-checkout-captcha' ),
			array( $this, 'render_rate_limit_section' ),
			'oli-checkout-captcha'
		);

		add_settings_field(
			'oli_occ_rate_limit_enabled',
			__( 'Limit failed attempts', 'oli-checkout-captcha' ),
			array( $this, 'render_rate_limit_enabled_field' ),
			'oli-checkout-captcha',
			'oli_occ_rate_limit_section'
		);

		add_settings_field(
			'oli_occ_rate_limit_max',
			__( 'Maximum failures', 'oli-checkout-captcha' ),
			array( $this, 'render_rate_limit_max_field' ),
			'oli-checkout-captcha',
			'oli_occ_rate_limit_section'
		);

		add_settings_field(
			'oli_occ_rate_limit_window',
			__( 'Window (seconds)', 'oli-checkout-captcha' ),
			array( $this, 'render_rate_limit_window_field' ),
			'oli-checkout-captcha',
			'oli_occ_rate_limit_section'
		);

		add_settings_section(
			'oli_occ_appearance_section',
			__( 'Appearance', 'oli-checkout-captcha' ),
			'__return_false',
			'oli-checkout-captcha'
		);

		add_settings_field(
			'oli_occ_theme',
			__( 'Theme', 'oli-checkout-captcha' ),
			array( $this, 'render_theme_field' ),
			'oli-checkout-captcha',
			'oli_occ_appearance_section'
		);
	}

	/**
	 * Register one string option on the settings page.
	 *
	 * @since 1.2.0
	 *
	 * @param string   $option_name Option name.
	 * @param callable $sanitize    Sanitization callback.
	 * @param string   $default_value Default value.
	 * @return void
	 */
	private function register_string_setting( $option_name, $sanitize, $default_value ) {
		register_setting(
			'oli_occ_settings',
			$option_name,
			array(
				'type'              => 'string',
				'sanitize_callback' => $sanitize,
				'default'           => $default_value,
				'show_in_rest'      => false,
			)
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
		echo '<p>' . esc_html__( 'Protect WooCommerce classic and block checkout from bots and card testing. Choose Google reCAPTCHA v2, reCAPTCHA v3, or Cloudflare Turnstile.', 'oli-checkout-captcha' ) . '</p>';

		if ( $this->has_keys() ) {
			echo '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Captcha is active and protecting checkout.', 'oli-checkout-captcha' ) . '</strong></p></div>';
		} else {
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Enter your site key and secret key below to enable checkout protection.', 'oli-checkout-captcha' ) . '</strong></p></div>';
		}

		echo '<form method="post" action="options.php">';
		settings_fields( 'oli_occ_settings' );
		do_settings_sections( 'oli-checkout-captcha' );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the provider section description.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_provider_section() {
		echo '<p>' . esc_html__( 'Use one provider at a time. Scripts load only on the checkout page.', 'oli-checkout-captcha' ) . '</p>';
	}

	/**
	 * Render the provider field.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_provider_field() {
		$provider = $this->get_provider();
		$choices  = array(
			Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V2 => __( 'Google reCAPTCHA v2 (checkbox)', 'oli-checkout-captcha' ),
			Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V3 => __( 'Google reCAPTCHA v3 (score)', 'oli-checkout-captcha' ),
			Oli_OCC_Verifier::PROVIDER_TURNSTILE    => __( 'Cloudflare Turnstile', 'oli-checkout-captcha' ),
		);

		echo '<select id="oli_occ_provider" name="oli_occ_provider">';
		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $provider, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render the API keys section description.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Keys are stored in the WordPress options table and used only to verify checkout submissions with the selected provider. This plugin does not track shoppers.', 'oli-checkout-captcha' ) . '</p>';
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
			'<input type="text" id="oli_occ_site_key" name="oli_occ_site_key" value="%s" class="regular-text" autocomplete="off" />',
			esc_attr( $this->get_stored_option( 'oli_occ_site_key' ) )
		);

		$recaptcha_link = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( 'https://www.google.com/recaptcha/admin/create' ),
			esc_html__( 'Google reCAPTCHA Admin', 'oli-checkout-captcha' )
		);
		$turnstile_link = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( 'https://dash.cloudflare.com/?to=/:account/turnstile' ), // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Settings link to the documented Turnstile dashboard.
			esc_html__( 'Cloudflare Turnstile', 'oli-checkout-captcha' )
		);

		echo '<p class="description">';
		echo wp_kses(
			sprintf(
				/* translators: 1: Google reCAPTCHA admin link, 2: Cloudflare Turnstile dashboard link */
				__( 'Get reCAPTCHA keys from %1$s. Get Turnstile keys from %2$s.', 'oli-checkout-captcha' ),
				$recaptcha_link,
				$turnstile_link
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
			'<input type="text" id="oli_occ_secret_key" name="oli_occ_secret_key" value="%s" class="regular-text" autocomplete="off" />',
			esc_attr( $this->get_stored_option( 'oli_occ_secret_key' ) )
		);
	}

	/**
	 * Render the verification section description.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_verification_section() {
		echo '<p>' . esc_html__( 'Server-side verification runs on classic checkout and WooCommerce Store API (block) checkout.', 'oli-checkout-captcha' ) . '</p>';
	}

	/**
	 * Render the score threshold field.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_score_threshold_field() {
		printf(
			'<input type="number" id="oli_occ_score_threshold" name="oli_occ_score_threshold" value="%s" min="0" max="1" step="0.05" class="small-text" />',
			esc_attr( (string) $this->get_score_threshold() )
		);
		echo '<p class="description">' . esc_html__( 'Used only for reCAPTCHA v3. Requests scoring below this value (0.0–1.0) are rejected. Default is 0.5.', 'oli-checkout-captcha' ) . '</p>';
	}

	/**
	 * Render the fail-closed field.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_fail_closed_field() {
		echo '<input type="hidden" name="oli_occ_fail_closed" value="0" />';
		printf(
			'<label><input type="checkbox" id="oli_occ_fail_closed" name="oli_occ_fail_closed" value="1"%s /> %s</label>',
			checked( $this->is_fail_closed(), true, false ),
			esc_html__( 'Block checkout when the captcha provider cannot be reached.', 'oli-checkout-captcha' )
		);
		echo '<p class="description">' . esc_html__( 'When unchecked, a provider outage allows checkout to continue (fail open).', 'oli-checkout-captcha' ) . '</p>';
	}

	/**
	 * Render the rate-limit section description.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_rate_limit_section() {
		echo '<p>' . esc_html__( 'Optional. Failed checkout verifications can be limited per visitor IP. Successful checkouts clear the counter. IPs are hashed before storage.', 'oli-checkout-captcha' ) . '</p>';
	}

	/**
	 * Render the rate-limit enabled field.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_rate_limit_enabled_field() {
		echo '<input type="hidden" name="oli_occ_rate_limit_enabled" value="0" />';
		printf(
			'<label><input type="checkbox" id="oli_occ_rate_limit_enabled" name="oli_occ_rate_limit_enabled" value="1"%s /> %s</label>',
			checked( $this->is_rate_limit_enabled(), true, false ),
			esc_html__( 'Enable rate limiting of failed captcha attempts.', 'oli-checkout-captcha' )
		);
	}

	/**
	 * Render the rate-limit maximum field.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_rate_limit_max_field() {
		printf(
			'<input type="number" id="oli_occ_rate_limit_max" name="oli_occ_rate_limit_max" value="%s" min="1" step="1" class="small-text" />',
			esc_attr( (string) $this->get_rate_limit_max() )
		);
	}

	/**
	 * Render the rate-limit window field.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_rate_limit_window_field() {
		printf(
			'<input type="number" id="oli_occ_rate_limit_window" name="oli_occ_rate_limit_window" value="%s" min="60" step="1" class="small-text" />',
			esc_attr( (string) $this->get_rate_limit_window() )
		);
		echo '<p class="description">' . esc_html__( 'Number of seconds the failure counter is kept. Minimum 60.', 'oli-checkout-captcha' ) . '</p>';
	}

	/**
	 * Render the theme field.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_theme_field() {
		$theme   = $this->get_theme();
		$choices = array(
			'light' => __( 'Light', 'oli-checkout-captcha' ),
			'dark'  => __( 'Dark', 'oli-checkout-captcha' ),
			'auto'  => __( 'Auto (Turnstile only)', 'oli-checkout-captcha' ),
		);

		echo '<select id="oli_occ_theme" name="oli_occ_theme">';
		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $theme, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Used for reCAPTCHA v2 and Turnstile widgets. reCAPTCHA v3 is invisible.', 'oli-checkout-captcha' ) . '</p>';
	}

	/**
	 * Sanitize a captcha provider slug.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_provider( $value ) {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V2;
		}

		$value   = sanitize_key( wp_unslash( (string) $value ) );
		$allowed = array(
			Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V2,
			Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V3,
			Oli_OCC_Verifier::PROVIDER_TURNSTILE,
		);

		return in_array( $value, $allowed, true ) ? $value : Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V2;
	}

	/**
	 * Sanitize a provider API key.
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
		$allowed = array( 'light', 'dark', 'auto' );

		return in_array( $value, $allowed, true ) ? $value : 'light';
	}

	/**
	 * Sanitize the reCAPTCHA v3 score threshold.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_score_threshold( $value ) {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return '0.5';
		}

		$score = (float) sanitize_text_field( wp_unslash( (string) $value ) );
		if ( $score < 0 ) {
			$score = 0.0;
		}
		if ( $score > 1 ) {
			$score = 1.0;
		}

		return (string) round( $score, 2 );
	}

	/**
	 * Sanitize a checkbox stored as 1 or 0.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_checkbox( $value ) {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return '0';
		}

		return ( '1' === (string) $value || 1 === $value || true === $value ) ? '1' : '0';
	}

	/**
	 * Sanitize the maximum failed attempts.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_rate_limit_max( $value ) {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return '10';
		}

		return (string) max( 1, absint( $value ) );
	}

	/**
	 * Sanitize the rate-limit window in seconds.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_rate_limit_window( $value ) {
		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return '900';
		}

		return (string) max( 60, absint( $value ) );
	}

	/**
	 * Show an admin notice when WooCommerce is missing.
	 *
	 * Shown only on this plugin's settings screen.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_missing_woocommerce_notice() {
		if ( ! $this->is_plugin_settings_screen() ) {
			return;
		}

		if ( ! current_user_can( $this->get_settings_capability() ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Oli Checkout Captcha requires WooCommerce to be installed and active.', 'oli-checkout-captcha' );
		echo '</p></div>';
	}

	/**
	 * Enqueue checkout CSS and JavaScript only on the checkout page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_frontend_checkout() || ! $this->has_keys() ) {
			return;
		}

		wp_enqueue_style(
			'oli-occ-checkout',
			plugins_url( 'css/checkout.css', OLI_OCC_PLUGIN_FILE ),
			array(),
			OLI_OCC_VERSION
		);

		wp_register_script(
			'oli-occ-checkout',
			plugins_url( 'js/checkout.js', OLI_OCC_PLUGIN_FILE ),
			$this->get_checkout_script_dependencies(),
			OLI_OCC_VERSION,
			true
		);

		wp_localize_script(
			'oli-occ-checkout',
			'oliOccConfig',
			array(
				'siteKey'   => $this->get_stored_option( 'oli_occ_site_key' ),
				'theme'     => $this->get_theme(),
				'namespace' => self::EXTENSION_NAMESPACE,
				'provider'  => $this->get_provider(),
				'action'    => self::CAPTCHA_ACTION,
			)
		);

		wp_enqueue_script( 'oli-occ-checkout' );

		$provider = $this->get_provider();
		if ( Oli_OCC_Verifier::PROVIDER_TURNSTILE === $provider ) {
			wp_enqueue_script(
				'oli-occ-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=oliOccCaptchaOnLoad&render=explicit', // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Documented Turnstile widget script, loaded only on checkout.
				array( 'oli-occ-checkout' ),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Cloudflare Turnstile api.js rejects the WordPress ?ver= query argument.
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			return;
		}

		if ( Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V3 === $provider ) {
			wp_enqueue_script(
				'oli-occ-recaptcha',
				'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $this->get_stored_option( 'oli_occ_site_key' ) ),
				array( 'oli-occ-checkout' ),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google reCAPTCHA api.js also rejects unknown query arguments such as ?ver=.
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			return;
		}

		wp_enqueue_script(
			'oli-occ-recaptcha',
			'https://www.google.com/recaptcha/api.js?onload=oliOccCaptchaOnLoad&render=explicit',
			array( 'oli-occ-checkout' ),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google reCAPTCHA api.js also rejects unknown query arguments such as ?ver=.
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Render the classic checkout widget container and hidden token field.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_classic_widget() {
		if ( ! $this->has_keys() ) {
			return;
		}

		$provider = $this->get_provider();
		$classes  = 'oli-occ-captcha-container';
		if ( Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V3 === $provider ) {
			$classes .= ' oli-occ-captcha-container--v3';
		}

		echo '<div id="oli-occ-captcha-container" class="' . esc_attr( $classes ) . '">';
		echo '<div id="oli-occ-captcha-widget"></div>';
		echo '<input type="hidden" name="oli_occ_captcha_token" id="oli-occ-captcha-token" value="" />';
		echo '</div>';
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
				'description' => __( 'Checkout captcha token', 'oli-checkout-captcha' ),
				'type'        => 'string',
				'readonly'    => false,
				'context'     => array( 'view', 'edit' ),
			),
		);
	}

	/**
	 * Validate the captcha token on classic shortcode checkout.
	 *
	 * WooCommerce verifies its checkout nonce before this hook runs.
	 * Guests must be able to check out, so a manage_options check is not applied.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function validate_classic_checkout() {
		if ( $this->is_express_checkout( null ) ) {
			return;
		}

		$result = $this->verify_posted_token( $this->get_token_from_post() );
		if ( $result['ok'] ) {
			return;
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( esc_html( $result['message'] ), 'error' );
		}
	}

	/**
	 * Validate the captcha token when the Store API creates an order.
	 *
	 * This is a WooCommerce Store API checkout callback, not a custom REST route.
	 * Only POST (place order) is validated so draft checkout updates are not blocked.
	 * Guests must be able to check out, so a manage_options capability check is not applied.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order        $order   Order being updated.
	 * @param WP_REST_Request $request Store API request.
	 * @return WC_Order
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When verification fails.
	 * @throws \Exception When verification fails and RouteException is unavailable.
	 */
	public function validate_store_api_checkout( $order, $request ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_method' ) || 'POST' !== $request->get_method() ) {
			return $order;
		}

		if ( $this->is_express_checkout( $request ) ) {
			return $order;
		}

		$result = $this->verify_posted_token( $this->get_token_from_request( $request ) );
		if ( $result['ok'] ) {
			return $order;
		}

		$this->throw_store_api_failure( $result['message'] );
	}

	/**
	 * Abort a Store API checkout with HTTP 400 instead of a generic 500.
	 *
	 * @since 1.2.2
	 *
	 * @param string $message Translated failure message.
	 * @return void
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When the Store API exception class exists.
	 * @throws \Exception When RouteException is unavailable (older WooCommerce).
	 */
	private function throw_store_api_failure( $message ) {
		if ( class_exists( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException::class ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'oli_occ_captcha_failed', esc_html( $message ), 400 );
		}

		throw new \Exception( esc_html( $message ), 400 );
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

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', OLI_OCC_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', OLI_OCC_PLUGIN_FILE, true );
	}

	/**
	 * Run rate-limit and provider verification for a token.
	 *
	 * @since 1.2.0
	 *
	 * @param string $token Captcha token.
	 * @return array{ok:bool, message:string, score:float|null, http_failed:bool}
	 */
	private function verify_posted_token( $token ) {
		$ip = $this->get_remote_ip();

		if ( $this->rate_limiter->is_limited( $ip, $this->get_rate_limit_max(), $this->get_rate_limit_window() ) ) {
			return array(
				'ok'          => false,
				'message'     => __( 'Too many failed captcha attempts. Please wait and try again.', 'oli-checkout-captcha' ),
				'score'       => null,
				'http_failed' => false,
			);
		}

		$result = $this->verifier->verify( $token, $this->get_verify_settings() );

		if ( ! $result['ok'] ) {
			$this->rate_limiter->record_failure( $ip, $this->get_rate_limit_window() );
			return $result;
		}

		$this->rate_limiter->clear( $ip );

		return $result;
	}

	/**
	 * Settings passed to the verifier.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_verify_settings() {
		return array(
			'provider'        => $this->get_provider(),
			'secret'          => $this->get_stored_option( 'oli_occ_secret_key' ),
			'remote_ip'       => $this->get_remote_ip(),
			'score_threshold' => $this->get_score_threshold(),
			'expected_action' => self::CAPTCHA_ACTION,
			'fail_closed'     => $this->is_fail_closed(),
		);
	}

	/**
	 * Whether both API keys are configured.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private function has_keys() {
		return ( '' !== $this->get_stored_option( 'oli_occ_site_key' ) && '' !== $this->get_stored_option( 'oli_occ_secret_key' ) );
	}

	/**
	 * Get a stored string option.
	 *
	 * @since 1.2.0
	 *
	 * @param string $option_name Option name.
	 * @param string $default_value Default value.
	 * @return string
	 */
	private function get_stored_option( $option_name, $default_value = '' ) {
		$value = get_option( $option_name, $default_value );
		return is_string( $value ) ? $value : $default_value;
	}

	/**
	 * Get the selected captcha provider.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	private function get_provider() {
		$provider = sanitize_key( $this->get_stored_option( 'oli_occ_provider', Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V2 ) );
		$allowed  = array(
			Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V2,
			Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V3,
			Oli_OCC_Verifier::PROVIDER_TURNSTILE,
		);

		return in_array( $provider, $allowed, true ) ? $provider : Oli_OCC_Verifier::PROVIDER_RECAPTCHA_V2;
	}

	/**
	 * Get the sanitized widget theme.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function get_theme() {
		$theme   = sanitize_key( $this->get_stored_option( 'oli_occ_theme', 'light' ) );
		$allowed = array( 'light', 'dark', 'auto' );

		if ( ! in_array( $theme, $allowed, true ) ) {
			return 'light';
		}

		if ( 'auto' === $theme && Oli_OCC_Verifier::PROVIDER_TURNSTILE !== $this->get_provider() ) {
			return 'light';
		}

		return $theme;
	}

	/**
	 * Get the reCAPTCHA v3 score threshold.
	 *
	 * @since 1.2.0
	 *
	 * @return float
	 */
	private function get_score_threshold() {
		$score = (float) $this->get_stored_option( 'oli_occ_score_threshold', '0.5' );
		if ( $score < 0 ) {
			return 0.0;
		}
		if ( $score > 1 ) {
			return 1.0;
		}

		return $score;
	}

	/**
	 * Whether verification should fail closed on provider outages.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	private function is_fail_closed() {
		return ( '1' === $this->get_stored_option( 'oli_occ_fail_closed', '1' ) );
	}

	/**
	 * Whether failed-attempt rate limiting is enabled.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	private function is_rate_limit_enabled() {
		return ( '1' === $this->get_stored_option( 'oli_occ_rate_limit_enabled', '0' ) );
	}

	/**
	 * Maximum failed attempts in the window.
	 *
	 * @since 1.2.0
	 *
	 * @return int
	 */
	private function get_rate_limit_max() {
		return max( 1, absint( $this->get_stored_option( 'oli_occ_rate_limit_max', '10' ) ) );
	}

	/**
	 * Rate-limit window in seconds.
	 *
	 * @since 1.2.0
	 *
	 * @return int
	 */
	private function get_rate_limit_window() {
		return max( 60, absint( $this->get_stored_option( 'oli_occ_rate_limit_window', '900' ) ) );
	}

	/**
	 * Script dependencies for checkout.js.
	 *
	 * Block checkout uses wp.data and the WooCommerce checkout store.
	 * Those handles are declared only when registered so classic checkout
	 * still loads if WooCommerce Blocks is not present.
	 *
	 * @since 1.2.3
	 *
	 * @return array<int, string>
	 */
	private function get_checkout_script_dependencies() {
		$deps = array( 'jquery' );

		if ( ! $this->is_block_checkout() ) {
			return $deps;
		}

		$optional_deps = array( 'wp-data', 'wc-blocks-data-store' );
		foreach ( $optional_deps as $handle ) {
			if ( function_exists( 'wp_script_is' ) && wp_script_is( $handle, 'registered' ) ) {
				$deps[] = $handle;
			}
		}

		return $deps;
	}

	/**
	 * Whether the current checkout page uses the WooCommerce checkout block.
	 *
	 * @since 1.2.3
	 *
	 * @return bool
	 */
	private function is_block_checkout() {
		$utils = 'Automattic\\WooCommerce\\Blocks\\Utils\\CartCheckoutUtils';
		if ( class_exists( $utils ) && is_callable( array( $utils, 'is_checkout_block_default' ) ) && $utils::is_checkout_block_default() ) {
			return true;
		}

		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}

		if ( has_block( 'woocommerce/checkout' ) ) {
			return true;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$checkout_page_id = wc_get_page_id( 'checkout' );
			if ( $checkout_page_id > 0 && has_block( 'woocommerce/checkout', $checkout_page_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current request is the shopper checkout page.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	private function is_frontend_checkout() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return false;
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return false;
		}

		if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the current admin request is this plugin's settings screen.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	private function is_plugin_settings_screen() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && isset( $screen->id ) && 'settings_page_oli-checkout-captcha' === $screen->id ) {
				return true;
			}
		}

		// Core sets $plugin_page from the sanitized admin.php request before admin_enqueue_scripts.
		global $plugin_page;

		return ( is_string( $plugin_page ) && 'oli-checkout-captcha' === $plugin_page );
	}

	/**
	 * Read the captcha token from classic checkout POST data.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	private function get_token_from_post() {
		// Classic checkout: same nonce WooCommerce checks before woocommerce_checkout_process.
		if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ), 'woocommerce-process_checkout' ) ) {
			return '';
		}

		$keys = array( 'oli_occ_captcha_token', 'g-recaptcha-response', 'cf-turnstile-response' );

		foreach ( $keys as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$token = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			if ( '' !== $token ) {
				return $token;
			}
		}

		return '';
	}

	/**
	 * Read the captcha token from the Store API request.
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
	 * Whether this checkout request is an express/wallet payment.
	 *
	 * Express buttons (Apple Pay, Google Pay, Payment Request) must not be
	 * blocked by a captcha widget the shopper never sees.
	 *
	 * @since 1.2.0
	 *
	 * @param WP_REST_Request|null $request Optional Store API request.
	 * @return bool
	 */
	private function is_express_checkout( $request ) {
		$skip = true;
		if ( has_filter( 'olic_skip_express_payments' ) ) {
			$skip = apply_filters_deprecated( 'olic_skip_express_payments', array( $skip, $request ), '1.2.6', 'oli_occ_skip_express_payments' );
		}

		/**
		 * Whether express payments (Apple Pay, Google Pay...) skip the captcha.
		 *
		 * @since 1.2.6 Previously olic_skip_express_payments (deprecated, still applied first).
		 *
		 * @param bool                 $skip    Default true.
		 * @param WP_REST_Request|null $request Store API request, or null on classic checkout.
		 */
		if ( ! apply_filters( 'oli_occ_skip_express_payments', $skip, $request ) ) {
			return false;
		}

		$method = $this->get_payment_method( $request );
		$ids    = array(
			'apple_pay',
			'google_pay',
			'stripe_applepay',
			'stripe_googlepay',
			'stripe_payment_request',
			'payment_request',
			'woocommerce_payments_apple_pay',
			'woocommerce_payments_google_pay',
			'ppcp-applepay',
			'ppcp-googlepay',
			'amazon_payments_advanced',
		);
		if ( has_filter( 'olic_express_payment_ids' ) ) {
			$ids = apply_filters_deprecated( 'olic_express_payment_ids', array( $ids, $request ), '1.2.6', 'oli_occ_express_payment_ids' );
		}

		/**
		 * Payment method IDs treated as express payments.
		 *
		 * @since 1.2.6 Previously olic_express_payment_ids (deprecated, still applied first).
		 *
		 * @param string[]             $ids     Gateway IDs.
		 * @param WP_REST_Request|null $request Store API request, or null on classic checkout.
		 */
		$ids = (array) apply_filters( 'oli_occ_express_payment_ids', $ids, $request );

		if ( '' !== $method && in_array( $method, $ids, true ) ) {
			return true;
		}

		$data           = $this->get_payment_data_map( $request );
		$express_values = array(
			'1',
			'true',
			'yes',
			'apple_pay',
			'google_pay',
			'apple-pay',
			'google-pay',
			'payment_request',
			'express',
		);

		$flag_keys = array(
			'paymentRequestType',
			'payment_request_type',
			'express_payment_type',
			'expressPaymentType',
			'wc-stripe-is-express-checkout',
			'isExpressCheckout',
			'is_express',
			'isExpress',
		);

		foreach ( $flag_keys as $key ) {
			if ( empty( $data[ $key ] ) ) {
				continue;
			}

			$value = strtolower( (string) $data[ $key ] );
			if ( in_array( $value, $express_values, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read the payment method id from the Store API request or classic POST.
	 *
	 * @since 1.2.0
	 *
	 * @param WP_REST_Request|null $request Optional Store API request.
	 * @return string
	 */
	private function get_payment_method( $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$method = $request->get_param( 'payment_method' );
			return is_string( $method ) ? sanitize_text_field( $method ) : '';
		}

		if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ), 'woocommerce-process_checkout' ) ) {
			return '';
		}

		if ( ! isset( $_POST['payment_method'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST['payment_method'] ) );
	}

	/**
	 * Flatten Store API payment_data and relevant POST flags into a map.
	 *
	 * @since 1.2.0
	 *
	 * @param WP_REST_Request|null $request Optional Store API request.
	 * @return array<string, string>
	 */
	private function get_payment_data_map( $request ) {
		$map = array();

		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$payment_data = $request->get_param( 'payment_data' );
			if ( is_array( $payment_data ) ) {
				foreach ( $payment_data as $entry ) {
					if ( ! is_array( $entry ) || empty( $entry['key'] ) || ! is_string( $entry['key'] ) ) {
						continue;
					}

					$key         = (string) $entry['key'];
					$map[ $key ] = isset( $entry['value'] ) ? sanitize_text_field( (string) $entry['value'] ) : '';
				}
			}
		}

		if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ), 'woocommerce-process_checkout' ) ) {
			return $map;
		}

		$post_keys = array(
			'paymentRequestType',
			'payment_request_type',
			'wc-stripe-is-express-checkout',
			'is_express',
		);

		foreach ( $post_keys as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$map[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}

		return $map;
	}

	/**
	 * Sanitize the visitor IP used for siteverify and rate limiting.
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
