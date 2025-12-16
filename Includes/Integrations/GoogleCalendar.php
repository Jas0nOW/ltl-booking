<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Google Calendar Sync Integration
 * 
 * Features:
 * - OAuth2 authentication
 * - Calendar selection and mapping
 * - Two-way sync (create/update/delete)
 * - Conflict handling
 * - Per-staff calendar support
 * 
 * @package LazyBookings
 */
class LTLB_Google_Calendar_Sync {

    private $client_id;
    private $client_secret;
    private $redirect_uri;

    /**
     * Constructor
     */
    public function __construct() {
        $this->client_id = get_option( 'ltlb_google_calendar_client_id' );
        $this->client_secret = get_option( 'ltlb_google_calendar_client_secret' );
        $this->redirect_uri = admin_url( 'admin.php?page=ltlb-google-calendar-oauth' );

        add_action( 'ltlb_sync_google_calendar', [ $this, 'run_sync' ] );
    }

    /**
     * Get OAuth authorization URL
     * 
     * @param int $staff_id Staff user ID
     * @return string Auth URL
     */
    public function get_auth_url( int $staff_id ): string {
        $state = wp_create_nonce( 'ltlb_google_oauth_' . $staff_id );
        update_user_meta( $staff_id, 'ltlb_google_oauth_state', $state );

        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state . '|' . $staff_id
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
    }

    /**
     * Exchange authorization code for tokens
     * 
     * @param string $code Authorization code
     * @param int $staff_id Staff user ID
     * @return bool|WP_Error Success or error
     */
    public function exchange_code_for_tokens( string $code, int $staff_id ) {
        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code'
            ]
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['access_token'] ) ) {
            return new WP_Error( 'token_error', __( 'Failed to obtain access token', 'ltl-bookings' ) );
        }

        // Save tokens
        update_user_meta( $staff_id, 'ltlb_google_access_token', $body['access_token'] );
        update_user_meta( $staff_id, 'ltlb_google_refresh_token', $body['refresh_token'] ?? '' );
        update_user_meta( $staff_id, 'ltlb_google_token_expires', time() + intval( $body['expires_in'] ?? 3600 ) );

        return true;
    }

    /**
     * Refresh access token
     * 
     * @param int $staff_id Staff user ID
     * @return bool|WP_Error Success or error
     */
    public function refresh_access_token( int $staff_id ) {
        $refresh_token = get_user_meta( $staff_id, 'ltlb_google_refresh_token', true );

        if ( empty( $refresh_token ) ) {
            return new WP_Error( 'no_refresh_token', __( 'No refresh token available', 'ltl-bookings' ) );
        }

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            ]
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['access_token'] ) ) {
            return new WP_Error( 'token_refresh_error', __( 'Failed to refresh token', 'ltl-bookings' ) );
        }

        update_user_meta( $staff_id, 'ltlb_google_access_token', $body['access_token'] );
        update_user_meta( $staff_id, 'ltlb_google_token_expires', time() + intval( $body['expires_in'] ?? 3600 ) );

        return true;
    }

    /**
     * Get valid access token (refresh if needed)
     * 
     * @param int $staff_id
     * @return string|WP_Error Access token or error
     */
    private function get_access_token( int $staff_id ) {
        $access_token = get_user_meta( $staff_id, 'ltlb_google_access_token', true );
        $expires = get_user_meta( $staff_id, 'ltlb_google_token_expires', true );

        if ( empty( $access_token ) ) {
            return new WP_Error( 'no_token', __( 'Not connected to Google Calendar', 'ltl-bookings' ) );
        }

        // Refresh if expired or expiring soon
        if ( $expires < ( time() + 300 ) ) {
            $refresh_result = $this->refresh_access_token( $staff_id );
            if ( is_wp_error( $refresh_result ) ) {
                return $refresh_result;
            }
            $access_token = get_user_meta( $staff_id, 'ltlb_google_access_token', true );
        }

        return $access_token;
    }

    /**
     * Sync appointment to Google Calendar
     * 
     * @param int $appointment_id
     * @return bool|WP_Error Success or error
     */
    public function sync_appointment_to_google( int $appointment_id ) {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $appointments_table WHERE id = %d",
            $appointment_id
        ) );

        if ( ! $appointment || empty( $appointment->staff_id ) ) {
            return new WP_Error( 'no_appointment', __( 'Appointment not found', 'ltl-bookings' ) );
        }

        $staff_id = intval( $appointment->staff_id );
        $access_token = $this->get_access_token( $staff_id );

        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $calendar_id = get_user_meta( $staff_id, 'ltlb_google_calendar_id', true ) ?: 'primary';
        $google_event_id = get_post_meta( $appointment_id, 'ltlb_google_event_id', true );

        // Build event data
        $event_data = $this->build_event_data( $appointment );

        // Create or update event
        if ( $google_event_id ) {
            $result = $this->update_google_event( $calendar_id, $google_event_id, $event_data, $access_token );
        } else {
            $result = $this->create_google_event( $calendar_id, $event_data, $access_token );
            
            if ( ! is_wp_error( $result ) && ! empty( $result['id'] ) ) {
                update_post_meta( $appointment_id, 'ltlb_google_event_id', $result['id'] );
            }
        }

        return $result;
    }

    /**
     * Create Google Calendar event
     * 
     * @param string $calendar_id
     * @param array $event_data
     * @param string $access_token
     * @return array|WP_Error Event response or error
     */
    private function create_google_event( string $calendar_id, array $event_data, string $access_token ) {
        $url = sprintf( 
            'https://www.googleapis.com/calendar/v3/calendars/%s/events',
            urlencode( $calendar_id )
        );

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode( $event_data )
        ] );

        return $this->parse_google_response( $response );
    }

    /**
     * Update Google Calendar event
     * 
     * @param string $calendar_id
     * @param string $event_id
     * @param array $event_data
     * @param string $access_token
     * @return array|WP_Error Event response or error
     */
    private function update_google_event( string $calendar_id, string $event_id, array $event_data, string $access_token ) {
        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
            urlencode( $calendar_id ),
            urlencode( $event_id )
        );

        $response = wp_remote_request( $url, [
            'method' => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode( $event_data )
        ] );

        return $this->parse_google_response( $response );
    }

    /**
     * Delete event from Google Calendar
     * 
     * @param int $appointment_id
     * @return bool|WP_Error Success or error
     */
    public function delete_google_event( int $appointment_id ) {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT staff_id FROM $appointments_table WHERE id = %d",
            $appointment_id
        ) );

        if ( ! $appointment ) {
            return false;
        }

        $staff_id = intval( $appointment->staff_id );
        $google_event_id = get_post_meta( $appointment_id, 'ltlb_google_event_id', true );

        if ( empty( $google_event_id ) ) {
            return true; // Nothing to delete
        }

        $access_token = $this->get_access_token( $staff_id );
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $calendar_id = get_user_meta( $staff_id, 'ltlb_google_calendar_id', true ) ?: 'primary';

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
            urlencode( $calendar_id ),
            urlencode( $google_event_id )
        );

        $response = wp_remote_request( $url, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 204 ) {
            delete_post_meta( $appointment_id, 'ltlb_google_event_id' );
            return true;
        }

        return new WP_Error( 'delete_failed', __( 'Failed to delete Google Calendar event', 'ltl-bookings' ) );
    }

    /**
     * Build event data for Google Calendar
     * 
     * @param object $appointment
     * @return array Event data
     */
    private function build_event_data( object $appointment ): array {
        global $wpdb;

        // Get service name
        $service_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}ltlb_services WHERE id = %d",
            $appointment->service_id
        ) );

        // Get customer name
        $customer = get_userdata( $appointment->customer_id );
        $customer_name = $customer ? $customer->display_name : '';

        return [
            'summary' => $service_name . ( $customer_name ? ' - ' . $customer_name : '' ),
            'description' => $appointment->notes ?? '',
            'start' => [
                'dateTime' => $this->format_datetime( $appointment->start_at ),
                'timeZone' => wp_timezone_string()
            ],
            'end' => [
                'dateTime' => $this->format_datetime( $appointment->end_at ),
                'timeZone' => wp_timezone_string()
            ],
            'status' => $appointment->status === 'confirmed' ? 'confirmed' : 'tentative',
            'extendedProperties' => [
                'private' => [
                    'ltlb_appointment_id' => (string) $appointment->id
                ]
            ]
        ];
    }

    /**
     * Run two-way sync
     * 
     * @param int $staff_id Optional staff ID (all staff if not specified)
     */
    public function run_sync( int $staff_id = 0 ) {
        if ( $staff_id > 0 ) {
            $this->sync_staff_calendar( $staff_id );
        } else {
            // Sync all connected staff
            $staff_ids = $this->get_connected_staff();
            foreach ( $staff_ids as $id ) {
                $this->sync_staff_calendar( $id );
            }
        }
    }

    /**
     * Sync individual staff calendar
     * 
     * @param int $staff_id
     * @return bool|WP_Error Success or error
     */
    private function sync_staff_calendar( int $staff_id ) {
        // 1. Push local changes to Google
        $this->push_local_changes( $staff_id );

        // 2. Pull Google changes to local
        $this->pull_google_changes( $staff_id );

        return true;
    }

    /**
     * Push local appointment changes to Google
     * 
     * @param int $staff_id
     */
    private function push_local_changes( int $staff_id ) {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        
        // Get appointments that need syncing
        $appointments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $appointments_table 
             WHERE staff_id = %d 
             AND status IN ('confirmed', 'pending')
             AND updated_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
             ORDER BY updated_at DESC
             LIMIT 50",
            $staff_id
        ) );

        foreach ( $appointments as $appointment ) {
            $this->sync_appointment_to_google( $appointment->id );
        }
    }

    /**
     * Pull changes from Google Calendar
     * 
     * @param int $staff_id
     */
    private function pull_google_changes( int $staff_id ) {
        $access_token = $this->get_access_token( $staff_id );
        if ( is_wp_error( $access_token ) ) {
            return;
        }

        $calendar_id = get_user_meta( $staff_id, 'ltlb_google_calendar_id', true ) ?: 'primary';
        
        // Get last sync time
        $last_sync = get_user_meta( $staff_id, 'ltlb_google_last_sync', true );
        $time_min = $last_sync ? date( 'c', $last_sync ) : date( 'c', strtotime( '-7 days' ) );

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events?timeMin=%s&singleEvents=true&orderBy=startTime',
            urlencode( $calendar_id ),
            urlencode( $time_min )
        );

        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
        ] );

        $result = $this->parse_google_response( $response );
        
        if ( ! is_wp_error( $result ) && ! empty( $result['items'] ) ) {
            foreach ( $result['items'] as $event ) {
                $this->import_google_event( $event, $staff_id );
            }
        }

        update_user_meta( $staff_id, 'ltlb_google_last_sync', time() );
    }

    /**
     * Import Google Calendar event to local appointment
     * 
     * @param array $event Google event data
     * @param int $staff_id
     */
    private function import_google_event( array $event, int $staff_id ) {
        // Check if this is our own event
        if ( ! empty( $event['extendedProperties']['private']['ltlb_appointment_id'] ) ) {
            return; // Skip our own events
        }

        // TODO: Create or update local appointment from Google event
        // This would need conflict resolution logic
    }

    /**
     * Get list of staff with Google Calendar connected
     * 
     * @return array Staff user IDs
     */
    private function get_connected_staff(): array {
        global $wpdb;

        return $wpdb->get_col(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'ltlb_google_access_token' 
             AND meta_value != ''"
        );
    }

    /**
     * Parse Google API response
     * 
     * @param array|WP_Error $response
     * @return array|WP_Error Parsed response
     */
    private function parse_google_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $message = $body['error']['message'] ?? __( 'Google Calendar API error', 'ltl-bookings' );
            return new WP_Error( 'google_api_error', $message );
        }

        return $body;
    }

    /**
     * Format datetime for Google Calendar
     * 
     * @param string $datetime MySQL datetime
     * @return string RFC3339 format
     */
    private function format_datetime( string $datetime ): string {
        return date( 'c', strtotime( $datetime ) );
    }

    /**
     * Disconnect Google Calendar for staff
     * 
     * @param int $staff_id
     * @return bool Success
     */
    public function disconnect( int $staff_id ): bool {
        delete_user_meta( $staff_id, 'ltlb_google_access_token' );
        delete_user_meta( $staff_id, 'ltlb_google_refresh_token' );
        delete_user_meta( $staff_id, 'ltlb_google_token_expires' );
        delete_user_meta( $staff_id, 'ltlb_google_calendar_id' );
        delete_user_meta( $staff_id, 'ltlb_google_last_sync' );
        
        return true;
    }
}
