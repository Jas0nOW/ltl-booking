<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Booking Status State Machine
 * 
 * Defines valid booking statuses and allowed transitions.
 * Centralized status management for both Hotel and Service modes.
 */
class LTLB_BookingStatus {

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_NO_SHOW = 'no-show';
    const STATUS_COMPLETED = 'completed';

    /**
     * Get all valid statuses
     */
    public static function get_all_statuses(): array {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED,
            self::STATUS_NO_SHOW,
            self::STATUS_COMPLETED,
        ];
    }

    /**
     * Get valid state transitions
     * 
     * Format: 'from_status' => ['allowed', 'target', 'statuses']
     */
    public static function get_transitions(): array {
        return [
            self::STATUS_DRAFT => [
                self::STATUS_PENDING,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_PENDING => [
                self::STATUS_CONFIRMED,
                self::STATUS_PAID,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_CONFIRMED => [
                self::STATUS_PAID,
                self::STATUS_CANCELLED,
                self::STATUS_NO_SHOW,
                self::STATUS_COMPLETED,
            ],
            self::STATUS_PAID => [
                self::STATUS_CANCELLED,
                self::STATUS_REFUNDED,
                self::STATUS_NO_SHOW,
                self::STATUS_COMPLETED,
            ],
            self::STATUS_CANCELLED => [
                self::STATUS_PENDING, // Re-activation allowed
            ],
            self::STATUS_REFUNDED => [
                // Terminal state - no transitions allowed
            ],
            self::STATUS_NO_SHOW => [
                // Terminal state - no transitions allowed
            ],
            self::STATUS_COMPLETED => [
                // Terminal state - no transitions allowed
            ],
        ];
    }

    /**
     * Check if a status transition is valid
     */
    public static function can_transition( string $from_status, string $to_status ): bool {
        $from_status = sanitize_key( $from_status );
        $to_status = sanitize_key( $to_status );

        // Same status is always allowed
        if ( $from_status === $to_status ) {
            return true;
        }

        // Check if both statuses are valid
        $all_statuses = self::get_all_statuses();
        if ( ! in_array( $from_status, $all_statuses, true ) || ! in_array( $to_status, $all_statuses, true ) ) {
            return false;
        }

        // Get allowed transitions
        $transitions = self::get_transitions();
        $allowed_targets = $transitions[ $from_status ] ?? [];

        return in_array( $to_status, $allowed_targets, true );
    }

    /**
     * Validate and perform status transition
     * 
     * @param int $appointment_id Appointment ID
     * @param string $to_status Target status
     * @param string $reason Reason for transition (for audit log)
     * @return array ['success' => bool, 'message' => string, 'old_status' => string, 'new_status' => string]
     */
    public static function transition( int $appointment_id, string $to_status, string $reason = '' ): array {
        global $wpdb;

        $to_status = sanitize_key( $to_status );

        // Get current appointment
        $table = $wpdb->prefix . 'lazy_appointments';
        $appointment = $wpdb->get_row( 
            $wpdb->prepare( "SELECT id, status FROM {$table} WHERE id = %d", $appointment_id ),
            ARRAY_A
        );

        if ( ! $appointment ) {
            return [
                'success' => false,
                'message' => __( 'Appointment not found.', 'ltl-bookings' ),
                'old_status' => '',
                'new_status' => '',
            ];
        }

        $old_status = sanitize_key( (string) ( $appointment['status'] ?? '' ) );

        // Check if transition is allowed
        if ( ! self::can_transition( $old_status, $to_status ) ) {
            return [
                'success' => false,
                'message' => sprintf(
                    __( 'Cannot transition from "%s" to "%s". Invalid state change.', 'ltl-bookings' ),
                    $old_status,
                    $to_status
                ),
                'old_status' => $old_status,
                'new_status' => $to_status,
            ];
        }

        // Perform transition
        $updated = $wpdb->update(
            $table,
            [
                'status' => $to_status,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $appointment_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            return [
                'success' => false,
                'message' => __( 'Failed to update appointment status.', 'ltl-bookings' ),
                'old_status' => $old_status,
                'new_status' => $to_status,
            ];
        }

        // Log transition
        if ( class_exists( 'LTLB_AuditLog' ) ) {
            LTLB_AuditLog::log(
                'booking_status_changed',
                $appointment_id,
                sprintf(
                    'Status changed: %s → %s%s',
                    $old_status,
                    $to_status,
                    $reason ? ' (' . $reason . ')' : ''
                )
            );
        }

        // Fire action hook for custom logic
        do_action( 'ltlb_booking_status_changed', $appointment_id, $old_status, $to_status, $reason );

        return [
            'success' => true,
            'message' => sprintf(
                __( 'Status changed from "%s" to "%s".', 'ltl-bookings' ),
                $old_status,
                $to_status
            ),
            'old_status' => $old_status,
            'new_status' => $to_status,
        ];
    }

    /**
     * Get human-readable status label
     */
    public static function get_label( string $status ): string {
        $status = sanitize_key( $status );

        $labels = [
            self::STATUS_DRAFT => __( 'Draft', 'ltl-bookings' ),
            self::STATUS_PENDING => __( 'Pending', 'ltl-bookings' ),
            self::STATUS_CONFIRMED => __( 'Confirmed', 'ltl-bookings' ),
            self::STATUS_PAID => __( 'Paid', 'ltl-bookings' ),
            self::STATUS_CANCELLED => __( 'Cancelled', 'ltl-bookings' ),
            self::STATUS_REFUNDED => __( 'Refunded', 'ltl-bookings' ),
            self::STATUS_NO_SHOW => __( 'No-Show', 'ltl-bookings' ),
            self::STATUS_COMPLETED => __( 'Completed', 'ltl-bookings' ),
        ];

        return $labels[ $status ] ?? ucfirst( $status );
    }

    /**
     * Get status color for UI
     */
    public static function get_color( string $status ): string {
        $status = sanitize_key( $status );

        $colors = [
            self::STATUS_DRAFT => '#999999',
            self::STATUS_PENDING => '#f0ad4e',
            self::STATUS_CONFIRMED => '#5bc0de',
            self::STATUS_PAID => '#5cb85c',
            self::STATUS_CANCELLED => '#d9534f',
            self::STATUS_REFUNDED => '#f0ad4e',
            self::STATUS_NO_SHOW => '#d9534f',
            self::STATUS_COMPLETED => '#5cb85c',
        ];

        return $colors[ $status ] ?? '#999999';
    }

    /**
     * Get status text color for UI
     */
    public static function get_text_color( string $status ): string {
        $status = sanitize_key( $status );

        $colors = [
            self::STATUS_DRAFT => '#666666',
            self::STATUS_PENDING => '#8a6d3b',
            self::STATUS_CONFIRMED => '#31708f',
            self::STATUS_PAID => '#3c763d',
            self::STATUS_CANCELLED => '#a94442',
            self::STATUS_REFUNDED => '#8a6d3b',
            self::STATUS_NO_SHOW => '#a94442',
            self::STATUS_COMPLETED => '#3c763d',
        ];

        return $colors[ $status ] ?? '#666666';
    }

    /**
     * Render status badge HTML
     */
    public static function render_badge( string $status ): string {
        $status = sanitize_key( $status );
        $label = self::get_label( $status );
        $class = 'ltlb-status-badge status-' . esc_attr( $status );
        return '<span class="' . $class . '">' . esc_html( $label ) . '</span>';
    }

    /**
     * Get allowed next statuses for a booking
     */
    public static function get_next_statuses( int $appointment_id ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'lazy_appointments';
        $current_status = $wpdb->get_var(
            $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $appointment_id )
        );

        if ( ! $current_status ) {
            return [];
        }

        $current_status = sanitize_key( $current_status );
        $transitions = self::get_transitions();

        return $transitions[ $current_status ] ?? [];
    }
}
