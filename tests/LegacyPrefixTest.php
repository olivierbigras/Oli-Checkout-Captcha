<?php
/**
 * No leftover 1.2.5 "olic" prefix outside the documented exceptions.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * Scans the shipped files for "olic".
 */
class LegacyPrefixTest extends TestCase {

	/**
	 * Allowed occurrences: file => regex the whole line must match.
	 *
	 * @return array<string,string>
	 */
	private function allowed() {
		return array(
			// Deprecated aliases of the two public filters (apply_filters_deprecated, has_filter, docblock).
			'includes/class-oli-occ-checkout-captcha.php' => '/olic_(skip_express_payments|express_payment_ids)/',
			// Option and transient migration from 1.2.5.
			'includes/oli-occ-upgrade.php'                => '/./',
			// Uninstall also removes the 1.2.5 names.
			'uninstall.php'                               => '/./',
			// Rate-limit hash salt kept so migrated counters keep matching.
			'includes/class-oli-occ-rate-limiter.php'     => "/'olic\\|'/",
			// Release history (changelog, upgrade notice, migration note).
			'readme.txt'                                  => '/./',
			'CHANGELOG.md'                                => '/./',
		);
	}

	/**
	 * Shipped files (same exclusions as .distignore for the paths that matter).
	 *
	 * @return string[]
	 */
	private function shipped_files() {
		$root  = dirname( __DIR__ );
		$files = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
			if ( preg_match( '#^\.phpunit|^(\.git|\.github|tests|vendor|node_modules|bin)/|^composer\.(json|lock)$|\.(png|jpg|mo|zip)$#', $rel ) ) {
				continue;
			}
			$files[] = $rel;
		}
		return $files;
	}

	/**
	 * @return void
	 */
	public function test_no_olic_outside_allowed_list() {
		$allowed = $this->allowed();
		$bad     = array();
		foreach ( $this->shipped_files() as $rel ) {
			$lines = file( dirname( __DIR__ ) . '/' . $rel );
			foreach ( (array) $lines as $i => $line ) {
				if ( ! preg_match( '/olic(?![a-z])|olic_/i', $line ) ) {
					continue;
				}
				if ( isset( $allowed[ $rel ] ) && preg_match( $allowed[ $rel ], $line ) ) {
					continue;
				}
				$bad[] = $rel . ':' . ( $i + 1 ) . ' ' . trim( $line );
			}
		}
		$this->assertSame( array(), $bad );
	}

	/**
	 * The capability filter must follow the registered settings group.
	 *
	 * @return void
	 */
	public function test_capability_filter_matches_settings_group() {
		$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-oli-occ-checkout-captcha.php' );
		$this->assertMatchesRegularExpression( "/register_setting\\(\\s*'oli_occ_settings'/", $src );
		$this->assertStringContainsString( "add_filter( 'option_page_capability_oli_occ_settings'", $src );
	}
}
