<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_DB_Migrator {

    public static function migrate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $services_table         = $wpdb->prefix . 'lazy_services';
        $customers_table        = $wpdb->prefix . 'lazy_customers';
        $appointments_table     = $wpdb->prefix . 'lazy_appointments';
        $resources_table        = $wpdb->prefix . 'lazy_resources';
        $service_resources_tbl  = $wpdb->prefix . 'lazy_service_resources';
        $appointment_resources  = $wpdb->prefix . 'lazy_appointment_resources';

        $to_create = [
            [ $services_table, 'services' ],
            [ $customers_table, 'customers' ],
            [ $appointments_table, 'appointments' ],
            [ $resources_table, 'resources' ],
            [ $service_resources_tbl, 'service_resources' ],
            [ $appointment_resources, 'appointment_resources' ],
        ];

        foreach ( $to_create as list($table, $type) ) {
            $sql = LTLB_DB_Schema::get_create_table_sql($table, $type);
            if ( $sql ) dbDelta( $sql );
        }

        // store current DB version for future migrations
        update_option('ltlb_db_version', '0.3.0');
    }
}