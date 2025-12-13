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
                duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
                buffer_before_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                buffer_after_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                price_cents INT UNSIGNED NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_group TINYINT(1) NOT NULL DEFAULT 0,
                max_seats_per_booking SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY is_active (is_active)
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
                timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin',
                seats SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY service_id (service_id),
                KEY customer_id (customer_id),
                KEY start_at (start_at),
                KEY status (status)
            ) {$charset_collate};";
        }

        if ($type === 'resources') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) {$charset_collate};";
        }

        if ($type === 'service_resources') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                service_id BIGINT UNSIGNED NOT NULL,
                resource_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY service_id (service_id),
                KEY resource_id (resource_id)
            ) {$charset_collate};";
        }

        if ($type === 'appointment_resources') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                appointment_id BIGINT UNSIGNED NOT NULL,
                resource_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY appointment_id (appointment_id),
                KEY resource_id (resource_id)
            ) {$charset_collate};";
        }

        return '';
    }
}