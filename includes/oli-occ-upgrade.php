<?php
/**
 * Upgrade helpers: option migration and legacy main-file rewrite.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map of legacy option names to the prefixed oli_occ_ options.
 *
 * @since 1.1.0
 *
 * @return array<string, string>
 */
function oli_occ_get_legacy_option_map() {
	return array(
		'rbc_site_key'   => 'oli_occ_site_key',
		'rbc_secret_key' => 'oli_occ_secret_key',
		'rbc_theme'      => 'oli_occ_theme',
	);
}

/**
 * Target version stored after upgrade routines run.
 *
 * @since 1.1.1
 *
 * @return string
 */
function oli_occ_get_upgrade_target_version() {
	return defined( 'OLI_OCC_VERSION' ) ? OLI_OCC_VERSION : '1.1.1';
}

/**
 * Read an option directly from the database, without option filters.
 *
 * @since 1.1.1
 *
 * @param string $option_name Option name.
 * @return mixed False when the row is missing.
 */
function oli_occ_get_raw_option( $option_name ) {
	global $wpdb;

	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->options ) ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Must read the unfiltered option value.
	$raw = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT option_value FROM %i WHERE option_name = %s LIMIT 1',
			$wpdb->options,
			$option_name
		)
	);

	if ( null === $raw ) {
		return false;
	}

	return maybe_unserialize( $raw );
}

/**
 * Read a network option directly from sitemeta, without filters.
 *
 * @since 1.1.1
 *
 * @param string $option_name Option name.
 * @return mixed False when not multisite or the row is missing.
 */
function oli_occ_get_raw_site_option( $option_name ) {
	global $wpdb;

	if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
		return false;
	}

	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->sitemeta ) ) {
		return false;
	}

	$network_id = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Must read the unfiltered site option value.
	$raw = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT meta_value FROM %i WHERE meta_key = %s AND site_id = %d LIMIT 1',
			$wpdb->sitemeta,
			$option_name,
			$network_id
		)
	);

	if ( null === $raw ) {
		return false;
	}

	return maybe_unserialize( $raw );
}

/**
 * Replace only the targeted basename in a numerically indexed plugin list.
 *
 * @since 1.1.1
 *
 * @param mixed  $plugins Raw active_plugins value.
 * @param string $old_basename Old plugin basename.
 * @param string $new_basename New plugin basename.
 * @return array{0: mixed, 1: bool}
 */
function oli_occ_replace_active_plugin_in_list( $plugins, $old_basename, $new_basename ) {
	if ( ! is_array( $plugins ) ) {
		return array( $plugins, false );
	}

	$changed = false;

	foreach ( $plugins as $index => $plugin ) {
		if ( $old_basename !== $plugin ) {
			continue;
		}

		$plugins[ $index ] = $new_basename;
		$changed           = true;
	}

	if ( ! $changed ) {
		return array( $plugins, false );
	}

	$deduped = array();
	foreach ( $plugins as $plugin ) {
		if ( in_array( $plugin, $deduped, true ) ) {
			continue;
		}
		$deduped[] = $plugin;
	}

	return array( $deduped, true );
}

/**
 * Replace only the targeted basename in a sitewide plugin map.
 *
 * @since 1.1.1
 *
 * @param mixed  $plugins Raw active_sitewide_plugins value.
 * @param string $old_basename Old plugin basename.
 * @param string $new_basename New plugin basename.
 * @return array{0: mixed, 1: bool}
 */
function oli_occ_replace_active_plugin_in_sitewide( $plugins, $old_basename, $new_basename ) {
	if ( ! is_array( $plugins ) || ! array_key_exists( $old_basename, $plugins ) ) {
		return array( $plugins, false );
	}

	$timestamp = $plugins[ $old_basename ];
	unset( $plugins[ $old_basename ] );

	if ( ! array_key_exists( $new_basename, $plugins ) ) {
		$plugins[ $new_basename ] = $timestamp;
	}

	return array( $plugins, true );
}

/**
 * Point stored active-plugin paths at the new main file.
 *
 * Reads the raw option rows so filtered values are not persisted.
 *
 * @since 1.1.1
 *
 * @return void
 */
function oli_occ_maybe_rewrite_active_plugin_file() {
	if ( ! defined( 'OLI_OCC_PLUGIN_FILE' ) ) {
		return;
	}

	$new_basename = plugin_basename( OLI_OCC_PLUGIN_FILE );
	$old_basename = dirname( $new_basename ) . '/recaptcha-block-checkout.php';

	if ( $old_basename === $new_basename ) {
		return;
	}

	$active                   = oli_occ_get_raw_option( 'active_plugins' );
	list( $active, $changed ) = oli_occ_replace_active_plugin_in_list( $active, $old_basename, $new_basename );
	if ( $changed ) {
		update_option( 'active_plugins', $active );
	}

	$sitewide                        = oli_occ_get_raw_site_option( 'active_sitewide_plugins' );
	list( $sitewide, $site_changed ) = oli_occ_replace_active_plugin_in_sitewide( $sitewide, $old_basename, $new_basename );
	if ( $site_changed ) {
		update_site_option( 'active_sitewide_plugins', $sitewide );
	}
}

/**
 * Option names used by 1.2.5 and earlier (4-letter prefix) mapped to the current names.
 *
 * @since 1.2.6
 *
 * @return array<string, string>
 */
function oli_occ_get_previous_prefix_option_map() {
	$map = array();
	foreach ( array( 'provider', 'site_key', 'secret_key', 'theme', 'score_threshold', 'fail_closed', 'rate_limit_enabled', 'rate_limit_max', 'rate_limit_window' ) as $suffix ) {
		$map[ 'olic_' . $suffix ] = 'oli_occ_' . $suffix;
	}

	return $map;
}

/**
 * Copy one option to its new name, then delete the old row once the copy is confirmed.
 *
 * Idempotent: an existing new value is never overwritten, and a leftover old row is
 * only removed when the new row exists.
 *
 * @since 1.2.6
 *
 * @param string $old_name Old option name.
 * @param string $new_name New option name.
 * @return bool True when the old row no longer exists.
 */
function oli_occ_migrate_option( $old_name, $new_name ) {
	$old_value = oli_occ_get_raw_option( $old_name );
	if ( false === $old_value ) {
		return true;
	}

	if ( false === oli_occ_get_raw_option( $new_name ) ) {
		add_option( $new_name, $old_value );
		wp_cache_delete( $new_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		if ( maybe_serialize( oli_occ_get_raw_option( $new_name ) ) !== maybe_serialize( $old_value ) ) {
			return false;
		}
	}

	return delete_option( $old_name ) || false === oli_occ_get_raw_option( $old_name );
}

/**
 * Move live rate-limit counters from olic_rl_* to oli_occ_rl_* with their remaining lifetime.
 *
 * @since 1.2.6
 *
 * @return void
 */
function oli_occ_migrate_rate_limit_transients() {
	global $wpdb;

	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->options ) ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration of transient rows.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT option_name, option_value FROM %i WHERE option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like( '_transient_olic_rl_' ) . '%'
		),
		ARRAY_A
	);

	if ( ! is_array( $rows ) ) {
		return;
	}

	$now = time();
	foreach ( $rows as $row ) {
		$suffix = substr( (string) $row['option_name'], strlen( '_transient_olic_rl_' ) );
		if ( '' === $suffix ) {
			continue;
		}

		$expires   = (int) oli_occ_get_raw_option( '_transient_timeout_olic_rl_' . $suffix );
		$remaining = $expires - $now;

		if ( $remaining > 0 && false === get_transient( 'oli_occ_rl_' . $suffix ) ) {
			set_transient( 'oli_occ_rl_' . $suffix, (int) maybe_unserialize( $row['option_value'] ), $remaining );
		}

		delete_transient( 'olic_rl_' . $suffix );
	}
}

/**
 * Fall back to the 1.2.5 option while its row still exists (for example if a migration step failed).
 *
 * @since 1.2.6
 *
 * @return void
 */
function oli_occ_register_option_fallbacks() {
	foreach ( oli_occ_get_previous_prefix_option_map() as $old_name => $new_name ) {
		add_filter(
			'default_option_' . $new_name,
			static function ( $default_value ) use ( $old_name ) {
				$old_value = oli_occ_get_raw_option( $old_name );
				return false === $old_value ? $default_value : $old_value;
			}
		);
	}
}

/**
 * Run option migrations once per version (rbc_* and olic_* to oli_occ_*).
 *
 * Runs on activation and on plugins_loaded so in-place updates do not need a reactivation.
 *
 * @since 1.1.0
 * @since 1.2.6 Migrates the 1.2.5 olic_* options and rate-limit transients.
 *
 * @return void
 */
function oli_occ_maybe_migrate_options() {
	$target_version = oli_occ_get_upgrade_target_version();
	$stored_version = get_option( 'oli_occ_db_version', '' );

	if ( is_string( $stored_version ) && '' !== $stored_version && version_compare( $stored_version, $target_version, '>=' ) ) {
		return;
	}

	$complete = true;

	foreach ( oli_occ_get_legacy_option_map() as $legacy_name => $new_name ) {
		$complete = oli_occ_migrate_option( $legacy_name, $new_name ) && $complete;
	}

	foreach ( oli_occ_get_previous_prefix_option_map() as $old_name => $new_name ) {
		$complete = oli_occ_migrate_option( $old_name, $new_name ) && $complete;
	}

	oli_occ_migrate_rate_limit_transients();

	if ( ! $complete ) {
		// Keep the old rows and retry on the next load; reads fall back to them meanwhile.
		return;
	}

	delete_option( 'olic_db_version' );
	update_option( 'oli_occ_db_version', $target_version );
}
