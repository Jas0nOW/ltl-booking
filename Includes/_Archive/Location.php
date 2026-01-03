<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Location Domain Class - Multi-Location Support
 * 
 * Manages multiple business locations with:
 * - Independent opening hours and timezones
 * - Location-specific staff assignments
 * - Service availability per location
 * - Tax settings per location
 * - Custom branding per location
 * 
 * @package LazyBookings
 */
class LTLB_Location {

    /**
     * Get location by ID
     * 
     * @param int $location_id
     * @return object|null Location data
     */
    public static function get( int $location_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_locations';
        
        $location = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND is_active = 1",
            $location_id
        ) );
        
        if ( ! $location ) {
            return null;
        }
        
        // Parse JSON fields
        $location->opening_hours = json_decode( $location->opening_hours, true ) ?? [];
        $location->branding = json_decode( $location->branding, true ) ?? [];
        
        return $location;
    }

    /**
     * Get all active locations
     * 
     * @return array Locations
     */
    public static function get_all_active(): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_locations';
        
        $locations = $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        );
        
        // Parse JSON fields
        foreach ( $locations as $location ) {
            $location->opening_hours = json_decode( $location->opening_hours, true ) ?? [];
            $location->branding = json_decode( $location->branding, true ) ?? [];
        }
        
        return $locations;
    }

    /**
     * Create new location
     * 
     * @param array $data Location data
     * @return int|WP_Error Location ID or error
     */
    public static function create( array $data ) {
        global $wpdb;
        
        // Validate required fields
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'missing_name', __( 'Location name is required', 'ltl-bookings' ) );
        }
        
        $table = $wpdb->prefix . 'ltlb_locations';
        
        $insert_data = [
            'name' => sanitize_text_field( $data['name'] ),
            'address' => sanitize_textarea_field( $data['address'] ?? '' ),
            'city' => sanitize_text_field( $data['city'] ?? '' ),
            'state' => sanitize_text_field( $data['state'] ?? '' ),
            'zip' => sanitize_text_field( $data['zip'] ?? '' ),
            'country' => sanitize_text_field( $data['country'] ?? '' ),
            'phone' => sanitize_text_field( $data['phone'] ?? '' ),
            'email' => sanitize_email( $data['email'] ?? '' ),
            'timezone' => sanitize_text_field( $data['timezone'] ?? 'UTC' ),
            'opening_hours' => wp_json_encode( $data['opening_hours'] ?? [] ),
            'tax_rate_percent' => floatval( $data['tax_rate_percent'] ?? 0 ),
            'currency' => sanitize_text_field( $data['currency'] ?? 'EUR' ),
            'branding' => wp_json_encode( $data['branding'] ?? [] ),
            'sort_order' => intval( $data['sort_order'] ?? 0 ),
            'is_active' => ! empty( $data['is_active'] ) ? 1 : 0,
            'created_at' => current_time( 'mysql' )
        ];
        
        $wpdb->insert( $table, $insert_data, [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%s'
        ] );
        
        if ( $wpdb->last_error ) {
            return new WP_Error( 'db_error', $wpdb->last_error );
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Update location
     * 
     * @param int $location_id
     * @param array $data Update data
     * @return bool|WP_Error Success or error
     */
    public static function update( int $location_id, array $data ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_locations';
        
        $update_data = [];
        $formats = [];
        
        // Only update provided fields
        $allowed_fields = [
            'name' => '%s',
            'address' => '%s',
            'city' => '%s',
            'state' => '%s',
            'zip' => '%s',
            'country' => '%s',
            'phone' => '%s',
            'email' => '%s',
            'timezone' => '%s',
            'tax_rate_percent' => '%f',
            'currency' => '%s',
            'sort_order' => '%d',
            'is_active' => '%d'
        ];
        
        foreach ( $allowed_fields as $field => $format ) {
            if ( isset( $data[$field] ) ) {
                $update_data[$field] = $data[$field];
                $formats[] = $format;
            }
        }
        
        // Handle JSON fields separately
        if ( isset( $data['opening_hours'] ) ) {
            $update_data['opening_hours'] = wp_json_encode( $data['opening_hours'] );
            $formats[] = '%s';
        }
        
        if ( isset( $data['branding'] ) ) {
            $update_data['branding'] = wp_json_encode( $data['branding'] );
            $formats[] = '%s';
        }
        
        if ( empty( $update_data ) ) {
            return new WP_Error( 'no_data', __( 'No data to update', 'ltl-bookings' ) );
        }
        
        $update_data['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';
        
        $result = $wpdb->update(
            $table,
            $update_data,
            [ 'id' => $location_id ],
            $formats,
            [ '%d' ]
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', $wpdb->last_error );
        }
        
        return true;
    }

    /**
     * Delete location (soft delete)
     * 
     * @param int $location_id
     * @return bool|WP_Error
     */
    public static function delete( int $location_id ) {
        global $wpdb;
        
        // Check if location has bookings
        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $has_bookings = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE location_id = %d",
            $location_id
        ) );
        
        if ( $has_bookings > 0 ) {
            return new WP_Error( 'has_bookings', __( 'Cannot delete location with existing bookings', 'ltl-bookings' ) );
        }
        
        // Soft delete
        return self::update( $location_id, [ 'is_active' => 0 ] );
    }

    /**
     * Get staff assigned to location
     * 
     * @param int $location_id
     * @return array Staff IDs
     */
    public static function get_staff( int $location_id ): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_location_staff';
        
        $staff_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT staff_id FROM $table WHERE location_id = %d",
            $location_id
        ) );
        
        return array_map( 'intval', $staff_ids );
    }

    /**
     * Assign staff to location
     * 
     * @param int $location_id
     * @param array $staff_ids Array of staff user IDs
     * @return bool Success
     */
    public static function assign_staff( int $location_id, array $staff_ids ): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_location_staff';
        
        // Remove current assignments
        $wpdb->delete( $table, [ 'location_id' => $location_id ], [ '%d' ] );
        
        // Insert new assignments
        foreach ( $staff_ids as $staff_id ) {
            $wpdb->insert( $table, [
                'location_id' => $location_id,
                'staff_id' => intval( $staff_id ),
                'created_at' => current_time( 'mysql' )
            ], [ '%d', '%d', '%s' ] );
        }
        
        return true;
    }

    /**
     * Get services available at location
     * 
     * @param int $location_id
     * @return array Service IDs
     */
    public static function get_services( int $location_id ): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_location_services';
        
        $service_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT service_id FROM $table WHERE location_id = %d",
            $location_id
        ) );
        
        return array_map( 'intval', $service_ids );
    }

    /**
     * Assign services to location
     * 
     * @param int $location_id
     * @param array $service_ids
     * @return bool Success
     */
    public static function assign_services( int $location_id, array $service_ids ): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_location_services';
        
        // Remove current assignments
        $wpdb->delete( $table, [ 'location_id' => $location_id ], [ '%d' ] );
        
        // Insert new assignments
        foreach ( $service_ids as $service_id ) {
            $wpdb->insert( $table, [
                'location_id' => $location_id,
                'service_id' => intval( $service_id ),
                'created_at' => current_time( 'mysql' )
            ], [ '%d', '%d', '%s' ] );
        }
        
        return true;
    }

    /**
     * Check if location is open at specific time
     * 
     * @param int $location_id
     * @param string $datetime DateTime string
     * @return bool Is open
     */
    public static function is_open_at( int $location_id, string $datetime ): bool {
        $location = self::get( $location_id );
        
        if ( ! $location || empty( $location->opening_hours ) ) {
            return false;
        }
        
        // Convert to location timezone
        $dt = new DateTime( $datetime, new DateTimeZone( 'UTC' ) );
        $dt->setTimezone( new DateTimeZone( $location->timezone ) );
        
        $day_of_week = strtolower( $dt->format( 'l' ) ); // monday, tuesday, etc.
        $time = $dt->format( 'H:i' );
        
        $hours = $location->opening_hours[$day_of_week] ?? null;
        
        if ( ! $hours || empty( $hours['open'] ) || empty( $hours['close'] ) ) {
            return false; // Closed that day
        }
        
        return $time >= $hours['open'] && $time <= $hours['close'];
    }

    /**
     * Get location selector for frontend
     * 
     * @param int $selected_id Currently selected location
     * @return string HTML select dropdown
     */
    public static function get_selector( int $selected_id = 0 ): string {
        $locations = self::get_all_active();
        
        if ( empty( $locations ) ) {
            return '';
        }
        
        $output = '<select name="location_id" class="ltlb-location-selector" required>';
        $output .= '<option value="">' . esc_html__( 'Select Location', 'ltl-bookings' ) . '</option>';
        
        foreach ( $locations as $location ) {
            $selected = ( $location->id === $selected_id ) ? ' selected' : '';
            $output .= sprintf(
                '<option value="%d"%s>%s - %s</option>',
                $location->id,
                $selected,
                esc_html( $location->name ),
                esc_html( $location->city )
            );
        }
        
        $output .= '</select>';
        
        return $output;
    }
}
