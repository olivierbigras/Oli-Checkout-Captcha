<?php
/**
 * Server-side captcha token verification tests (HTTP mocked).
 *
 * @package Olic_Checkout_Captcha
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-olic-verifier.php';

/**
 * Covers Olic_Verifier with wp_remote_post mocked.
 */
class TokenVerificationTest extends TestCase {

	/**
	 * Verifier under test.
	 *
	 * @var Olic_Verifier
	 */
	private $verifier;

	/**
	 * Reset HTTP mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['olic_test_http_requests'] = array();
		$GLOBALS['olic_test_http_response'] = null;
		$this->verifier                     = new Olic_Verifier();
	}

	/**
	 * Empty tokens fail without calling the provider.
	 *
	 * @return void
	 */
	public function test_empty_token_fails_without_http() {
		$result = $this->verifier->verify( '', $this->settings() );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'Please complete the captcha verification.', $result['message'] );
		$this->assertSame( array(), $GLOBALS['olic_test_http_requests'] );
	}

	/**
	 * Missing secret fails without calling the provider.
	 *
	 * @return void
	 */
	public function test_missing_secret_fails_without_http() {
		$settings           = $this->settings();
		$settings['secret'] = '';

		$result = $this->verifier->verify( 'token-value', $settings );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'Captcha is not configured.', $result['message'] );
		$this->assertSame( array(), $GLOBALS['olic_test_http_requests'] );
	}

	/**
	 * A successful reCAPTCHA v2 siteverify response is accepted.
	 *
	 * @return void
	 */
	public function test_recaptcha_v2_success() {
		$this->mock_json(
			array( 'success' => true ),
			Olic_Verifier::RECAPTCHA_VERIFY_URL
		);

		$result = $this->verifier->verify( 'v2-token', $this->settings() );

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( $result['http_failed'] );
		$this->assertCount( 1, $GLOBALS['olic_test_http_requests'] );
		$this->assertSame( Olic_Verifier::RECAPTCHA_VERIFY_URL, $GLOBALS['olic_test_http_requests'][0]['url'] );
		$this->assertSame(
			array(
				'secret'   => 'test-secret',
				'response' => 'v2-token',
				'remoteip' => '203.0.113.10',
			),
			$GLOBALS['olic_test_http_requests'][0]['args']['body']
		);
	}

	/**
	 * Provider success=false is always a failure.
	 *
	 * @return void
	 */
	public function test_recaptcha_v2_invalid_token() {
		$this->mock_json( array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) ) );

		$result = $this->verifier->verify( 'bad-token', $this->settings() );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'Captcha verification failed. Please complete the challenge again.', $result['message'] );
		$this->assertFalse( $result['http_failed'] );
	}

	/**
	 * HTTP errors fail closed when the setting is enabled.
	 *
	 * @return void
	 */
	public function test_http_error_fails_closed() {
		$GLOBALS['olic_test_http_response'] = new WP_Error( 'http_request_failed', 'timeout' );

		$result = $this->verifier->verify( 'token-value', $this->settings( array( 'fail_closed' => true ) ) );

		$this->assertFalse( $result['ok'] );
		$this->assertTrue( $result['http_failed'] );
		$this->assertSame( 'Captcha verification is temporarily unavailable. Please try again.', $result['message'] );
	}

	/**
	 * HTTP errors allow checkout when fail-closed is off.
	 *
	 * @return void
	 */
	public function test_http_error_fails_open() {
		$GLOBALS['olic_test_http_response'] = new WP_Error( 'http_request_failed', 'timeout' );

		$result = $this->verifier->verify( 'token-value', $this->settings( array( 'fail_closed' => false ) ) );

		$this->assertTrue( $result['ok'] );
		$this->assertTrue( $result['http_failed'] );
	}

	/**
	 * Non-JSON bodies follow the fail-closed setting.
	 *
	 * @return void
	 */
	public function test_invalid_json_fails_closed() {
		$GLOBALS['olic_test_http_response'] = $this->http_response( '<html>nope</html>', 200 );

		$result = $this->verifier->verify( 'token-value', $this->settings( array( 'fail_closed' => true ) ) );

		$this->assertFalse( $result['ok'] );
		$this->assertTrue( $result['http_failed'] );
	}

	/**
	 * reCAPTCHA v3 rejects scores below the threshold.
	 *
	 * @return void
	 */
	public function test_recaptcha_v3_low_score_fails() {
		$this->mock_json(
			array(
				'success' => true,
				'score'   => 0.2,
				'action'  => 'checkout',
			)
		);

		$result = $this->verifier->verify(
			'v3-token',
			$this->settings(
				array(
					'provider'        => Olic_Verifier::PROVIDER_RECAPTCHA_V3,
					'score_threshold' => 0.5,
				)
			)
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 0.2, $result['score'] );
	}

	/**
	 * reCAPTCHA v3 accepts scores at or above the threshold with the expected action.
	 *
	 * @return void
	 */
	public function test_recaptcha_v3_score_and_action_pass() {
		$this->mock_json(
			array(
				'success' => true,
				'score'   => 0.9,
				'action'  => 'checkout',
			)
		);

		$result = $this->verifier->verify(
			'v3-token',
			$this->settings(
				array(
					'provider'        => Olic_Verifier::PROVIDER_RECAPTCHA_V3,
					'score_threshold' => 0.5,
					'expected_action' => 'checkout',
				)
			)
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 0.9, $result['score'] );
	}

	/**
	 * reCAPTCHA v3 rejects an unexpected action even with a high score.
	 *
	 * @return void
	 */
	public function test_recaptcha_v3_unexpected_action_fails() {
		$this->mock_json(
			array(
				'success' => true,
				'score'   => 0.9,
				'action'  => 'homepage',
			)
		);

		$result = $this->verifier->verify(
			'v3-token',
			$this->settings(
				array(
					'provider'        => Olic_Verifier::PROVIDER_RECAPTCHA_V3,
					'score_threshold' => 0.5,
					'expected_action' => 'checkout',
				)
			)
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 0.9, $result['score'] );
	}

	/**
	 * Turnstile success posts to the Cloudflare siteverify URL.
	 *
	 * @return void
	 */
	public function test_turnstile_success_uses_cloudflare_endpoint() {
		$this->mock_json(
			array( 'success' => true ),
			Olic_Verifier::TURNSTILE_VERIFY_URL
		);

		$result = $this->verifier->verify(
			'turnstile-token',
			$this->settings( array( 'provider' => Olic_Verifier::PROVIDER_TURNSTILE ) )
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( Olic_Verifier::TURNSTILE_VERIFY_URL, $GLOBALS['olic_test_http_requests'][0]['url'] );
		$this->assertSame( 'turnstile-token', $GLOBALS['olic_test_http_requests'][0]['args']['body']['response'] );
	}

	/**
	 * Turnstile failure is rejected.
	 *
	 * @return void
	 */
	public function test_turnstile_failure() {
		$this->mock_json(
			array( 'success' => false ),
			Olic_Verifier::TURNSTILE_VERIFY_URL
		);

		$result = $this->verifier->verify(
			'turnstile-token',
			$this->settings( array( 'provider' => Olic_Verifier::PROVIDER_TURNSTILE ) )
		);

		$this->assertFalse( $result['ok'] );
	}

	/**
	 * HTTP 500 is treated as a provider outage.
	 *
	 * @return void
	 */
	public function test_http_500_fails_closed() {
		$GLOBALS['olic_test_http_response'] = $this->http_response( '{"success":true}', 500 );

		$result = $this->verifier->verify( 'token-value', $this->settings( array( 'fail_closed' => true ) ) );

		$this->assertFalse( $result['ok'] );
		$this->assertTrue( $result['http_failed'] );
	}

	/**
	 * Default verification settings.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array<string, mixed>
	 */
	private function settings( $overrides = array() ) {
		return array_merge(
			array(
				'provider'        => Olic_Verifier::PROVIDER_RECAPTCHA_V2,
				'secret'          => 'test-secret',
				'remote_ip'       => '203.0.113.10',
				'score_threshold' => 0.5,
				'expected_action' => 'checkout',
				'fail_closed'     => true,
			),
			$overrides
		);
	}

	/**
	 * Queue a JSON siteverify response.
	 *
	 * @param array<string, mixed> $payload Decoded payload.
	 * @param string|null          $url     Optional expected URL.
	 * @return void
	 */
	private function mock_json( $payload, $url = null ) {
		unset( $url );
		$GLOBALS['olic_test_http_response'] = $this->http_response( json_encode( $payload ), 200 );
	}

	/**
	 * Build a wp_remote_post-shaped response.
	 *
	 * @param string $body Response body.
	 * @param int    $code HTTP status.
	 * @return array<string, mixed>
	 */
	private function http_response( $body, $code ) {
		return array(
			'body'     => $body,
			'response' => array(
				'code' => $code,
			),
		);
	}
}
