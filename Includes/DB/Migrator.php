<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_DB_Migrator {
    private const OPTION_DB_PLUGIN_VERSION = 'ltlb_db_version';
    private const OPTION_DB_SCHEMA_VERSION = 'ltlb_db_schema_version';
    private const OPTION_DB_APPLIED_MIGRATIONS = 'ltlb_db_migrations_applied';
    private const OPTION_LAST_MIGRATION_TIME = 'ltlb_last_migration_time';

    // Increment this when schema changes require a migration.
    private const TARGET_SCHEMA_VERSION = 7;

    /**
     * Backwards-compatible entrypoint (WP-CLI and older code calls this).
     */
    public static function migrate(): void {
        self::migrate_to_latest();
    }

    /**
     * Apply versioned schema migrations up to TARGET_SCHEMA_VERSION.
     * Uses a global lock to avoid concurrent migrations.
     */
    public static function migrate_to_latest(): void {
        $lock_key = 'ltlb_db_migration_global';
        $result = class_exists( 'LTLB_LockManager' )
            ? LTLB_LockManager::with_lock( $lock_key, function() {
                self::apply_pending_migrations();
                return true;
            } )
            : ( function() {
                self::apply_pending_migrations();
                return true;
            } )();

        if ( $result === false ) {
            // Another request is migrating right now.
            return;
        }
    }

    /**
     * Optional rollback API for critical changes.
     * Not called automatically; intended for WP-CLI or controlled admin tooling.
     */
    public static function rollback_to( int $target_schema_version ): void {
        $target_schema_version = max( 0, $target_schema_version );
        $current = self::get_schema_version();
        if ( $current <= $target_schema_version ) {
            return;
        }

        $migrations = self::get_migrations();
        for ( $v = $current; $v > $target_schema_version; $v-- ) {
            if ( empty( $migrations[ $v ]['down'] ) || ! is_callable( $migrations[ $v ]['down'] ) ) {
                throw new RuntimeException( 'No rollback available for schema version ' . $v );
            }
            call_user_func( $migrations[ $v ]['down'] );
        }
        update_option( self::OPTION_DB_SCHEMA_VERSION, $target_schema_version );
        update_option( self::OPTION_LAST_MIGRATION_TIME, current_time( 'mysql' ) );
    }

    private static function get_schema_version(): int {
        $v = get_option( self::OPTION_DB_SCHEMA_VERSION, 0 );
        return max( 0, intval( $v ) );
    }

    private static function get_applied_migrations(): array {
        $applied = get_option( self::OPTION_DB_APPLIED_MIGRATIONS, [] );
        if ( ! is_array( $applied ) ) {
            return [];
        }
        $clean = [];
        foreach ( $applied as $id ) {
            $id = sanitize_text_field( (string) $id );
            if ( $id !== '' ) {
                $clean[] = $id;
            }
        }
        return array_values( array_unique( $clean ) );
    }

    private static function mark_migration_applied( string $id ): void {
        $id = sanitize_text_field( $id );
        if ( $id === '' ) {
            return;
        }
        $applied = self::get_applied_migrations();
        if ( in_array( $id, $applied, true ) ) {
            return;
        }
        $applied[] = $id;
        update_option( self::OPTION_DB_APPLIED_MIGRATIONS, $applied, false );
    }

    private static function get_migrations(): array {
        return [
            1 => [
                'id' => '001_initial_schema',
                'description' => 'Initial schema (core tables)',
                'up' => [ __CLASS__, 'migration_001_initial_schema' ],
                'down' => null,
            ],
            2 => [
                'id' => '002_convert_appointments_to_utc',
                'description' => 'Convert existing appointment start_at/end_at to UTC storage',
                'up' => [ __CLASS__, 'migration_002_convert_appointments_to_utc' ],
                'down' => null,
            ],
            3 => [
                'id' => '003_add_refund_fields',
                'description' => 'Add refund tracking fields to appointments table',
                'up' => function() {
                    $migration = require LTLB_PATH . 'Includes/DB/migrations/003_add_refund_fields.php';
                    return call_user_func( $migration['up'] );
                },
                'down' => function() {
                    $migration = require LTLB_PATH . 'Includes/DB/migrations/003_add_refund_fields.php';
                    return isset( $migration['down'] ) ? call_user_func( $migration['down'] ) : true;
                },
            ],
            4 => [
                'id' => '004_add_notification_queue',
                'description' => 'Add notification queue table',
                'up' => function() {
                    require_once LTLB_PATH . 'Includes/DB/migrations/004_add_notification_queue.php';
                    return ltlb_migration_004_up();
                },
                'down' => function() {
                    require_once LTLB_PATH . 'Includes/DB/migrations/004_add_notification_queue.php';
                    return function_exists( 'ltlb_migration_004_down' ) ? ltlb_migration_004_down() : true;
                },
            ],
            5 => [
                'id' => '005_add_availability_rules',
                'description' => 'Add availability rule fields to services table',
                'up' => function() {
                    require_once LTLB_PATH . 'Includes/DB/migrations/005_add_availability_rules.php';
                    return ltlb_migration_005_up();
                },
                'down' => function() {
                    require_once LTLB_PATH . 'Includes/DB/migrations/005_add_availability_rules.php';
                    return function_exists( 'ltlb_migration_005_down' ) ? ltlb_migration_005_down() : true;
                },
            ],
            6 => [
                'id' => '006_add_hotel_tables',
                'description' => 'Add Hotel-specific tables (Rooms, RoomTypes)',
                'up' => function() {
                    require_once LTLB_PATH . 'Includes/DB/migrations/006_add_hotel_tables.php';
                    return ltlb_migration_006_up();
                },
                'down' => function() {
                    require_once LTLB_PATH . 'Includes/DB/migrations/006_add_hotel_tables.php';
                    return function_exists( 'ltlb_migration_006_down' ) ? ltlb_migration_006_down() : true;
                },
            ],
            7 => [
                'id' => '007_add_p2_features',
                'description' => 'Add P2 feature tables (Webhooks, Waitlist, Group Bookings, Packages)',
                'up' => function() {
                    require_once LTLB_PATH . 'Includes/DB/migrations/007_add_p2_features.php';
                    return ltlb_migration_007_up();
                },
                'down' => function() {
                    require_once LTLB_PATH . 'Includes/DB/migrations/007_add_p2_features.php';
                    return function_exists( 'ltlb_migration_007_down' ) ? ltlb_migration_007_down() : true;
                },
            ],
        ];
    }

    private static function apply_pending_migrations(): void {
        $target = self::TARGET_SCHEMA_VERSION;
        $current = self::get_schema_version();
        $migrations = self::get_migrations();
        $applied = self::get_applied_migrations();

        // Apply missing migrations up to target.
        for ( $v = $current + 1; $v <= $target; $v++ ) {
            if ( empty( $migrations[ $v ] ) || empty( $migrations[ $v ]['up'] ) || ! is_callable( $migrations[ $v ]['up'] ) ) {
                throw new RuntimeException( 'Missing migration for schema version ' . $v );
            }
            $m = $migrations[ $v ];
            $id = isset( $m['id'] ) ? (string) $m['id'] : ( 'schema_' . $v );
            // Already-applied guard (in addition to schema version).
            if ( in_array( $id, $applied, true ) ) {
                update_option( self::OPTION_DB_SCHEMA_VERSION, $v );
                continue;
            }
            call_user_func( $m['up'] );
            self::mark_migration_applied( $id );
            update_option( self::OPTION_DB_SCHEMA_VERSION, $v );
        }

        // Maintain legacy plugin-version marker for backwards compatibility.
        if ( defined( 'LTLB_VERSION' ) ) {
            update_option( self::OPTION_DB_PLUGIN_VERSION, LTLB_VERSION );
        } else {
            update_option( self::OPTION_DB_PLUGIN_VERSION, '0.0.0' );
        }
        update_option( self::OPTION_LAST_MIGRATION_TIME, current_time( 'mysql' ) );
    }

    /**
     * Migration 001: create/upgrade core tables.
     * Uses dbDelta for idempotent schema updates.
     */
    public static function migration_001_initial_schema(): void {
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
            if (strpos((string) $table_name, 'appointment_resources') !== false) {
                $wpdb->query("ALTER TABLE {$table_name} ADD PRIMARY KEY (appointment_id,resource_id)");
            } elseif (strpos((string) $table_name, 'service_resources') !== false) {
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
                if (strpos((string) $table_name, 'appointment_resources') !== false) {
                    $wpdb->query("INSERT INTO {$table_name} (appointment_id, resource_id) SELECT appointment_id, resource_id FROM {$backup_table}");
                } elseif (strpos((string) $table_name, 'service_resources') !== false) {
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
     * Migration 002: Convert existing appointment times from local to UTC storage.
     * 
     * Previously, start_at/end_at were stored in the site timezone (or appointment timezone).
     * Now they must be stored as UTC. This migration converts all existing rows.
     * 
     * Strategy:
     * - For each appointment, read the stored timezone (or fallback to site timezone).
     * - Parse start_at/end_at as local times in that timezone.
     * - Convert to UTC and update the row.
     * 
     * Safe to run multiple times (idempotent): if already UTC, conversion is harmless.
     */
    public static function migration_002_convert_appointments_to_utc(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
        if ( ! $table_exists ) {
            // No appointments table yet, nothing to migrate.
            return;
        }

        // Get site timezone as fallback
        $site_tz_string = class_exists( 'LTLB_Time' ) ? LTLB_Time::get_site_timezone_string() : 'UTC';

        // Fetch all appointments (do in batches if huge; for typical installs this is fine).
        $appointments = $wpdb->get_results( "SELECT id, start_at, end_at, timezone FROM {$table}", ARRAY_A );
        if ( empty( $appointments ) ) {
            // No appointments to migrate.
            return;
        }

        $updated_count = 0;
        foreach ( $appointments as $appt ) {
            $id = intval( $appt['id'] );
            $start_raw = (string) ( $appt['start_at'] ?? '' );
            $end_raw = (string) ( $appt['end_at'] ?? '' );
            $tz_raw = (string) ( $appt['timezone'] ?? '' );

            if ( $start_raw === '' || $end_raw === '' ) {
                continue;
            }

            // Use appointment timezone, fallback to site timezone.
            $tz_string = ( $tz_raw !== '' ) ? $tz_raw : $site_tz_string;

            try {
                $tz = new DateTimeZone( $tz_string );
            } catch ( Exception $e ) {
                // Invalid timezone, fallback to site timezone.
                $tz = new DateTimeZone( $site_tz_string );
            }

            // Parse as local time in the appointment timezone.
            try {
                $start_dt = new DateTimeImmutable( $start_raw, $tz );
                $end_dt = new DateTimeImmutable( $end_raw, $tz );
            } catch ( Exception $e ) {
                // Parsing failed, skip this appointment (log warning).
                error_log( "LTLB Migration 002: Could not parse times for appointment {$id}: {$e->getMessage()}" );
                continue;
            }

            // Convert to UTC and format as MySQL DATETIME.
            $utc = new DateTimeZone( 'UTC' );
            $start_utc = $start_dt->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
            $end_utc = $end_dt->setTimezone( $utc )->format( 'Y-m-d H:i:s' );

            // Update the row.
            $wpdb->update(
                $table,
                [
                    'start_at' => $start_utc,
                    'end_at' => $end_utc,
                ],
                [ 'id' => $id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );

            $updated_count++;
        }

        error_log( "LTLB Migration 002: Converted {$updated_count} appointments to UTC storage." );
    }

    /**
     * Run migrations if stored DB version differs from plugin version.
     * Safe to call on every request (lightweight compare).
     */
    public static function maybe_migrate(): void {
        // If schema migrations already ran, skip. (Legacy plugin-version marker is updated too.)
        $current_schema = self::get_schema_version();
        if ( $current_schema >= self::TARGET_SCHEMA_VERSION ) {
            return;
        }

        // run migrations
        try {
            self::migrate_to_latest();
        } catch ( Throwable $e ) {
            // swallow to avoid fatal errors during normal page loads; admin can see notices
            error_log( 'LTLB migration failed: ' . $e->getMessage() );
        }
    }
}