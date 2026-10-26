<?php
/**
 * Translation template coverage.
 *
 * @package Olic_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * The release POT must contain extracted plugin strings, not only the header.
 */
class PotFileTest extends TestCase {

	/**
	 * @return void
	 */
	public function test_pot_contains_extracted_plugin_strings() {
		$pot = file_get_contents( dirname( __DIR__ ) . '/languages/oli-checkout-captcha.pot' );
		$this->assertNotFalse( $pot );
		$this->assertStringContainsString( 'X-Domain: oli-checkout-captcha', $pot );
		$this->assertStringContainsString( 'Oli Checkout Captcha', $pot );
		$this->assertStringContainsString( 'Please complete the captcha verification.', $pot );
		$this->assertStringContainsString( 'Captcha provider', $pot );

		preg_match_all( '/^msgid "(.+)"$/m', $pot, $matches );
		$non_empty = array_values(
			array_filter(
				$matches[1],
				static function ( $msgid ) {
					return '' !== $msgid;
				}
			)
		);

		$this->assertGreaterThanOrEqual( 40, count( $non_empty ) );
	}
}
