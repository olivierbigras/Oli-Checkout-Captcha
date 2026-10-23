<?php
/**
 * Upgrade helpers: option migration and legacy main-file rewrite.
 *
 * @package Olic_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map of legacy option names to the prefixed olic_ options.
 *
 * @since 1.1.0
 *
 * @return array<string, string>
 */
function olic_get_legacy_option_map() {
	return array(
		'rbc_site_key'   => 'olic_site_key',
		'rbc_secret_key' => 'olic_secret_key',
		'rbc_theme'      => 'olic_theme',
	);
}

/**
 * Target version stored after upgrade routines run.
 *
 * @since 1.1.1
 *
 * @return string
 */
function olic_get_upgrade_target_version() {
	return defined( 'OLIC_VERSION' ) ? OLIC_VERSION : '1.1.1';
}

/**
 * Read an option directly from the database, without option filters.
 *
 * @since 1.1.1
 *
 * @param string $option_name Option name.
 * @return mixed False when the row is missing.
 */
function olic_get_raw_option( $option_name ) {
	global $wpdb;

	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->options ) ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Must read the unfiltered option value.
	$raw = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
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
function olic_get_raw_site_option( $option_name ) {
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
			"SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d LIMIT 1",
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
function olic_replace_active_plugin_in_list( $plugins, $old_basename, $new_basename ) {
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
function olic_replace_active_plugin_in_sitewide( $plugins, $old_basename, $new_basename ) {
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
function olic_maybe_rewrite_active_plugin_file() {
	if ( ! defined( 'OLIC_PLUGIN_FILE' ) ) {
		return;
	}

	$new_basename = plugin_basename( OLIC_PLUGIN_FILE );
	$old_basename = dirname( $new_basename ) . '/recaptcha-block-checkout.php';

	if ( $old_basename === $new_basename ) {
		return;
	}

	$active                   = olic_get_raw_option( 'active_plugins' );
	list( $active, $changed ) = olic_replace_active_plugin_in_list( $active, $old_basename, $new_basename );
	if ( $changed ) {
		update_option( 'active_plugins', $active );
	}

	$sitewide                        = olic_get_raw_site_option( 'active_sitewide_plugins' );
	list( $sitewide, $site_changed ) = olic_replace_active_plugin_in_sitewide( $sitewide, $old_basename, $new_basename );
	if ( $site_changed ) {
		update_site_option( 'active_sitewide_plugins', $sitewide );
	}
}

/**
 * Migrate options from the previous rbc_ prefix when the version marker is behind.
 *
 * Runs on plugins_loaded so in-place updates do not need a reactivation.
 *
 * @since 1.1.0
 *
 * @return void
 */
function olic_maybe_migrate_options() {
	$target_version = olic_get_upgrade_target_version();
	$stored_version = get_option( 'olic_db_version', '' );

	if ( is_string( $stored_version ) && '' !== $stored_version && version_compare( $stored_version, $target_version, '>=' ) ) {
		return;
	}

	foreach ( olic_get_legacy_option_map() as $legacy_name => $new_name ) {
		if ( false !== get_option( $new_name, false ) ) {
			continue;
		}

		$legacy_value = get_option( $legacy_name, false );
		if ( false === $legacy_value ) {
			continue;
		}

		add_option( $new_name, $legacy_value );
		delete_option( $legacy_name );
	}

	update_option( 'olic_db_version', $target_version );
}
