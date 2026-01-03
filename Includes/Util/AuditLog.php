<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Audit Log for critical changes.
 * 
 * Tracks all mutations to bookings, appointments, services, resources, prices, and settings.
 * Stores: who (user_id), what (entity_type + entity_id), action, old_value, new_value, when (timestamp), IP (optional).
 * 
 * Usage:
 *   LTLB_AuditLog::log( 'appointment', $id, 'status_change', [ 'old' => 'pending', 'new' => 'confirmed' ] );
 */
class LTLB_AuditLog {

    /**
     * Log a change event.
     * 
     * @param string $entity_type E.g. 'appointment', 'service', 'customer', 'resource', 'setting'
     * @param int $entity_id The ID of the changed entity (0 for global settings)
     * @param string $action E.g. 'created', 'updated', 'deleted', 'status_change', 'price_change', 'assigned', 'moved'
     * @param array $data Arbitrary data (old/new values, context)
     * @param int|null $user_id User who performed the action (defaults to current user)
     * @return void
     */
    public static function log( string $entity_type, int $entity_id, string $action, array $data = [], ?int $user_id = null ): void {
        global $wpdb;

        // Ensure audit table exists.
        self::maybe_create_table();

        $user_id = $user_id ?? get_current_user_id();
        $ip = self::get_client_ip();
        $timestamp = current_time( 'mysql' );

        // Serialize data as JSON.
        $data_json = ! empty( $data ) ? wp_json_encode( $data ) : null;

        $table = $wpdb->prefix . 'lazy_audit_log';
        $wpdb->insert(
            $table,
            [
                'entity_type' => sanitize_key( $entity_type ),
                'entity_id' => intval( $entity_id ),
                'action' => sanitize_key( $action ),
                'user_id' => intval( $user_id ),
                'ip_address' => $ip,
                'data_json' => $data_json,
                'created_at' => $timestamp,
            ],
            [ '%s', '%d', '%s', '%d', '%s', '%s', '%s' ]
        );
    }

    /**
     * Retrieve audit log entries with optional filters.
     * 
     * @param array $args Filters: entity_type, entity_id, user_id, action, limit, offset
     * @return array
     */
    public static function get_entries( array $args = [] ): array {
        global $wpdb;
        self::maybe_create_table();

        $table = $wpdb->prefix . 'lazy_audit_log';
        $where = [];
        $values = [];

        if ( ! empty( $args['entity_type'] ) ) {
            $where[] = 'entity_type = %s';
            $values[] = sanitize_key( $args['entity_type'] );
        }
        if ( isset( $args['entity_id'] ) ) {
            $where[] = 'entity_id = %d';
            $values[] = intval( $args['entity_id'] );
        }
        if ( ! empty( $args['user_id'] ) ) {
            $where[] = 'user_id = %d';
            $values[] = intval( $args['user_id'] );
        }
        if ( ! empty( $args['action'] ) ) {
            $where[] = 'action = %s';
            $values[] = sanitize_key( $args['action'] );
        }

        $where_clause = ! empty( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '';

        $limit = isset( $args['limit'] ) ? intval( $args['limit'] ) : 100;
        $offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;

        $sql = "SELECT * FROM {$table}{$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, ...$values );
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        // Decode JSON data.
        foreach ( $rows as &$row ) {
            if ( ! empty( $row['data_json'] ) ) {
                $decoded = json_decode( $row['data_json'], true );
                $row['data'] = is_array( $decoded ) ? $decoded : [];
            } else {
                $row['data'] = [];
            }
        }

        return $rows;
    }

    /**
     * Get count of audit entries (for pagination).
     * 
     * @param array $args Same filters as get_entries
     * @return int
     */
    public static function get_count( array $args = [] ): int {
        global $wpdb;
        self::maybe_create_table();

        $table = $wpdb->prefix . 'lazy_audit_log';
        $where = [];
        $values = [];

        if ( ! empty( $args['entity_type'] ) ) {
            $where[] = 'entity_type = %s';
            $values[] = sanitize_key( $args['entity_type'] );
        }
        if ( isset( $args['entity_id'] ) ) {
            $where[] = 'entity_id = %d';
            $values[] = intval( $args['entity_id'] );
        }
        if ( ! empty( $args['user_id'] ) ) {
            $where[] = 'user_id = %d';
            $values[] = intval( $args['user_id'] );
        }
        if ( ! empty( $args['action'] ) ) {
            $where[] = 'action = %s';
            $values[] = sanitize_key( $args['action'] );
        }

        $where_clause = ! empty( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '';

        $sql = "SELECT COUNT(*) FROM {$table}{$where_clause}";

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, ...$values );
        }

        return intval( $wpdb->get_var( $sql ) );
    }

    /**
     * Purge old audit log entries (older than X days).
     * 
     * @param int $days Retention period (default 365 days)
     * @return int Number of deleted rows
     */
    public static function purge_old( int $days = 365 ): int {
        global $wpdb;
        self::maybe_create_table();

        $table = $wpdb->prefix . 'lazy_audit_log';
        $cutoff = date( 'Y-m-d H:i:s', strtotime( '-' . intval( $days ) . ' days' ) );

        $deleted = $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff )
        );

        return intval( $deleted );
    }

    /**
     * Get client IP address (with proxy awareness).
     * 
     * @return string|null
     */
    private static function get_client_ip(): ?string {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            // X-Forwarded-For can contain multiple IPs; take the first.
            $ips = explode( ',', (string) $ip );
            $ip = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        } else {
            return null;
        }

        // Validate IP.
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }

        return null;
    }

    /**
     * Create audit log table if it doesn't exist.
     */
    private static function maybe_create_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'lazy_audit_log';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists.
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
        if ( $exists ) {
            return;
        }

        // Create table.
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(50) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            data_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY entity (entity_type, entity_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
