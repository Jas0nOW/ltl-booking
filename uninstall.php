<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Only delete data if explicit opt-in is enabled
$settings = get_option('lazy_settings', []);
$delete = !empty($settings['delete_data_on_uninstall']);

if ( ! $delete ) {
	// Do nothing; keep data for safety
	return;
}

global $wpdb;

// Drop custom tables
$tables = [
	$wpdb->prefix . 'lazy_services',
	$wpdb->prefix . 'lazy_customers',
	$wpdb->prefix . 'lazy_appointments',
	$wpdb->prefix . 'lazy_staff_hours',
	$wpdb->prefix . 'lazy_staff_exceptions',
	$wpdb->prefix . 'lazy_resources',
	$wpdb->prefix . 'lazy_appointment_resources',
	$wpdb->prefix . 'lazy_service_resources',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete plugin options
delete_option('lazy_settings');
delete_option('lazy_design');
delete_option('ltlb_db_version');
