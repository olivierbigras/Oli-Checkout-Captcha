<?php
/**
 * Optional failed-attempt rate limiter.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Limits checkout captcha failures per visitor IP using transients.
 *
 * @since 1.2.0
 */
class Oli_OCC_Rate_Limiter {

	/**
	 * Transient key prefix.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const KEY_PREFIX = 'oli_occ_rl_';

	/**
	 * Whether rate limiting is enabled.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ( '1' === (string) get_option( 'oli_occ_rate_limit_enabled', '0' ) );
	}

	/**
	 * Whether the visitor is currently limited.
	 *
	 * @since 1.2.0
	 *
	 * @param string $ip      Visitor IP address.
	 * @param int    $max     Maximum failed attempts.
	 * @param int    $window  Window in seconds (unused for the read; kept for API symmetry).
	 * @return bool
	 */
	public function is_limited( $ip, $max, $window = 900 ) {
		unset( $window );

		if ( ! $this->is_enabled() || '' === $ip ) {
			return false;
		}

		$max   = max( 1, (int) $max );
		$count = (int) get_transient( $this->get_key( $ip ) );

		return ( $count >= $max );
	}

	/**
	 * Record a failed verification attempt.
	 *
	 * @since 1.2.0
	 *
	 * @param string $ip     Visitor IP address.
	 * @param int    $window Window in seconds.
	 * @return void
	 */
	public function record_failure( $ip, $window ) {
		if ( ! $this->is_enabled() || '' === $ip ) {
			return;
		}

		$key     = $this->get_key( $ip );
		$window  = max( 60, (int) $window );
		$current = (int) get_transient( $key );

		set_transient( $key, $current + 1, $window );
	}

	/**
	 * Clear the failure counter after a successful verification.
	 *
	 * @since 1.2.0
	 *
	 * @param string $ip Visitor IP address.
	 * @return void
	 */
	public function clear( $ip ) {
		if ( '' === $ip ) {
			return;
		}

		delete_transient( $this->get_key( $ip ) );
	}

	/**
	 * Build a transient key that does not store the raw IP.
	 *
	 * @since 1.2.0
	 *
	 * @param string $ip Visitor IP address.
	 * @return string
	 */
	private function get_key( $ip ) {
		return self::KEY_PREFIX . substr( hash( 'sha256', 'olic|' . $ip ), 0, 20 ); // Hash salt kept from 1.2.5 so migrated counters keep matching.
	}
}
