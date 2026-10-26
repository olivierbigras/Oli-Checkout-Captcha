<?php
/**
 * Checkout script enqueue tests (B2, B3).
 *
 * @package Olic_Checkout_Captcha
 */

namespace Automattic\WooCommerce\Blocks\Utils {

	/**
	 * Toggleable stand-in for WooCommerce CartCheckoutUtils.
	 */
	class CartCheckoutUtils {

		/**
		 * Whether the checkout block is the store default.
		 *
		 * @var bool
		 */
		public static $is_checkout_block_default = false;

		/**
		 * @return bool
		 */
		public static function is_checkout_block_default() {
			return ! empty( self::$is_checkout_block_default );
		}
	}
}

namespace {

	use PHPUnit\Framework\TestCase;

	/**
	 * Covers checkout.js dependencies and Turnstile script version.
	 */
	class EnqueueScriptsTest extends TestCase {

		/**
		 * Plugin instance.
		 *
		 * @var \Olic_Checkout_Captcha
		 */
		private $plugin;

		/**
		 * Reset enqueue stubs and checkout flags.
		 *
		 * @return void
		 */
		protected function setUp(): void {
			parent::setUp();

			$GLOBALS['olic_test_registered_scripts'] = array();
			$GLOBALS['olic_test_enqueued_scripts']   = array();
			$GLOBALS['olic_test_enqueued_styles']    = array();
			$GLOBALS['olic_test_localized_scripts']  = array();
			$GLOBALS['olic_test_is_checkout']        = true;
			$GLOBALS['olic_test_is_order_received']  = false;
			$GLOBALS['olic_test_is_checkout_pay']    = false;
			$GLOBALS['olic_test_has_blocks']         = array();
			$GLOBALS['olic_test_has_blocks_by_page'] = array();
			$GLOBALS['olic_test_checkout_page_id']   = 0;
			$GLOBALS['olic_test_options']            = array(
				'olic_site_key'   => 'test-site-key',
				'olic_secret_key' => 'test-secret-key',
				'olic_provider'   => 'recaptcha_v2',
			);

			\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::$is_checkout_block_default = false;

			if ( ! defined( 'OLIC_PLUGIN_LOADED' ) ) {
				require dirname( __DIR__ ) . '/oli-checkout-captcha.php';
			}

			$this->plugin = \Olic_Checkout_Captcha::get_instance();
		}

		/**
		 * Classic checkout keeps jquery only when the blocks store is absent.
		 *
		 * @return void
		 */
		public function test_classic_checkout_script_depends_on_jquery_only_when_blocks_store_missing() {
			$this->plugin->enqueue_scripts();

			$this->assertSame( array( 'jquery' ), $this->registered_deps( 'olic-checkout' ) );
			$this->assertContains( 'olic-checkout', $GLOBALS['olic_test_enqueued_scripts'] );
		}

		/**
		 * Classic checkout must not depend on wc-blocks-data-store even if registered.
		 *
		 * @return void
		 */
		public function test_classic_checkout_omits_blocks_store_even_when_registered() {
			$this->register_script( 'wp-data' );
			$this->register_script( 'wc-blocks-data-store' );

			$this->plugin->enqueue_scripts();

			$this->assertSame( array( 'jquery' ), $this->registered_deps( 'olic-checkout' ) );
			$this->assertNotContains( 'wc-blocks-data-store', $this->registered_deps( 'olic-checkout' ) );
			$this->assertNotContains( 'wp-data', $this->registered_deps( 'olic-checkout' ) );
		}

		/**
		 * Block checkout declares wp-data and wc-blocks-data-store when registered.
		 *
		 * @return void
		 */
		public function test_block_checkout_declares_registered_blocks_store_deps() {
			$GLOBALS['olic_test_has_blocks'] = array( 'woocommerce/checkout' );
			$this->register_script( 'wp-data' );
			$this->register_script( 'wc-blocks-data-store' );

			$this->plugin->enqueue_scripts();

			$this->assertSame(
				array( 'jquery', 'wp-data', 'wc-blocks-data-store' ),
				$this->registered_deps( 'olic-checkout' )
			);
		}

		/**
		 * Block checkout still enqueues when wc-blocks-data-store is not registered.
		 *
		 * @return void
		 */
		public function test_block_checkout_omits_unregistered_blocks_store() {
			$GLOBALS['olic_test_has_blocks'] = array( 'woocommerce/checkout' );
			$this->register_script( 'wp-data' );

			$this->plugin->enqueue_scripts();

			$deps = $this->registered_deps( 'olic-checkout' );
			$this->assertSame( array( 'jquery', 'wp-data' ), $deps );
			$this->assertNotContains( 'wc-blocks-data-store', $deps );
			$this->assertContains( 'olic-checkout', $GLOBALS['olic_test_enqueued_scripts'] );
		}

		/**
		 * Checkout page id with the checkout block also declares the store deps.
		 *
		 * @return void
		 */
		public function test_block_checkout_detected_from_checkout_page_id() {
			$GLOBALS['olic_test_checkout_page_id']   = 37;
			$GLOBALS['olic_test_has_blocks_by_page'] = array(
				37 => array( 'woocommerce/checkout' ),
			);
			$this->register_script( 'wp-data' );
			$this->register_script( 'wc-blocks-data-store' );

			$this->plugin->enqueue_scripts();

			$this->assertSame(
				array( 'jquery', 'wp-data', 'wc-blocks-data-store' ),
				$this->registered_deps( 'olic-checkout' )
			);
		}

		/**
		 * WooCommerce CartCheckoutUtils default-block stores also get the deps.
		 *
		 * @return void
		 */
		public function test_block_checkout_detected_from_cart_checkout_utils() {
			\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::$is_checkout_block_default = true;
			$this->register_script( 'wp-data' );
			$this->register_script( 'wc-blocks-data-store' );

			$this->plugin->enqueue_scripts();

			$this->assertSame(
				array( 'jquery', 'wp-data', 'wc-blocks-data-store' ),
				$this->registered_deps( 'olic-checkout' )
			);
		}

		/**
		 * Turnstile api.js must be enqueued without a ?ver= query argument.
		 *
		 * @return void
		 */
		public function test_turnstile_api_js_has_null_version() {
			$GLOBALS['olic_test_options']['olic_provider'] = 'turnstile';

			$this->plugin->enqueue_scripts();

			$this->assertArrayHasKey( 'olic-turnstile', $GLOBALS['olic_test_registered_scripts'] );
			$this->assertNull( $GLOBALS['olic_test_registered_scripts']['olic-turnstile']['ver'] );
			$this->assertStringContainsString(
				'challenges.cloudflare.com/turnstile/v0/api.js',
				$GLOBALS['olic_test_registered_scripts']['olic-turnstile']['src']
			);
			$this->assertContains( 'olic-turnstile', $GLOBALS['olic_test_enqueued_scripts'] );
		}

		/**
		 * reCAPTCHA scripts keep a plugin version (Turnstile-only change).
		 *
		 * @return void
		 */
		public function test_recaptcha_script_keeps_plugin_version() {
			$this->plugin->enqueue_scripts();

			$this->assertArrayHasKey( 'olic-recaptcha', $GLOBALS['olic_test_registered_scripts'] );
			$this->assertSame( OLIC_VERSION, $GLOBALS['olic_test_registered_scripts']['olic-recaptcha']['ver'] );
		}

		/**
		 * @param string $handle Script handle.
		 * @return array<int, string>
		 */
		private function registered_deps( $handle ) {
			$this->assertArrayHasKey( $handle, $GLOBALS['olic_test_registered_scripts'] );
			return $GLOBALS['olic_test_registered_scripts'][ $handle ]['deps'];
		}

		/**
		 * @param string $handle Script handle.
		 * @return void
		 */
		private function register_script( $handle ) {
			$GLOBALS['olic_test_registered_scripts'][ $handle ] = array(
				'src'  => 'https://example.test/' . $handle . '.js',
				'deps' => array(),
				'ver'  => '1.0.0',
				'args' => false,
			);
		}
	}
}
