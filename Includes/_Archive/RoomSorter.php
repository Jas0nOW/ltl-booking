<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Room Sorting Utility
 * 
 * Features:
 * - Auto-sort rooms by various criteria
 * - Persistent sort order
 * - Manual drag & drop support
 * - Multiple sort strategies
 * 
 * @package LazyBookings
 */
class LTLB_Room_Sorter {

    /**
     * Sort rooms by specified criteria
     * 
     * @param string $sort_by Sort criteria
     * @param string $direction Sort direction (asc/desc)
     * @return array Sorted room IDs
     */
    public static function sort_rooms( string $sort_by = 'name', string $direction = 'asc' ): array {
        global $wpdb;

        $services_table = $wpdb->prefix . 'ltlb_services';

        // Get all active rooms
        $rooms = $wpdb->get_results(
            "SELECT id, name, beds_type, max_adults, price_cents, sort_order 
             FROM $services_table 
             WHERE is_active = 1 
             AND beds_type IS NOT NULL 
             AND beds_type != ''
             ORDER BY id ASC",
            ARRAY_A
        );

        if ( empty( $rooms ) ) {
            return [];
        }

        // Apply sorting strategy
        switch ( $sort_by ) {
            case 'name':
                $rooms = self::sort_by_name( $rooms, $direction );
                break;

            case 'type':
                $rooms = self::sort_by_type( $rooms, $direction );
                break;

            case 'capacity':
                $rooms = self::sort_by_capacity( $rooms, $direction );
                break;

            case 'price':
                $rooms = self::sort_by_price( $rooms, $direction );
                break;

            case 'occupancy':
                $rooms = self::sort_by_occupancy( $rooms, $direction );
                break;

            case 'floor':
                $rooms = self::sort_by_floor( $rooms, $direction );
                break;

            case 'manual':
                $rooms = self::sort_by_manual_order( $rooms, $direction );
                break;

            default:
                // Keep original order
                break;
        }

        // Save the new sort order
        self::save_sort_order( $rooms );

        return array_column( $rooms, 'id' );
    }

    /**
     * Sort by room name (alphabetically)
     * 
     * @param array $rooms Rooms array
     * @param string $direction Direction
     * @return array Sorted rooms
     */
    private static function sort_by_name( array $rooms, string $direction ): array {
        usort( $rooms, function( $a, $b ) use ( $direction ) {
            $cmp = strcasecmp( $a['name'], $b['name'] );
            return $direction === 'desc' ? -$cmp : $cmp;
        } );

        return $rooms;
    }

    /**
     * Sort by room type
     * 
     * @param array $rooms Rooms array
     * @param string $direction Direction
     * @return array Sorted rooms
     */
    private static function sort_by_type( array $rooms, string $direction ): array {
        // Define type hierarchy
        $type_order = [
            'single' => 1,
            'double' => 2,
            'twin' => 3,
            'triple' => 4,
            'quad' => 5,
            'suite' => 6,
            'apartment' => 7
        ];

        usort( $rooms, function( $a, $b ) use ( $direction, $type_order ) {
            $order_a = $type_order[ $a['beds_type'] ] ?? 999;
            $order_b = $type_order[ $b['beds_type'] ] ?? 999;
            
            $cmp = $order_a <=> $order_b;
            
            // Secondary sort by name
            if ( $cmp === 0 ) {
                $cmp = strcasecmp( $a['name'], $b['name'] );
            }
            
            return $direction === 'desc' ? -$cmp : $cmp;
        } );

        return $rooms;
    }

    /**
     * Sort by capacity (max adults)
     * 
     * @param array $rooms Rooms array
     * @param string $direction Direction
     * @return array Sorted rooms
     */
    private static function sort_by_capacity( array $rooms, string $direction ): array {
        usort( $rooms, function( $a, $b ) use ( $direction ) {
            $cmp = intval( $a['max_adults'] ) <=> intval( $b['max_adults'] );
            
            // Secondary sort by name
            if ( $cmp === 0 ) {
                $cmp = strcasecmp( $a['name'], $b['name'] );
            }
            
            return $direction === 'desc' ? -$cmp : $cmp;
        } );

        return $rooms;
    }

    /**
     * Sort by price
     * 
     * @param array $rooms Rooms array
     * @param string $direction Direction
     * @return array Sorted rooms
     */
    private static function sort_by_price( array $rooms, string $direction ): array {
        usort( $rooms, function( $a, $b ) use ( $direction ) {
            $cmp = intval( $a['price_cents'] ) <=> intval( $b['price_cents'] );
            
            // Secondary sort by name
            if ( $cmp === 0 ) {
                $cmp = strcasecmp( $a['name'], $b['name'] );
            }
            
            return $direction === 'desc' ? -$cmp : $cmp;
        } );

        return $rooms;
    }

    /**
     * Sort by current occupancy rate
     * 
     * @param array $rooms Rooms array
     * @param string $direction Direction
     * @return array Sorted rooms
     */
    private static function sort_by_occupancy( array $rooms, string $direction ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $room_ids = array_column( $rooms, 'id' );

        if ( empty( $room_ids ) ) {
            return $rooms;
        }

        // Calculate occupancy for next 30 days
        $start_date = current_time( 'Y-m-d' );
        $end_date = date( 'Y-m-d', strtotime( '+30 days' ) );

        $placeholders = implode( ',', array_fill( 0, count( $room_ids ), '%d' ) );
        $params = array_merge( $room_ids, [ $start_date, $end_date ] );

        $occupancy_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT service_id, COUNT(*) as booking_count 
                 FROM $appointments_table 
                 WHERE service_id IN ($placeholders)
                 AND status IN ('confirmed', 'pending')
                 AND start_at >= %s 
                 AND start_at <= %s
                 GROUP BY service_id",
                $params
            ),
            OBJECT_K
        );

        // Add occupancy count to rooms
        foreach ( $rooms as &$room ) {
            $room['occupancy_count'] = isset( $occupancy_data[ $room['id'] ] ) 
                ? intval( $occupancy_data[ $room['id'] ]->booking_count ) 
                : 0;
        }

        // Sort by occupancy
        usort( $rooms, function( $a, $b ) use ( $direction ) {
            $cmp = $a['occupancy_count'] <=> $b['occupancy_count'];
            
            // Secondary sort by name
            if ( $cmp === 0 ) {
                $cmp = strcasecmp( $a['name'], $b['name'] );
            }
            
            return $direction === 'desc' ? -$cmp : $cmp;
        } );

        return $rooms;
    }

    /**
     * Sort by floor number (extracted from room name or custom field)
     * 
     * @param array $rooms Rooms array
     * @param string $direction Direction
     * @return array Sorted rooms
     */
    private static function sort_by_floor( array $rooms, string $direction ): array {
        usort( $rooms, function( $a, $b ) use ( $direction ) {
            $floor_a = self::extract_floor_number( $a['name'] );
            $floor_b = self::extract_floor_number( $b['name'] );
            
            $cmp = $floor_a <=> $floor_b;
            
            // Secondary sort by name
            if ( $cmp === 0 ) {
                $cmp = strcasecmp( $a['name'], $b['name'] );
            }
            
            return $direction === 'desc' ? -$cmp : $cmp;
        } );

        return $rooms;
    }

    /**
     * Sort by manual order (existing sort_order field)
     * 
     * @param array $rooms Rooms array
     * @param string $direction Direction
     * @return array Sorted rooms
     */
    private static function sort_by_manual_order( array $rooms, string $direction ): array {
        usort( $rooms, function( $a, $b ) use ( $direction ) {
            $order_a = intval( $a['sort_order'] ?? 0 );
            $order_b = intval( $b['sort_order'] ?? 0 );
            
            $cmp = $order_a <=> $order_b;
            
            return $direction === 'desc' ? -$cmp : $cmp;
        } );

        return $rooms;
    }

    /**
     * Extract floor number from room name
     * 
     * @param string $name Room name
     * @return int Floor number (0 if not found)
     */
    private static function extract_floor_number( string $name ): int {
        // Try to find floor number in name (e.g., "Room 301" -> 3, "1st Floor Room A" -> 1)
        if ( preg_match( '/(\d)(?:st|nd|rd|th)?\s*floor/i', $name, $matches ) ) {
            return intval( $matches[1] );
        }

        // Try room number format (e.g., 301 -> 3, 405 -> 4)
        if ( preg_match( '/\b([1-9])(\d{2})\b/', $name, $matches ) ) {
            return intval( $matches[1] );
        }

        return 0; // Ground floor or unknown
    }

    /**
     * Save sort order to database
     * 
     * @param array $rooms Sorted rooms array
     * @return int Number of updated rooms
     */
    private static function save_sort_order( array $rooms ): int {
        global $wpdb;

        $services_table = $wpdb->prefix . 'ltlb_services';
        $updated = 0;

        foreach ( $rooms as $index => $room ) {
            $sort_order = $index + 1;
            
            $result = $wpdb->update(
                $services_table,
                [ 'sort_order' => $sort_order ],
                [ 'id' => $room['id'] ],
                [ '%d' ],
                [ '%d' ]
            );

            if ( $result !== false ) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Update manual sort order (for drag & drop)
     * 
     * @param array $room_ids_in_order Room IDs in desired order
     * @return bool Success
     */
    public static function update_manual_order( array $room_ids_in_order ): bool {
        global $wpdb;

        $services_table = $wpdb->prefix . 'ltlb_services';

        foreach ( $room_ids_in_order as $index => $room_id ) {
            $wpdb->update(
                $services_table,
                [ 'sort_order' => $index + 1 ],
                [ 'id' => intval( $room_id ) ],
                [ '%d' ],
                [ '%d' ]
            );
        }

        return true;
    }

    /**
     * Get current sort preference
     * 
     * @return array Sort settings
     */
    public static function get_sort_preference(): array {
        $settings = get_option( 'lazy_settings', [] );
        
        return [
            'sort_by' => $settings['room_sort_by'] ?? 'name',
            'direction' => $settings['room_sort_direction'] ?? 'asc'
        ];
    }

    /**
     * Save sort preference
     * 
     * @param string $sort_by Sort criteria
     * @param string $direction Direction
     * @return bool Success
     */
    public static function save_sort_preference( string $sort_by, string $direction ): bool {
        $settings = get_option( 'lazy_settings', [] );
        $settings['room_sort_by'] = $sort_by;
        $settings['room_sort_direction'] = $direction;
        
        return update_option( 'lazy_settings', $settings );
    }

    /**
     * Get available sort options
     * 
     * @return array Sort options
     */
    public static function get_sort_options(): array {
        return [
            'name' => __( 'Name (Alphabetical)', 'ltl-bookings' ),
            'type' => __( 'Room Type', 'ltl-bookings' ),
            'capacity' => __( 'Capacity', 'ltl-bookings' ),
            'price' => __( 'Price', 'ltl-bookings' ),
            'occupancy' => __( 'Occupancy Rate', 'ltl-bookings' ),
            'floor' => __( 'Floor Number', 'ltl-bookings' ),
            'manual' => __( 'Manual Order', 'ltl-bookings' )
        ];
    }

    /**
     * Render sort button for calendar toolbar
     */
    public static function render_sort_button(): void {
        $preference = self::get_sort_preference();
        $options = self::get_sort_options();
        
        ?>
        <div class="ltlb-room-sorter">
            <button type="button" class="button" id="ltlb-sort-rooms-btn">
                <span class="dashicons dashicons-sort"></span>
                <?php esc_html_e( 'Sort Rooms', 'ltl-bookings' ); ?>
            </button>
            
            <div class="ltlb-sort-dropdown" style="display:none;">
                <select id="ltlb-sort-by">
                    <?php foreach ( $options as $value => $label ): ?>
                        <option value="<?php echo esc_attr( $value ); ?>" 
                                <?php selected( $preference['sort_by'], $value ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="ltlb-sort-direction">
                    <option value="asc" <?php selected( $preference['direction'], 'asc' ); ?>>
                        <?php esc_html_e( 'Ascending', 'ltl-bookings' ); ?>
                    </option>
                    <option value="desc" <?php selected( $preference['direction'], 'desc' ); ?>>
                        <?php esc_html_e( 'Descending', 'ltl-bookings' ); ?>
                    </option>
                </select>
                
                <button type="button" class="button button-primary" onclick="ltlbApplyRoomSort()">
                    <?php esc_html_e( 'Apply', 'ltl-bookings' ); ?>
                </button>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sortBtn = document.getElementById('ltlb-sort-rooms-btn');
            const dropdown = document.querySelector('.ltlb-sort-dropdown');
            
            if (sortBtn && dropdown) {
                sortBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
                });
                
                document.addEventListener('click', function() {
                    dropdown.style.display = 'none';
                });
            }
        });
        
        function ltlbApplyRoomSort() {
            const sortBy = document.getElementById('ltlb-sort-by').value;
            const direction = document.getElementById('ltlb-sort-direction').value;
            
            // Send AJAX request
            jQuery.post(ajaxurl, {
                action: 'ltlb_sort_rooms',
                sort_by: sortBy,
                direction: direction,
                nonce: '<?php echo wp_create_nonce( 'ltlb_sort_rooms' ); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload(); // Reload calendar
                } else {
                    alert(response.data.message || 'Failed to sort rooms');
                }
            });
        }
        </script>
        
        <style>
        .ltlb-room-sorter {
            position: relative;
            display: inline-block;
        }
        
        .ltlb-sort-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 5px;
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 250px;
        }
        
        .ltlb-sort-dropdown select {
            display: block;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .ltlb-sort-dropdown button {
            width: 100%;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler for room sorting
     */
    public static function ajax_sort_rooms(): void {
        check_ajax_referer( 'ltlb_sort_rooms', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'ltl-bookings' ) ] );
        }

        $sort_by = sanitize_key( $_POST['sort_by'] ?? 'name' );
        $direction = sanitize_key( $_POST['direction'] ?? 'asc' );

        // Save preference
        self::save_sort_preference( $sort_by, $direction );

        // Apply sorting
        $room_ids = self::sort_rooms( $sort_by, $direction );

        wp_send_json_success( [
            'message' => __( 'Rooms sorted successfully', 'ltl-bookings' ),
            'room_ids' => $room_ids,
            'count' => count( $room_ids )
        ] );
    }
}

// Register AJAX handler
add_action( 'wp_ajax_ltlb_sort_rooms', [ 'LTLB_Room_Sorter', 'ajax_sort_rooms' ] );
