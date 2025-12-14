<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Booking Status Management
 * 
 * Centralized status definitions and utilities
 */
class LTLB_BookingStatus {

    /**
     * Get all available statuses
     */
    public static function get_statuses(): array {
        return [
            'pending' => __( 'Pending', 'ltl-bookings' ),
            'confirmed' => __( 'Confirmed', 'ltl-bookings' ),
            'cancelled' => __( 'Cancelled', 'ltl-bookings' ),
            'completed' => __( 'Completed', 'ltl-bookings' ),
            'paid' => __( 'Paid', 'ltl-bookings' ),
            'no-show' => __( 'No Show', 'ltl-bookings' ),
        ];
    }

    /**
     * Get translated label for a status
     */
    public static function get_label( string $status ): string {
        $statuses = self::get_statuses();
        return $statuses[ $status ] ?? ucfirst( $status );
    }

    /**
     * Render status badge HTML
     */
    public static function render_badge( string $status ): string {
        $label = self::get_label( $status );
        $class = 'ltlb-status-badge status-' . esc_attr( $status );
        return '<span class="' . $class . '">' . esc_html( $label ) . '</span>';
    }

    /**
     * Get status color (for calendar, reports, etc.)
     */
    public static function get_color( string $status ): string {
        $colors = [
            'pending' => '#fef3c7',
            'confirmed' => '#d1fae5',
            'cancelled' => '#fee2e2',
            'completed' => '#e0e7ff',
            'paid' => '#d1fae5',
            'no-show' => '#fecaca',
        ];
        return $colors[ $status ] ?? '#f3f4f6';
    }

    /**
     * Get status text color
     */
    public static function get_text_color( string $status ): string {
        $colors = [
            'pending' => '#92400e',
            'confirmed' => '#065f46',
            'cancelled' => '#991b1b',
            'completed' => '#3730a3',
            'paid' => '#065f46',
            'no-show' => '#991b1b',
        ];
        return $colors[ $status ] ?? '#1f2937';
    }

    /**
     * Check if status is valid
     */
    public static function is_valid( string $status ): bool {
        return array_key_exists( $status, self::get_statuses() );
    }

    /**
     * Get allowed status transitions
     */
    public static function get_allowed_transitions( string $current_status ): array {
        $transitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'cancelled', 'no-show'],
            'cancelled' => ['pending'], // Allow re-activation
            'completed' => [],
            'paid' => ['confirmed', 'completed', 'cancelled'],
            'no-show' => [],
        ];

        return $transitions[ $current_status ] ?? [];
    }

    /**
     * Check if status transition is allowed
     */
    public static function can_transition( string $from, string $to ): bool {
        $allowed = self::get_allowed_transitions( $from );
        return in_array( $to, $allowed, true );
    }
}
