<?php
/**
 * Classic checkout POST reads require the WooCommerce checkout nonce.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * Nonce gate on $_POST reads (1.2.6).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClassicNonceTest extends TestCase {

	/**
	 * Call a private method on an instance built without its constructor.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function call( $method, array $args = array() ) {
		$dir = dirname( __DIR__ ) . '/includes/';
		require_once $dir . 'class-oli-occ-verifier.php';
		require_once $dir . 'class-oli-occ-rate-limiter.php';
		require_once $dir . 'class-oli-occ-checkout-captcha.php';
		$ref      = new ReflectionClass( 'Oli_OCC_Checkout_Captcha' );
		$instance = $ref->newInstanceWithoutConstructor();
		$m        = $ref->getMethod( $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $instance, $args );
	}

	/**
	 * Without a valid nonce nothing is read from $_POST.
	 *
	 * @return void
	 */
	public function test_post_is_ignored_without_valid_nonce() {
		$_POST = array(
			'oli_occ_captcha_token'             => 'tok',
			'payment_method'                    => 'cod',
			'is_express'                        => '1',
			'woocommerce-process-checkout-nonce' => 'forged',
		);
		$this->assertSame( '', $this->call( 'get_token_from_post' ) );
		$this->assertSame( '', $this->call( 'get_payment_method', array( null ) ) );
		$this->assertSame( array(), $this->call( 'get_payment_data_map', array( null ) ) );
	}

	/**
	 * With the WooCommerce checkout nonce, values are read and sanitized.
	 *
	 * @return void
	 */
	public function test_post_is_read_with_valid_nonce() {
		$_POST = array(
			'oli_occ_captcha_token'             => 'tok',
			'payment_method'                    => 'cod',
			'is_express'                        => '1',
			'woocommerce-process-checkout-nonce' => 'valid-woocommerce-process_checkout',
		);
		$this->assertSame( 'tok', $this->call( 'get_token_from_post' ) );
		$this->assertSame( 'cod', $this->call( 'get_payment_method', array( null ) ) );
		$this->assertSame( array( 'is_express' => '1' ), $this->call( 'get_payment_data_map', array( null ) ) );
	}
}
