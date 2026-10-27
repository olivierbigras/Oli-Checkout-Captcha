<?php
/**
 * Minimal WordPress stubs for upgrade and in-place update tests.
 *
 * @package Olic_Checkout_Captcha
 */

$GLOBALS['olic_test_options']      = array();
$GLOBALS['olic_test_site_options'] = array();
$GLOBALS['olic_test_filters']      = array();
$GLOBALS['olic_test_actions']      = array();
$GLOBALS['olic_test_is_multisite'] = true;

if ( ! class_exists( 'olic_test_wpdb', false ) ) {
	/**
	 * In-memory wpdb stand-in that returns unfiltered option rows.
	 */
	class olic_test_wpdb {
		/**
		 * Options table name.
		 *
		 * @var string
		 */
		public $options = 'wp_options';

		/**
		 * Sitemeta table name.
		 *
		 * @var string
		 */
		public $sitemeta = 'wp_sitemeta';

		/**
		 * Prepare a query with sprintf-style placeholders.
		 *
		 * @param string $query Query with %s / %d placeholders.
		 * @param mixed  ...$args Replacements.
		 * @return string
		 */
		public function prepare( $query, ...$args ) {
			foreach ( $args as $arg ) {
				if ( is_int( $arg ) ) {
					$query = preg_replace( '/%d/', (string) $arg, $query, 1 );
					continue;
				}

				$query = preg_replace( '/%s/', "'" . addslashes( (string) $arg ) . "'", $query, 1 );
			}

			return $query;
		}

		/**
		 * Read a stored option or site option by inspecting the SQL.
		 *
		 * @param string $query Prepared query.
		 * @return string|null Serialized value or null.
		 */
		public function get_var( $query ) {
			if ( preg_match( "/option_name = '([^']+)'/", $query, $matches ) ) {
				$name = stripslashes( $matches[1] );
				if ( ! array_key_exists( $name, $GLOBALS['olic_test_options'] ) ) {
					return null;
				}

				return maybe_serialize( $GLOBALS['olic_test_options'][ $name ] );
			}

			if ( preg_match( "/meta_key = '([^']+)'/", $query, $matches ) ) {
				$name = stripslashes( $matches[1] );
				if ( ! array_key_exists( $name, $GLOBALS['olic_test_site_options'] ) ) {
					return null;
				}

				return maybe_serialize( $GLOBALS['olic_test_site_options'][ $name ] );
			}

			return null;
		}

		/**
		 * Escape LIKE wildcards the same way WordPress does.
		 *
		 * @param string $text Raw fragment.
		 * @return string
		 */
		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}

		/**
		 * Run a prepared DELETE against the in-memory options table.
		 *
		 * @param string $query Prepared SQL.
		 * @return int Number of deleted rows.
		 */
		public function query( $query ) {
			if ( ! preg_match( '/DELETE FROM\s+(\S+)\s+WHERE\s+(.+)/is', $query, $matches ) ) {
				return 0;
			}

			if ( $this->options !== $matches[1] ) {
				return 0;
			}

			if ( ! preg_match_all( "/option_name LIKE '((?:[^'\\\\]|\\\\.)*)'/", $matches[2], $like_matches ) ) {
				return 0;
			}

			$deleted = 0;
			foreach ( array_keys( $GLOBALS['olic_test_options'] ) as $name ) {
				foreach ( $like_matches[1] as $pattern ) {
					if ( $this->like_match( $name, stripslashes( $pattern ) ) ) {
						unset( $GLOBALS['olic_test_options'][ $name ] );
						++$deleted;
						break;
					}
				}
			}

			return $deleted;
		}

		/**
		 * Match a value against a SQL LIKE pattern that uses \ as escape.
		 *
		 * @param string $value   Option name.
		 * @param string $pattern LIKE pattern.
		 * @return bool
		 */
		private function like_match( $value, $pattern ) {
			$regex = '';
			$length = strlen( $pattern );

			for ( $i = 0; $i < $length; $i++ ) {
				$char = $pattern[ $i ];
				if ( '\\' === $char && isset( $pattern[ $i + 1 ] ) ) {
					$regex .= preg_quote( $pattern[ $i + 1 ], '/' );
					++$i;
					continue;
				}
				if ( '%' === $char ) {
					$regex .= '.*';
					continue;
				}
				if ( '_' === $char ) {
					$regex .= '.';
					continue;
				}
				$regex .= preg_quote( $char, '/' );
			}

			return (bool) preg_match( '/^' . $regex . '$/', $value );
		}
	}
}

$GLOBALS['wpdb'] = new olic_test_wpdb();

if ( ! function_exists( 'maybe_serialize' ) ) {
	/**
	 * @param mixed $data Data to serialize when needed.
	 * @return mixed
	 */
	function maybe_serialize( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			return serialize( $data );
		}

		return $data;
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	/**
	 * @param mixed $data Data to unserialize when needed.
	 * @return mixed
	 */
	function maybe_unserialize( $data ) {
		if ( ! is_string( $data ) ) {
			return $data;
		}

		$unserialized = @unserialize( $data, array( 'allowed_classes' => false ) );
		if ( false !== $unserialized || 'b:0;' === $data ) {
			return $unserialized;
		}

		return $data;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $option_name Option name.
	 * @param mixed  $default     Default value.
	 * @return mixed
	 */
	function get_option( $option_name, $default = false ) {
		$value = array_key_exists( $option_name, $GLOBALS['olic_test_options'] )
			? $GLOBALS['olic_test_options'][ $option_name ]
			: $default;

		return apply_filters( 'option_' . $option_name, $value, $option_name );
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $option_name Option name.
	 * @param mixed  $value       Value to store.
	 * @param mixed  $autoload    Unused.
	 * @return bool
	 */
	function update_option( $option_name, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['olic_test_options'][ $option_name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * @param string $option_name Option name.
	 * @param mixed  $value       Value to store.
	 * @return bool
	 */
	function add_option( $option_name, $value ) {
		if ( array_key_exists( $option_name, $GLOBALS['olic_test_options'] ) ) {
			return false;
		}

		$GLOBALS['olic_test_options'][ $option_name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $option_name Option name.
	 * @return bool
	 */
	function delete_option( $option_name ) {
		unset( $GLOBALS['olic_test_options'][ $option_name ] );
		return true;
	}
}

if ( ! function_exists( 'update_site_option' ) ) {
	/**
	 * @param string $option_name Option name.
	 * @param mixed  $value       Value to store.
	 * @return bool
	 */
	function update_site_option( $option_name, $value ) {
		$GLOBALS['olic_test_site_options'][ $option_name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted args.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		unset( $priority, $args );
		$GLOBALS['olic_test_filters'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value to filter.
	 * @param mixed  ...$args Additional args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['olic_test_filters'][ $hook ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['olic_test_filters'][ $hook ] as $callback ) {
			$value = call_user_func( $callback, $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted args.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		unset( $priority, $args );
		$GLOBALS['olic_test_actions'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Args.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
		if ( empty( $GLOBALS['olic_test_actions'][ $hook ] ) ) {
			return;
		}

		foreach ( $GLOBALS['olic_test_actions'][ $hook ] as $callback ) {
			call_user_func( $callback, ...$args );
		}
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * @param string   $file     Plugin file.
	 * @param callable $callback Callback.
	 * @return void
	 */
	function register_activation_hook( $file, $callback ) {
		unset( $file, $callback );
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * @return bool
	 */
	function is_multisite() {
		return ! empty( $GLOBALS['olic_test_is_multisite'] );
	}
}

if ( ! function_exists( 'get_current_network_id' ) ) {
	/**
	 * @return int
	 */
	function get_current_network_id() {
		return 1;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * @param string $file Absolute plugin file path.
	 * @return string
	 */
	function plugin_basename( $file ) {
		$file = str_replace( '\\', '/', (string) $file );

		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$plugin_dir = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' );
			if ( 0 === strpos( $file, $plugin_dir . '/' ) ) {
				return ltrim( substr( $file, strlen( $plugin_dir ) ), '/' );
			}
		}

		$parts = explode( '/', $file );
		$count = count( $parts );
		if ( $count >= 2 ) {
			return $parts[ $count - 2 ] . '/' . $parts[ $count - 1 ];
		}

		return (string) end( $parts );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * @param string $file File path.
	 * @return string
	 */
	function plugin_dir_path( $file ) {
		return rtrim( str_replace( '\\', '/', dirname( $file ) ), '/' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * @param string $file File path.
	 * @return string
	 */
	function plugin_dir_url( $file ) {
		return 'http://example.test/wp-content/plugins/' . dirname( plugin_basename( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	/**
	 * @param string $path Path relative to the plugin.
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugins_url( $path = '', $file = '' ) {
		return rtrim( plugin_dir_url( $file ), '/' ) . '/' . ltrim( (string) $path, '/' );
	}
}

$GLOBALS['olic_test_http_requests']  = array();
$GLOBALS['olic_test_http_response']  = null;
$GLOBALS['olic_test_transients']     = array();

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Minimal WP_Error stand-in.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code = '';

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message = '';

		/**
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Unused.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			unset( $data );
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value to test.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Request args.
	 * @return array<string, mixed>|WP_Error
	 */
	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['olic_test_http_requests'][] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( is_callable( $GLOBALS['olic_test_http_response'] ) ) {
			return call_user_func( $GLOBALS['olic_test_http_response'], $url, $args );
		}

		if ( null === $GLOBALS['olic_test_http_response'] ) {
			return new WP_Error( 'http_request_failed', 'No mocked HTTP response.' );
		}

		return $GLOBALS['olic_test_http_response'];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param array<string, mixed>|WP_Error $response HTTP response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['body'] ) ) {
			return '';
		}

		return (string) $response['body'];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param array<string, mixed>|WP_Error $response HTTP response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return 0;
		}

		if ( isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return 0;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $value Raw value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		if ( is_string( $value ) ) {
			return stripslashes( $value );
		}

		return $value;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @param string $transient Transient name.
	 * @return mixed
	 */
	function get_transient( $transient ) {
		return array_key_exists( $transient, $GLOBALS['olic_test_transients'] )
			? $GLOBALS['olic_test_transients'][ $transient ]
			: false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param string $transient Transient name.
	 * @param mixed  $value     Value.
	 * @param int    $expiration Expiration.
	 * @return bool
	 */
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['olic_test_transients'][ $transient ]                  = $value;
		$GLOBALS['olic_test_options'][ '_transient_' . $transient ]     = $value;
		if ( (int) $expiration > 0 ) {
			$GLOBALS['olic_test_options'][ '_transient_timeout_' . $transient ] = time() + (int) $expiration;
		}
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * @param string $transient Transient name.
	 * @return bool
	 */
	function delete_transient( $transient ) {
		unset( $GLOBALS['olic_test_transients'][ $transient ] );
		unset( $GLOBALS['olic_test_options'][ '_transient_' . $transient ] );
		unset( $GLOBALS['olic_test_options'][ '_transient_timeout_' . $transient ] );
		return true;
	}
}

$GLOBALS['olic_test_registered_scripts'] = array();
$GLOBALS['olic_test_enqueued_scripts']   = array();
$GLOBALS['olic_test_enqueued_styles']    = array();
$GLOBALS['olic_test_localized_scripts']  = array();
$GLOBALS['olic_test_is_checkout']        = false;
$GLOBALS['olic_test_is_order_received']  = false;
$GLOBALS['olic_test_is_checkout_pay']    = false;
$GLOBALS['olic_test_has_blocks']         = array();
$GLOBALS['olic_test_has_blocks_by_page'] = array();
$GLOBALS['olic_test_checkout_page_id']   = 0;

if ( ! function_exists( 'wp_register_script' ) ) {
	/**
	 * @param string           $handle Script handle.
	 * @param string           $src    Script URL.
	 * @param array<int, string> $deps Dependencies.
	 * @param string|bool|null $ver    Version.
	 * @param bool|array<string, mixed> $args Footer flag or args.
	 * @return bool
	 */
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = false ) {
		$GLOBALS['olic_test_registered_scripts'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
			'args' => $args,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * @param string           $handle Script handle.
	 * @param string           $src    Script URL.
	 * @param array<int, string> $deps Dependencies.
	 * @param string|bool|null $ver    Version.
	 * @param bool|array<string, mixed> $args Footer flag or args.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = false ) {
		if ( '' !== $src ) {
			wp_register_script( $handle, $src, $deps, $ver, $args );
		}

		$GLOBALS['olic_test_enqueued_scripts'][] = $handle;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * @param string           $handle Style handle.
	 * @param string           $src    Style URL.
	 * @param array<int, string> $deps Dependencies.
	 * @param string|bool|null $ver    Version.
	 * @param string           $media  Media.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['olic_test_enqueued_styles'][ $handle ] = array(
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * @param string               $handle Script handle.
	 * @param string               $name   JS object name.
	 * @param array<string, mixed> $data   Data.
	 * @return bool
	 */
	function wp_localize_script( $handle, $name, $data ) {
		$GLOBALS['olic_test_localized_scripts'][ $handle ] = array(
			'name' => $name,
			'data' => $data,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	/**
	 * @param string $handle Script handle.
	 * @param string $list   Queue to check.
	 * @return bool
	 */
	function wp_script_is( $handle, $list = 'enqueued' ) {
		if ( 'registered' === $list ) {
			return isset( $GLOBALS['olic_test_registered_scripts'][ $handle ] );
		}

		if ( 'enqueued' === $list ) {
			return in_array( $handle, $GLOBALS['olic_test_enqueued_scripts'], true );
		}

		return false;
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	/**
	 * @return bool
	 */
	function is_checkout() {
		return ! empty( $GLOBALS['olic_test_is_checkout'] );
	}
}

if ( ! function_exists( 'is_order_received_page' ) ) {
	/**
	 * @return bool
	 */
	function is_order_received_page() {
		return ! empty( $GLOBALS['olic_test_is_order_received'] );
	}
}

if ( ! function_exists( 'is_checkout_pay_page' ) ) {
	/**
	 * @return bool
	 */
	function is_checkout_pay_page() {
		return ! empty( $GLOBALS['olic_test_is_checkout_pay'] );
	}
}

if ( ! function_exists( 'has_block' ) ) {
	/**
	 * @param string     $block_name Block name.
	 * @param int|object $post       Optional post.
	 * @return bool
	 */
	function has_block( $block_name, $post = null ) {
		$blocks = isset( $GLOBALS['olic_test_has_blocks'] ) && is_array( $GLOBALS['olic_test_has_blocks'] )
			? $GLOBALS['olic_test_has_blocks']
			: array();

		if ( null === $post || 0 === $post ) {
			return in_array( $block_name, $blocks, true );
		}

		$page_blocks = isset( $GLOBALS['olic_test_has_blocks_by_page'][ (int) $post ] )
			? $GLOBALS['olic_test_has_blocks_by_page'][ (int) $post ]
			: array();

		return in_array( $block_name, $page_blocks, true );
	}
}

if ( ! function_exists( 'wc_get_page_id' ) ) {
	/**
	 * @param string $page Page slug.
	 * @return int
	 */
	function wc_get_page_id( $page ) {
		if ( 'checkout' === $page ) {
			return (int) $GLOBALS['olic_test_checkout_page_id'];
		}

		return 0;
	}
}
