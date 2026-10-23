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
