<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_DB_Schema {

    public static function get_create_table_sql(string $table_name, string $type): string {
        // Einheitliches Charset/Collation korrekt aus WP holen
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        if ($type === 'services') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                description LONGTEXT NULL,
                staff_user_id BIGINT UNSIGNED NULL,
                duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
                buffer_before_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                buffer_after_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                price_cents INT UNSIGNED NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_group TINYINT(1) NOT NULL DEFAULT 0,
                max_seats_per_booking SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                availability_mode VARCHAR(20) NOT NULL DEFAULT 'window',
                available_weekdays VARCHAR(20) NULL,
                available_start_time TIME NULL,
                available_end_time TIME NULL,
                fixed_weekly_slots LONGTEXT NULL,
                beds_type VARCHAR(50) NULL,
                amenities LONGTEXT NULL,
                max_adults SMALLINT UNSIGNED NOT NULL DEFAULT 2,
                max_children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY is_active (is_active),
                KEY staff_user_id (staff_user_id)
            ) {$charset_collate};";
        }

        if ($type === 'customers') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(190) NOT NULL,
                first_name VARCHAR(100) NULL,
                last_name VARCHAR(100) NULL,
                phone VARCHAR(50) NULL,
                notes LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY email (email)
            ) {$charset_collate};";
        }

        if ($type === 'appointments') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                service_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                staff_user_id BIGINT UNSIGNED NULL,
                start_at DATETIME NOT NULL,
                end_at DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                payment_status VARCHAR(20) NOT NULL DEFAULT 'free',
                payment_ref VARCHAR(190) NULL,
                paid_at DATETIME NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin',
                seats SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY service_id (service_id),
                KEY customer_id (customer_id),
                KEY start_at (start_at),
                KEY status (status),
                KEY status_start (status, start_at),
                KEY end_at (end_at),
                KEY time_range (start_at, end_at)
            ) {$charset_collate};";
        }

        if ($type === 'staff_hours') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                weekday TINYINT NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                is_active TINYINT(1) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY user_weekday (user_id, weekday)
            ) {$charset_collate};";
        }

        if ($type === 'staff_exceptions') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                date DATE NOT NULL,
                is_off_day TINYINT(1) NOT NULL,
                start_time TIME NULL,
                end_time TIME NULL,
                note TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY user_date (user_id, date)
            ) {$charset_collate};";
        }

        if ($type === 'resources') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                description LONGTEXT NULL,
                capacity INT UNSIGNED NOT NULL DEFAULT 1,
                cost_per_night_cents INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY is_active (is_active)
            ) {$charset_collate};";
        }

        if ($type === 'appointment_resources') {
            return "CREATE TABLE {$table_name} (
                appointment_id BIGINT UNSIGNED NOT NULL,
                resource_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (appointment_id,resource_id),
                KEY resource_id (resource_id)
            ) {$charset_collate};";
        }

        if ($type === 'service_resources') {
            return "CREATE TABLE {$table_name} (
                service_id BIGINT UNSIGNED NOT NULL,
                resource_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (service_id,resource_id),
                KEY resource_id (resource_id)
            ) {$charset_collate};";
        }

        if ( $type === 'ai_actions' ) {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                ai_input LONGTEXT NULL,
                ai_output LONGTEXT NULL,
                final_state LONGTEXT NULL,
                metadata LONGTEXT NULL,
                notes TEXT NULL,
                approved_by BIGINT UNSIGNED NULL,
                approved_at DATETIME NULL,
                executed_at DATETIME NULL,
                failed_at DATETIME NULL,
                error_message TEXT NULL,
                PRIMARY KEY  (id),
                KEY status (status),
                KEY action_type (action_type),
                KEY created_at (created_at)
            ) {$charset_collate};";
        }
        return '';
    }
}