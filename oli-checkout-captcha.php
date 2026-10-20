<?php
/**
 * Plugin Name: Oli Checkout Captcha – reCAPTCHA v2 for WooCommerce Block Checkout
 * Plugin URI: https://github.com/olivierbigras/Oli-Checkout-Captcha
 * Description: Adds Google reCAPTCHA v2 protection to WooCommerce block-based checkout to prevent spam orders.
 * Version: 1.1.0
 * Author: Olivier Bigras
 * Author URI: https://olivierbigras.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.2
 * Text Domain: oli-checkout-captcha
 * Domain Path: /languages
 *
 * @package Oli_Checkout_Captcha
 */

defined( 'ABSPATH' ) || exit;

define( 'OLI_CC_VERSION', '1.1.0' );
define( 'OLI_CC_PLUGIN_FILE', __FILE__ );
define( 'OLI_CC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OLI_CC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declares WooCommerce feature compatibility.
 *
 * Must run on before_woocommerce_init (before plugins_loaded).
 *
 * @since 1.1.0
 * @return void
 */
function oli_cc_declare_compatibility() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', OLI_CC_PLUGIN_FILE, true );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', OLI_CC_PLUGIN_FILE, true );
}
add_action( 'before_woocommerce_init', 'oli_cc_declare_compatibility' );

/**
 * Bootstraps the plugin after WordPress (and other plugins) have loaded.
 *
 * @since 1.1.0
 * @return void
 */
function oli_cc_bootstrap() {
	Oli_Checkout_Captcha::get_instance();
}
add_action( 'plugins_loaded', 'oli_cc_bootstrap' );

/**
 * Adds Google reCAPTCHA v2 to WooCommerce block checkout.
 *
 * @since 1.0.0
 */
class Oli_Checkout_Captcha {

	/**
	 * Store API / JS namespace used from 1.1.0.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const NAMESPACE = 'oli-checkout-captcha';

	/**
	 * Legacy Store API namespace from 1.0.0 (Dimex / cached JS fallback).
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const LEGACY_NAMESPACE = 'recaptcha-block-checkout';

	/**
	 * Singleton instance.
	 *
	 * @var Oli_Checkout_Captcha|null
	 */
	private static $instance = null;

	/**
	 * Public reCAPTCHA site key.
	 *
	 * @var string
	 */
	private $site_key = '';

	/**
	 * Private reCAPTCHA secret key (never sent to the browser).
	 *
	 * @var string
	 */
	private $secret_key = '';

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 * @return Oli_Checkout_Captcha
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks admin UI always; checkout protection only when WooCommerce and keys exist.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->maybe_migrate_legacy_options();

		$this->site_key   = $this->get_site_key();
		$this->secret_key = $this->get_secret_key();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( OLI_CC_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );

		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		if ( $this->site_key && $this->secret_key ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'woocommerce_blocks_loaded', array( $this, 'register_endpoint_data' ) );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_checkout' ), 10, 2 );
		}
	}

	/**
	 * Loads translations from /languages when not served by WordPress.org.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'oli-checkout-captcha',
			false,
			dirname( plugin_basename( OLI_CC_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @since 1.0.0
	 * @param array $links Existing plugin action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		$url = admin_url( 'options-general.php?page=oli-checkout-captcha' );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'oli-checkout-captcha' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Registers the Settings → Oli Checkout Captcha page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Oli Checkout Captcha', 'oli-checkout-captcha' ),
			__( 'Oli Checkout Captcha', 'oli-checkout-captcha' ),
			'manage_options',
			'oli-checkout-captcha',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Registers settings with sanitization. Settings API outputs the nonce via settings_fields().
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings() {
		$args = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => false,
		);

		register_setting( 'oli_cc_settings', 'oli_cc_site_key', $args );
		register_setting( 'oli_cc_settings', 'oli_cc_secret_key', $args );
		register_setting(
			'oli_cc_settings',
			'oli_cc_theme',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_theme' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'oli_cc_settings_section',
			__( 'API keys', 'oli-checkout-captcha' ),
			array( $this, 'render_settings_section' ),
			'oli-checkout-captcha'
		);

		add_settings_field(
			'oli_cc_site_key',
			__( 'Site Key', 'oli-checkout-captcha' ),
			array( $this, 'render_site_key_field' ),
			'oli-checkout-captcha',
			'oli_cc_settings_section'
		);

		add_settings_field(
			'oli_cc_secret_key',
			__( 'Secret Key', 'oli-checkout-captcha' ),
			array( $this, 'render_secret_key_field' ),
			'oli-checkout-captcha',
			'oli_cc_settings_section'
		);

		add_settings_field(
			'oli_cc_theme',
			__( 'Theme', 'oli-checkout-captcha' ),
			array( $this, 'render_theme_field' ),
			'oli-checkout-captcha',
			'oli_cc_settings_section'
		);
	}

	/**
	 * Restricts the widget theme to supported values.
	 *
	 * @since 1.1.0
	 * @param mixed $value Submitted theme.
	 * @return string
	 */
	public function sanitize_theme( $value ) {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, array( 'light', 'dark' ), true ) ? $value : 'light';
	}

	/**
	 * Intro text for the settings section.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Use Google reCAPTCHA v2 ("I\'m not a robot" Checkbox) keys. The secret key is stored in your WordPress database and is never sent to the browser.', 'oli-checkout-captcha' ) . '</p>';
	}

	/**
	 * Renders the site key field.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function render_site_key_field() {
		$value = $this->get_site_key();
		?>
		<input type="text" name="oli_cc_site_key" id="oli_cc_site_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off" />
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: HTML link to the Google reCAPTCHA admin console. */
					__( 'Get your keys from %s. Choose reCAPTCHA v2 "I\'m not a robot" Checkbox.', 'oli-checkout-captcha' ),
					'<a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google reCAPTCHA Admin', 'oli-checkout-captcha' ) . '</a>'
				),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Renders the secret key field.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function render_secret_key_field() {
		?>
		<input type="text" name="oli_cc_secret_key" id="oli_cc_secret_key" value="<?php echo esc_attr( $this->get_secret_key() ); ?>" class="regular-text" autocomplete="off" />
		<?php
	}

	/**
	 * Renders the theme select field.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function render_theme_field() {
		$theme = $this->get_theme();
		?>
		<select name="oli_cc_theme" id="oli_cc_theme">
			<option value="light" <?php selected( $theme, 'light' ); ?>><?php esc_html_e( 'Light', 'oli-checkout-captcha' ); ?></option>
			<option value="dark" <?php selected( $theme, 'dark' ); ?>><?php esc_html_e( 'Dark', 'oli-checkout-captcha' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Add Google reCAPTCHA v2 to your WooCommerce block checkout to prevent spam orders.', 'oli-checkout-captcha' ); ?></p>

			<?php if ( $this->get_site_key() && $this->get_secret_key() ) : ?>
				<div class="notice notice-success inline"><p><strong><?php esc_html_e( 'reCAPTCHA is active and protecting your checkout.', 'oli-checkout-captcha' ); ?></strong></p></div>
			<?php else : ?>
				<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Please configure your reCAPTCHA API keys below.', 'oli-checkout-captcha' ); ?></strong></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'oli_cc_settings' );
				do_settings_sections( 'oli-checkout-captcha' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Shows an admin notice when WooCommerce is not active.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Oli Checkout Captcha requires WooCommerce to be installed and active.', 'oli-checkout-captcha' );
		echo '</p></div>';
	}

	/**
	 * Enqueues Google reCAPTCHA and the checkout integration on the checkout page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		$recaptcha_url = add_query_arg(
			array(
				'onload' => 'oliCcRecaptchaOnLoad',
				'render' => 'explicit',
			),
			'https://www.google.com/recaptcha/api.js'
		);

		wp_enqueue_script(
			'oli-cc-google-recaptcha',
			$recaptcha_url,
			array(),
			OLI_CC_VERSION,
			true
		);

		wp_enqueue_script(
			'oli-cc-checkout',
			plugins_url( 'js/checkout.js', OLI_CC_PLUGIN_FILE ),
			array( 'oli-cc-google-recaptcha', 'wp-data' ),
			OLI_CC_VERSION,
			true
		);

		wp_localize_script(
			'oli-cc-checkout',
			'oliCcConfig',
			array(
				'siteKey'   => $this->get_site_key(),
				'theme'     => $this->get_theme(),
				'namespace' => self::NAMESPACE,
			)
		);
	}

	/**
	 * Registers Store API checkout extension data for the reCAPTCHA token.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_endpoint_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		$schema = function () {
			return array(
				'token' => array(
					'description' => __( 'reCAPTCHA token', 'oli-checkout-captcha' ),
					'type'        => 'string',
					'readonly'    => false,
				),
			);
		};

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => self::NAMESPACE,
				'schema_callback' => $schema,
			)
		);

		// Keep the 1.0.0 namespace so a cached checkout.js still posts a readable token.
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => self::LEGACY_NAMESPACE,
				'schema_callback' => $schema,
			)
		);
	}

	/**
	 * Verifies the reCAPTCHA token with Google before the order is created.
	 *
	 * @since 1.0.0
	 * @param mixed           $order   Order object being updated.
	 * @param WP_REST_Request $request Store API request.
	 * @return mixed
	 * @throws \Exception When the token is missing or Google rejects it.
	 */
	public function validate_checkout( $order, $request ) {
		if ( ! is_a( $request, 'WP_REST_Request' ) || 'POST' !== $request->get_method() ) {
			return $order;
		}

		$token = $this->get_token_from_request( $request );

		if ( '' === $token ) {
			throw new \Exception( esc_html__( 'Please complete the reCAPTCHA verification.', 'oli-checkout-captcha' ) );
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 15,
				'body'    => array(
					'secret'   => $this->get_secret_key(),
					'response' => $token,
					'remoteip' => $this->get_remote_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html__( 'reCAPTCHA verification failed. Please try again.', 'oli-checkout-captcha' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['success'] ) ) {
			throw new \Exception( esc_html__( 'reCAPTCHA verification failed. Please complete the challenge again.', 'oli-checkout-captcha' ) );
		}

		return $order;
	}

	/**
	 * Copies 1.0.0 rbc_* options into oli_cc_* when the new keys are absent.
	 *
	 * Existing Dimex (and other 1.0.0) installs keep working without re-entering keys.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function maybe_migrate_legacy_options() {
		$map = array(
			'rbc_site_key'   => 'oli_cc_site_key',
			'rbc_secret_key' => 'oli_cc_secret_key',
			'rbc_theme'      => 'oli_cc_theme',
		);

		foreach ( $map as $legacy_name => $new_name ) {
			if ( $this->option_is_present( $new_name ) ) {
				continue;
			}

			if ( ! $this->option_is_present( $legacy_name ) ) {
				continue;
			}

			add_option( $new_name, get_option( $legacy_name ) );
		}
	}

	/**
	 * Whether WooCommerce is available.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Public site key, falling back to the 1.0.0 option name.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	private function get_site_key() {
		return $this->get_option_with_legacy( 'oli_cc_site_key', 'rbc_site_key' );
	}

	/**
	 * Secret key, falling back to the 1.0.0 option name.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	private function get_secret_key() {
		return $this->get_option_with_legacy( 'oli_cc_secret_key', 'rbc_secret_key' );
	}

	/**
	 * Widget theme, falling back to the 1.0.0 option name.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	private function get_theme() {
		return $this->sanitize_theme( $this->get_option_with_legacy( 'oli_cc_theme', 'rbc_theme', 'light' ) );
	}

	/**
	 * Reads a setting, using the 1.0.0 name only when the 1.1.0 option is not stored yet.
	 *
	 * @since 1.1.0
	 * @param string $new_name     Current option name.
	 * @param string $legacy_name  1.0.0 option name.
	 * @param string $default_value Default when neither option exists.
	 * @return string
	 */
	private function get_option_with_legacy( $new_name, $legacy_name, $default_value = '' ) {
		if ( $this->option_is_present( $new_name ) ) {
			return (string) get_option( $new_name, $default_value );
		}

		if ( $this->option_is_present( $legacy_name ) ) {
			return (string) get_option( $legacy_name, $default_value );
		}

		return $default_value;
	}

	/**
	 * Detects a stored option without treating an empty string as missing.
	 *
	 * @since 1.1.0
	 * @param string $option_name Option name.
	 * @return bool
	 */
	private function option_is_present( $option_name ) {
		$missing = new stdClass();
		$value   = get_option( $option_name, $missing );

		return $missing !== $value;
	}

	/**
	 * Reads the token from the current or legacy Store API extension namespace.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Store API request.
	 * @return string
	 */
	private function get_token_from_request( $request ) {
		$extensions = $request->get_param( 'extensions' );

		if ( ! is_array( $extensions ) ) {
			return '';
		}

		foreach ( array( self::NAMESPACE, self::LEGACY_NAMESPACE ) as $namespace ) {
			if ( empty( $extensions[ $namespace ]['token'] ) ) {
				continue;
			}

			return sanitize_text_field( wp_unslash( $extensions[ $namespace ]['token'] ) );
		}

		return '';
	}

	/**
	 * Sanitized visitor IP for Google's siteverify `remoteip` field.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	private function get_remote_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
}
