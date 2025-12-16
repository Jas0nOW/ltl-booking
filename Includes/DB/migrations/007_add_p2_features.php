<?php
/**
 * Migration 007: Add P2 Feature Tables
 * 
 * Adds tables for:
 * - Webhooks (event-driven notifications)
 * - Waitlist (booking waitlists)
 * - Group Bookings (multi-participant bookings)
 * - Packages (5er-Karten, subscriptions)
 */

if ( ! defined('ABSPATH') ) exit;

function ltlb_migration_007_up(): bool {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $tables = [
        'webhooks',
        'webhook_logs',
        'waitlist',
        'group_bookings',
        'group_participants',
        'packages',
        'package_usage',
    ];

    foreach ( $tables as $table ) {
        $sql = LTLB_DB_Schema::get_create_table_sql( '', $table );
        if ( ! empty( $sql ) ) {
            dbDelta( $sql );
        }
    }

    return true;
}

function ltlb_migration_007_down(): bool {
    global $wpdb;

    $tables = [
        'ltlb_webhooks',
        'ltlb_webhook_logs',
        'ltlb_waitlist',
        'ltlb_group_bookings',
        'ltlb_group_participants',
        'ltlb_packages',
        'ltlb_package_usage',
    ];

    foreach ( $tables as $table ) {
        $table_name = $wpdb->prefix . $table;
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    }

    return true;
}
