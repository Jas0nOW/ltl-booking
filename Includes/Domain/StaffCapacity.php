<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Staff Capacity & Schedule Management
 * 
 * Handles:
 * - Parallel appointment slots per staff
 * - Service capacity (how many clients simultaneously)
 * - Buffer times between appointments
 * - Resource constraints (rooms, equipment)
 * - Maximum concurrent bookings
 * 
 * @package LazyBookings
 */
class LTLB_Staff_Capacity {

    /**
     * Check if staff can handle appointment at given time
     * 
     * @param int $staff_id Staff user ID
     * @param string $start_datetime Start time
     * @param string $end_datetime End time
     * @param int $service_id Service to check capacity for
     * @param int $exclude_appointment_id Exclude this appointment from conflict check
     * @return true|WP_Error True if available, error otherwise
     */
    public function can_book( 
        int $staff_id, 
        string $start_datetime, 
        string $end_datetime,
        int $service_id = 0,
        int $exclude_appointment_id = 0
    ) {
        // Check working hours
        if ( ! $this->is_working_at( $staff_id, $start_datetime ) ) {
            return new WP_Error( 'not_working', __( 'Staff member is not working at this time', 'ltl-bookings' ) );
        }

        // Get capacity settings
        $capacity = $this->get_capacity_settings( $staff_id, $service_id );
        
        // Check if at capacity for this time slot
        $concurrent_count = $this->get_concurrent_appointments(
            $staff_id,
            $start_datetime,
            $end_datetime,
            $exclude_appointment_id
        );
        
        if ( $concurrent_count >= $capacity['max_concurrent'] ) {
            return new WP_Error( 'at_capacity', sprintf(
                __( 'Staff member can handle maximum %d appointments simultaneously', 'ltl-bookings' ),
                $capacity['max_concurrent']
            ) );
        }

        // Check buffer time with adjacent appointments
        if ( $capacity['buffer_minutes'] > 0 ) {
            $has_buffer = $this->check_buffer_time(
                $staff_id,
                $start_datetime,
                $end_datetime,
                $capacity['buffer_minutes'],
                $exclude_appointment_id
            );
            
            if ( ! $has_buffer ) {
                return new WP_Error( 'buffer_required', sprintf(
                    __( 'Requires %d minutes buffer between appointments', 'ltl-bookings' ),
                    $capacity['buffer_minutes']
                ) );
            }
        }

        // Check resource availability (rooms, equipment)
        if ( $service_id > 0 ) {
            $resources_available = $this->check_resource_availability(
                $service_id,
                $start_datetime,
                $end_datetime,
                $exclude_appointment_id
            );
            
            if ( is_wp_error( $resources_available ) ) {
                return $resources_available;
            }
        }

        return true;
    }

    /**
     * Get capacity settings for staff/service combination
     * 
     * @param int $staff_id
     * @param int $service_id
     * @return array Capacity settings
     */
    public function get_capacity_settings( int $staff_id, int $service_id = 0 ): array {
        global $wpdb;

        // Try service-specific capacity first
        if ( $service_id > 0 ) {
            $table = $wpdb->prefix . 'ltlb_staff_capacity';
            $capacity = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table WHERE staff_id = %d AND service_id = %d",
                $staff_id,
                $service_id
            ) );
            
            if ( $capacity ) {
                return [
                    'max_concurrent' => intval( $capacity->max_concurrent ),
                    'buffer_minutes' => intval( $capacity->buffer_minutes ),
                    'requires_resource' => ! empty( $capacity->requires_resource )
                ];
            }
        }

        // Fallback to staff default capacity
        $table = $wpdb->prefix . 'ltlb_staff_capacity';
        $default = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE staff_id = %d AND service_id IS NULL",
            $staff_id
        ) );

        if ( $default ) {
            return [
                'max_concurrent' => intval( $default->max_concurrent ),
                'buffer_minutes' => intval( $default->buffer_minutes ),
                'requires_resource' => false
            ];
        }

        // Ultimate fallback: 1 appointment at a time, 15min buffer
        return [
            'max_concurrent' => 1,
            'buffer_minutes' => 15,
            'requires_resource' => false
        ];
    }

    /**
     * Set capacity for staff (globally or per service)
     * 
     * @param int $staff_id
     * @param array $settings Capacity settings
     * @param int $service_id Optional service ID
     * @return bool Success
     */
    public function set_capacity( int $staff_id, array $settings, int $service_id = 0 ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_staff_capacity';

        $data = [
            'staff_id' => $staff_id,
            'service_id' => $service_id > 0 ? $service_id : null,
            'max_concurrent' => intval( $settings['max_concurrent'] ?? 1 ),
            'buffer_minutes' => intval( $settings['buffer_minutes'] ?? 15 ),
            'requires_resource' => ! empty( $settings['requires_resource'] ) ? 1 : 0,
            'updated_at' => current_time( 'mysql' )
        ];

        // Check if exists
        $where = [ 'staff_id' => $staff_id ];
        if ( $service_id > 0 ) {
            $where['service_id'] = $service_id;
        } else {
            $where['service_id'] = null;
        }

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE staff_id = %d AND " . 
            ( $service_id > 0 ? "service_id = %d" : "service_id IS NULL" ),
            $service_id > 0 ? [ $staff_id, $service_id ] : [ $staff_id ]
        ) );

        if ( $exists ) {
            return $wpdb->update( $table, $data, [ 'id' => $exists ] ) !== false;
        } else {
            $data['created_at'] = current_time( 'mysql' );
            return $wpdb->insert( $table, $data ) !== false;
        }
    }

    /**
     * Get number of concurrent appointments for staff in time range
     * 
     * @param int $staff_id
     * @param string $start_datetime
     * @param string $end_datetime
     * @param int $exclude_appointment_id
     * @return int Count
     */
    private function get_concurrent_appointments(
        int $staff_id,
        string $start_datetime,
        string $end_datetime,
        int $exclude_appointment_id = 0
    ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_appointments';
        
        $query = "SELECT COUNT(*) FROM $table WHERE staff_id = %d 
                  AND status IN ('confirmed', 'pending')
                  AND (
                      (start_at < %s AND end_at > %s) OR
                      (start_at >= %s AND start_at < %s)
                  )";
        
        $params = [ $staff_id, $end_datetime, $start_datetime, $start_datetime, $end_datetime ];

        if ( $exclude_appointment_id > 0 ) {
            $query .= " AND id != %d";
            $params[] = $exclude_appointment_id;
        }

        $count = $wpdb->get_var( $wpdb->prepare( $query, $params ) );

        return intval( $count );
    }

    /**
     * Check if buffer time exists around appointment
     * 
     * @param int $staff_id
     * @param string $start_datetime
     * @param string $end_datetime
     * @param int $buffer_minutes
     * @param int $exclude_appointment_id
     * @return bool Has sufficient buffer
     */
    private function check_buffer_time(
        int $staff_id,
        string $start_datetime,
        string $end_datetime,
        int $buffer_minutes,
        int $exclude_appointment_id = 0
    ): bool {
        global $wpdb;

        // Calculate buffer window
        $buffer_before = date( 'Y-m-d H:i:s', strtotime( $start_datetime ) - ( $buffer_minutes * 60 ) );
        $buffer_after = date( 'Y-m-d H:i:s', strtotime( $end_datetime ) + ( $buffer_minutes * 60 ) );

        $table = $wpdb->prefix . 'ltlb_appointments';
        
        $query = "SELECT COUNT(*) FROM $table WHERE staff_id = %d 
                  AND status IN ('confirmed', 'pending')
                  AND (
                      (start_at >= %s AND start_at < %s) OR
                      (end_at > %s AND end_at <= %s)
                  )";
        
        $params = [ $staff_id, $buffer_before, $start_datetime, $end_datetime, $buffer_after ];

        if ( $exclude_appointment_id > 0 ) {
            $query .= " AND id != %d";
            $params[] = $exclude_appointment_id;
        }

        $conflicts = $wpdb->get_var( $wpdb->prepare( $query, $params ) );

        return intval( $conflicts ) === 0;
    }

    /**
     * Check if staff is working at given time
     * 
     * @param int $staff_id
     * @param string $datetime
     * @return bool Is working
     */
    private function is_working_at( int $staff_id, string $datetime ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_staff_hours';
        
        $dt = new DateTime( $datetime );
        $day_of_week = intval( $dt->format( 'N' ) ); // 1=Monday, 7=Sunday
        $time = $dt->format( 'H:i:s' );

        $hours = $wpdb->get_row( $wpdb->prepare(
            "SELECT start_time, end_time FROM $table 
             WHERE staff_id = %d AND day_of_week = %d",
            $staff_id,
            $day_of_week
        ) );

        if ( ! $hours ) {
            return false; // Not working this day
        }

        return $time >= $hours->start_time && $time <= $hours->end_time;
    }

    /**
     * Check resource availability
     * 
     * @param int $service_id
     * @param string $start_datetime
     * @param string $end_datetime
     * @param int $exclude_appointment_id
     * @return true|WP_Error
     */
    private function check_resource_availability(
        int $service_id,
        string $start_datetime,
        string $end_datetime,
        int $exclude_appointment_id = 0
    ) {
        global $wpdb;

        // Get required resources for service
        $service_resources_table = $wpdb->prefix . 'ltlb_service_resources';
        $required_resources = $wpdb->get_results( $wpdb->prepare(
            "SELECT resource_id, quantity FROM $service_resources_table WHERE service_id = %d",
            $service_id
        ) );

        if ( empty( $required_resources ) ) {
            return true; // No resources required
        }

        // Check each resource availability
        foreach ( $required_resources as $req ) {
            $available = $this->get_available_resource_quantity(
                $req->resource_id,
                $start_datetime,
                $end_datetime,
                $exclude_appointment_id
            );

            if ( $available < intval( $req->quantity ) ) {
                $resource_table = $wpdb->prefix . 'ltlb_resources';
                $resource_name = $wpdb->get_var( $wpdb->prepare(
                    "SELECT name FROM $resource_table WHERE id = %d",
                    $req->resource_id
                ) );

                return new WP_Error( 'resource_unavailable', sprintf(
                    __( 'Resource "%s" is not available at this time', 'ltl-bookings' ),
                    $resource_name
                ) );
            }
        }

        return true;
    }

    /**
     * Get available quantity of resource at time
     * 
     * @param int $resource_id
     * @param string $start_datetime
     * @param string $end_datetime
     * @param int $exclude_appointment_id
     * @return int Available quantity
     */
    private function get_available_resource_quantity(
        int $resource_id,
        string $start_datetime,
        string $end_datetime,
        int $exclude_appointment_id = 0
    ): int {
        global $wpdb;

        // Get total resource quantity
        $resource_table = $wpdb->prefix . 'ltlb_resources';
        $total_qty = $wpdb->get_var( $wpdb->prepare(
            "SELECT quantity FROM $resource_table WHERE id = %d",
            $resource_id
        ) );

        if ( ! $total_qty ) {
            return 0;
        }

        // Get used quantity in time range
        $appointment_resources_table = $wpdb->prefix . 'ltlb_appointment_resources';
        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        
        $query = "SELECT COALESCE(SUM(ar.quantity), 0) 
                  FROM $appointment_resources_table ar
                  INNER JOIN $appointments_table a ON ar.appointment_id = a.id
                  WHERE ar.resource_id = %d
                  AND a.status IN ('confirmed', 'pending')
                  AND (
                      (a.start_at < %s AND a.end_at > %s) OR
                      (a.start_at >= %s AND a.start_at < %s)
                  )";
        
        $params = [ $resource_id, $end_datetime, $start_datetime, $start_datetime, $end_datetime ];

        if ( $exclude_appointment_id > 0 ) {
            $query .= " AND a.id != %d";
            $params[] = $exclude_appointment_id;
        }

        $used_qty = $wpdb->get_var( $wpdb->prepare( $query, $params ) );

        return intval( $total_qty ) - intval( $used_qty );
    }

    /**
     * Get staff availability slots for day
     * 
     * @param int $staff_id
     * @param string $date Date (Y-m-d)
     * @param int $service_id Service to check capacity for
     * @param int $slot_duration_minutes Slot duration
     * @return array Available time slots
     */
    public function get_available_slots(
        int $staff_id,
        string $date,
        int $service_id = 0,
        int $slot_duration_minutes = 60
    ): array {
        $capacity = $this->get_capacity_settings( $staff_id, $service_id );
        
        // Get working hours for day
        $dt = new DateTime( $date );
        $day_of_week = intval( $dt->format( 'N' ) );
        
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_staff_hours';
        $hours = $wpdb->get_row( $wpdb->prepare(
            "SELECT start_time, end_time FROM $table WHERE staff_id = %d AND day_of_week = %d",
            $staff_id,
            $day_of_week
        ) );

        if ( ! $hours ) {
            return []; // Not working this day
        }

        // Generate slots
        $slots = [];
        $current = strtotime( $date . ' ' . $hours->start_time );
        $end = strtotime( $date . ' ' . $hours->end_time );
        
        while ( $current < $end ) {
            $slot_start = date( 'Y-m-d H:i:s', $current );
            $slot_end = date( 'Y-m-d H:i:s', $current + ( $slot_duration_minutes * 60 ) );
            
            // Check if slot is available
            $can_book = $this->can_book( $staff_id, $slot_start, $slot_end, $service_id );
            
            if ( $can_book === true ) {
                $slots[] = [
                    'start' => $slot_start,
                    'end' => $slot_end,
                    'available' => true
                ];
            }
            
            $current += ( $slot_duration_minutes * 60 );
        }

        return $slots;
    }
}
