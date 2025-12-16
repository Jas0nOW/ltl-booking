<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Migration 005: Add availability rule fields to services table
 * 
 * Adds columns for:
 * - Min/max duration limits
 * - Buffer times before/after
 * - Max capacity per time slot
 */
function ltlb_migration_005_up(): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'lazy_services';

    // Check if columns already exist (idempotent)
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
    
    $changes = [];
    
    if ( ! in_array( 'min_duration_min', $columns, true ) ) {
        $changes[] = "ADD COLUMN min_duration_min INT UNSIGNED DEFAULT 0 COMMENT 'Minimum booking duration in minutes (0=use default)'";
    }
    
    if ( ! in_array( 'max_duration_min', $columns, true ) ) {
        $changes[] = "ADD COLUMN max_duration_min INT UNSIGNED DEFAULT 0 COMMENT 'Maximum booking duration in minutes (0=use default)'";
    }
    
    if ( ! in_array( 'buffer_before_min', $columns, true ) ) {
        $changes[] = "ADD COLUMN buffer_before_min INT UNSIGNED DEFAULT 0 COMMENT 'Buffer time before appointment in minutes'";
    }
    
    if ( ! in_array( 'buffer_after_min', $columns, true ) ) {
        $changes[] = "ADD COLUMN buffer_after_min INT UNSIGNED DEFAULT 0 COMMENT 'Buffer time after appointment in minutes'";
    }
    
    if ( ! in_array( 'max_capacity', $columns, true ) ) {
        $changes[] = "ADD COLUMN max_capacity INT UNSIGNED DEFAULT 1 COMMENT 'Maximum concurrent bookings for this service'";
    }
    
    if ( empty( $changes ) ) {
        // All columns already exist
        return true;
    }
    
    // Apply all changes in one ALTER TABLE
    $sql = "ALTER TABLE {$table} " . implode( ', ', $changes );
    $result = $wpdb->query( $sql );
    
    if ( $result !== false && class_exists( 'LTLB_Logger' ) ) {
        LTLB_Logger::info( 'Migration 005: Availability rule fields added to services table' );
    }
    
    return $result !== false;
}

/**
 * Rollback migration 005
 */
function ltlb_migration_005_down(): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'lazy_services';
    
    // Check which columns exist
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
    
    $drops = [];
    
    if ( in_array( 'min_duration_min', $columns, true ) ) {
        $drops[] = "DROP COLUMN min_duration_min";
    }
    if ( in_array( 'max_duration_min', $columns, true ) ) {
        $drops[] = "DROP COLUMN max_duration_min";
    }
    if ( in_array( 'buffer_before_min', $columns, true ) ) {
        $drops[] = "DROP COLUMN buffer_before_min";
    }
    if ( in_array( 'buffer_after_min', $columns, true ) ) {
        $drops[] = "DROP COLUMN buffer_after_min";
    }
    if ( in_array( 'max_capacity', $columns, true ) ) {
        $drops[] = "DROP COLUMN max_capacity";
    }
    
    if ( empty( $drops ) ) {
        return true;
    }
    
    $sql = "ALTER TABLE {$table} " . implode( ', ', $drops );
    $result = $wpdb->query( $sql );
    
    if ( $result !== false && class_exists( 'LTLB_Logger' ) ) {
        LTLB_Logger::info( 'Migration 005 rolled back: Availability rule fields removed from services table' );
    }
    
    return $result !== false;
}
