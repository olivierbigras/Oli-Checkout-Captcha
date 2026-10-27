<?php
/**
 * In-place update from recaptcha-block-checkout.php to oli-checkout-captcha.php.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * Simulates a folder-preserving 1.0.0 → 1.1.1 update.
 */
class InPlaceUpdateTest extends TestCase {

	/**
	 * Temporary WP_PLUGIN_DIR used by the isolated test.
	 *
	 * @var string
	 */
	private $plugin_root = '';

	/**
	 * Remove the temporary plugin copy.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( '' !== $this->plugin_root && is_dir( $this->plugin_root ) ) {
			$this->remove_directory( $this->plugin_root );
		}

		parent::tearDown();
	}

	/**
	 * The legacy bootstrap must not look like a second plugin.
	 *
	 * @return void
	 */
	public function test_shim_has_no_plugin_header() {
		$shim = file_get_contents( dirname( __DIR__ ) . '/recaptcha-block-checkout.php' );

		$this->assertNotFalse( $shim );
		$this->assertDoesNotMatchRegularExpression( '/^\s*\*\s*Plugin Name\s*:/m', $shim );
		$this->assertStringContainsString( 'OLI_OCC_PLUGIN_LOADED', $shim );
		$this->assertStringContainsString( 'oli-checkout-captcha.php', $shim );
	}

	/**
	 * Loading the new file first, then the shim, must not fatal.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_shim_does_not_load_the_plugin_twice() {
		$root = $this->copy_plugin_to_legacy_folder();
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', $root );
		}

		$folder = $root . '/recaptcha-block-checkout';

		require $folder . '/oli-checkout-captcha.php';
		require $folder . '/recaptcha-block-checkout.php';
		require $folder . '/oli-checkout-captcha.php';

		$this->assertTrue( defined( 'OLI_OCC_PLUGIN_LOADED' ) );
		$this->assertTrue( defined( 'OLI_OCC_VERSION' ) );
		$this->assertSame( '1.2.7', OLI_OCC_VERSION );
		$this->assertSame(
			1,
			substr_count(
				implode( "\n", get_included_files() ),
				'includes/class-oli-occ-checkout-captcha.php'
			)
		);
	}

	/**
	 * In-place update keeps the plugin loaded, rewrites only the targeted
	 * raw active-plugin entries, and migrates options without reactivation.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_inplace_update_rewrites_raw_paths_and_migrates_options() {
		$root = $this->copy_plugin_to_legacy_folder();
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', $root );
		}

		$old = 'recaptcha-block-checkout/recaptcha-block-checkout.php';
		$new = 'recaptcha-block-checkout/oli-checkout-captcha.php';

		$GLOBALS['oli_occ_test_options'] = array(
			'active_plugins' => array(
				'woocommerce/woocommerce.php',
				$old,
				'akismet/akismet.php',
			),
			'rbc_site_key'   => 'legacy-site-key',
			'rbc_secret_key' => 'legacy-secret-key',
			'rbc_theme'      => 'dark',
		);

		$GLOBALS['oli_occ_test_site_options'] = array(
			'active_sitewide_plugins' => array(
				'woocommerce/woocommerce.php' => 100,
				$old                         => 200,
			),
		);

		add_filter(
			'option_active_plugins',
			static function ( $plugins ) {
				if ( ! is_array( $plugins ) ) {
					$plugins = array();
				}

				$plugins[] = 'filtered-only/plugin.php';
				return $plugins;
			}
		);

		$filtered_before = get_option( 'active_plugins' );
		$this->assertContains( 'filtered-only/plugin.php', $filtered_before );

		require $root . '/recaptcha-block-checkout/recaptcha-block-checkout.php';

		$this->assertTrue( defined( 'OLI_OCC_PLUGIN_LOADED' ) );
		$this->assertSame( $root . '/recaptcha-block-checkout/oli-checkout-captcha.php', OLI_OCC_PLUGIN_FILE );

		$stored_active = $GLOBALS['oli_occ_test_options']['active_plugins'];
		$this->assertSame(
			array(
				'woocommerce/woocommerce.php',
				$new,
				'akismet/akismet.php',
			),
			$stored_active
		);
		$this->assertNotContains( 'filtered-only/plugin.php', $stored_active );
		$this->assertNotContains( $old, $stored_active );

		$this->assertSame(
			array(
				'woocommerce/woocommerce.php' => 100,
				$new                         => 200,
			),
			$GLOBALS['oli_occ_test_site_options']['active_sitewide_plugins']
		);

		$this->assertArrayNotHasKey( 'oli_occ_site_key', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayNotHasKey( 'oli_occ_db_version', $GLOBALS['oli_occ_test_options'] );

		do_action( 'plugins_loaded' );

		$this->assertSame( 'legacy-site-key', $GLOBALS['oli_occ_test_options']['oli_occ_site_key'] );
		$this->assertSame( 'legacy-secret-key', $GLOBALS['oli_occ_test_options']['oli_occ_secret_key'] );
		$this->assertSame( 'dark', $GLOBALS['oli_occ_test_options']['oli_occ_theme'] );
		$this->assertSame( '1.2.7', $GLOBALS['oli_occ_test_options']['oli_occ_db_version'] );
		$this->assertArrayNotHasKey( 'rbc_site_key', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayNotHasKey( 'rbc_secret_key', $GLOBALS['oli_occ_test_options'] );
		$this->assertArrayNotHasKey( 'rbc_theme', $GLOBALS['oli_occ_test_options'] );

		do_action( 'plugins_loaded' );
		$this->assertSame( '1.2.7', $GLOBALS['oli_occ_test_options']['oli_occ_db_version'] );
		$this->assertTrue( class_exists( 'Oli_OCC_Checkout_Captcha', false ) );
	}

	/**
	 * Copy plugin PHP files into a folder named like the 1.0.0 install.
	 *
	 * @return string Temporary WP_PLUGIN_DIR.
	 */
	private function copy_plugin_to_legacy_folder() {
		$source = dirname( __DIR__ );
		$root   = sys_get_temp_dir() . '/oli-occ-inplace-' . str_replace( '.', '', uniqid( '', true ) );
		$folder = $root . '/recaptcha-block-checkout';

		if ( ! mkdir( $folder . '/includes', 0777, true ) && ! is_dir( $folder . '/includes' ) ) {
			$this->fail( 'Unable to create the temporary plugin folder.' );
		}

		$files = array(
			'recaptcha-block-checkout.php',
			'oli-checkout-captcha.php',
			'includes/oli-occ-upgrade.php',
			'includes/class-oli-occ-verifier.php',
			'includes/class-oli-occ-rate-limiter.php',
			'includes/class-oli-occ-checkout-captcha.php',
		);

		foreach ( $files as $relative ) {
			if ( ! copy( $source . '/' . $relative, $folder . '/' . $relative ) ) {
				$this->fail( 'Unable to copy ' . $relative . ' into the temporary plugin folder.' );
			}
		}

		$this->plugin_root = $root;

		return $root;
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	private function remove_directory( $directory ) {
		$items = scandir( $directory );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $directory . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->remove_directory( $path );
				continue;
			}

			unlink( $path );
		}

		rmdir( $directory );
	}
}
