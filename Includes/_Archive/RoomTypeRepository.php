<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Room Type Repository (Hotel Mode)
 * 
 * Manages room type entities for hotel bookings.
 */
class LTLB_RoomTypeRepository {
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ltlb_room_types';
    }

    /**
     * Get all room types
     */
    public function get_all( bool $active_only = true ): array {
        global $wpdb;
        
        $where = $active_only ? 'WHERE is_active = 1' : '';
        $sql = "SELECT * FROM {$this->table_name} {$where} ORDER BY display_order ASC, name ASC";
        
        $results = $wpdb->get_results( $sql, ARRAY_A );
        return is_array( $results ) ? $results : [];
    }

    /**
     * Get room type by ID
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
     * Get room type by slug
     */
    public function get_by_slug( string $slug ): ?array {
        global $wpdb;
        
        $result = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE slug = %s", $slug ),
            ARRAY_A
        );
        
        return $result ?: null;
    }

    /**
     * Create room type
     */
    public function create( array $data ): int {
        global $wpdb;
        
        $defaults = [
            'name' => '',
            'slug' => '',
            'description' => null,
            'max_occupancy' => 2,
            'base_price_cents' => 0,
            'amenities' => null,
            'image_url' => null,
            'is_active' => 1,
            'display_order' => 0,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        // Auto-generate slug if empty
        if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
            $data['slug'] = sanitize_title( $data['name'] );
        }
        
        // Ensure unique slug
        $original_slug = $data['slug'];
        $counter = 1;
        while ( $this->get_by_slug( $data['slug'] ) ) {
            $data['slug'] = $original_slug . '-' . $counter;
            $counter++;
        }
        
        $result = $wpdb->insert( $this->table_name, $data );
        
        return $result ? $wpdb->insert_id : 0;
    }

    /**
     * Update room type
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;
        
        $data['updated_at'] = current_time( 'mysql' );
        
        // Handle slug uniqueness
        if ( isset( $data['slug'] ) ) {
            $existing = $this->get_by_slug( $data['slug'] );
            if ( $existing && intval( $existing['id'] ) !== $id ) {
                return false; // Slug conflict
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
     * Delete room type
     */
    public function delete( int $id ): bool {
        global $wpdb;
        
        // Check if any rooms use this type
        $rooms_table = $wpdb->prefix . 'ltlb_rooms';
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$rooms_table} WHERE room_type_id = %d",
            $id
        ) );
        
        if ( intval( $count ) > 0 ) {
            return false; // Cannot delete, rooms still reference this type
        }
        
        $result = $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );
        
        return $result !== false;
    }
}
