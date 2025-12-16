<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Webhooks System
 * 
 * Event-driven webhook notifications with retry logic and HMAC signing.
 * Supports booking lifecycle events and custom triggers.
 */
class LTLB_Webhooks {

    public static function init(): void {
        add_action( 'ltlb_webhook_send', [ __CLASS__, 'send_webhook' ], 10, 3 );
    }

    /**
     * Register a webhook endpoint
     */
    public static function register( string $event, string $url, string $secret = '' ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_webhooks';

        $wpdb->insert( $table, [
            'event' => sanitize_text_field( $event ),
            'url' => esc_url_raw( $url ),
            'secret' => $secret, // Store raw - needed for HMAC validation
            'status' => 'active',
            'created_at' => current_time( 'mysql' ),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Trigger webhook for an event
     */
    public static function trigger( string $event, array $payload ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_webhooks';

        $webhooks = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE event = %s AND status = 'active'",
            $event
        ));

        foreach ( $webhooks as $webhook ) {
            wp_schedule_single_event( time(), 'ltlb_webhook_send', [
                'webhook_id' => $webhook->id,
                'payload' => $payload,
                'attempt' => 1,
            ]);
        }
    }

    /**
     * Send webhook with retry logic
     */
    public static function send_webhook( int $webhook_id, array $payload, int $attempt = 1 ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_webhooks';

        $webhook = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $webhook_id
        ));

        if ( ! $webhook || $webhook->status !== 'active' ) {
            return;
        }

        $body = wp_json_encode( $payload );
        $signature = hash_hmac( 'sha256', $body, $webhook->secret );

        $response = wp_remote_post( $webhook->url, [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-LTLB-Signature' => $signature,
                'X-LTLB-Event' => $webhook->event,
                'X-LTLB-Delivery' => wp_generate_uuid4(),
            ],
            'timeout' => 15,
        ]);

        $status_code = wp_remote_retrieve_response_code( $response );
        $success = $status_code >= 200 && $status_code < 300;

        // Log delivery
        $wpdb->insert( $wpdb->prefix . 'ltlb_webhook_logs', [
            'webhook_id' => $webhook_id,
            'status_code' => $status_code,
            'response_body' => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ),
            'attempt' => $attempt,
            'delivered_at' => current_time( 'mysql' ),
        ]);

        // Retry on failure (max 3 attempts with exponential backoff)
        if ( ! $success && $attempt < 3 ) {
            $retry_delay = pow( 2, $attempt ) * MINUTE_IN_SECONDS;
            wp_schedule_single_event( time() + $retry_delay, 'ltlb_webhook_send', [
                'webhook_id' => $webhook_id,
                'payload' => $payload,
                'attempt' => $attempt + 1,
            ]);
        } elseif ( ! $success && $attempt >= 3 ) {
            // Disable webhook after 3 failed attempts
            $wpdb->update( $table, [ 'status' => 'failed' ], [ 'id' => $webhook_id ] );
        }
    }

    /**
     * Get all registered webhooks
     */
    public static function get_all(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_webhooks';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
    }

    /**
     * Delete a webhook
     */
    public static function delete( int $webhook_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_webhooks';
        return (bool) $wpdb->delete( $table, [ 'id' => $webhook_id ] );
    }

    /**
     * Validate incoming webhook signature (for receiving webhooks)
     */
    public static function validate_signature( string $payload, string $signature, string $secret ): bool {
        $expected = hash_hmac( 'sha256', $payload, $secret );
        return hash_equals( $expected, $signature );
    }

    /**
     * Common webhook events
     */
    public static function get_available_events(): array {
        return [
            'appointment.created' => __('Appointment Created', 'ltl-bookings'),
            'appointment.confirmed' => __('Appointment Confirmed', 'ltl-bookings'),
            'appointment.cancelled' => __('Appointment Cancelled', 'ltl-bookings'),
            'appointment.completed' => __('Appointment Completed', 'ltl-bookings'),
            'payment.received' => __('Payment Received', 'ltl-bookings'),
            'customer.created' => __('Customer Created', 'ltl-bookings'),
        ];
    }
}
