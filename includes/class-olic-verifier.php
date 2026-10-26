<?php
/**
 * Server-side captcha token verification.
 *
 * @package Olic_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies reCAPTCHA and Turnstile tokens against their siteverify APIs.
 *
 * @since 1.2.0
 */
class Olic_Verifier {

	/**
	 * reCAPTCHA v2 checkbox provider.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const PROVIDER_RECAPTCHA_V2 = 'recaptcha_v2';

	/**
	 * reCAPTCHA v3 score provider.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const PROVIDER_RECAPTCHA_V3 = 'recaptcha_v3';

	/**
	 * Cloudflare Turnstile provider.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const PROVIDER_TURNSTILE = 'turnstile';

	/**
	 * Google reCAPTCHA siteverify endpoint.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * Cloudflare Turnstile siteverify endpoint.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Default reCAPTCHA v3 action name.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const DEFAULT_ACTION = 'checkout';

	/**
	 * Verify a checkout captcha token.
	 *
	 * @since 1.2.0
	 *
	 * @param string               $token    Response token from the shopper.
	 * @param array<string, mixed> $settings {
	 *     Verification settings.
	 *
	 *     @type string $provider         Provider slug.
	 *     @type string $secret           Secret key.
	 *     @type string $remote_ip        Optional visitor IP.
	 *     @type float  $score_threshold  Minimum reCAPTCHA v3 score.
	 *     @type string $expected_action  Expected reCAPTCHA v3 action.
	 *     @type bool   $fail_closed      Whether HTTP/API outages should block checkout.
	 * }
	 * @return array{ok:bool, message:string, score:float|null, http_failed:bool}
	 */
	public function verify( $token, $settings ) {
		$token       = is_string( $token ) ? trim( $token ) : '';
		$provider    = isset( $settings['provider'] ) ? (string) $settings['provider'] : self::PROVIDER_RECAPTCHA_V2;
		$secret      = isset( $settings['secret'] ) ? (string) $settings['secret'] : '';
		$fail_closed = ! empty( $settings['fail_closed'] );

		if ( '' === $token ) {
			return $this->result( false, __( 'Please complete the captcha verification.', 'oli-checkout-captcha' ) );
		}

		if ( '' === $secret ) {
			return $this->result( false, __( 'Captcha is not configured.', 'oli-checkout-captcha' ) );
		}

		$url  = ( self::PROVIDER_TURNSTILE === $provider ) ? self::TURNSTILE_VERIFY_URL : self::RECAPTCHA_VERIFY_URL;
		$body = array(
			'secret'   => $secret,
			'response' => $token,
		);

		if ( ! empty( $settings['remote_ip'] ) && is_string( $settings['remote_ip'] ) ) {
			$body['remoteip'] = $settings['remote_ip'];
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => $body,
			)
		);

		if ( $this->is_http_failure( $response ) ) {
			if ( $fail_closed ) {
				return $this->result(
					false,
					__( 'Captcha verification is temporarily unavailable. Please try again.', 'oli-checkout-captcha' ),
					null,
					true
				);
			}

			return $this->result( true, '', null, true );
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $payload ) ) {
			if ( $fail_closed ) {
				return $this->result(
					false,
					__( 'Captcha verification failed. Please try again.', 'oli-checkout-captcha' ),
					null,
					true
				);
			}

			return $this->result( true, '', null, true );
		}

		if ( empty( $payload['success'] ) ) {
			return $this->result(
				false,
				__( 'Captcha verification failed. Please complete the challenge again.', 'oli-checkout-captcha' )
			);
		}

		if ( self::PROVIDER_RECAPTCHA_V3 === $provider ) {
			return $this->verify_recaptcha_v3( $payload, $settings );
		}

		return $this->result( true, '' );
	}

	/**
	 * Apply reCAPTCHA v3 score and action checks.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $payload  Decoded siteverify payload.
	 * @param array<string, mixed> $settings Verification settings.
	 * @return array{ok:bool, message:string, score:float|null, http_failed:bool}
	 */
	private function verify_recaptcha_v3( $payload, $settings ) {
		$score     = isset( $payload['score'] ) ? (float) $payload['score'] : 0.0;
		$threshold = isset( $settings['score_threshold'] ) ? (float) $settings['score_threshold'] : 0.5;
		$action    = isset( $payload['action'] ) ? (string) $payload['action'] : '';
		$expected  = isset( $settings['expected_action'] ) ? (string) $settings['expected_action'] : self::DEFAULT_ACTION;

		if ( $score < $threshold ) {
			return $this->result(
				false,
				__( 'Captcha verification failed. Please try again.', 'oli-checkout-captcha' ),
				$score
			);
		}

		if ( '' !== $expected && $action !== $expected ) {
			return $this->result(
				false,
				__( 'Captcha verification failed. Please try again.', 'oli-checkout-captcha' ),
				$score
			);
		}

		return $this->result( true, '', $score );
	}

	/**
	 * Whether the HTTP call failed or returned a non-success status.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed>|WP_Error $response wp_remote_post response.
	 * @return bool
	 */
	private function is_http_failure( $response ) {
		if ( is_wp_error( $response ) ) {
			return true;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return ( $code < 200 || $code >= 300 );
	}

	/**
	 * Build a verification result array.
	 *
	 * @since 1.2.0
	 *
	 * @param bool       $ok          Whether verification passed.
	 * @param string     $message     Shopper-facing error message.
	 * @param float|null $score       reCAPTCHA v3 score when present.
	 * @param bool       $http_failed Whether the provider HTTP call failed.
	 * @return array{ok:bool, message:string, score:float|null, http_failed:bool}
	 */
	private function result( $ok, $message, $score = null, $http_failed = false ) {
		return array(
			'ok'          => (bool) $ok,
			'message'     => (string) $message,
			'score'       => $score,
			'http_failed' => (bool) $http_failed,
		);
	}
}
