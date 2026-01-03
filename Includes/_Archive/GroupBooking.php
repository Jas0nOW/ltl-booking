<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Group Bookings
 * 
 * Manages multi-participant bookings with individual check-in tracking.
 * Useful for events, classes, workshops, tours.
 */
class LTLB_GroupBooking {

    /**
     * Create a group booking
     */
    public static function create( int $service_id, string $start_time, array $participants, array $metadata = [] ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_bookings';

        $wpdb->insert( $table, [
            'service_id' => $service_id,
            'start_time' => $start_time,
            'participant_count' => count( $participants ),
            'metadata' => wp_json_encode( $metadata ),
            'status' => 'confirmed',
            'created_at' => current_time( 'mysql' ),
        ]);

        $group_id = (int) $wpdb->insert_id;

        // Add participants
        foreach ( $participants as $participant ) {
            self::add_participant( $group_id, $participant );
        }

        return $group_id;
    }

    /**
     * Add participant to group
     */
    public static function add_participant( int $group_id, array $participant ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_participants';

        $wpdb->insert( $table, [
            'group_booking_id' => $group_id,
            'customer_id' => $participant['customer_id'] ?? null,
            'name' => sanitize_text_field( $participant['name'] ?? '' ),
            'email' => sanitize_email( $participant['email'] ?? '' ),
            'phone' => sanitize_text_field( $participant['phone'] ?? '' ),
            'status' => 'registered',
            'created_at' => current_time( 'mysql' ),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Check in participant
     */
    public static function check_in( int $participant_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_participants';

        return (bool) $wpdb->update( $table, [
            'status' => 'checked_in',
            'checked_in_at' => current_time( 'mysql' ),
        ], [ 'id' => $participant_id ] );
    }

    /**
     * Mark participant as no-show
     */
    public static function mark_no_show( int $participant_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_participants';

        return (bool) $wpdb->update( $table, [
            'status' => 'no_show',
        ], [ 'id' => $participant_id ] );
    }

    /**
     * Get all participants for a group
     */
    public static function get_participants( int $group_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_participants';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE group_booking_id = %d ORDER BY created_at ASC",
            $group_id
        ));
    }

    /**
     * Get group booking details
     */
    public static function get( int $group_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_bookings';

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $group_id
        ));
    }

    /**
     * Get check-in statistics
     */
    public static function get_check_in_stats( int $group_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_participants';

        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
                SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as pending
             FROM {$table}
             WHERE group_booking_id = %d",
            $group_id
        ), ARRAY_A );

        return $stats ?: [
            'total' => 0,
            'checked_in' => 0,
            'no_show' => 0,
            'pending' => 0,
        ];
    }

    /**
     * Cancel group booking
     */
    public static function cancel( int $group_id, string $reason = '' ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_bookings';

        return (bool) $wpdb->update( $table, [
            'status' => 'cancelled',
            'cancelled_at' => current_time( 'mysql' ),
            'cancel_reason' => $reason,
        ], [ 'id' => $group_id ] );
    }

    /**
     * Get all group bookings for a service
     */
    public static function get_by_service( int $service_id, string $status = 'confirmed' ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_bookings';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE service_id = %d AND status = %s ORDER BY start_time DESC",
            $service_id,
            $status
        ));
    }

    /**
     * Get upcoming group bookings
     */
    public static function get_upcoming( int $limit = 10 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_group_bookings';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE status = 'confirmed' 
             AND start_time >= NOW()
             ORDER BY start_time ASC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Send group confirmation emails
     */
    public static function send_confirmations( int $group_id ): void {
        $participants = self::get_participants( $group_id );
        $group = self::get( $group_id );

        if ( ! $group ) {
            return;
        }

        $service_repo = new LTLB_Service_Repository();
        $service = $service_repo->find( $group->service_id );

        foreach ( $participants as $participant ) {
            if ( empty( $participant->email ) ) {
                continue;
            }

            $message = sprintf(
                __('Your registration for %s on %s is confirmed.', 'ltl-bookings'),
                $service ? $service->name : __('Event', 'ltl-bookings'),
                date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $group->start_time ) )
            );

            wp_mail(
                $participant->email,
                __('Group Booking Confirmation', 'ltl-bookings'),
                $message,
                [ 'Content-Type: text/plain; charset=UTF-8' ]
            );
        }
    }
}
