<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_DB_Migrator {

    public static function migrate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $services_table     = $wpdb->prefix . 'lazy_services';
        $customers_table    = $wpdb->prefix . 'lazy_customers';
        $appointments_table = $wpdb->prefix . 'lazy_appointments';
        $staff_hours_table = $wpdb->prefix . 'lazy_staff_hours';
        $staff_exceptions_table = $wpdb->prefix . 'lazy_staff_exceptions';

        $sql_services     = LTLB_DB_Schema::get_create_table_sql($services_table, 'services');
        $sql_customers    = LTLB_DB_Schema::get_create_table_sql($customers_table, 'customers');
        $sql_appointments = LTLB_DB_Schema::get_create_table_sql($appointments_table, 'appointments');
        $sql_staff_hours = LTLB_DB_Schema::get_create_table_sql($staff_hours_table, 'staff_hours');
        $sql_staff_exceptions = LTLB_DB_Schema::get_create_table_sql($staff_exceptions_table, 'staff_exceptions');

        if ($sql_services)     dbDelta($sql_services);
        if ($sql_customers)    dbDelta($sql_customers);
        if ($sql_appointments) dbDelta($sql_appointments);
        if ($sql_staff_hours) dbDelta($sql_staff_hours);
        if ($sql_staff_exceptions) dbDelta($sql_staff_exceptions);

        // Version merken für spätere Migrationen
        update_option('ltlb_db_version', '0.2.0');
    }
}