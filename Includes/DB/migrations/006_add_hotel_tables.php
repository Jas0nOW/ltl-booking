<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Migration 006: Add Hotel-specific tables (Rooms, RoomTypes)
 * 
 * Separates Hotel entities from Services entities for clean data model.
 * Shared entities: Customers, Appointments, Payments remain in existing tables.
 */
function ltlb_migration_006_up(): bool {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $success = true;

    // 1. Room Types Table (Hotel mode only)
    $room_types_table = $wpdb->prefix . 'ltlb_room_types';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$room_types_table}'" ) !== $room_types_table ) {
        $sql = "CREATE TABLE {$room_types_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            max_occupancy INT UNSIGNED DEFAULT 2,
            base_price_cents INT UNSIGNED DEFAULT 0 COMMENT 'Base price per night in cents',
            amenities TEXT DEFAULT NULL COMMENT 'JSON array of amenities',
            image_url VARCHAR(500) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY display_order (display_order)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        $success = $success && ( $wpdb->get_var( "SHOW TABLES LIKE '{$room_types_table}'" ) === $room_types_table );
    }

    // 2. Rooms Table (Hotel mode only)
    $rooms_table = $wpdb->prefix . 'ltlb_rooms';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$rooms_table}'" ) !== $rooms_table ) {
        $sql = "CREATE TABLE {$rooms_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            room_number VARCHAR(50) NOT NULL,
            room_type_id BIGINT UNSIGNED DEFAULT NULL,
            floor_number INT DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'available' COMMENT 'available, occupied, maintenance, blocked',
            notes TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY room_number (room_number),
            KEY room_type_id (room_type_id),
            KEY status (status),
            KEY is_active (is_active),
            KEY display_order (display_order)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        $success = $success && ( $wpdb->get_var( "SHOW TABLES LIKE '{$rooms_table}'" ) === $rooms_table );
    }

    // 3. Rate Plans Table (Hotel mode only)
    $rate_plans_table = $wpdb->prefix . 'ltlb_rate_plans';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$rate_plans_table}'" ) !== $rate_plans_table ) {
        $sql = "CREATE TABLE {$rate_plans_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            room_type_id BIGINT UNSIGNED DEFAULT NULL,
            price_modifier_type VARCHAR(20) DEFAULT 'percentage' COMMENT 'percentage, fixed_amount, override',
            price_modifier_value DECIMAL(10,2) DEFAULT 0,
            min_nights INT UNSIGNED DEFAULT 1,
            max_nights INT UNSIGNED DEFAULT NULL,
            valid_from DATE DEFAULT NULL,
            valid_to DATE DEFAULT NULL,
            valid_weekdays VARCHAR(50) DEFAULT NULL COMMENT 'Comma-separated: 0-6 (0=Sunday)',
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY room_type_id (room_type_id),
            KEY is_active (is_active),
            KEY valid_from (valid_from),
            KEY valid_to (valid_to)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        $success = $success && ( $wpdb->get_var( "SHOW TABLES LIKE '{$rate_plans_table}'" ) === $rate_plans_table );
    }

    // 4. Add mode discriminator to appointments table
    $appointments_table = $wpdb->prefix . 'lazy_appointments';
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$appointments_table}" );
    
    if ( ! in_array( 'booking_mode', $columns, true ) ) {
        $wpdb->query( "ALTER TABLE {$appointments_table} ADD COLUMN booking_mode VARCHAR(20) DEFAULT 'service' COMMENT 'service or hotel'" );
        $success = $success && in_array( 'booking_mode', $wpdb->get_col( "SHOW COLUMNS FROM {$appointments_table}" ), true );
    }
    
    if ( ! in_array( 'room_id', $columns, true ) ) {
        $wpdb->query( "ALTER TABLE {$appointments_table} ADD COLUMN room_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'For hotel bookings'" );
        $wpdb->query( "ALTER TABLE {$appointments_table} ADD INDEX idx_room_id (room_id)" );
    }
    
    if ( ! in_array( 'room_type_id', $columns, true ) ) {
        $wpdb->query( "ALTER TABLE {$appointments_table} ADD COLUMN room_type_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'For hotel bookings'" );
    }
    
    if ( ! in_array( 'rate_plan_id', $columns, true ) ) {
        $wpdb->query( "ALTER TABLE {$appointments_table} ADD COLUMN rate_plan_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'For hotel bookings'" );
    }
    
    if ( ! in_array( 'num_guests', $columns, true ) ) {
        $wpdb->query( "ALTER TABLE {$appointments_table} ADD COLUMN num_guests INT UNSIGNED DEFAULT 1 COMMENT 'Number of guests'" );
    }

    if ( $success && class_exists( 'LTLB_Logger' ) ) {
        LTLB_Logger::info( 'Migration 006: Hotel tables created successfully (room_types, rooms, rate_plans)' );
    }

    return $success;
}

/**
 * Rollback migration 006
 */
function ltlb_migration_006_down(): bool {
    global $wpdb;
    
    // Drop Hotel-specific tables
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ltlb_rate_plans" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ltlb_rooms" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ltlb_room_types" );
    
    // Remove Hotel-specific columns from appointments
    $appointments_table = $wpdb->prefix . 'lazy_appointments';
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$appointments_table}" );
    
    $drops = [];
    if ( in_array( 'booking_mode', $columns, true ) ) $drops[] = "DROP COLUMN booking_mode";
    if ( in_array( 'room_id', $columns, true ) ) $drops[] = "DROP COLUMN room_id";
    if ( in_array( 'room_type_id', $columns, true ) ) $drops[] = "DROP COLUMN room_type_id";
    if ( in_array( 'rate_plan_id', $columns, true ) ) $drops[] = "DROP COLUMN rate_plan_id";
    if ( in_array( 'num_guests', $columns, true ) ) $drops[] = "DROP COLUMN num_guests";
    
    if ( ! empty( $drops ) ) {
        $wpdb->query( "ALTER TABLE {$appointments_table} " . implode( ', ', $drops ) );
    }
    
    if ( class_exists( 'LTLB_Logger' ) ) {
        LTLB_Logger::info( 'Migration 006 rolled back: Hotel tables dropped' );
    }
    
    return true;
}
