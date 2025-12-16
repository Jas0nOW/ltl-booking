<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Only delete data if explicit opt-in is enabled.
 */
function ltlb_uninstall_should_delete_data(): bool {
	$settings = get_option( 'lazy_settings', [] );
	return ! empty( $settings['delete_data_on_uninstall'] );
}

/**
 * Cleanup for the current blog.
 */
function ltlb_uninstall_cleanup_current_blog(): void {
	if ( ! ltlb_uninstall_should_delete_data() ) {
		return;
	}

	global $wpdb;

	// Drop custom tables.
	$tables = [
		$wpdb->prefix . 'lazy_services',
		$wpdb->prefix . 'lazy_customers',
		$wpdb->prefix . 'lazy_appointments',
		$wpdb->prefix . 'lazy_staff_hours',
		$wpdb->prefix . 'lazy_staff_exceptions',
		$wpdb->prefix . 'lazy_resources',
		$wpdb->prefix . 'lazy_appointment_resources',
		$wpdb->prefix . 'lazy_service_resources',
		$wpdb->prefix . 'lazy_ai_actions',
	];

	foreach ( $tables as $table ) {
		// Table names cannot be prepared placeholders; we only use our own known names.
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
	}

	// Remove scheduled cron jobs.
	if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( 'ltlb_retention_cleanup' );
		wp_clear_scheduled_hook( 'ltlb_automation_runner' );
		wp_clear_scheduled_hook( 'ltlb_cleanup_old_appointments' );
	}

	// Delete plugin options.
	$option_keys = [
		'lazy_settings',
		'lazy_design',
		'lazy_design_backend',
		'lazy_ai_config',
		'lazy_business_context',
		'lazy_reply_templates',
		'lazy_ai_last_report',
		'lazy_api_keys',
		'lazy_payment_keys',
		'lazy_mail_keys',
		'ltlb_db_version',
		'ltlb_last_migration_time',
		'ltlb_calendar_status_colors',
	];

	foreach ( $option_keys as $key ) {
		delete_option( $key );
	}

	// Remove transients and internal lock options.
	$ltlb_prefix = $wpdb->esc_like( 'ltlb_' ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_' ) . $ltlb_prefix,
			$wpdb->esc_like( '_transient_timeout_' ) . $ltlb_prefix
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'ltlb_lock_opt_' ) . '%'
		)
	);

	// Remove user meta used by this plugin.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN (%s,%s)",
			'ltlb_ics_token',
			'ltlb_admin_lang'
		)
	);

	// Remove plugin roles and capabilities.
	if ( function_exists( 'remove_role' ) ) {
		remove_role( 'ltlb_ceo' );
		remove_role( 'ltlb_staff' );
	}

	if ( function_exists( 'get_role' ) ) {
		$caps = [
			'manage_ai_settings',
			'manage_ai_secrets',
			'view_ai_reports',
			'approve_ai_drafts',
		];
		$roles = [ 'administrator', 'ltlb_ceo' ];
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}

// Multisite: when uninstalled network-wide, clean up each blog (still respecting per-blog opt-in).
$network_wide = function_exists( 'is_multisite' ) && is_multisite()
	&& (
		( isset( $_GET['networkwide'] ) && in_array( (string) $_GET['networkwide'], [ '1', 'true' ], true ) )
		|| ( isset( $_POST['networkwide'] ) && in_array( (string) $_POST['networkwide'], [ '1', 'true' ], true ) )
	);

if ( $network_wide && function_exists( 'get_sites' ) && function_exists( 'switch_to_blog' ) && function_exists( 'restore_current_blog' ) ) {
	$site_ids = get_sites( [ 'fields' => 'ids' ] );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		ltlb_uninstall_cleanup_current_blog();
		restore_current_blog();
	}
} else {
	ltlb_uninstall_cleanup_current_blog();
}
