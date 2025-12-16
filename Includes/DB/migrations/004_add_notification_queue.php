<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Migration 004: Add notification queue table
 * 
 * Creates table for robust email/SMS notification queue with retry logic.
 */
function ltlb_migration_004_up(): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'ltlb_notification_queue';
    $charset_collate = $wpdb->get_charset_collate();

    // Check if table already exists
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
    if ( $table_exists ) {
        return true; // Already exists, idempotent
    }

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        type VARCHAR(20) NOT NULL COMMENT 'email or sms',
        recipient VARCHAR(255) NOT NULL COMMENT 'email address or phone number',
        subject VARCHAR(255) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        metadata TEXT DEFAULT NULL COMMENT 'JSON metadata (appointment_id, etc.)',
        status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, sent, failed_retry, failed_permanent',
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        error_message TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL,
        last_attempt_at DATETIME DEFAULT NULL,
        sent_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        INDEX idx_status (status),
        INDEX idx_type (type),
        INDEX idx_created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Verify table was created
    $created = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
    
    if ( $created && class_exists( 'LTLB_Logger' ) ) {
        LTLB_Logger::info( 'Migration 004: Notification queue table created successfully' );
    }

    return $created;
}

/**
 * Rollback migration 004
 */
function ltlb_migration_004_down(): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'ltlb_notification_queue';
    
    $result = $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    
    if ( $result !== false && class_exists( 'LTLB_Logger' ) ) {
        LTLB_Logger::info( 'Migration 004 rolled back: Notification queue table dropped' );
    }
    
    return $result !== false;
}
