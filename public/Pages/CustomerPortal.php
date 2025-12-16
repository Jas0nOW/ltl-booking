<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Customer Portal - Frontend Account Management
 * 
 * Allows customers to:
 * - View their bookings (upcoming/past)
 * - Reschedule appointments
 * - Cancel bookings
 * - Download invoices/receipts
 * - Update personal information
 * - Manage consent/privacy settings
 * 
 * @package LazyBookings
 */
class LTLB_Customer_Portal {

    /**
     * Initialize customer portal
     */
    public function __construct() {
        add_shortcode( 'ltlb_customer_portal', [ $this, 'render_portal' ] );
        add_action( 'wp_ajax_ltlb_get_my_bookings', [ $this, 'ajax_get_bookings' ] );
        add_action( 'wp_ajax_ltlb_cancel_booking', [ $this, 'ajax_cancel_booking' ] );
        add_action( 'wp_ajax_ltlb_request_reschedule', [ $this, 'ajax_request_reschedule' ] );
    }

    /**
     * Render customer portal
     * 
     * @param array $atts Shortcode attributes
     * @return string Portal HTML
     */
    public function render_portal( $atts = [] ): string {
        if ( ! is_user_logged_in() ) {
            return $this->render_login_form();
        }

        wp_enqueue_style( 'ltlb-customer-portal', plugins_url( 'assets/css/customer-portal.css', LTLB_PLUGIN_FILE ), [], '1.0' );
        wp_enqueue_script( 'ltlb-customer-portal', plugins_url( 'assets/js/customer-portal.js', LTLB_PLUGIN_FILE ), [ 'jquery' ], '1.0', true );
        
        wp_localize_script( 'ltlb-customer-portal', 'ltlbPortal', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ltlb_customer_portal' ),
            'strings' => [
                'confirmCancel' => __( 'Are you sure you want to cancel this booking?', 'ltl-bookings' ),
                'cancelSuccess' => __( 'Booking cancelled successfully', 'ltl-bookings' ),
                'cancelError' => __( 'Failed to cancel booking', 'ltl-bookings' )
            ]
        ] );

        ob_start();
        ?>
        <div class="ltlb-customer-portal">
            <div class="ltlb-portal-header">
                <h2><?php esc_html_e( 'My Bookings', 'ltl-bookings' ); ?></h2>
                <div class="ltlb-portal-tabs">
                    <button class="ltlb-tab-btn active" data-tab="upcoming">
                        <?php esc_html_e( 'Upcoming', 'ltl-bookings' ); ?>
                    </button>
                    <button class="ltlb-tab-btn" data-tab="past">
                        <?php esc_html_e( 'Past', 'ltl-bookings' ); ?>
                    </button>
                    <button class="ltlb-tab-btn" data-tab="cancelled">
                        <?php esc_html_e( 'Cancelled', 'ltl-bookings' ); ?>
                    </button>
                </div>
            </div>

            <div class="ltlb-portal-content">
                <div class="ltlb-tab-content active" id="tab-upcoming">
                    <?php echo $this->render_bookings_list( 'upcoming' ); ?>
                </div>
                <div class="ltlb-tab-content" id="tab-past">
                    <?php echo $this->render_bookings_list( 'past' ); ?>
                </div>
                <div class="ltlb-tab-content" id="tab-cancelled">
                    <?php echo $this->render_bookings_list( 'cancelled' ); ?>
                </div>
            </div>

            <div class="ltlb-portal-footer">
                <a href="<?php echo wp_logout_url( get_permalink() ); ?>">
                    <?php esc_html_e( 'Logout', 'ltl-bookings' ); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login form
     * 
     * @return string Login form HTML
     */
    private function render_login_form(): string {
        ob_start();
        ?>
        <div class="ltlb-login-form">
            <h3><?php esc_html_e( 'Please log in to view your bookings', 'ltl-bookings' ); ?></h3>
            <?php wp_login_form( [
                'redirect' => get_permalink(),
                'label_log_in' => __( 'Log In', 'ltl-bookings' )
            ] ); ?>
            <p>
                <a href="<?php echo wp_registration_url(); ?>">
                    <?php esc_html_e( 'Register', 'ltl-bookings' ); ?>
                </a>
                |
                <a href="<?php echo wp_lostpassword_url(); ?>">
                    <?php esc_html_e( 'Lost Password?', 'ltl-bookings' ); ?>
                </a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render bookings list
     * 
     * @param string $type Type: upcoming, past, cancelled
     * @return string Bookings list HTML
     */
    private function render_bookings_list( string $type ): string {
        $bookings = $this->get_customer_bookings( get_current_user_id(), $type );

        if ( empty( $bookings ) ) {
            return '<p class="ltlb-no-bookings">' . esc_html__( 'No bookings found', 'ltl-bookings' ) . '</p>';
        }

        ob_start();
        ?>
        <div class="ltlb-bookings-list">
            <?php foreach ( $bookings as $booking ) : ?>
                <div class="ltlb-booking-card" data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
                    <div class="ltlb-booking-header">
                        <h4><?php echo esc_html( $booking->service_name ); ?></h4>
                        <span class="ltlb-booking-status ltlb-status-<?php echo esc_attr( $booking->status ); ?>">
                            <?php echo esc_html( ucfirst( $booking->status ) ); ?>
                        </span>
                    </div>

                    <div class="ltlb-booking-details">
                        <div class="ltlb-detail">
                            <span class="ltlb-detail-label"><?php esc_html_e( 'Date & Time:', 'ltl-bookings' ); ?></span>
                            <span class="ltlb-detail-value">
                                <?php echo esc_html( date_i18n( 
                                    get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), 
                                    strtotime( $booking->start_at ) 
                                ) ); ?>
                            </span>
                        </div>

                        <?php if ( ! empty( $booking->staff_name ) ) : ?>
                        <div class="ltlb-detail">
                            <span class="ltlb-detail-label"><?php esc_html_e( 'Staff:', 'ltl-bookings' ); ?></span>
                            <span class="ltlb-detail-value"><?php echo esc_html( $booking->staff_name ); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $booking->location_name ) ) : ?>
                        <div class="ltlb-detail">
                            <span class="ltlb-detail-label"><?php esc_html_e( 'Location:', 'ltl-bookings' ); ?></span>
                            <span class="ltlb-detail-value"><?php echo esc_html( $booking->location_name ); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="ltlb-detail">
                            <span class="ltlb-detail-label"><?php esc_html_e( 'Amount:', 'ltl-bookings' ); ?></span>
                            <span class="ltlb-detail-value">
                                <?php echo $this->format_amount( $booking->amount_cents ); ?>
                            </span>
                        </div>
                    </div>

                    <div class="ltlb-booking-actions">
                        <?php if ( $type === 'upcoming' && in_array( $booking->status, [ 'confirmed', 'pending' ] ) ) : ?>
                            <?php if ( $this->can_reschedule( $booking ) ) : ?>
                            <button class="ltlb-btn ltlb-btn-reschedule" data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
                                <?php esc_html_e( 'Reschedule', 'ltl-bookings' ); ?>
                            </button>
                            <?php endif; ?>

                            <?php if ( $this->can_cancel( $booking ) ) : ?>
                            <button class="ltlb-btn ltlb-btn-cancel" data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
                                <?php esc_html_e( 'Cancel', 'ltl-bookings' ); ?>
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ( $this->has_invoice( $booking->id ) ) : ?>
                        <a href="<?php echo esc_url( $this->get_invoice_url( $booking->id ) ); ?>" 
                           class="ltlb-btn ltlb-btn-invoice" target="_blank">
                            <?php esc_html_e( 'Download Invoice', 'ltl-bookings' ); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get customer bookings
     * 
     * @param int $customer_id
     * @param string $type Type: upcoming, past, cancelled
     * @return array Bookings
     */
    private function get_customer_bookings( int $customer_id, string $type ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $services_table = $wpdb->prefix . 'ltlb_services';
        $locations_table = $wpdb->prefix . 'ltlb_locations';

        $now = current_time( 'mysql' );

        $where = "a.customer_id = %d";
        $params = [ $customer_id ];

        switch ( $type ) {
            case 'upcoming':
                $where .= " AND a.start_at > %s AND a.status IN ('confirmed', 'pending')";
                $params[] = $now;
                break;
            case 'past':
                $where .= " AND a.start_at <= %s AND a.status IN ('confirmed', 'completed')";
                $params[] = $now;
                break;
            case 'cancelled':
                $where .= " AND a.status = 'cancelled'";
                break;
        }

        $query = "SELECT 
                    a.*,
                    s.name as service_name,
                    l.name as location_name,
                    u.display_name as staff_name
                  FROM $appointments_table a
                  LEFT JOIN $services_table s ON a.service_id = s.id
                  LEFT JOIN $locations_table l ON a.location_id = l.id
                  LEFT JOIN {$wpdb->users} u ON a.staff_id = u.ID
                  WHERE $where
                  ORDER BY a.start_at DESC";

        return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
    }

    /**
     * Check if booking can be rescheduled
     * 
     * @param object $booking
     * @return bool Can reschedule
     */
    private function can_reschedule( object $booking ): bool {
        $policy_engine = new LTLB_Policy_Engine();
        $new_date = date( 'Y-m-d H:i:s', strtotime( $booking->start_at ) + 86400 ); // Example: +1 day
        $result = $policy_engine->can_reschedule( $booking->id, $new_date );
        
        return $result === true;
    }

    /**
     * Check if booking can be cancelled
     * 
     * @param object $booking
     * @return bool Can cancel
     */
    private function can_cancel( object $booking ): bool {
        // Check if within cancellation window
        $hours_until = ( strtotime( $booking->start_at ) - time() ) / 3600;
        return $hours_until > 0; // Can cancel if in future
    }

    /**
     * Check if booking has invoice
     * 
     * @param int $appointment_id
     * @return bool Has invoice
     */
    private function has_invoice( int $appointment_id ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_invoices';
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE appointment_id = %d AND pdf_path IS NOT NULL",
            $appointment_id
        ) );

        return intval( $count ) > 0;
    }

    /**
     * Get invoice download URL
     * 
     * @param int $appointment_id
     * @return string URL
     */
    private function get_invoice_url( int $appointment_id ): string {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_invoices';
        $pdf_path = $wpdb->get_var( $wpdb->prepare(
            "SELECT pdf_path FROM $table WHERE appointment_id = %d",
            $appointment_id
        ) );

        return $pdf_path ? esc_url( $pdf_path ) : '';
    }

    /**
     * AJAX: Get bookings
     */
    public function ajax_get_bookings() {
        check_ajax_referer( 'ltlb_customer_portal', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Not logged in', 'ltl-bookings' ) ] );
        }

        $type = sanitize_text_field( $_POST['type'] ?? 'upcoming' );
        $bookings = $this->get_customer_bookings( get_current_user_id(), $type );

        wp_send_json_success( [ 'bookings' => $bookings ] );
    }

    /**
     * AJAX: Cancel booking
     */
    public function ajax_cancel_booking() {
        check_ajax_referer( 'ltlb_customer_portal', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Not logged in', 'ltl-bookings' ) ] );
        }

        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        
        if ( ! $booking_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid booking ID', 'ltl-bookings' ) ] );
        }

        // Verify ownership
        if ( ! $this->verify_booking_ownership( $booking_id, get_current_user_id() ) ) {
            wp_send_json_error( [ 'message' => __( 'Access denied', 'ltl-bookings' ) ] );
        }

        // Cancel booking
        $policy_engine = new LTLB_Policy_Engine();
        $reason = sanitize_text_field( $_POST['reason'] ?? 'Customer requested cancellation' );
        $result = $policy_engine->cancel_booking( $booking_id, $reason );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => __( 'Booking cancelled successfully', 'ltl-bookings' ) ] );
    }

    /**
     * AJAX: Request reschedule
     */
    public function ajax_request_reschedule() {
        check_ajax_referer( 'ltlb_customer_portal', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Not logged in', 'ltl-bookings' ) ] );
        }

        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        $new_date = sanitize_text_field( $_POST['new_date'] ?? '' );

        if ( ! $booking_id || ! $new_date ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data', 'ltl-bookings' ) ] );
        }

        // Verify ownership
        if ( ! $this->verify_booking_ownership( $booking_id, get_current_user_id() ) ) {
            wp_send_json_error( [ 'message' => __( 'Access denied', 'ltl-bookings' ) ] );
        }

        // Reschedule
        $policy_engine = new LTLB_Policy_Engine();
        $result = $policy_engine->reschedule_booking( $booking_id, $new_date );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => __( 'Booking rescheduled successfully', 'ltl-bookings' ) ] );
    }

    /**
     * Verify booking ownership
     * 
     * @param int $booking_id
     * @param int $customer_id
     * @return bool Is owner
     */
    private function verify_booking_ownership( int $booking_id, int $customer_id ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_appointments';
        $owner_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT customer_id FROM $table WHERE id = %d",
            $booking_id
        ) );

        return intval( $owner_id ) === $customer_id;
    }

    /**
     * Format amount with currency
     * 
     * @param int $amount_cents
     * @return string Formatted amount
     */
    private function format_amount( int $amount_cents ): string {
        $amount = $amount_cents / 100;
        return number_format( $amount, 2, ',', '.' ) . ' €';
    }
}

// Initialize
new LTLB_Customer_Portal();
