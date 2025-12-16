<?php
/**
 * Webhooks System
 * 
 * Provides webhook delivery for booking lifecycle events.
 * Supports retry logic, signing secrets, and developer hooks documentation.
 *
 * @package LTL_Bookings
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LTLB_Webhooks {
    
    /**
     * Available webhook events
     */
    private const EVENTS = [
        'booking.created' => 'Booking Created',
        'booking.updated' => 'Booking Updated',
        'booking.confirmed' => 'Booking Confirmed',
        'booking.cancelled' => 'Booking Cancelled',
        'booking.completed' => 'Booking Completed',
        'booking.no_show' => 'Booking No-Show',
        'payment.received' => 'Payment Received',
        'payment.failed' => 'Payment Failed',
        'payment.refunded' => 'Payment Refunded',
        'customer.created' => 'Customer Created',
        'customer.updated' => 'Customer Updated',
    ];
    
    /**
     * Initialize webhooks
     */
    public static function init(): void {
        // Register lifecycle hooks
        add_action( 'ltlb_booking_created', [ __CLASS__, 'on_booking_created' ], 10, 1 );
        add_action( 'ltlb_booking_updated', [ __CLASS__, 'on_booking_updated' ], 10, 2 );
        add_action( 'ltlb_booking_status_changed', [ __CLASS__, 'on_status_changed' ], 10, 3 );
        add_action( 'ltlb_payment_received', [ __CLASS__, 'on_payment_received' ], 10, 1 );
        add_action( 'ltlb_payment_failed', [ __CLASS__, 'on_payment_failed' ], 10, 2 );
        add_action( 'ltlb_customer_created', [ __CLASS__, 'on_customer_created' ], 10, 1 );
        
        // Admin page
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ], 20 );
        
        // AJAX handlers
        add_action( 'wp_ajax_ltlb_save_webhook', [ __CLASS__, 'ajax_save_webhook' ] );
        add_action( 'wp_ajax_ltlb_delete_webhook', [ __CLASS__, 'ajax_delete_webhook' ] );
        add_action( 'wp_ajax_ltlb_test_webhook', [ __CLASS__, 'ajax_test_webhook' ] );
        
        // Retry cron
        add_action( 'ltlb_retry_webhook', [ __CLASS__, 'retry_webhook' ], 10, 2 );
    }
    
    /**
     * Trigger webhook for booking created
     */
    public static function on_booking_created( int $booking_id ): void {
        $booking = self::get_booking_data( $booking_id );
        if ( $booking ) {
            self::trigger( 'booking.created', $booking );
        }
    }
    
    /**
     * Trigger webhook for booking updated
     */
    public static function on_booking_updated( int $booking_id, array $old_data ): void {
        $booking = self::get_booking_data( $booking_id );
        if ( $booking ) {
            self::trigger( 'booking.updated', [
                'booking' => $booking,
                'changes' => self::get_changes( $old_data, $booking )
            ] );
        }
    }
    
    /**
     * Trigger webhook for status change
     */
    public static function on_status_changed( int $booking_id, string $old_status, string $new_status ): void {
        $booking = self::get_booking_data( $booking_id );
        if ( ! $booking ) return;
        
        $event_map = [
            'confirmed' => 'booking.confirmed',
            'cancelled' => 'booking.cancelled',
            'completed' => 'booking.completed',
            'no-show' => 'booking.no_show',
        ];
        
        $event = $event_map[ $new_status ] ?? null;
        
        if ( $event ) {
            self::trigger( $event, [
                'booking' => $booking,
                'old_status' => $old_status,
                'new_status' => $new_status
            ] );
        }
    }
    
    /**
     * Trigger webhook for payment received
     */
    public static function on_payment_received( int $booking_id ): void {
        $booking = self::get_booking_data( $booking_id );
        if ( $booking ) {
            self::trigger( 'payment.received', $booking );
        }
    }
    
    /**
     * Trigger webhook for payment failed
     */
    public static function on_payment_failed( int $booking_id, string $error ): void {
        $booking = self::get_booking_data( $booking_id );
        if ( $booking ) {
            self::trigger( 'payment.failed', [
                'booking' => $booking,
                'error' => $error
            ] );
        }
    }
    
    /**
     * Trigger webhook for customer created
     */
    public static function on_customer_created( int $customer_id ): void {
        $customer = self::get_customer_data( $customer_id );
        if ( $customer ) {
            self::trigger( 'customer.created', $customer );
        }
    }
    
    /**
     * Trigger webhook delivery
     *
     * @param string $event Event name (e.g., 'booking.created')
     * @param array $data Payload data
     */
    public static function trigger( string $event, array $data ): void {
        $webhooks = self::get_active_webhooks( $event );
        
        if ( empty( $webhooks ) ) {
            return;
        }
        
        $payload = [
            'event' => $event,
            'timestamp' => current_time( 'mysql' ),
            'data' => $data
        ];
        
        foreach ( $webhooks as $webhook ) {
            self::deliver( $webhook, $payload );
        }
        
        // Fire developer hook
        do_action( 'ltlb_webhook_triggered', $event, $data, $webhooks );
    }
    
    /**
     * Deliver webhook to endpoint
     *
     * @param array $webhook Webhook configuration
     * @param array $payload Payload data
     * @param int $attempt Attempt number (for retries)
     */
    private static function deliver( array $webhook, array $payload, int $attempt = 1 ): void {
        $url = $webhook['url'];
        $secret = $webhook['secret'] ?? '';
        
        // Generate signature
        $signature = self::generate_signature( $payload, $secret );
        
        // Prepare request
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-LTLB-Event' => $payload['event'],
                'X-LTLB-Signature' => $signature,
                'X-LTLB-Delivery' => wp_generate_uuid4(),
                'User-Agent' => 'LTL-Bookings-Webhooks/1.0'
            ],
            'body' => wp_json_encode( $payload )
        ];
        
        // Send request
        $response = wp_remote_post( $url, $args );
        
        // Log delivery
        $log_entry = [
            'webhook_id' => $webhook['id'],
            'event' => $payload['event'],
            'url' => $url,
            'attempt' => $attempt,
            'timestamp' => current_time( 'mysql' ),
            'response_code' => wp_remote_retrieve_response_code( $response ),
            'response_body' => wp_remote_retrieve_body( $response ),
            'success' => ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 200 && wp_remote_retrieve_response_code( $response ) < 300
        ];
        
        self::log_delivery( $log_entry );
        
        // Schedule retry if failed
        if ( ! $log_entry['success'] && $attempt < 3 ) {
            $retry_delay = $attempt * 300; // 5 minutes, 10 minutes, 15 minutes
            wp_schedule_single_event( time() + $retry_delay, 'ltlb_retry_webhook', [
                $webhook,
                $payload,
                $attempt + 1
            ] );
        }
        
        // Fire developer hook
        do_action( 'ltlb_webhook_delivered', $webhook, $payload, $log_entry );
    }
    
    /**
     * Retry webhook delivery
     */
    public static function retry_webhook( array $webhook, array $payload, int $attempt ): void {
        self::deliver( $webhook, $payload, $attempt );
    }
    
    /**
     * Generate HMAC signature
     */
    private static function generate_signature( array $payload, string $secret ): string {
        if ( empty( $secret ) ) {
            return '';
        }
        
        $json = wp_json_encode( $payload );
        return hash_hmac( 'sha256', $json, $secret );
    }
    
    /**
     * Get active webhooks for event
     */
    private static function get_active_webhooks( string $event ): array {
        $all_webhooks = get_option( 'ltlb_webhooks', [] );
        
        return array_filter( $all_webhooks, function( $webhook ) use ( $event ) {
            return ( $webhook['is_active'] ?? false ) 
                && in_array( $event, $webhook['events'] ?? [], true );
        } );
    }
    
    /**
     * Get booking data for webhook
     */
    private static function get_booking_data( int $booking_id ): ?array {
        $repo = new LTLB_AppointmentRepository();
        $booking = $repo->get_by_id( $booking_id );
        
        if ( ! $booking ) {
            return null;
        }
        
        // Enrich with related data
        $service_repo = new LTLB_ServiceRepository();
        $customer_repo = new LTLB_CustomerRepository();
        
        $booking['service'] = $service_repo->get_by_id( intval( $booking['service_id'] ?? 0 ) );
        $booking['customer'] = $customer_repo->get_by_id( intval( $booking['customer_id'] ?? 0 ) );
        
        // Remove sensitive data
        if ( isset( $booking['customer']['notes'] ) ) {
            unset( $booking['customer']['notes'] );
        }
        
        return $booking;
    }
    
    /**
     * Get customer data for webhook
     */
    private static function get_customer_data( int $customer_id ): ?array {
        $repo = new LTLB_CustomerRepository();
        $customer = $repo->get_by_id( $customer_id );
        
        if ( ! $customer ) {
            return null;
        }
        
        // Remove sensitive data
        unset( $customer['notes'] );
        
        return $customer;
    }
    
    /**
     * Get changes between old and new data
     */
    private static function get_changes( array $old, array $new ): array {
        $changes = [];
        
        foreach ( $new as $key => $value ) {
            if ( ! isset( $old[ $key ] ) || $old[ $key ] !== $value ) {
                $changes[ $key ] = [
                    'old' => $old[ $key ] ?? null,
                    'new' => $value
                ];
            }
        }
        
        return $changes;
    }
    
    /**
     * Log webhook delivery
     */
    private static function log_delivery( array $log_entry ): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_webhook_logs';
        
        $wpdb->insert( $table, [
            'webhook_id' => $log_entry['webhook_id'],
            'event' => $log_entry['event'],
            'url' => $log_entry['url'],
            'attempt' => $log_entry['attempt'],
            'response_code' => $log_entry['response_code'],
            'response_body' => $log_entry['response_body'],
            'success' => $log_entry['success'] ? 1 : 0,
            'created_at' => $log_entry['timestamp']
        ] );
    }
    
    /**
     * Add admin menu
     */
    public static function add_menu(): void {
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Webhooks', 'ltl-bookings' ),
            __( 'Webhooks', 'ltl-bookings' ),
            'manage_options',
            'ltlb_webhooks',
            [ __CLASS__, 'render_page' ]
        );
    }
    
    /**
     * Render admin page
     */
    public static function render_page(): void {
        $webhooks = get_option( 'ltlb_webhooks', [] );
        $logs = self::get_recent_logs( 50 );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Webhooks', 'ltl-bookings' ); ?></h1>
            
            <div class="ltlb-webhooks-page">
                <div class="ltlb-webhooks-list">
                    <h2><?php esc_html_e( 'Configured Webhooks', 'ltl-bookings' ); ?></h2>
                    <button type="button" class="button button-primary" id="ltlb-add-webhook">
                        <?php esc_html_e( 'Add Webhook', 'ltl-bookings' ); ?>
                    </button>
                    
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'URL', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Events', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'ltl-bookings' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $webhooks ) ): ?>
                                <tr>
                                    <td colspan="4"><?php esc_html_e( 'No webhooks configured.', 'ltl-bookings' ); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ( $webhooks as $id => $webhook ): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $webhook['url'] ); ?></strong>
                                            <?php if ( ! empty( $webhook['description'] ) ): ?>
                                                <br><small><?php echo esc_html( $webhook['description'] ); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html( implode( ', ', $webhook['events'] ?? [] ) ); ?>
                                        </td>
                                        <td>
                                            <?php if ( $webhook['is_active'] ?? false ): ?>
                                                <span class="ltlb-status-badge ltlb-status-active">
                                                    <?php esc_html_e( 'Active', 'ltl-bookings' ); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="ltlb-status-badge ltlb-status-inactive">
                                                    <?php esc_html_e( 'Inactive', 'ltl-bookings' ); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="button ltlb-test-webhook" data-id="<?php echo esc_attr( $id ); ?>">
                                                <?php esc_html_e( 'Test', 'ltl-bookings' ); ?>
                                            </button>
                                            <button class="button ltlb-edit-webhook" data-id="<?php echo esc_attr( $id ); ?>">
                                                <?php esc_html_e( 'Edit', 'ltl-bookings' ); ?>
                                            </button>
                                            <button class="button ltlb-delete-webhook" data-id="<?php echo esc_attr( $id ); ?>">
                                                <?php esc_html_e( 'Delete', 'ltl-bookings' ); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="ltlb-webhook-logs" style="margin-top: 40px;">
                    <h2><?php esc_html_e( 'Recent Deliveries', 'ltl-bookings' ); ?></h2>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Time', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Event', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'URL', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Attempt', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'ltl-bookings' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $logs ) ): ?>
                                <tr>
                                    <td colspan="5"><?php esc_html_e( 'No deliveries yet.', 'ltl-bookings' ); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ( $logs as $log ): ?>
                                    <tr>
                                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['created_at'] ) ) ); ?></td>
                                        <td><code><?php echo esc_html( $log['event'] ); ?></code></td>
                                        <td><?php echo esc_html( $log['url'] ); ?></td>
                                        <td><?php echo esc_html( $log['attempt'] ); ?></td>
                                        <td>
                                            <?php if ( $log['success'] ): ?>
                                                <span style="color: green;">✓ <?php echo esc_html( $log['response_code'] ); ?></span>
                                            <?php else: ?>
                                                <span style="color: red;">✗ <?php echo esc_html( $log['response_code'] ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="ltlb-webhook-docs" style="margin-top: 40px;">
                    <h2><?php esc_html_e( 'Developer Hooks', 'ltl-bookings' ); ?></h2>
                    <p><?php esc_html_e( 'The following WordPress actions are available for custom integrations:', 'ltl-bookings' ); ?></p>
                    
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Hook', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Parameters', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'ltl-bookings' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>ltlb_booking_created</code></td>
                                <td><code>$booking_id</code></td>
                                <td><?php esc_html_e( 'Fires when a new booking is created', 'ltl-bookings' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>ltlb_booking_updated</code></td>
                                <td><code>$booking_id, $old_data</code></td>
                                <td><?php esc_html_e( 'Fires when a booking is updated', 'ltl-bookings' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>ltlb_booking_status_changed</code></td>
                                <td><code>$booking_id, $old_status, $new_status</code></td>
                                <td><?php esc_html_e( 'Fires when booking status changes', 'ltl-bookings' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>ltlb_payment_received</code></td>
                                <td><code>$booking_id</code></td>
                                <td><?php esc_html_e( 'Fires when payment is received', 'ltl-bookings' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>ltlb_webhook_triggered</code></td>
                                <td><code>$event, $data, $webhooks</code></td>
                                <td><?php esc_html_e( 'Fires when a webhook is triggered', 'ltl-bookings' ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ltlb-add-webhook').on('click', function() {
                // Show modal (implementation would be added)
                alert('<?php echo esc_js( __( 'Add webhook modal would open here', 'ltl-bookings' ) ); ?>');
            });
            
            $('.ltlb-test-webhook').on('click', function() {
                var id = $(this).data('id');
                $.post(ajaxurl, {
                    action: 'ltlb_test_webhook',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_webhooks' ); ?>',
                    webhook_id: id
                }, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js( __( 'Test webhook sent!', 'ltl-bookings' ) ); ?>');
                    } else {
                        alert('<?php echo esc_js( __( 'Failed to send test webhook', 'ltl-bookings' ) ); ?>');
                    }
                });
            });
            
            $('.ltlb-delete-webhook').on('click', function() {
                if (!confirm('<?php echo esc_js( __( 'Delete this webhook?', 'ltl-bookings' ) ); ?>')) return;
                
                var id = $(this).data('id');
                $.post(ajaxurl, {
                    action: 'ltlb_delete_webhook',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_webhooks' ); ?>',
                    webhook_id: id
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get recent webhook logs
     */
    private static function get_recent_logs( int $limit = 50 ): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_webhook_logs';
        
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ),
            ARRAY_A
        ) ?: [];
    }
    
    /**
     * AJAX: Save webhook
     */
    public static function ajax_save_webhook(): void {
        check_ajax_referer( 'ltlb_webhooks', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        
        $webhooks = get_option( 'ltlb_webhooks', [] );
        
        $id = sanitize_key( $_POST['webhook_id'] ?? wp_generate_uuid4() );
        
        $webhooks[ $id ] = [
            'id' => $id,
            'url' => esc_url_raw( $_POST['url'] ?? '' ),
            'description' => sanitize_text_field( $_POST['description'] ?? '' ),
            'events' => array_map( 'sanitize_key', $_POST['events'] ?? [] ),
            'secret' => sanitize_text_field( $_POST['secret'] ?? '' ),
            'is_active' => isset( $_POST['is_active'] ) && $_POST['is_active'] === '1'
        ];
        
        update_option( 'ltlb_webhooks', $webhooks );
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Delete webhook
     */
    public static function ajax_delete_webhook(): void {
        check_ajax_referer( 'ltlb_webhooks', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        
        $id = sanitize_key( $_POST['webhook_id'] ?? '' );
        $webhooks = get_option( 'ltlb_webhooks', [] );
        
        if ( isset( $webhooks[ $id ] ) ) {
            unset( $webhooks[ $id ] );
            update_option( 'ltlb_webhooks', $webhooks );
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * AJAX: Test webhook
     */
    public static function ajax_test_webhook(): void {
        check_ajax_referer( 'ltlb_webhooks', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        
        $id = sanitize_key( $_POST['webhook_id'] ?? '' );
        $webhooks = get_option( 'ltlb_webhooks', [] );
        
        if ( ! isset( $webhooks[ $id ] ) ) {
            wp_send_json_error();
        }
        
        $webhook = $webhooks[ $id ];
        
        $test_payload = [
            'event' => 'test.webhook',
            'timestamp' => current_time( 'mysql' ),
            'data' => [
                'message' => 'This is a test webhook from LTL Bookings',
                'site_url' => get_site_url()
            ]
        ];
        
        self::deliver( $webhook, $test_payload );
        
        wp_send_json_success();
    }
}

// Initialize
LTLB_Webhooks::init();
