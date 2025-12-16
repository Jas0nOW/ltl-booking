<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Waitlist Engine
 * 
 * Manages waiting lists for fully booked time slots.
 * Auto-offers spots when cancellations occur.
 */
class LTLB_WaitlistEngine {

    /**
     * Add customer to waitlist
     */
    public static function add( int $service_id, string $date, string $time, int $customer_id, array $preferences = [] ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_waitlist';

        $wpdb->insert( $table, [
            'service_id' => $service_id,
            'customer_id' => $customer_id,
            'preferred_date' => $date,
            'preferred_time' => $time,
            'preferences' => wp_json_encode( $preferences ),
            'status' => 'waiting',
            'created_at' => current_time( 'mysql' ),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Process waitlist when slot becomes available
     */
    public static function process_availability( int $service_id, string $date, string $time ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_waitlist';

        // Find first waiting customer matching this slot
        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE service_id = %d 
             AND preferred_date = %s 
             AND preferred_time = %s 
             AND status = 'waiting'
             ORDER BY created_at ASC
             LIMIT 1",
            $service_id,
            $date,
            $time
        ));

        if ( ! $entry ) {
            return;
        }

        // Create offer with expiration (24h)
        $offer_expires = date( 'Y-m-d H:i:s', strtotime( '+24 hours' ) );
        
        $wpdb->update( $table, [
            'status' => 'offered',
            'offer_expires_at' => $offer_expires,
            'offered_at' => current_time( 'mysql' ),
        ], [ 'id' => $entry->id ] );

        // Send notification
        self::send_offer_notification( $entry );

        // Schedule expiration check
        wp_schedule_single_event( strtotime( $offer_expires ), 'ltlb_waitlist_expire_offer', [
            'waitlist_id' => $entry->id,
        ]);
    }

    /**
     * Accept offer and create appointment
     */
    public static function accept_offer( int $waitlist_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_waitlist';

        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'offered'",
            $waitlist_id
        ));

        if ( ! $entry ) {
            return [ 'success' => false, 'message' => __('Offer not found or expired', 'ltl-bookings') ];
        }

        if ( strtotime( $entry->offer_expires_at ) < time() ) {
            $wpdb->update( $table, [ 'status' => 'expired' ], [ 'id' => $waitlist_id ] );
            return [ 'success' => false, 'message' => __('Offer has expired', 'ltl-bookings') ];
        }

        // Create appointment
        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( $entry->service_id );

        if ( ! $service ) {
            return [ 'success' => false, 'message' => __('Service not found', 'ltl-bookings') ];
        }

        $appointment_repo = new LTLB_AppointmentRepository();
        $appointment_id = $appointment_repo->create([
            'customer_id' => $entry->customer_id,
            'service_id' => $entry->service_id,
            'start_time' => $entry->preferred_date . ' ' . $entry->preferred_time,
            'end_time' => date( 'Y-m-d H:i:s', strtotime( $entry->preferred_date . ' ' . $entry->preferred_time . ' +' . $service->duration . ' minutes' ) ),
            'status' => 'confirmed',
            'source' => 'waitlist',
        ]);

        $wpdb->update( $table, [
            'status' => 'converted',
            'appointment_id' => $appointment_id,
            'converted_at' => current_time( 'mysql' ),
        ], [ 'id' => $waitlist_id ] );

        return [ 'success' => true, 'appointment_id' => $appointment_id ];
    }

    /**
     * Decline offer
     */
    public static function decline_offer( int $waitlist_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_waitlist';

        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $waitlist_id
        ));

        if ( $entry ) {
            $wpdb->update( $table, [ 'status' => 'declined' ], [ 'id' => $waitlist_id ] );

            // Offer to next person in line
            self::process_availability( $entry->service_id, $entry->preferred_date, $entry->preferred_time );
        }
    }

    /**
     * Expire old offers
     */
    public static function expire_offer( int $waitlist_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_waitlist';

        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'offered'",
            $waitlist_id
        ));

        if ( $entry && strtotime( $entry->offer_expires_at ) < time() ) {
            $wpdb->update( $table, [ 'status' => 'expired' ], [ 'id' => $waitlist_id ] );

            // Offer to next person
            self::process_availability( $entry->service_id, $entry->preferred_date, $entry->preferred_time );
        }
    }

    /**
     * Send offer notification
     */
    private static function send_offer_notification( object $entry ): void {
        $customer_repo = new LTLB_Customer_Repository();
        $customer = $customer_repo->find( $entry->customer_id );

        if ( ! $customer ) {
            return;
        }

        $accept_url = add_query_arg([
            'action' => 'ltlb_accept_waitlist_offer',
            'id' => $entry->id,
            'token' => wp_hash( $entry->id . $entry->customer_id ),
        ], home_url());

        $message = sprintf(
            __('Good news! A spot has opened up for your requested booking on %s at %s. You have 24 hours to accept this offer.', 'ltl-bookings'),
            $entry->preferred_date,
            $entry->preferred_time
        );

        $message .= "\n\n" . sprintf( __('Accept offer: %s', 'ltl-bookings'), $accept_url );

        wp_mail(
            $customer->email,
            __('Booking Spot Available', 'ltl-bookings'),
            $message,
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );
    }

    /**
     * Get waitlist for a service
     */
    public static function get_by_service( int $service_id, string $status = 'waiting' ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_waitlist';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE service_id = %d AND status = %s ORDER BY created_at ASC",
            $service_id,
            $status
        ));
    }
}
