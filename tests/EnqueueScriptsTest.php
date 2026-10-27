<?php
/**
 * Checkout script enqueue tests (B2, B3).
 *
 * @package Oli_OCC_Checkout_Captcha
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
		 * @var \Oli_OCC_Checkout_Captcha
		 */
		private $plugin;

		/**
		 * Reset enqueue stubs and checkout flags.
		 *
		 * @return void
		 */
		protected function setUp(): void {
			parent::setUp();

			$GLOBALS['oli_occ_test_registered_scripts'] = array();
			$GLOBALS['oli_occ_test_enqueued_scripts']   = array();
			$GLOBALS['oli_occ_test_enqueued_styles']    = array();
			$GLOBALS['oli_occ_test_localized_scripts']  = array();
			$GLOBALS['oli_occ_test_is_checkout']        = true;
			$GLOBALS['oli_occ_test_is_order_received']  = false;
			$GLOBALS['oli_occ_test_is_checkout_pay']    = false;
			$GLOBALS['oli_occ_test_has_blocks']         = array();
			$GLOBALS['oli_occ_test_has_blocks_by_page'] = array();
			$GLOBALS['oli_occ_test_checkout_page_id']   = 0;
			$GLOBALS['oli_occ_test_options']            = array(
				'oli_occ_site_key'   => 'test-site-key',
				'oli_occ_secret_key' => 'test-secret-key',
				'oli_occ_provider'   => 'recaptcha_v2',
			);

			\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::$is_checkout_block_default = false;

			if ( ! defined( 'OLI_OCC_PLUGIN_LOADED' ) ) {
				require dirname( __DIR__ ) . '/oli-checkout-captcha.php';
			}

			$this->plugin = \Oli_OCC_Checkout_Captcha::get_instance();
		}

		/**
		 * Classic checkout keeps jquery only when the blocks store is absent.
		 *
		 * @return void
		 */
		public function test_classic_checkout_script_depends_on_jquery_only_when_blocks_store_missing() {
			$this->plugin->enqueue_scripts();

			$this->assertSame( array( 'jquery' ), $this->registered_deps( 'oli-occ-checkout' ) );
			$this->assertContains( 'oli-occ-checkout', $GLOBALS['oli_occ_test_enqueued_scripts'] );
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

			$this->assertSame( array( 'jquery' ), $this->registered_deps( 'oli-occ-checkout' ) );
			$this->assertNotContains( 'wc-blocks-data-store', $this->registered_deps( 'oli-occ-checkout' ) );
			$this->assertNotContains( 'wp-data', $this->registered_deps( 'oli-occ-checkout' ) );
		}

		/**
		 * Block checkout declares wp-data and wc-blocks-data-store when registered.
		 *
		 * @return void
		 */
		public function test_block_checkout_declares_registered_blocks_store_deps() {
			$GLOBALS['oli_occ_test_has_blocks'] = array( 'woocommerce/checkout' );
			$this->register_script( 'wp-data' );
			$this->register_script( 'wc-blocks-data-store' );

			$this->plugin->enqueue_scripts();

			$this->assertSame(
				array( 'jquery', 'wp-data', 'wc-blocks-data-store' ),
				$this->registered_deps( 'oli-occ-checkout' )
			);
		}

		/**
		 * Block checkout still enqueues when wc-blocks-data-store is not registered.
		 *
		 * @return void
		 */
		public function test_block_checkout_omits_unregistered_blocks_store() {
			$GLOBALS['oli_occ_test_has_blocks'] = array( 'woocommerce/checkout' );
			$this->register_script( 'wp-data' );

			$this->plugin->enqueue_scripts();

			$deps = $this->registered_deps( 'oli-occ-checkout' );
			$this->assertSame( array( 'jquery', 'wp-data' ), $deps );
			$this->assertNotContains( 'wc-blocks-data-store', $deps );
			$this->assertContains( 'oli-occ-checkout', $GLOBALS['oli_occ_test_enqueued_scripts'] );
		}

		/**
		 * Checkout page id with the checkout block also declares the store deps.
		 *
		 * @return void
		 */
		public function test_block_checkout_detected_from_checkout_page_id() {
			$GLOBALS['oli_occ_test_checkout_page_id']   = 37;
			$GLOBALS['oli_occ_test_has_blocks_by_page'] = array(
				37 => array( 'woocommerce/checkout' ),
			);
			$this->register_script( 'wp-data' );
			$this->register_script( 'wc-blocks-data-store' );

			$this->plugin->enqueue_scripts();

			$this->assertSame(
				array( 'jquery', 'wp-data', 'wc-blocks-data-store' ),
				$this->registered_deps( 'oli-occ-checkout' )
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
				$this->registered_deps( 'oli-occ-checkout' )
			);
		}

		/**
		 * Turnstile api.js must be enqueued without a ?ver= query argument.
		 *
		 * @return void
		 */
		public function test_turnstile_api_js_has_null_version() {
			$GLOBALS['oli_occ_test_options']['oli_occ_provider'] = 'turnstile';

			$this->plugin->enqueue_scripts();

			$this->assertArrayHasKey( 'oli-occ-turnstile', $GLOBALS['oli_occ_test_registered_scripts'] );
			$this->assertNull( $GLOBALS['oli_occ_test_registered_scripts']['oli-occ-turnstile']['ver'] );
			$this->assertStringContainsString(
				'challenges.cloudflare.com/turnstile/v0/api.js',
				$GLOBALS['oli_occ_test_registered_scripts']['oli-occ-turnstile']['src']
			);
			$this->assertContains( 'oli-occ-turnstile', $GLOBALS['oli_occ_test_enqueued_scripts'] );
		}

		/**
		 * reCAPTCHA api.js is also enqueued without a ?ver= query argument.
		 *
		 * @return void
		 */
		public function test_recaptcha_api_js_has_null_version() {
			$this->plugin->enqueue_scripts();

			$this->assertArrayHasKey( 'oli-occ-recaptcha', $GLOBALS['oli_occ_test_registered_scripts'] );
			$this->assertNull( $GLOBALS['oli_occ_test_registered_scripts']['oli-occ-recaptcha']['ver'] );
		}

		/**
		 * @param string $handle Script handle.
		 * @return array<int, string>
		 */
		private function registered_deps( $handle ) {
			$this->assertArrayHasKey( $handle, $GLOBALS['oli_occ_test_registered_scripts'] );
			return $GLOBALS['oli_occ_test_registered_scripts'][ $handle ]['deps'];
		}

		/**
		 * @param string $handle Script handle.
		 * @return void
		 */
		private function register_script( $handle ) {
			$GLOBALS['oli_occ_test_registered_scripts'][ $handle ] = array(
				'src'  => 'https://example.test/' . $handle . '.js',
				'deps' => array(),
				'ver'  => '1.0.0',
				'args' => false,
			);
		}
	}
}
