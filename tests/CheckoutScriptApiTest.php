<?php
/**
 * Checkout.js Store API helper tests (B1 / token transport).
 *
 * @package Olic_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

/**
 * Guards the public setExtensionData preference and place-order-only store writes.
 */
class CheckoutScriptApiTest extends TestCase {

	/**
	 * checkout.js contents.
	 *
	 * @var string
	 */
	private $script = '';

	/**
	 * Load checkout.js once.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$contents = file_get_contents( dirname( __DIR__ ) . '/js/checkout.js' );
		$this->assertNotFalse( $contents );
		$this->script = $contents;
	}

	/**
	 * Prefer the public setExtensionData API, then fall back to the internal one.
	 *
	 * @return void
	 */
	public function test_prefers_public_set_extension_data_with_internal_fallback() {
		$this->assertMatchesRegularExpression(
			'/typeof\s+dispatch\.setExtensionData\s*===\s*[\'"]function[\'"]/',
			$this->script
		);
		$this->assertMatchesRegularExpression(
			'/dispatch\.setExtensionData\(\s*namespace\s*,\s*data\s*\)/',
			$this->script
		);
		$this->assertMatchesRegularExpression(
			'/typeof\s+dispatch\.__internalSetExtensionData\s*===\s*[\'"]function[\'"]/',
			$this->script
		);
		$this->assertMatchesRegularExpression(
			'/dispatch\.__internalSetExtensionData\(\s*namespace\s*,\s*data\s*\)/',
			$this->script
		);

		$public_pos   = strpos( $this->script, 'dispatch.setExtensionData' );
		$internal_pos = strpos( $this->script, 'dispatch.__internalSetExtensionData' );
		$this->assertNotFalse( $public_pos );
		$this->assertNotFalse( $internal_pos );
		$this->assertLessThan( $internal_pos, $public_pos );
	}

	/**
	 * The deprecated internal helper is never the first choice.
	 *
	 * @return void
	 */
	public function test_does_not_call_internal_api_unconditionally() {
		$this->assertDoesNotMatchRegularExpression(
			'/if\s*\(\s*dispatch\s*&&\s*dispatch\.__internalSetExtensionData\s*\)/',
			$this->script
		);
	}

	/**
	 * persistToken must only keep the token locally (hidden field / memory).
	 *
	 * @return void
	 */
	public function test_persist_token_does_not_write_the_checkout_store() {
		$this->assertMatchesRegularExpression(
			'/function persistToken\(token\) \{[\s\S]*?function getPendingToken\(/',
			$this->script
		);

		if ( ! preg_match( '/function persistToken\(token\) \{([\s\S]*?)\n\tfunction getPendingToken\(/', $this->script, $matches ) ) {
			$this->fail( 'Unable to isolate persistToken().' );
		}

		$this->assertStringNotContainsString( 'setCheckoutExtensionData', $matches[1] );
		$this->assertStringNotContainsString( 'setExtensionData', $matches[1] );
		$this->assertStringNotContainsString( '__internalSetExtensionData', $matches[1] );
		$this->assertStringContainsString( 'latestToken', $matches[1] );
	}

	/**
	 * Block checkout writes the token at place-order via apiFetch or isBeforeProcessing.
	 *
	 * @return void
	 */
	public function test_block_checkout_sends_token_only_at_place_order() {
		$this->assertStringContainsString( 'wp.apiFetch.use', $this->script );
		$this->assertStringContainsString( 'isStoreApiCheckoutPost', $this->script );
		$this->assertStringContainsString( 'isBeforeProcessing', $this->script );
		$this->assertStringContainsString( 'flushTokenToCheckoutStore', $this->script );
		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*bindApiFetchTokenMiddleware\(\)\s*\)\s*\{\s*return;/',
			$this->script
		);
	}
}
