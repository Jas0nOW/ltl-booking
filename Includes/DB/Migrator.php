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

        // Handle legacy mapping tables that may not have id column
        self::migrate_mapping_table_structure($service_resources_tbl);
        self::migrate_mapping_table_structure($appointment_resources);

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

    /**
     * Migrate mapping tables that were created without id column.
     * If table exists without id column, drop and recreate it.
     */
    private static function migrate_mapping_table_structure(string $table_name): void {
        global $wpdb;

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        if (!$table_exists) {
            return; // Table doesn't exist yet, will be created by dbDelta
        }

        // Check if id column exists
        $id_column = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'id'
        ));

        if (empty($id_column)) {
            // Table exists but has no id column - drop and recreate
            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
        }
    }
}