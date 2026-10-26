<?php
/**
 * WooCommerce Store API RouteException stub for unit tests.
 *
 * @package Olic_Checkout_Captcha
 */

namespace Automattic\WooCommerce\StoreApi\Exceptions;

if ( class_exists( RouteException::class, false ) ) {
	return;
}

/**
 * Mirrors WooCommerce Store API RouteException (error code + HTTP status).
 */
class RouteException extends \Exception {

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	private $error_code;

	/**
	 * Extra data attached to the error.
	 *
	 * @var array<string, mixed>
	 */
	private $additional_data = array();

	/**
	 * @param string               $error_code       Error code.
	 * @param string               $message          Human-readable message.
	 * @param int                  $http_status_code HTTP status.
	 * @param array<string, mixed> $additional_data  Optional extra data.
	 */
	public function __construct( $error_code, $message, $http_status_code = 400, $additional_data = array() ) {
		$this->error_code       = (string) $error_code;
		$this->additional_data  = array_filter( (array) $additional_data );
		parent::__construct( $message, (int) $http_status_code );
	}

	/**
	 * @return string
	 */
	public function getErrorCode() {
		return $this->error_code;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getAdditionalData() {
		return $this->additional_data;
	}
}
