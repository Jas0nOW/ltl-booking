<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Extended Availability Rules Engine
 * 
 * Provides additional availability rules:
 * - Holidays/Blackout dates
 * - Min/Max booking duration
 * - Buffer times between appointments
 * - Capacity limits per time slot
 * - Break times (lunch, etc.)
 */
class LTLB_AvailabilityRules {

    /**
     * Check if date is a holiday/blackout
     * 
     * @param string $date Date in Y-m-d format
     * @return bool True if date is blocked
     */
    public static function is_holiday( string $date ): bool {
        $holidays = get_option( 'ltlb_holidays', [] );
        if ( ! is_array( $holidays ) ) {
            return false;
        }
        
        return in_array( $date, $holidays, true );
    }

    /**
     * Add a holiday/blackout date
     */
    public static function add_holiday( string $date ): bool {
        $holidays = get_option( 'ltlb_holidays', [] );
        if ( ! is_array( $holidays ) ) {
            $holidays = [];
        }
        
        if ( ! in_array( $date, $holidays, true ) ) {
            $holidays[] = $date;
            sort( $holidays );
            update_option( 'ltlb_holidays', $holidays );
            return true;
        }
        
        return false;
    }

    /**
     * Remove a holiday/blackout date
     */
    public static function remove_holiday( string $date ): bool {
        $holidays = get_option( 'ltlb_holidays', [] );
        if ( ! is_array( $holidays ) ) {
            return false;
        }
        
        $key = array_search( $date, $holidays, true );
        if ( $key !== false ) {
            unset( $holidays[ $key ] );
            $holidays = array_values( $holidays );
            update_option( 'ltlb_holidays', $holidays );
            return true;
        }
        
        return false;
    }

    /**
     * Get all holidays
     */
    public static function get_holidays(): array {
        $holidays = get_option( 'ltlb_holidays', [] );
        return is_array( $holidays ) ? $holidays : [];
    }

    /**
     * Check if duration is within service limits
     * 
     * @param int $service_id Service ID
     * @param int $duration_minutes Requested duration in minutes
     * @return array ['valid' => bool, 'min' => int, 'max' => int]
     */
    public static function check_duration_limits( int $service_id, int $duration_minutes ): array {
        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( $service_id );
        
        if ( ! $service ) {
            return [ 'valid' => false, 'min' => 0, 'max' => 0 ];
        }
        
        $default_duration = intval( $service['duration_min'] ?? 60 );
        $min_duration = intval( $service['min_duration_min'] ?? $default_duration );
        $max_duration = intval( $service['max_duration_min'] ?? $default_duration );
        
        // If min/max not set, use default duration as both min and max (fixed duration)
        if ( $min_duration === 0 ) {
            $min_duration = $default_duration;
        }
        if ( $max_duration === 0 ) {
            $max_duration = $default_duration;
        }
        
        $valid = ( $duration_minutes >= $min_duration && $duration_minutes <= $max_duration );
        
        return [
            'valid' => $valid,
            'min' => $min_duration,
            'max' => $max_duration,
        ];
    }

    /**
     * Get buffer time required before/after a service
     * 
     * @param int $service_id Service ID
     * @return array ['buffer_before' => int, 'buffer_after' => int] in minutes
     */
    public static function get_buffer_times( int $service_id ): array {
        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( $service_id );
        
        if ( ! $service ) {
            return [ 'buffer_before' => 0, 'buffer_after' => 0 ];
        }
        
        return [
            'buffer_before' => intval( $service['buffer_before_min'] ?? 0 ),
            'buffer_after' => intval( $service['buffer_after_min'] ?? 0 ),
        ];
    }

    /**
     * Check if time slot is within capacity limit
     * 
     * @param int $service_id Service ID
     * @param string $start_datetime Start datetime (Y-m-d H:i:s)
     * @param string $end_datetime End datetime (Y-m-d H:i:s)
     * @return array ['available' => bool, 'current' => int, 'max' => int]
     */
    public static function check_capacity( int $service_id, string $start_datetime, string $end_datetime ): array {
        global $wpdb;
        
        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( $service_id );
        
        if ( ! $service ) {
            return [ 'available' => false, 'current' => 0, 'max' => 0 ];
        }
        
        // Get max capacity for service
        $max_capacity = intval( $service['max_capacity'] ?? 1 );
        
        // Count current bookings in this time slot
        $table = $wpdb->prefix . 'lazy_appointments';
        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE service_id = %d 
             AND status NOT IN ('cancelled', 'refunded') 
             AND start_at < %s 
             AND end_at > %s",
            $service_id,
            $end_datetime,
            $start_datetime
        ) );
        
        $current = intval( $current );
        $available = $current < $max_capacity;
        
        return [
            'available' => $available,
            'current' => $current,
            'max' => $max_capacity,
        ];
    }

    /**
     * Get break times for a staff member on a specific date
     * 
     * @param int $staff_user_id Staff user ID
     * @param string $date Date in Y-m-d format
     * @return array Array of breaks: [['start' => 'HH:MM', 'end' => 'HH:MM'], ...]
     */
    public static function get_staff_breaks( int $staff_user_id, string $date ): array {
        $breaks = get_user_meta( $staff_user_id, 'ltlb_staff_breaks', true );
        
        if ( ! is_array( $breaks ) ) {
            // Default: 1 hour lunch break from 12:00-13:00
            return [
                [ 'start' => '12:00', 'end' => '13:00' ],
            ];
        }
        
        return $breaks;
    }

    /**
     * Check if time slot conflicts with a break
     * 
     * @param string $slot_start Slot start time (HH:MM)
     * @param string $slot_end Slot end time (HH:MM)
     * @param array $breaks Array of breaks from get_staff_breaks()
     * @return bool True if conflicts with a break
     */
    public static function conflicts_with_break( string $slot_start, string $slot_end, array $breaks ): bool {
        foreach ( $breaks as $break ) {
            $break_start = $break['start'] ?? '';
            $break_end = $break['end'] ?? '';
            
            if ( $break_start === '' || $break_end === '' ) {
                continue;
            }
            
            // Check for overlap: slot_start < break_end AND slot_end > break_start
            if ( $slot_start < $break_end && $slot_end > $break_start ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate booking against all rules
     * 
     * @param int $service_id Service ID
     * @param string $start_datetime Start datetime (Y-m-d H:i:s)
     * @param string $end_datetime End datetime (Y-m-d H:i:s)
     * @param int|null $staff_user_id Staff user ID (optional)
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate_booking( int $service_id, string $start_datetime, string $end_datetime, ?int $staff_user_id = null ): array {
        $errors = [];
        
        // Extract date from start_datetime
        $date = substr( $start_datetime, 0, 10 ); // Y-m-d
        
        // Check holiday
        if ( self::is_holiday( $date ) ) {
            $errors[] = __( 'This date is not available for booking (holiday).', 'ltl-bookings' );
        }
        
        // Check duration limits
        $start_dt = new DateTime( $start_datetime );
        $end_dt = new DateTime( $end_datetime );
        $duration = intval( ( $end_dt->getTimestamp() - $start_dt->getTimestamp() ) / 60 );
        
        $duration_check = self::check_duration_limits( $service_id, $duration );
        if ( ! $duration_check['valid'] ) {
            $errors[] = sprintf(
                __( 'Duration must be between %d and %d minutes.', 'ltl-bookings' ),
                $duration_check['min'],
                $duration_check['max']
            );
        }
        
        // Check capacity
        $capacity = self::check_capacity( $service_id, $start_datetime, $end_datetime );
        if ( ! $capacity['available'] ) {
            $errors[] = sprintf(
                __( 'This time slot is fully booked (%d/%d).', 'ltl-bookings' ),
                $capacity['current'],
                $capacity['max']
            );
        }
        
        // Check staff breaks
        if ( $staff_user_id ) {
            $breaks = self::get_staff_breaks( $staff_user_id, $date );
            $slot_start = $start_dt->format( 'H:i' );
            $slot_end = $end_dt->format( 'H:i' );
            
            if ( self::conflicts_with_break( $slot_start, $slot_end, $breaks ) ) {
                $errors[] = __( 'This time slot conflicts with a scheduled break.', 'ltl-bookings' );
            }
        }
        
        return [
            'valid' => empty( $errors ),
            'errors' => $errors,
        ];
    }
}
