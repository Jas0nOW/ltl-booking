<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Performance Optimization Utility
 * 
 * Features:
 * - Query optimization with pagination
 * - Database index recommendations
 * - Caching layer for expensive queries
 * - Query monitoring and optimization suggestions
 * - Batch operations for large datasets
 * 
 * @package LazyBookings
 */
class LTLB_Performance {

    private $cache_group = 'ltlb_queries';
    private $cache_ttl = HOUR_IN_SECONDS;

    /**
     * Get appointments with pagination and caching
     * 
     * @param array $args Query arguments
     * @return array Results with pagination
     */
    public function get_appointments_paginated( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 50,
            'status' => null,
            'start_date' => null,
            'end_date' => null,
            'service_id' => null,
            'staff_id' => null,
            'customer_id' => null,
            'location_id' => null,
            'order_by' => 'start_at',
            'order' => 'ASC',
            'use_cache' => true
        ];

        $args = wp_parse_args( $args, $defaults );

        // Generate cache key
        $cache_key = 'appointments_' . md5( wp_json_encode( $args ) );

        // Check cache
        if ( $args['use_cache'] ) {
            $cached = wp_cache_get( $cache_key, $this->cache_group );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Build query
        $table = $wpdb->prefix . 'ltlb_appointments';
        $where = [ '1=1' ];
        $params = [];

        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if ( $args['start_date'] ) {
            $where[] = 'start_at >= %s';
            $params[] = $args['start_date'];
        }

        if ( $args['end_date'] ) {
            $where[] = 'start_at <= %s';
            $params[] = $args['end_date'];
        }

        if ( $args['service_id'] ) {
            $where[] = 'service_id = %d';
            $params[] = $args['service_id'];
        }

        if ( $args['staff_id'] ) {
            $where[] = 'staff_user_id = %d';
            $params[] = $args['staff_id'];
        }

        if ( $args['customer_id'] ) {
            $where[] = 'customer_id = %d';
            $params[] = $args['customer_id'];
        }

        $where_clause = implode( ' AND ', $where );

        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if ( ! empty( $params ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $params );
        }
        $total = intval( $wpdb->get_var( $count_sql ) );

        // Get items
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        $order_by = esc_sql( $args['order_by'] );
        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        $items_sql = "SELECT * FROM {$table} 
                      WHERE {$where_clause} 
                      ORDER BY {$order_by} {$order} 
                      LIMIT %d OFFSET %d";
        
        $params[] = $args['per_page'];
        $params[] = $offset;

        $items = $wpdb->get_results( $wpdb->prepare( $items_sql, $params ), ARRAY_A );

        $result = [
            'items' => $items,
            'total' => $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
            'total_pages' => ceil( $total / $args['per_page'] )
        ];

        // Cache result
        if ( $args['use_cache'] ) {
            wp_cache_set( $cache_key, $result, $this->cache_group, $this->cache_ttl );
        }

        return $result;
    }

    /**
     * Get services with pagination
     * 
     * @param array $args Query arguments
     * @return array Results with pagination
     */
    public function get_services_paginated( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 50,
            'is_active' => null,
            'search' => null,
            'order_by' => 'name',
            'order' => 'ASC',
            'use_cache' => true
        ];

        $args = wp_parse_args( $args, $defaults );

        // Generate cache key
        $cache_key = 'services_' . md5( wp_json_encode( $args ) );

        // Check cache
        if ( $args['use_cache'] ) {
            $cached = wp_cache_get( $cache_key, $this->cache_group );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Build query
        $table = $wpdb->prefix . 'ltlb_services';
        $where = [ '1=1' ];
        $params = [];

        if ( null !== $args['is_active'] ) {
            $where[] = 'is_active = %d';
            $params[] = $args['is_active'] ? 1 : 0;
        }

        if ( $args['search'] ) {
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_clause = implode( ' AND ', $where );

        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if ( ! empty( $params ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $params );
        }
        $total = intval( $wpdb->get_var( $count_sql ) );

        // Get items
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        $order_by = esc_sql( $args['order_by'] );
        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        $items_sql = "SELECT * FROM {$table} 
                      WHERE {$where_clause} 
                      ORDER BY {$order_by} {$order} 
                      LIMIT %d OFFSET %d";
        
        $query_params = array_merge( $params, [ $args['per_page'], $offset ] );
        $items = $wpdb->get_results( $wpdb->prepare( $items_sql, $query_params ), ARRAY_A );

        $result = [
            'items' => $items,
            'total' => $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
            'total_pages' => ceil( $total / $args['per_page'] )
        ];

        // Cache result
        if ( $args['use_cache'] ) {
            wp_cache_set( $cache_key, $result, $this->cache_group, $this->cache_ttl );
        }

        return $result;
    }

    /**
     * Get customers with pagination
     * 
     * @param array $args Query arguments
     * @return array Results with pagination
     */
    public function get_customers_paginated( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 50,
            'search' => null,
            'order_by' => 'created_at',
            'order' => 'DESC',
            'use_cache' => true
        ];

        $args = wp_parse_args( $args, $defaults );

        // Generate cache key
        $cache_key = 'customers_' . md5( wp_json_encode( $args ) );

        // Check cache
        if ( $args['use_cache'] ) {
            $cached = wp_cache_get( $cache_key, $this->cache_group );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Build query
        $table = $wpdb->prefix . 'ltlb_customers';
        $where = [ '1=1' ];
        $params = [];

        if ( $args['search'] ) {
            $where[] = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_clause = implode( ' AND ', $where );

        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if ( ! empty( $params ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $params );
        }
        $total = intval( $wpdb->get_var( $count_sql ) );

        // Get items
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        $order_by = esc_sql( $args['order_by'] );
        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        $items_sql = "SELECT * FROM {$table} 
                      WHERE {$where_clause} 
                      ORDER BY {$order_by} {$order} 
                      LIMIT %d OFFSET %d";
        
        $query_params = array_merge( $params, [ $args['per_page'], $offset ] );
        $items = $wpdb->get_results( $wpdb->prepare( $items_sql, $query_params ), ARRAY_A );

        $result = [
            'items' => $items,
            'total' => $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
            'total_pages' => ceil( $total / $args['per_page'] )
        ];

        // Cache result
        if ( $args['use_cache'] ) {
            wp_cache_set( $cache_key, $result, $this->cache_group, $this->cache_ttl );
        }

        return $result;
    }

    /**
     * Clear query cache
     * 
     * @param string|null $pattern Optional pattern to clear specific keys
     * @return bool Success
     */
    public function clear_cache( ?string $pattern = null ): bool {
        if ( $pattern ) {
            // WordPress doesn't support pattern-based deletion natively
            // This would require a custom caching layer
            return false;
        }

        // Clear entire group (requires object cache backend like Redis)
        return wp_cache_flush();
    }

    /**
     * Batch update appointments
     * 
     * @param array $appointment_ids Array of appointment IDs
     * @param array $data Data to update
     * @return int Number of updated appointments
     */
    public function batch_update_appointments( array $appointment_ids, array $data ): int {
        global $wpdb;

        if ( empty( $appointment_ids ) || empty( $data ) ) {
            return 0;
        }

        $table = $wpdb->prefix . 'ltlb_appointments';
        $updated = 0;

        // Prepare SET clause
        $set_parts = [];
        $set_values = [];

        foreach ( $data as $key => $value ) {
            $set_parts[] = esc_sql( $key ) . ' = %s';
            $set_values[] = $value;
        }

        $set_clause = implode( ', ', $set_parts );

        // Update in chunks to avoid query size limits
        $chunks = array_chunk( $appointment_ids, 100 );

        foreach ( $chunks as $chunk ) {
            $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
            $sql = "UPDATE {$table} SET {$set_clause} WHERE id IN ({$placeholders})";
            
            $params = array_merge( $set_values, $chunk );
            $result = $wpdb->query( $wpdb->prepare( $sql, $params ) );
            
            if ( false !== $result ) {
                $updated += $result;
            }
        }

        // Clear cache
        $this->clear_cache();

        return $updated;
    }

    /**
     * Batch delete appointments
     * 
     * @param array $appointment_ids Array of appointment IDs
     * @return int Number of deleted appointments
     */
    public function batch_delete_appointments( array $appointment_ids ): int {
        global $wpdb;

        if ( empty( $appointment_ids ) ) {
            return 0;
        }

        $table = $wpdb->prefix . 'ltlb_appointments';
        $deleted = 0;

        // Delete in chunks
        $chunks = array_chunk( $appointment_ids, 100 );

        foreach ( $chunks as $chunk ) {
            $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
            $sql = "DELETE FROM {$table} WHERE id IN ({$placeholders})";
            
            $result = $wpdb->query( $wpdb->prepare( $sql, $chunk ) );
            
            if ( false !== $result ) {
                $deleted += $result;
            }
        }

        // Clear cache
        $this->clear_cache();

        return $deleted;
    }

    /**
     * Analyze database and recommend indexes
     * 
     * @return array Recommendations
     */
    public function analyze_database(): array {
        global $wpdb;

        $recommendations = [];

        // Check appointments table
        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        
        // Check if composite indexes exist
        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$appointments_table}", ARRAY_A );
        
        $existing_indexes = [];
        foreach ( $indexes as $index ) {
            $existing_indexes[] = $index['Key_name'];
        }

        // Recommended indexes
        $recommended = [
            'status_start' => "ALTER TABLE {$appointments_table} ADD INDEX status_start (status, start_at)",
            'customer_status' => "ALTER TABLE {$appointments_table} ADD INDEX customer_status (customer_id, status)",
            'staff_date' => "ALTER TABLE {$appointments_table} ADD INDEX staff_date (staff_user_id, start_at)",
            'service_date' => "ALTER TABLE {$appointments_table} ADD INDEX service_date (service_id, start_at)"
        ];

        foreach ( $recommended as $index_name => $sql ) {
            if ( ! in_array( $index_name, $existing_indexes, true ) ) {
                $recommendations[] = [
                    'type' => 'missing_index',
                    'table' => 'appointments',
                    'index' => $index_name,
                    'sql' => $sql,
                    'impact' => 'high'
                ];
            }
        }

        // Check table sizes
        $table_sizes = $this->get_table_sizes();
        
        foreach ( $table_sizes as $table => $size ) {
            if ( $size['rows'] > 10000 ) {
                $recommendations[] = [
                    'type' => 'large_table',
                    'table' => $table,
                    'rows' => $size['rows'],
                    'size_mb' => $size['size_mb'],
                    'suggestion' => 'Consider implementing data archival or partitioning'
                ];
            }
        }

        // Check slow queries
        $slow_queries = $this->find_slow_queries();
        
        if ( ! empty( $slow_queries ) ) {
            $recommendations[] = [
                'type' => 'slow_queries',
                'queries' => $slow_queries,
                'suggestion' => 'Optimize these queries or add appropriate indexes'
            ];
        }

        return $recommendations;
    }

    /**
     * Get table sizes
     * 
     * @return array Table sizes
     */
    private function get_table_sizes(): array {
        global $wpdb;

        $tables = [
            'appointments',
            'services',
            'customers',
            'resources',
            'staff_hours',
            'payment_schedule',
            'coupons',
            'locations',
            'invoices'
        ];

        $sizes = [];

        foreach ( $tables as $table ) {
            $full_table = $wpdb->prefix . 'ltlb_' . $table;
            
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$full_table}" );
            
            $size_result = $wpdb->get_row( 
                $wpdb->prepare(
                    "SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                     FROM information_schema.TABLES 
                     WHERE table_schema = %s 
                     AND table_name = %s",
                    DB_NAME,
                    $full_table
                ),
                ARRAY_A
            );

            $sizes[ $table ] = [
                'rows' => intval( $count ),
                'size_mb' => floatval( $size_result['size_mb'] ?? 0 )
            ];
        }

        return $sizes;
    }

    /**
     * Find slow queries (placeholder - requires query logging)
     * 
     * @return array Slow queries
     */
    private function find_slow_queries(): array {
        // This would require enabling slow query log
        // For now, return empty array
        return [];
    }

    /**
     * Optimize tables
     * 
     * @return array Optimization results
     */
    public function optimize_tables(): array {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'ltlb_appointments',
            $wpdb->prefix . 'ltlb_services',
            $wpdb->prefix . 'ltlb_customers',
            $wpdb->prefix . 'ltlb_resources'
        ];

        $results = [];

        foreach ( $tables as $table ) {
            $result = $wpdb->query( "OPTIMIZE TABLE {$table}" );
            $results[ $table ] = $result !== false;
        }

        return $results;
    }

    /**
     * Get query statistics
     * 
     * @return array Statistics
     */
    public function get_query_stats(): array {
        global $wpdb;

        return [
            'total_queries' => $wpdb->num_queries,
            'query_time' => timer_stop( 0, 3 ),
            'cache_hits' => wp_cache_get_hit_count(),
            'cache_misses' => wp_cache_get_miss_count()
        ];
    }

    /**
     * Warm up cache for common queries
     * 
     * @return int Number of queries cached
     */
    public function warmup_cache(): int {
        $cached = 0;

        // Cache upcoming appointments
        $this->get_appointments_paginated([
            'status' => 'confirmed',
            'start_date' => current_time( 'mysql' ),
            'per_page' => 100,
            'use_cache' => true
        ]);
        $cached++;

        // Cache active services
        $this->get_services_paginated([
            'is_active' => 1,
            'per_page' => 100,
            'use_cache' => true
        ]);
        $cached++;

        // Cache recent customers
        $this->get_customers_paginated([
            'per_page' => 100,
            'use_cache' => true
        ]);
        $cached++;

        return $cached;
    }
}
