<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Room Repository (Hotel Mode)
 * 
 * Manages individual room entities for hotel bookings.
 */
class LTLB_RoomRepository {
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ltlb_rooms';
    }

    /**
     * Get all rooms
     */
    public function get_all( bool $active_only = true, ?int $room_type_id = null ): array {
        global $wpdb;
        
        $where_clauses = [];
        $params = [];
        
        if ( $active_only ) {
            $where_clauses[] = 'is_active = 1';
        }
        
        if ( $room_type_id !== null ) {
            $where_clauses[] = 'room_type_id = %d';
            $params[] = $room_type_id;
        }
        
        $where = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
        $sql = "SELECT * FROM {$this->table_name} {$where} ORDER BY display_order ASC, room_number ASC";
        
        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, ...$params );
        }
        
        $results = $wpdb->get_results( $sql, ARRAY_A );
        return is_array( $results ) ? $results : [];
    }

    /**
     * Get room by ID
     */
    public function get_by_id( int $id ): ?array {
        global $wpdb;
        
        $result = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ),
            ARRAY_A
        );
        
        return $result ?: null;
    }

    /**
     * Get room by number
     */
    public function get_by_number( string $room_number ): ?array {
        global $wpdb;
        
        $result = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE room_number = %s", $room_number ),
            ARRAY_A
        );
        
        return $result ?: null;
    }

    /**
     * Create room
     */
    public function create( array $data ): int {
        global $wpdb;
        
        $defaults = [
            'room_number' => '',
            'room_type_id' => null,
            'floor_number' => null,
            'status' => 'available',
            'notes' => null,
            'is_active' => 1,
            'display_order' => 0,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        // Check for duplicate room number
        if ( $this->get_by_number( $data['room_number'] ) ) {
            return 0; // Duplicate
        }
        
        $result = $wpdb->insert( $this->table_name, $data );
        
        return $result ? $wpdb->insert_id : 0;
    }

    /**
     * Update room
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;
        
        $data['updated_at'] = current_time( 'mysql' );
        
        // Check room number uniqueness
        if ( isset( $data['room_number'] ) ) {
            $existing = $this->get_by_number( $data['room_number'] );
            if ( $existing && intval( $existing['id'] ) !== $id ) {
                return false; // Duplicate
            }
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $data,
            [ 'id' => $id ],
            null,
            [ '%d' ]
        );
        
        return $result !== false;
    }

    /**
     * Delete room
     */
    public function delete( int $id ): bool {
        global $wpdb;
        
        // Check if any bookings reference this room
        $appointments_table = $wpdb->prefix . 'lazy_appointments';
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$appointments_table} WHERE room_id = %d AND status NOT IN ('cancelled', 'refunded')",
            $id
        ) );
        
        if ( intval( $count ) > 0 ) {
            return false; // Cannot delete, active bookings exist
        }
        
        $result = $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );
        
        return $result !== false;
    }

    /**
     * Get available rooms for date range
     * 
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @param int|null $room_type_id Filter by room type
     * @return array Available rooms
     */
    public function get_available_for_dates( string $start_date, string $end_date, ?int $room_type_id = null ): array {
        global $wpdb;
        $appointments_table = $wpdb->prefix . 'lazy_appointments';
        
        // Build query to exclude booked rooms
        $type_clause = $room_type_id ? $wpdb->prepare( 'AND r.room_type_id = %d', $room_type_id ) : '';
        
        $sql = "SELECT r.* FROM {$this->table_name} r
                WHERE r.is_active = 1
                AND r.status = 'available'
                {$type_clause}
                AND r.id NOT IN (
                    SELECT room_id FROM {$appointments_table}
                    WHERE room_id IS NOT NULL
                    AND status NOT IN ('cancelled', 'refunded')
                    AND booking_mode = 'hotel'
                    AND DATE(start_at) < %s
                    AND DATE(end_at) > %s
                )
                ORDER BY r.display_order ASC, r.room_number ASC";
        
        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, $end_date, $start_date ),
            ARRAY_A
        );
        
        return is_array( $results ) ? $results : [];
    }
}
