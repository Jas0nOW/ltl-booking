<?php
/**
 * Waitlist Engine
 * 
 * Manages waiting lists for fully booked slots.
 * Automatically offers slots to waitlisted customers when bookings are cancelled.
 * Includes timeout mechanism and notifications.
 *
 * @package LTL_Bookings
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LTLB_Waitlist_Engine {
    
    /**
     * Offer timeout in seconds (24 hours)
     */
    private const OFFER_TIMEOUT = 86400;
    
    /**
     * Initialize waitlist system
     */
    public static function init(): void {
        // Hook into booking cancellation
        add_action( 'ltlb_booking_cancelled', [ __CLASS__, 'on_booking_cancelled' ], 10, 1 );
        add_action( 'ltlb_booking_deleted', [ __CLASS__, 'on_booking_deleted' ], 10, 1 );
        
        // Cron for offer expiration
        add_action( 'ltlb_expire_waitlist_offer', [ __CLASS__, 'expire_offer' ], 10, 1 );
        
        // AJAX handlers
        add_action( 'wp_ajax_ltlb_join_waitlist', [ __CLASS__, 'ajax_join_waitlist' ] );
        add_action( 'wp_ajax_nopriv_ltlb_join_waitlist', [ __CLASS__, 'ajax_join_waitlist' ] );
        add_action( 'wp_ajax_ltlb_accept_waitlist_offer', [ __CLASS__, 'ajax_accept_offer' ] );
        add_action( 'wp_ajax_nopriv_ltlb_accept_waitlist_offer', [ __CLASS__, 'ajax_accept_offer' ] );
        add_action( 'wp_ajax_ltlb_decline_waitlist_offer', [ __CLASS__, 'ajax_decline_offer' ] );
        add_action( 'wp_ajax_nopriv_ltlb_decline_waitlist_offer', [ __CLASS__, 'ajax_decline_offer' ] );
        
        // Shortcode for waitlist status
        add_shortcode( 'ltlb_waitlist_status', [ __CLASS__, 'render_waitlist_status' ] );
    }
    
    /**
     * Add customer to waitlist
     *
     * @param int $service_id Service/Room ID
     * @param string $date_start Desired date/time
     * @param int $customer_id Customer ID
     * @param array $metadata Additional booking data
     * @return int|false Waitlist entry ID or false on failure
     */
    public static function add_to_waitlist( int $service_id, string $date_start, int $customer_id, array $metadata = [] ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_waitlist';
        
        // Check if already on waitlist for this slot
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE service_id = %d AND date_start = %s AND customer_id = %d AND status = 'waiting'",
            $service_id, $date_start, $customer_id
        ) );
        
        if ( $existing ) {
            return false; // Already on waitlist
        }
        
        // Insert waitlist entry
        $result = $wpdb->insert( $table, [
            'service_id' => $service_id,
            'customer_id' => $customer_id,
            'date_start' => $date_start,
            'metadata' => wp_json_encode( $metadata ),
            'status' => 'waiting',
            'created_at' => current_time( 'mysql' )
        ], [ '%d', '%d', '%s', '%s', '%s', '%s' ] );
        
        if ( ! $result ) {
            return false;
        }
        
        $entry_id = $wpdb->insert_id;
        
        // Send confirmation email
        self::send_waitlist_confirmation( $entry_id );
        
        do_action( 'ltlb_waitlist_joined', $entry_id, $service_id, $customer_id );
        
        return $entry_id;
    }
    
    /**
     * Handle booking cancellation - offer slot to waitlist
     */
    public static function on_booking_cancelled( int $booking_id ): void {
        $booking = ( new LTLB_AppointmentRepository() )->get_by_id( $booking_id );
        
        if ( ! $booking ) {
            return;
        }
        
        $service_id = intval( $booking['service_id'] ?? 0 );
        $date_start = $booking['date_start'] ?? '';
        
        if ( ! $service_id || ! $date_start ) {
            return;
        }
        
        self::offer_slot( $service_id, $date_start );
    }
    
    /**
     * Handle booking deletion
     */
    public static function on_booking_deleted( int $booking_id ): void {
        self::on_booking_cancelled( $booking_id );
    }
    
    /**
     * Offer slot to next person on waitlist
     *
     * @param int $service_id Service/Room ID
     * @param string $date_start Date/time slot
     */
    private static function offer_slot( int $service_id, string $date_start ): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_waitlist';
        
        // Find next waiting person
        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE service_id = %d AND date_start = %s AND status = 'waiting' ORDER BY created_at ASC LIMIT 1",
            $service_id, $date_start
        ), ARRAY_A );
        
        if ( ! $entry ) {
            return; // No one waiting
        }
        
        $entry_id = intval( $entry['id'] );
        
        // Update status to offered
        $wpdb->update( $table, [
            'status' => 'offered',
            'offered_at' => current_time( 'mysql' ),
            'expires_at' => date( 'Y-m-d H:i:s', time() + self::OFFER_TIMEOUT )
        ], [ 'id' => $entry_id ], [ '%s', '%s', '%s' ], [ '%d' ] );
        
        // Schedule expiration
        wp_schedule_single_event( time() + self::OFFER_TIMEOUT, 'ltlb_expire_waitlist_offer', [ $entry_id ] );
        
        // Send offer email
        self::send_offer_email( $entry_id );
        
        do_action( 'ltlb_waitlist_offer_sent', $entry_id, $service_id, $date_start );
    }
    
    /**
     * Accept waitlist offer
     *
     * @param int $entry_id Waitlist entry ID
     * @param string $token Security token
     * @return int|false Booking ID or false on failure
     */
    public static function accept_offer( int $entry_id, string $token ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_waitlist';
        
        // Verify entry and token
        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'offered'",
            $entry_id
        ), ARRAY_A );
        
        if ( ! $entry ) {
            return false;
        }
        
        // Check token
        $expected_token = self::generate_token( $entry_id );
        if ( ! hash_equals( $expected_token, $token ) ) {
            return false;
        }
        
        // Check expiration
        $expires_at = $entry['expires_at'] ?? '';
        if ( strtotime( $expires_at ) < time() ) {
            self::expire_offer( $entry_id );
            return false;
        }
        
        // Create booking
        $metadata = json_decode( $entry['metadata'] ?? '{}', true );
        
        $booking_data = [
            'service_id' => intval( $entry['service_id'] ),
            'customer_id' => intval( $entry['customer_id'] ),
            'date_start' => $entry['date_start'],
            'date_end' => $metadata['date_end'] ?? '',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'notes' => 'Converted from waitlist',
        ];
        
        // Merge additional metadata
        $booking_data = array_merge( $booking_data, $metadata );
        
        $booking_repo = new LTLB_AppointmentRepository();
        $booking_id = $booking_repo->create( $booking_data );
        
        if ( ! $booking_id ) {
            return false;
        }
        
        // Update waitlist entry
        $wpdb->update( $table, [
            'status' => 'accepted',
            'booking_id' => $booking_id,
            'accepted_at' => current_time( 'mysql' )
        ], [ 'id' => $entry_id ], [ '%s', '%d', '%s' ], [ '%d' ] );
        
        do_action( 'ltlb_waitlist_accepted', $entry_id, $booking_id );
        
        return $booking_id;
    }
    
    /**
     * Decline waitlist offer
     *
     * @param int $entry_id Waitlist entry ID
     * @param string $token Security token
     * @return bool Success
     */
    public static function decline_offer( int $entry_id, string $token ): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_waitlist';
        
        // Verify entry and token
        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'offered'",
            $entry_id
        ), ARRAY_A );
        
        if ( ! $entry ) {
            return false;
        }
        
        // Check token
        $expected_token = self::generate_token( $entry_id );
        if ( ! hash_equals( $expected_token, $token ) ) {
            return false;
        }
        
        // Update status
        $wpdb->update( $table, [
            'status' => 'declined',
            'declined_at' => current_time( 'mysql' )
        ], [ 'id' => $entry_id ], [ '%s', '%s' ], [ '%d' ] );
        
        do_action( 'ltlb_waitlist_declined', $entry_id );
        
        // Offer to next person
        $service_id = intval( $entry['service_id'] );
        $date_start = $entry['date_start'];
        self::offer_slot( $service_id, $date_start );
        
        return true;
    }
    
    /**
     * Expire offer and move to next person
     *
     * @param int $entry_id Waitlist entry ID
     */
    public static function expire_offer( int $entry_id ): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_waitlist';
        
        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'offered'",
            $entry_id
        ), ARRAY_A );
        
        if ( ! $entry ) {
            return;
        }
        
        // Update status
        $wpdb->update( $table, [
            'status' => 'expired',
            'expired_at' => current_time( 'mysql' )
        ], [ 'id' => $entry_id ], [ '%s', '%s' ], [ '%d' ] );
        
        do_action( 'ltlb_waitlist_expired', $entry_id );
        
        // Offer to next person
        $service_id = intval( $entry['service_id'] );
        $date_start = $entry['date_start'];
        self::offer_slot( $service_id, $date_start );
    }
    
    /**
     * Generate security token for entry
     */
    private static function generate_token( int $entry_id ): string {
        return wp_hash( 'ltlb_waitlist_' . $entry_id . '_' . AUTH_KEY );
    }
    
    /**
     * Send waitlist confirmation email
     */
    private static function send_waitlist_confirmation( int $entry_id ): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_waitlist';
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ), ARRAY_A );
        
        if ( ! $entry ) {
            return;
        }
        
        $customer = ( new LTLB_CustomerRepository() )->get_by_id( intval( $entry['customer_id'] ) );
        $service = ( new LTLB_ServiceRepository() )->get_by_id( intval( $entry['service_id'] ) );
        
        if ( ! $customer || ! $service ) {
            return;
        }
        
        $to = $customer['email'];
        $subject = sprintf( __( 'You\'re on the waitlist for %s', 'ltl-bookings' ), $service['name'] );
        
        $message = sprintf(
            __( "Hi %s,\n\nYou've been added to the waitlist for:\n\n%s\n%s\n\nWe'll notify you immediately if this slot becomes available.\n\nBest regards", 'ltl-bookings' ),
            $customer['first_name'],
            $service['name'],
            date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['date_start'] ) )
        );
        
        LTLB_Mailer::send( $to, $subject, $message );
    }
    
    /**
     * Send offer email
     */
    private static function send_offer_email( int $entry_id ): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_waitlist';
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ), ARRAY_A );
        
        if ( ! $entry ) {
            return;
        }
        
        $customer = ( new LTLB_CustomerRepository() )->get_by_id( intval( $entry['customer_id'] ) );
        $service = ( new LTLB_ServiceRepository() )->get_by_id( intval( $entry['service_id'] ) );
        
        if ( ! $customer || ! $service ) {
            return;
        }
        
        $token = self::generate_token( $entry_id );
        $accept_url = add_query_arg( [
            'ltlb_action' => 'accept_waitlist',
            'entry_id' => $entry_id,
            'token' => $token
        ], home_url() );
        
        $decline_url = add_query_arg( [
            'ltlb_action' => 'decline_waitlist',
            'entry_id' => $entry_id,
            'token' => $token
        ], home_url() );
        
        $expires_in = intval( ( strtotime( $entry['expires_at'] ) - time() ) / 3600 );
        
        $to = $customer['email'];
        $subject = sprintf( __( 'A slot is available: %s', 'ltl-bookings' ), $service['name'] );
        
        $message = sprintf(
            __( "Hi %s,\n\nGreat news! A slot has become available:\n\n%s\n%s\n\nThis offer expires in %d hours.\n\nAccept: %s\n\nDecline: %s\n\nBest regards", 'ltl-bookings' ),
            $customer['first_name'],
            $service['name'],
            date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['date_start'] ) ),
            $expires_in,
            $accept_url,
            $decline_url
        );
        
        LTLB_Mailer::send( $to, $subject, $message );
    }
    
    /**
     * Get waitlist position
     *
     * @param int $entry_id Waitlist entry ID
     * @return int Position (1-indexed)
     */
    public static function get_position( int $entry_id ): int {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_waitlist';
        
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ), ARRAY_A );
        
        if ( ! $entry ) {
            return 0;
        }
        
        $position = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) + 1 FROM {$table} WHERE service_id = %d AND date_start = %s AND status = 'waiting' AND created_at < %s",
            $entry['service_id'], $entry['date_start'], $entry['created_at']
        ) );
        
        return intval( $position );
    }
    
    /**
     * AJAX: Join waitlist
     */
    public static function ajax_join_waitlist(): void {
        $service_id = intval( $_POST['service_id'] ?? 0 );
        $date_start = sanitize_text_field( $_POST['date_start'] ?? '' );
        $customer_id = intval( $_POST['customer_id'] ?? 0 );
        
        if ( ! $service_id || ! $date_start ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request', 'ltl-bookings' ) ] );
        }
        
        $metadata = [
            'date_end' => sanitize_text_field( $_POST['date_end'] ?? '' ),
            'guests' => intval( $_POST['guests'] ?? 1 )
        ];
        
        $entry_id = self::add_to_waitlist( $service_id, $date_start, $customer_id, $metadata );
        
        if ( $entry_id ) {
            $position = self::get_position( $entry_id );
            wp_send_json_success( [
                'message' => sprintf( __( 'You\'re #%d on the waitlist', 'ltl-bookings' ), $position ),
                'entry_id' => $entry_id,
                'position' => $position
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to join waitlist', 'ltl-bookings' ) ] );
        }
    }
    
    /**
     * AJAX: Accept offer
     */
    public static function ajax_accept_offer(): void {
        $entry_id = intval( $_POST['entry_id'] ?? 0 );
        $token = sanitize_text_field( $_POST['token'] ?? '' );
        
        $booking_id = self::accept_offer( $entry_id, $token );
        
        if ( $booking_id ) {
            wp_send_json_success( [
                'message' => __( 'Booking confirmed!', 'ltl-bookings' ),
                'booking_id' => $booking_id
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Offer expired or invalid', 'ltl-bookings' ) ] );
        }
    }
    
    /**
     * AJAX: Decline offer
     */
    public static function ajax_decline_offer(): void {
        $entry_id = intval( $_POST['entry_id'] ?? 0 );
        $token = sanitize_text_field( $_POST['token'] ?? '' );
        
        if ( self::decline_offer( $entry_id, $token ) ) {
            wp_send_json_success( [ 'message' => __( 'Offer declined', 'ltl-bookings' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Invalid request', 'ltl-bookings' ) ] );
        }
    }
    
    /**
     * Render waitlist status shortcode
     */
    public static function render_waitlist_status( $atts ): string {
        $atts = shortcode_atts( [
            'entry_id' => 0
        ], $atts );
        
        $entry_id = intval( $atts['entry_id'] );
        
        if ( ! $entry_id ) {
            return '';
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_waitlist';
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ), ARRAY_A );
        
        if ( ! $entry ) {
            return __( 'Waitlist entry not found', 'ltl-bookings' );
        }
        
        $status = $entry['status'];
        $position = self::get_position( $entry_id );
        
        $output = '<div class="ltlb-waitlist-status">';
        
        switch ( $status ) {
            case 'waiting':
                $output .= sprintf( __( 'You are #%d on the waitlist', 'ltl-bookings' ), $position );
                break;
            case 'offered':
                $expires_at = $entry['expires_at'];
                $output .= __( 'A slot is available! Check your email to accept.', 'ltl-bookings' );
                break;
            case 'accepted':
                $output .= __( 'Your booking has been confirmed!', 'ltl-bookings' );
                break;
            case 'declined':
                $output .= __( 'You declined this offer', 'ltl-bookings' );
                break;
            case 'expired':
                $output .= __( 'Your offer has expired', 'ltl-bookings' );
                break;
        }
        
        $output .= '</div>';
        
        return $output;
    }
}

// Initialize
LTLB_Waitlist_Engine::init();
