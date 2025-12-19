<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * ICS Calendar Export
 */
class LTLB_ICS_Export {

    /**
     * Generate ICS feed for appointments
     */
    public static function generate_ics( array $filters = [] ): string {
        $appt_repo = new LTLB_AppointmentRepository();
        $service_repo = new LTLB_ServiceRepository();
        
        $appointments = $appt_repo->get_all( $filters );
        
        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//LazyBookings//NONSGML v1.0//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:LazyBookings\r\n";
        $ics .= "X-WR-TIMEZONE:" . wp_timezone_string() . "\r\n";
        
        foreach ( $appointments as $appt ) {
            $service = $service_repo->get_by_id( intval($appt['service_id']) );
            $service_name = $service ? $service['name'] : __( 'Service', 'ltl-bookings' );
            
            $status = $appt['status'] ?? 'pending';
            $customer_name = $appt['customer_name'] ?? '';
            
            $summary = sprintf(
                '%s - %s',
                $service_name,
                $customer_name
            );
            
            $description = sprintf(
                '%s: %s\n%s: %s\n%s: %s',
                __( 'Customer', 'ltl-bookings' ),
                $customer_name,
                __( 'Email', 'ltl-bookings' ),
                $appt['customer_email'] ?? '',
                __( 'Status', 'ltl-bookings' ),
                LTLB_BookingStatus::get_label( $status )
            );
            
            $start = self::format_ics_datetime( (string) ( $appt['start_at'] ?? '' ) );
            $end = self::format_ics_datetime( (string) ( $appt['end_at'] ?? '' ) );
            $created = gmdate( 'Ymd\THis\Z' );
            $uid = 'ltlb-' . $appt['id'] . '@' . parse_url( home_url(), PHP_URL_HOST );
            
            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:" . $uid . "\r\n";
            $ics .= "DTSTAMP:" . $created . "\r\n";
            $ics .= "DTSTART:" . $start . "\r\n";
            $ics .= "DTEND:" . $end . "\r\n";
            $ics .= "SUMMARY:" . self::escape_ics_text( $summary ) . "\r\n";
            $ics .= "DESCRIPTION:" . self::escape_ics_text( $description ) . "\r\n";
            $ics .= "STATUS:" . self::get_ics_status( $status ) . "\r\n";
            
            if ( ! empty($appt['customer_email']) ) {
                $ics .= "ATTENDEE;CN=" . self::escape_ics_text( $customer_name ) . ":mailto:" . $appt['customer_email'] . "\r\n";
            }
            
            $ics .= "END:VEVENT\r\n";
        }
        
        $ics .= "END:VCALENDAR\r\n";
        
        return $ics;
    }

    /**
     * Format datetime for ICS (YYYYMMDDTHHMMSSZ)
     */
    private static function format_ics_datetime( string $datetime ): string {
        $datetime = trim( $datetime );
        if ( $datetime === '' ) {
            return gmdate( 'Ymd\THis\Z' );
        }

        // Appointment times are stored as UTC MySQL DATETIME.
        if ( class_exists( 'LTLB_DateTime' ) ) {
            $dt = LTLB_DateTime::parse_utc_mysql( $datetime );
            if ( $dt ) {
                return $dt->format( 'Ymd\THis\Z' );
            }
        }

        try {
            $dt = new DateTimeImmutable( $datetime, new DateTimeZone( 'UTC' ) );
            return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
        } catch ( Exception $e ) {
            return gmdate( 'Ymd\THis\Z' );
        }
    }

    /**
     * Escape text for ICS format
     */
    private static function escape_ics_text( string $text ): string {
        // Ensure text is a string (prevent deprecation warnings in PHP 8.1+)
        if ( ! is_string( $text ) ) {
            $text = '';
        }
        $text = str_replace( '\\', '\\\\', (string) $text );
        $text = str_replace( ',', '\\,', (string) $text );
        $text = str_replace( ';', '\\;', (string) $text );
        $text = str_replace( "\n", '\\n', (string) $text );
        return $text;
    }

    /**
     * Convert booking status to ICS status
     */
    private static function get_ics_status( string $status ): string {
        $map = [
            'confirmed' => 'CONFIRMED',
            'pending' => 'TENTATIVE',
            'cancelled' => 'CANCELLED',
            'completed' => 'CONFIRMED',
            'paid' => 'CONFIRMED',
        ];
        
        return $map[ $status ] ?? 'TENTATIVE';
    }

    /**
     * Send ICS file as download
     */
    public static function download_ics( array $filters = [], string $filename = 'calendar.ics' ): void {
        $ics_content = self::generate_ics( $filters );
        
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $ics_content;
        exit;
    }

    /**
     * Get ICS feed URL with auth token
     */
    public static function get_feed_url( $user_id = null ): string {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        $token = self::get_or_create_feed_token( $user_id );
        
        return add_query_arg( [
            'ltlb_ics_feed' => '1',
            'token' => $token,
        ], home_url() );
    }

    /**
     * Get or create feed auth token for user
     */
    private static function get_or_create_feed_token( int $user_id ): string {
        $token = get_user_meta( $user_id, 'ltlb_ics_token', true );
        
        if ( empty( $token ) ) {
            $token = wp_generate_password( 32, false );
            update_user_meta( $user_id, 'ltlb_ics_token', $token );
        }
        
        return $token;
    }

    /**
     * Verify feed token
     */
    public static function verify_feed_token( string $token ): ?int {
        global $wpdb;
        
        $user_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ltlb_ics_token' AND meta_value = %s LIMIT 1",
            $token
        ) );
        
        return $user_id ? intval($user_id) : null;
    }
}
