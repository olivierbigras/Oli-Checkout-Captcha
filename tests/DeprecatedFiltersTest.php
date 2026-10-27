<?php
/**
 * Pre-1.2.6 filter names stay honored.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * Deprecated olic_* filter aliases.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DeprecatedFiltersTest extends TestCase {

	/**
	 * Call is_express_checkout() on an instance built without its constructor.
	 *
	 * @return bool
	 */
	private function is_express() {
		$dir = dirname( __DIR__ ) . '/includes/';
		require_once $dir . 'class-oli-occ-verifier.php';
		require_once $dir . 'class-oli-occ-rate-limiter.php';
		require_once $dir . 'class-oli-occ-checkout-captcha.php';
		$ref = new ReflectionClass( 'Oli_OCC_Checkout_Captcha' );
		$m   = $ref->getMethod( 'is_express_checkout' );
		$m->setAccessible( true );
		return $m->invoke( $ref->newInstanceWithoutConstructor(), null );
	}

	/**
	 * Valid classic checkout POST for a given gateway.
	 *
	 * @param string $method Gateway ID.
	 * @return void
	 */
	private function post( $method ) {
		$GLOBALS['oli_occ_test_filters']    = array();
		$GLOBALS['oli_occ_test_deprecated'] = array();
		$_POST                              = array(
			'payment_method'                     => $method,
			'woocommerce-process-checkout-nonce' => 'valid-woocommerce-process_checkout',
		);
	}

	/**
	 * A callback on olic_express_payment_ids is still applied, with a deprecation notice.
	 *
	 * @return void
	 */
	public function test_old_express_ids_filter_is_applied() {
		$this->post( 'my_wallet' );
		$this->assertFalse( $this->is_express() );

		add_filter(
			'olic_express_payment_ids',
			static function ( $ids ) {
				$ids[] = 'my_wallet';
				return $ids;
			}
		);
		$this->assertTrue( $this->is_express() );
		$this->assertSame( array( 'olic_express_payment_ids', '1.2.6', 'oli_occ_express_payment_ids' ), $GLOBALS['oli_occ_test_deprecated'][0] );
	}

	/**
	 * A callback on olic_skip_express_payments is still applied, and the new name runs after it.
	 *
	 * @return void
	 */
	public function test_old_skip_filter_is_applied_then_new() {
		$this->post( 'apple_pay' );
		$this->assertTrue( $this->is_express() );

		add_filter(
			'olic_skip_express_payments',
			static function () {
				return false;
			}
		);
		$this->assertFalse( $this->is_express() );

		$seen = null;
		add_filter(
			'oli_occ_skip_express_payments',
			static function ( $skip ) use ( &$seen ) {
				$seen = $skip;
				return true;
			}
		);
		$this->assertTrue( $this->is_express() );
		$this->assertFalse( $seen );
	}

	/**
	 * No deprecation notice when nobody uses the old names.
	 *
	 * @return void
	 */
	public function test_no_notice_without_old_callbacks() {
		$this->post( 'apple_pay' );
		$this->is_express();
		$this->assertSame( array(), $GLOBALS['oli_occ_test_deprecated'] );
	}
}
