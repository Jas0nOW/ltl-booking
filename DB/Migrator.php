<?php

// Migration for new staff hours and exceptions tables
function migrate_staff_tables() {
    global $wpdb;
    $table_name_hours = $wpdb->prefix . 'lazy_staff_hours';
    $table_name_exceptions = $wpdb->prefix . 'lazy_staff_exceptions';

    $charset_collate = $wpdb->get_charset_collate();

    $sql_hours = "CREATE TABLE IF NOT EXISTS $table_name_hours (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        weekday TINYINT NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_active TINYINT(1) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_exceptions = "CREATE TABLE IF NOT EXISTS $table_name_exceptions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        date DATE NOT NULL,
        is_off_day TINYINT(1) NOT NULL,
        start_time TIME NULL,
        end_time TIME NULL,
        note TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_hours);
    dbDelta($sql_exceptions);
}

// Call the migration function
migrate_staff_tables();
