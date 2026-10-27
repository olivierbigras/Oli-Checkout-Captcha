<?php
/**
 * Uninstall cleanup for rate-limit transients.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * uninstall.php must delete oli_occ_rl_* transient values and timeouts.
 */
class UninstallTest extends TestCase {

	/**
	 * Two oli_occ_rl_* transients (value + timeout) are removed by uninstall.php.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_uninstall_deletes_rate_limit_transients() {
		set_transient( 'oli_occ_rl_aaaaaaaaaaaaaaaaaaaa', 3, 900 );
		set_transient( 'oli_occ_rl_bbbbbbbbbbbbbbbbbbbb', 5, 900 );

		$this->assertArrayHasKey( '_transient_oli_occ_rl_aaaaaaaaaaaaaaaaaaaa', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayHasKey( '_transient_timeout_oli_occ_rl_aaaaaaaaaaaaaaaaaaaa', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayHasKey( '_transient_oli_occ_rl_bbbbbbbbbbbbbbbbbbbb', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayHasKey( '_transient_timeout_oli_occ_rl_bbbbbbbbbbbbbbbbbbbb', $GLOBALS['oli_occ_test_options'] );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertArrayNotHasKey( '_transient_oli_occ_rl_aaaaaaaaaaaaaaaaaaaa', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayNotHasKey( '_transient_timeout_oli_occ_rl_aaaaaaaaaaaaaaaaaaaa', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayNotHasKey( '_transient_oli_occ_rl_bbbbbbbbbbbbbbbbbbbb', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayNotHasKey( '_transient_timeout_oli_occ_rl_bbbbbbbbbbbbbbbbbbbb', $GLOBALS['oli_occ_test_options'] );
		$this->assertFalse( get_option( '_transient_oli_occ_rl_aaaaaaaaaaaaaaaaaaaa', false ) );
		$this->assertFalse( get_option( '_transient_timeout_oli_occ_rl_aaaaaaaaaaaaaaaaaaaa', false ) );
		$this->assertFalse( get_option( '_transient_oli_occ_rl_bbbbbbbbbbbbbbbbbbbb', false ) );
		$this->assertFalse( get_option( '_transient_timeout_oli_occ_rl_bbbbbbbbbbbbbbbbbbbb', false ) );
	}

	/**
	 * Old 1.2.5 names (olic_*) are removed too when the migration never ran.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_uninstall_deletes_previous_prefix_data() {
		update_option( 'olic_site_key', 'x' );
		update_option( 'oli_occ_site_key', 'y' );
		set_transient( 'olic_rl_cccccccccccccccccccc', 1, 900 );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'oli-checkout-captcha/oli-checkout-captcha.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertArrayNotHasKey( 'olic_site_key', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayNotHasKey( 'oli_occ_site_key', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayNotHasKey( '_transient_olic_rl_cccccccccccccccccccc', $GLOBALS['oli_occ_test_options'] );
		foreach ( array_keys( $GLOBALS['oli_occ_test_options'] ) as $name ) {
			$this->assertStringNotContainsString( 'olic_', $name );
			$this->assertStringNotContainsString( 'oli_occ_', $name );
		}
	}
}
