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
        $resources_table = $wpdb->prefix . 'lazy_resources';
        $appointment_resources_table = $wpdb->prefix . 'lazy_appointment_resources';
        $service_resources_table = $wpdb->prefix . 'lazy_service_resources';
        $ai_actions_table = $wpdb->prefix . 'lazy_ai_actions';

        $sql_services     = LTLB_DB_Schema::get_create_table_sql($services_table, 'services');
        $sql_customers    = LTLB_DB_Schema::get_create_table_sql($customers_table, 'customers');
        $sql_appointments = LTLB_DB_Schema::get_create_table_sql($appointments_table, 'appointments');
        $sql_staff_hours = LTLB_DB_Schema::get_create_table_sql($staff_hours_table, 'staff_hours');
        $sql_staff_exceptions = LTLB_DB_Schema::get_create_table_sql($staff_exceptions_table, 'staff_exceptions');
        $sql_resources = LTLB_DB_Schema::get_create_table_sql($resources_table, 'resources');
        $sql_appointment_resources = LTLB_DB_Schema::get_create_table_sql($appointment_resources_table, 'appointment_resources');
        $sql_service_resources = LTLB_DB_Schema::get_create_table_sql($service_resources_table, 'service_resources');
        $sql_ai_actions = LTLB_DB_Schema::get_create_table_sql($ai_actions_table, 'ai_actions');

        if ($sql_services)     dbDelta($sql_services);
        if ($sql_customers)    dbDelta($sql_customers);
        if ($sql_appointments) dbDelta($sql_appointments);
        if ($sql_staff_hours) dbDelta($sql_staff_hours);
        if ($sql_staff_exceptions) dbDelta($sql_staff_exceptions);
        if ($sql_resources) dbDelta($sql_resources);
		if ($sql_ai_actions) dbDelta($sql_ai_actions);
        
        // Junction tables: dbDelta has issues with composite PRIMARY KEYs on existing tables
        // Check if table exists and has correct structure, otherwise recreate
        self::ensure_junction_table($appointment_resources_table, $sql_appointment_resources);
        self::ensure_junction_table($service_resources_table, $sql_service_resources);

        // Version merken für spätere Migrationen (use plugin constant)
        if ( defined('LTLB_VERSION') ) {
            update_option('ltlb_db_version', LTLB_VERSION);
        } else {
            update_option('ltlb_db_version', '0.0.0');
        }
        
        // Track last migration time
        update_option('ltlb_last_migration_time', current_time('mysql'));
    }

    /**
     * Ensure junction table exists with correct structure.
     * dbDelta has problems with composite PRIMARY KEYs on existing tables,
     * so we check the table structure and recreate if needed.
     */
    private static function ensure_junction_table(string $table_name, string $create_sql): void {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        if (!$table_exists) {
            // Table doesn't exist, use dbDelta to create it
            dbDelta($create_sql);
            return;
        }
        
        // Table exists - check if it has the correct PRIMARY KEY
        $key_info = $wpdb->get_results("SHOW KEYS FROM {$table_name} WHERE Key_name = 'PRIMARY'");
        
        if (empty($key_info)) {
            // No PRIMARY KEY exists - add it manually
            if (strpos($table_name, 'appointment_resources') !== false) {
                $wpdb->query("ALTER TABLE {$table_name} ADD PRIMARY KEY (appointment_id,resource_id)");
            } elseif (strpos($table_name, 'service_resources') !== false) {
                $wpdb->query("ALTER TABLE {$table_name} ADD PRIMARY KEY (service_id,resource_id)");
            }
        } elseif (count($key_info) !== 2) {
            // PRIMARY KEY exists but is WRONG (should be composite with 2 columns)
            // Junction tables should NOT have AUTO_INCREMENT, but if they do, we can't simply DROP PRIMARY KEY
            // Solution: Backup data, drop table, recreate with correct structure, restore data
            error_log("LTLB: Recreating {$table_name} with correct structure (current PRIMARY KEY has " . count($key_info) . " columns, needs 2)");
            
            // Backup existing data
            $backup_table = $table_name . '_backup_' . time();
            $wpdb->query("CREATE TABLE {$backup_table} AS SELECT * FROM {$table_name}");
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM {$backup_table}");
            
            // Drop old table
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            
            // Recreate with correct structure using dbDelta
            dbDelta($create_sql);
            
            // Restore data (only if backup was successful and not empty)
            if ($row_count > 0) {
                // Determine column names based on table type
                if (strpos($table_name, 'appointment_resources') !== false) {
                    $wpdb->query("INSERT INTO {$table_name} (appointment_id, resource_id) SELECT appointment_id, resource_id FROM {$backup_table}");
                } elseif (strpos($table_name, 'service_resources') !== false) {
                    $wpdb->query("INSERT INTO {$table_name} (service_id, resource_id) SELECT service_id, resource_id FROM {$backup_table}");
                }
                error_log("LTLB: Restored {$row_count} rows to {$table_name}");
            }
            
            // Drop backup table
            $wpdb->query("DROP TABLE IF EXISTS {$backup_table}");
            
            error_log("LTLB: {$table_name} structure fixed");
        }
        // else: PRIMARY KEY is correct (2 columns), nothing to do
    }

    /**
     * Run migrations if stored DB version differs from plugin version.
     * Safe to call on every request (lightweight compare).
     */
    public static function maybe_migrate(): void {
        // if migrations already ran for this plugin version, skip
        $current = get_option('ltlb_db_version', '0.0.0');
        $target = defined('LTLB_VERSION') ? LTLB_VERSION : '0.0.0';
        if ( version_compare( $current, $target, '>=' ) ) {
            return;
        }

        // run migrations (this will update the stored version)
        try {
            self::migrate();
        } catch ( Throwable $e ) {
            // swallow to avoid fatal errors during normal page loads; admin can see notices
            error_log( 'LTLB migration failed: ' . $e->getMessage() );
        }
    }
}