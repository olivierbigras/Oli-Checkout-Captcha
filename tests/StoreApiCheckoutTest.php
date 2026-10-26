<?php
/**
 * Store API checkout captcha refusal tests.
 *
 * @package Olic_Checkout_Captcha
 */

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/class-wc-store-api-route-exception-stub.php';

/**
 * Minimal Store API request stand-in.
 */
class Olic_Test_Store_Api_Request {

	/**
	 * HTTP method.
	 *
	 * @var string
	 */
	public $method = 'POST';

	/**
	 * Request params.
	 *
	 * @var array<string, mixed>
	 */
	public $params = array();

	/**
	 * @return string
	 */
	public function get_method() {
		return $this->method;
	}

	/**
	 * @param string $key Param name.
	 * @return mixed
	 */
	public function get_param( $key ) {
		return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
	}
}

/**
 * Covers Store API refusals mapping to HTTP 400 RouteException.
 */
class StoreApiCheckoutTest extends TestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Olic_Checkout_Captcha
	 */
	private $plugin;

	/**
	 * Load the plugin and reset options.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['olic_test_options']     = array();
		$GLOBALS['olic_test_transients']  = array();
		$GLOBALS['olic_test_http_requests'] = array();
		$GLOBALS['olic_test_http_response'] = null;

		if ( ! defined( 'OLIC_PLUGIN_LOADED' ) ) {
			require dirname( __DIR__ ) . '/oli-checkout-captcha.php';
		}

		$this->plugin = Olic_Checkout_Captcha::get_instance();
	}

	/**
	 * A missing captcha token fails Store API checkout with HTTP 400.
	 *
	 * @return void
	 */
	public function test_store_api_refusal_throws_route_exception_400() {
		$request          = new Olic_Test_Store_Api_Request();
		$request->method  = 'POST';
		$request->params  = array();

		try {
			$this->plugin->validate_store_api_checkout( new stdClass(), $request );
			$this->fail( 'Expected a Store API RouteException.' );
		} catch ( RouteException $exception ) {
			$this->assertSame( 400, $exception->getCode() );
			$this->assertSame( 'olic_captcha_failed', $exception->getErrorCode() );
			$this->assertSame( 'Please complete the captcha verification.', $exception->getMessage() );
		}
	}
}
