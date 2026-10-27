<?php
/**
 * Uninstall cleanup for rate-limit transients.
 *
 * @package Olic_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * uninstall.php must delete olic_rl_* transient values and timeouts.
 */
class UninstallTest extends TestCase {

	/**
	 * Two olic_rl_* transients (value + timeout) are removed by uninstall.php.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_uninstall_deletes_rate_limit_transients() {
		set_transient( 'olic_rl_aaaaaaaaaaaaaaaaaaaa', 3, 900 );
		set_transient( 'olic_rl_bbbbbbbbbbbbbbbbbbbb', 5, 900 );

		$this->assertArrayHasKey( '_transient_olic_rl_aaaaaaaaaaaaaaaaaaaa', $GLOBALS['olic_test_options'] );
		$this->assertArrayHasKey( '_transient_timeout_olic_rl_aaaaaaaaaaaaaaaaaaaa', $GLOBALS['olic_test_options'] );
		$this->assertArrayHasKey( '_transient_olic_rl_bbbbbbbbbbbbbbbbbbbb', $GLOBALS['olic_test_options'] );
		$this->assertArrayHasKey( '_transient_timeout_olic_rl_bbbbbbbbbbbbbbbbbbbb', $GLOBALS['olic_test_options'] );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertArrayNotHasKey( '_transient_olic_rl_aaaaaaaaaaaaaaaaaaaa', $GLOBALS['olic_test_options'] );
		$this->assertArrayNotHasKey( '_transient_timeout_olic_rl_aaaaaaaaaaaaaaaaaaaa', $GLOBALS['olic_test_options'] );
		$this->assertArrayNotHasKey( '_transient_olic_rl_bbbbbbbbbbbbbbbbbbbb', $GLOBALS['olic_test_options'] );
		$this->assertArrayNotHasKey( '_transient_timeout_olic_rl_bbbbbbbbbbbbbbbbbbbb', $GLOBALS['olic_test_options'] );
		$this->assertFalse( get_option( '_transient_olic_rl_aaaaaaaaaaaaaaaaaaaa', false ) );
		$this->assertFalse( get_option( '_transient_timeout_olic_rl_aaaaaaaaaaaaaaaaaaaa', false ) );
		$this->assertFalse( get_option( '_transient_olic_rl_bbbbbbbbbbbbbbbbbbbb', false ) );
		$this->assertFalse( get_option( '_transient_timeout_olic_rl_bbbbbbbbbbbbbbbbbbbb', false ) );
	}
}
