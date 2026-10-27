<?php
/**
 * readme.txt screenshot captions.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * The WordPress.org readme must list four screenshot captions.
 */
class ReadmeScreenshotsTest extends TestCase {

	/**
	 * Captions 1–4 match the documented screenshot list.
	 *
	 * @return void
	 */
	public function test_readme_lists_four_screenshot_captions() {
		$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
		$this->assertNotFalse( $readme );

		$this->assertMatchesRegularExpression(
			'/== Screenshots ==\s+1\. Settings page\s+2\. Captcha on checkout\s+3\. Captcha on the block checkout\s+4\. Checkout blocked with "Please complete the captcha verification\." when the captcha is not completed\n/',
			$readme
		);
	}
}
