<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Policy Engine - Storno/Umbuchungsregeln
 * 
 * Handles booking policies:
 * - Cancellation fees based on time until booking
 * - No-show fees
 * - Reschedule limits and fees
 * - Refund windows
 * 
 * @package LazyBookings
 */
class LTLB_Policy_Engine {

    /**
     * Calculate cancellation fee
     * 
     * @param int $appointment_id
     * @param string $cancellation_time When cancelling (default: now)
     * @return array Fee details
     */
    public function calculate_cancellation_fee( int $appointment_id, string $cancellation_time = '' ): array {
        global $wpdb;
        
        if ( empty( $cancellation_time ) ) {
            $cancellation_time = current_time( 'mysql' );
        }
        
        // Get appointment
        $table = $wpdb->prefix . 'ltlb_appointments';
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, service_id, start_at, amount_cents FROM $table WHERE id = %d",
            $appointment_id
        ) );
        
        if ( ! $appointment ) {
            return [
                'error' => __( 'Appointment not found', 'ltl-bookings' ),
                'fee_cents' => 0,
                'refund_cents' => 0
            ];
        }
        
        $total_cents = intval( $appointment->amount_cents );
        
        // Calculate hours until appointment
        $start_timestamp = strtotime( $appointment->start_at );
        $cancel_timestamp = strtotime( $cancellation_time );
        $hours_until = ( $start_timestamp - $cancel_timestamp ) / 3600;
        
        // Get policy for service
        $policy = $this->get_cancellation_policy( intval( $appointment->service_id ) );
        
        // Apply policy
        $fee_cents = 0;
        $fee_percent = 0;
        
        if ( $hours_until < 0 ) {
            // No-show (already past)
            $fee_percent = $policy['no_show_fee_percent'] ?? 100;
        } elseif ( $hours_until < $policy['free_cancellation_hours'] ) {
            // Within cancellation window - apply fee
            $fee_percent = $policy['cancellation_fee_percent'] ?? 50;
        }
        // else: Free cancellation
        
        $fee_cents = round( $total_cents * ( $fee_percent / 100 ) );
        $refund_cents = $total_cents - $fee_cents;
        
        return [
            'total_cents' => $total_cents,
            'fee_cents' => $fee_cents,
            'fee_percent' => $fee_percent,
            'refund_cents' => $refund_cents,
            'hours_until' => round( $hours_until, 1 ),
            'policy' => $policy
        ];
    }

    /**
     * Process cancellation
     * 
     * @param int $appointment_id
     * @param string $reason Optional cancellation reason
     * @return bool|WP_Error Success or error
     */
    public function cancel_booking( int $appointment_id, string $reason = '' ) {
        global $wpdb;
        
        $appointment_table = $wpdb->prefix . 'ltlb_appointments';
        
        // Calculate fees
        $fee_calc = $this->calculate_cancellation_fee( $appointment_id );
        
        if ( isset( $fee_calc['error'] ) ) {
            return new WP_Error( 'calculation_failed', $fee_calc['error'] );
        }
        
        // Update appointment status
        $wpdb->update(
            $appointment_table,
            [
                'status' => 'cancelled',
                'updated_at' => current_time( 'mysql' )
            ],
            [ 'id' => $appointment_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        // Log cancellation
        $log_table = $wpdb->prefix . 'ltlb_cancellation_log';
        $wpdb->insert( $log_table, [
            'appointment_id' => $appointment_id,
            'cancelled_at' => current_time( 'mysql' ),
            'reason' => sanitize_text_field( $reason ),
            'fee_cents' => $fee_calc['fee_cents'],
            'refund_cents' => $fee_calc['refund_cents'],
            'hours_until_booking' => $fee_calc['hours_until']
        ], [ '%d', '%s', '%s', '%d', '%d', '%f' ] );
        
        // Trigger actions for notifications/refunds
        do_action( 'ltlb_booking_cancelled', $appointment_id, $fee_calc );
        
        return true;
    }

    /**
     * Check if reschedule is allowed
     * 
     * @param int $appointment_id
     * @param string $new_start_datetime
     * @return true|WP_Error
     */
    public function can_reschedule( int $appointment_id, string $new_start_datetime ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_appointments';
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, service_id, start_at, created_at FROM $table WHERE id = %d",
            $appointment_id
        ) );
        
        if ( ! $appointment ) {
            return new WP_Error( 'not_found', __( 'Appointment not found', 'ltl-bookings' ) );
        }
        
        // Get policy
        $policy = $this->get_reschedule_policy( intval( $appointment->service_id ) );
        
        // Check reschedule limit
        if ( $policy['max_reschedules'] > 0 ) {
            $reschedule_count = $this->get_reschedule_count( $appointment_id );
            if ( $reschedule_count >= $policy['max_reschedules'] ) {
                return new WP_Error( 'limit_reached', sprintf(
                    __( 'Maximum reschedule limit (%d) reached', 'ltl-bookings' ),
                    $policy['max_reschedules']
                ) );
            }
        }
        
        // Check minimum notice
        $hours_until = ( strtotime( $appointment->start_at ) - time() ) / 3600;
        if ( $hours_until < $policy['min_notice_hours'] ) {
            return new WP_Error( 'too_late', sprintf(
                __( 'Rescheduling requires at least %d hours notice', 'ltl-bookings' ),
                $policy['min_notice_hours']
            ) );
        }
        
        // Check reschedule window
        if ( $policy['reschedule_window_days'] > 0 ) {
            $max_days_ahead = $policy['reschedule_window_days'];
            $new_timestamp = strtotime( $new_start_datetime );
            $max_timestamp = time() + ( $max_days_ahead * 86400 );
            
            if ( $new_timestamp > $max_timestamp ) {
                return new WP_Error( 'too_far', sprintf(
                    __( 'New date must be within %d days', 'ltl-bookings' ),
                    $max_days_ahead
                ) );
            }
        }
        
        return true;
    }

    /**
     * Reschedule booking
     * 
     * @param int $appointment_id
     * @param string $new_start_datetime
     * @return bool|WP_Error
     */
    public function reschedule_booking( int $appointment_id, string $new_start_datetime ) {
        global $wpdb;
        
        // Validate
        $can_reschedule = $this->can_reschedule( $appointment_id, $new_start_datetime );
        if ( is_wp_error( $can_reschedule ) ) {
            return $can_reschedule;
        }
        
        $appointment_table = $wpdb->prefix . 'ltlb_appointments';
        
        // Get current appointment details
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT start_at, end_at, service_id FROM $appointment_table WHERE id = %d",
            $appointment_id
        ) );
        
        if ( ! $appointment ) {
            return new WP_Error( 'not_found', __( 'Appointment not found', 'ltl-bookings' ) );
        }
        
        // Calculate new end time (maintain duration)
        $duration_seconds = strtotime( $appointment->end_at ) - strtotime( $appointment->start_at );
        $new_end_datetime = date( 'Y-m-d H:i:s', strtotime( $new_start_datetime ) + $duration_seconds );
        
        // Update appointment
        $wpdb->update(
            $appointment_table,
            [
                'start_at' => $new_start_datetime,
                'end_at' => $new_end_datetime,
                'updated_at' => current_time( 'mysql' )
            ],
            [ 'id' => $appointment_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        // Log reschedule
        $log_table = $wpdb->prefix . 'ltlb_reschedule_log';
        $wpdb->insert( $log_table, [
            'appointment_id' => $appointment_id,
            'old_start' => $appointment->start_at,
            'new_start' => $new_start_datetime,
            'rescheduled_at' => current_time( 'mysql' )
        ], [ '%d', '%s', '%s', '%s' ] );
        
        // Trigger notifications
        do_action( 'ltlb_booking_rescheduled', $appointment_id, $appointment->start_at, $new_start_datetime );
        
        return true;
    }

    /**
     * Get cancellation policy for service
     * 
     * @param int $service_id
     * @return array Policy rules
     */
    private function get_cancellation_policy( int $service_id ): array {
        // TODO: Load from database per service/room
        // For now, return default policy
        
        return [
            'free_cancellation_hours' => 24, // Free cancel if >24h before
            'cancellation_fee_percent' => 50, // 50% fee if <24h before
            'no_show_fee_percent' => 100, // 100% fee for no-show
            'refund_window_days' => 14 // Refund processed within 14 days
        ];
    }

    /**
     * Get reschedule policy for service
     * 
     * @param int $service_id
     * @return array Policy rules
     */
    private function get_reschedule_policy( int $service_id ): array {
        // TODO: Load from database per service/room
        
        return [
            'max_reschedules' => 2, // Max 2 reschedules per booking
            'min_notice_hours' => 12, // At least 12h before original time
            'reschedule_window_days' => 30, // Can reschedule within 30 days
            'reschedule_fee_cents' => 0 // No fee for reschedules
        ];
    }

    /**
     * Get reschedule count for appointment
     * 
     * @param int $appointment_id
     * @return int Count
     */
    private function get_reschedule_count( int $appointment_id ): int {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'ltlb_reschedule_log';
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $log_table WHERE appointment_id = %d",
            $appointment_id
        ) );
        
        return intval( $count );
    }

    /**
     * Get policy display for frontend
     * 
     * @param int $service_id
     * @return string HTML policy text
     */
    public function get_policy_display( int $service_id ): string {
        $cancel_policy = $this->get_cancellation_policy( $service_id );
        $reschedule_policy = $this->get_reschedule_policy( $service_id );
        
        $output = '<div class="ltlb-booking-policy">';
        $output .= '<h4>' . esc_html__( 'Booking Policy', 'ltl-bookings' ) . '</h4>';
        
        // Cancellation
        $output .= '<p><strong>' . esc_html__( 'Cancellation:', 'ltl-bookings' ) . '</strong><br>';
        $output .= sprintf(
            esc_html__( 'Free cancellation up to %d hours before. After that, %d%% cancellation fee applies. No-show: %d%% fee.', 'ltl-bookings' ),
            $cancel_policy['free_cancellation_hours'],
            $cancel_policy['cancellation_fee_percent'],
            $cancel_policy['no_show_fee_percent']
        );
        $output .= '</p>';
        
        // Reschedule
        $output .= '<p><strong>' . esc_html__( 'Rescheduling:', 'ltl-bookings' ) . '</strong><br>';
        $output .= sprintf(
            esc_html__( 'Up to %d reschedules allowed. Minimum %d hours notice required.', 'ltl-bookings' ),
            $reschedule_policy['max_reschedules'],
            $reschedule_policy['min_notice_hours']
        );
        $output .= '</p>';
        
        $output .= '</div>';
        
        return $output;
    }
}
