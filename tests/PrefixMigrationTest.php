<?php
/**
 * Migration of the 1.2.5 olic_* options and transients to oli_occ_*.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * One-time prefix migration introduced in 1.2.6.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PrefixMigrationTest extends TestCase {

	/**
	 * Load the upgrade routines with a fresh in-memory options table.
	 *
	 * @param array<string, mixed> $options Initial options.
	 * @return void
	 */
	private function boot( array $options ) {
		if ( ! defined( 'OLI_OCC_VERSION' ) ) {
			define( 'OLI_OCC_VERSION', '1.2.6' );
		}
		$GLOBALS['oli_occ_test_options']    = $options;
		$GLOBALS['oli_occ_test_transients'] = array();
		$GLOBALS['oli_occ_test_filters']    = array();
		require_once dirname( __DIR__ ) . '/includes/oli-occ-upgrade.php';
	}

	/**
	 * A configured 1.2.5 site.
	 *
	 * @return array<string, mixed>
	 */
	private function site_125() {
		return array(
			'olic_provider'           => 'turnstile',
			'olic_site_key'           => 'site-key-125',
			'olic_secret_key'         => 'secret-key-125',
			'olic_theme'              => 'dark',
			'olic_score_threshold'    => '0.7',
			'olic_fail_closed'        => '1',
			'olic_rate_limit_enabled' => '1',
			'olic_rate_limit_max'     => 7,
			'olic_rate_limit_window'  => 30,
			'olic_db_version'         => '1.2.5',
		);
	}

	/**
	 * Every option is copied without loss and the old rows are removed.
	 *
	 * @return void
	 */
	public function test_options_are_copied_then_old_rows_deleted() {
		$this->boot( $this->site_125() );
		oli_occ_maybe_migrate_options();

		foreach ( $this->site_125() as $old => $value ) {
			if ( 'olic_db_version' === $old ) {
				continue;
			}
			$new = 'oli_occ_' . substr( $old, 5 );
			$this->assertSame( $value, get_option( $new ), $new );
			$this->assertArrayNotHasKey( $old, $GLOBALS['oli_occ_test_options'], $old );
		}
		$this->assertArrayNotHasKey( 'olic_db_version', $GLOBALS['oli_occ_test_options'] );
		$this->assertSame( '1.2.6', get_option( 'oli_occ_db_version' ) );
	}

	/**
	 * Running twice changes nothing and never overwrites a newer value.
	 *
	 * @return void
	 */
	public function test_migration_is_idempotent_and_keeps_new_values() {
		$options                     = $this->site_125();
		$options['oli_occ_site_key'] = 'already-new';
		$this->boot( $options );

		oli_occ_maybe_migrate_options();
		$after_first = $GLOBALS['oli_occ_test_options'];
		oli_occ_maybe_migrate_options();

		$this->assertSame( 'already-new', get_option( 'oli_occ_site_key' ) );
		$this->assertArrayNotHasKey( 'olic_site_key', $GLOBALS['oli_occ_test_options'] );
		$this->assertSame( $after_first, $GLOBALS['oli_occ_test_options'] );
	}

	/**
	 * Live rate-limit counters move with their remaining lifetime; expired ones are dropped.
	 *
	 * @return void
	 */
	public function test_rate_limit_transients_are_moved() {
		$options = $this->site_125();
		$options['_transient_olic_rl_aaaaaaaaaaaaaaaaaaaa']         = 4;
		$options['_transient_timeout_olic_rl_aaaaaaaaaaaaaaaaaaaa'] = time() + 600;
		$options['_transient_olic_rl_bbbbbbbbbbbbbbbbbbbb']         = 2;
		$options['_transient_timeout_olic_rl_bbbbbbbbbbbbbbbbbbbb'] = time() - 10;
		$this->boot( $options );

		oli_occ_maybe_migrate_options();

		$this->assertSame( 4, get_transient( 'oli_occ_rl_aaaaaaaaaaaaaaaaaaaa' ) );
		$timeout = $GLOBALS['oli_occ_test_options']['_transient_timeout_oli_occ_rl_aaaaaaaaaaaaaaaaaaaa'];
		$this->assertGreaterThan( time() + 590, $timeout );
		$this->assertLessThanOrEqual( time() + 600, $timeout );
		$this->assertFalse( get_transient( 'oli_occ_rl_bbbbbbbbbbbbbbbbbbbb' ) );
		foreach ( array_keys( $GLOBALS['oli_occ_test_options'] ) as $name ) {
			$this->assertStringNotContainsString( 'olic_rl_', $name );
		}
	}

	/**
	 * Legacy rbc_* options from pre-1.1 still migrate directly to oli_occ_*.
	 *
	 * @return void
	 */
	public function test_rbc_options_migrate_to_new_prefix() {
		$this->boot(
			array(
				'rbc_site_key'   => 'rbc-site',
				'rbc_secret_key' => 'rbc-secret',
				'rbc_theme'      => 'light',
			)
		);
		oli_occ_maybe_migrate_options();

		$this->assertSame( 'rbc-site', get_option( 'oli_occ_site_key' ) );
		$this->assertSame( 'rbc-secret', get_option( 'oli_occ_secret_key' ) );
		$this->assertSame( 'light', get_option( 'oli_occ_theme' ) );
		$this->assertArrayNotHasKey( 'rbc_site_key', $GLOBALS['oli_occ_test_options'] );
	}

	/**
	 * Before migration, reads of the new name fall back to the old row.
	 *
	 * @return void
	 */
	public function test_read_fallback_returns_old_value() {
		$this->boot( $this->site_125() );
		oli_occ_register_option_fallbacks();

		$this->assertSame( 'site-key-125', get_option( 'oli_occ_site_key', '' ) );
		$this->assertSame( 'fallback', get_option( 'oli_occ_unknown', 'fallback' ) );
	}

	/**
	 * A site already on 1.2.6 skips the routine entirely.
	 *
	 * @return void
	 */
	public function test_skips_when_db_version_is_current() {
		$this->boot(
			array(
				'oli_occ_db_version' => '1.2.6',
				'olic_site_key'      => 'left-alone',
			)
		);
		oli_occ_maybe_migrate_options();

		$this->assertSame( 'left-alone', $GLOBALS['oli_occ_test_options']['olic_site_key'] );
	}
}
