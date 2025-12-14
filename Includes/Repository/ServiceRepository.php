<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_ServiceRepository {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'lazy_services';
    }

    /**
     * Get all services sorted by ID DESC
     *
     * @return array
     */
    public function get_all(): array {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY id DESC",
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get a single service by ID
     *
     * @param int $id
     * @return array|null
     */
    public function get_by_id(int $id): ?array {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    /**
     * Create a new service. $data should be an associative array matching columns.
     * Returns inserted ID on success or false on failure.
     *
     * @param array $data
     * @return int|false
     */
    public function create(array $data) {
        global $wpdb;

        $now = current_time('mysql');
        $insert = [
            'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : null,
            'staff_user_id' => isset($data['staff_user_id']) ? ( $data['staff_user_id'] !== null ? intval($data['staff_user_id']) : null ) : null,
            'duration_min' => isset($data['duration_min']) ? intval($data['duration_min']) : 60,
            'buffer_before_min' => isset($data['buffer_before_min']) ? intval($data['buffer_before_min']) : 0,
            'buffer_after_min' => isset($data['buffer_after_min']) ? intval($data['buffer_after_min']) : 0,
            'price_cents' => isset($data['price_cents']) ? intval($data['price_cents']) : 0,
            'currency' => isset($data['currency']) ? sanitize_text_field($data['currency']) : 'EUR',
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
            'is_group' => isset($data['is_group']) ? intval($data['is_group']) : 0,
            'max_seats_per_booking' => isset($data['max_seats_per_booking']) ? intval($data['max_seats_per_booking']) : 1,
            'availability_mode' => isset($data['availability_mode']) ? sanitize_key($data['availability_mode']) : 'window',
            'available_weekdays' => isset($data['available_weekdays']) ? sanitize_text_field($data['available_weekdays']) : null,
            'available_start_time' => isset($data['available_start_time']) ? sanitize_text_field($data['available_start_time']) : null,
            'available_end_time' => isset($data['available_end_time']) ? sanitize_text_field($data['available_end_time']) : null,
            'fixed_weekly_slots' => isset($data['fixed_weekly_slots']) ? wp_kses_post($data['fixed_weekly_slots']) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $formats = ['%s','%s','%d','%d','%d','%d','%d','%s','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s'];
        $res = $wpdb->insert( $this->table_name, $insert, $formats );
        if ( $res === false ) return false;
        return (int) $wpdb->insert_id;
    }

    /**
     * Update a service by ID with given data. Returns true on success.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        global $wpdb;

        $update = [];
        $formats = [];

        $allowed = [
            'name',
            'description',
            'staff_user_id',
            'duration_min',
            'buffer_before_min',
            'buffer_after_min',
            'price_cents',
            'currency',
            'is_active',
            'is_group',
            'max_seats_per_booking',
            'availability_mode',
            'available_weekdays',
            'available_start_time',
            'available_end_time',
            'fixed_weekly_slots',
        ];
        foreach ( $allowed as $col ) {
            if ( isset( $data[ $col ] ) ) {
                if ( $col === 'staff_user_id' ) {
                    $update[ $col ] = $data[ $col ] !== null ? intval( $data[ $col ] ) : null;
                    $formats[] = '%d';
                } elseif ( in_array( $col, ['duration_min','buffer_before_min','buffer_after_min','price_cents','is_active','is_group','max_seats_per_booking'], true ) ) {
                    $update[ $col ] = intval( $data[ $col ] );
                    $formats[] = '%d';
                } elseif ( $col === 'availability_mode' ) {
                    $update[ $col ] = sanitize_key( $data[ $col ] );
                    $formats[] = '%s';
                } elseif ( $col === 'fixed_weekly_slots' ) {
                    $update[ $col ] = wp_kses_post( $data[ $col ] );
                    $formats[] = '%s';
                } else {
                    $update[ $col ] = sanitize_text_field( $data[ $col ] );
                    $formats[] = '%s';
                }
            }
        }

        if ( empty( $update ) ) return false;
        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $where = [ 'id' => $id ];
        $where_format = [ '%d' ];

        $res = $wpdb->update( $this->table_name, $update, $where, $formats, $where_format );
        return $res !== false;
    }

    /**
     * Soft delete a service (set is_active = 0)
     *
     * @param int $id
     * @return bool
     */
    public function soft_delete(int $id): bool {
        global $wpdb;
        $res = $wpdb->update( $this->table_name, [ 'is_active' => 0, 'updated_at' => current_time('mysql') ], [ 'id' => $id ], [ '%d', '%s' ], [ '%d' ] );
        return $res !== false;
    }

    /**
     * Get the total count of services
     *
     * @return int
     */
    public function get_count(): int {
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        return (int)$count;
    }

    /**
     * Get all services with staff information, supports pagination
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_all_with_staff_and_resources(int $limit = 20, int $offset = 0): array {
        global $wpdb;

        $staff_query = "
            SELECT s.id, s.name, s.duration_min, s.price_cents, s.currency, u.display_name as staff_name
            FROM {$this->table_name} s
            LEFT JOIN {$wpdb->users} u ON s.staff_user_id = u.ID
            ORDER BY s.id DESC
            LIMIT %d OFFSET %d
        ";

        $services = $wpdb->get_results($wpdb->prepare($staff_query, $limit, $offset), ARRAY_A);

        if (empty($services)) {
            return [];
        }

        return $services;
    }
}
