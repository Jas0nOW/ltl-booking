<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Staff Portal - Frontend Staff Management
 * 
 * Allows staff members to:
 * - View their schedule/calendar
 * - Set availability and time off
 * - Accept/decline pending bookings
 * - View customer information
 * - Update their profile
 * 
 * @package LazyBookings
 */
class LTLB_Staff_Portal {

    /**
     * Initialize staff portal
     */
    public function __construct() {
        add_shortcode( 'ltlb_staff_portal', [ $this, 'render_portal' ] );
        add_action( 'wp_ajax_ltlb_get_staff_schedule', [ $this, 'ajax_get_schedule' ] );
        add_action( 'wp_ajax_ltlb_update_availability', [ $this, 'ajax_update_availability' ] );
        add_action( 'wp_ajax_ltlb_accept_booking', [ $this, 'ajax_accept_booking' ] );
        add_action( 'wp_ajax_ltlb_decline_booking', [ $this, 'ajax_decline_booking' ] );
    }

    /**
     * Render staff portal
     * 
     * @param array $atts Shortcode attributes
     * @return string Portal HTML
     */
    public function render_portal( $atts = [] ): string {
        if ( ! is_user_logged_in() ) {
            return $this->render_login_form();
        }

        $user_id = get_current_user_id();
        
        // Check if user is staff
        if ( ! $this->is_staff_member( $user_id ) ) {
            return '<div class="ltlb-error">' . esc_html__( 'Access denied. Staff members only.', 'ltl-bookings' ) . '</div>';
        }

        wp_enqueue_style( 'ltlb-staff-portal', plugins_url( 'assets/css/staff-portal.css', LTLB_PLUGIN_FILE ), [], '1.0' );
        wp_enqueue_script( 'ltlb-staff-portal', plugins_url( 'assets/js/staff-portal.js', LTLB_PLUGIN_FILE ), [ 'jquery' ], '1.0', true );
        
        wp_localize_script( 'ltlb-staff-portal', 'ltlbStaffPortal', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ltlb_staff_portal' ),
            'staffId' => $user_id,
            'strings' => [
                'confirmAccept' => __( 'Accept this booking?', 'ltl-bookings' ),
                'confirmDecline' => __( 'Decline this booking? Please provide a reason.', 'ltl-bookings' ),
                'acceptSuccess' => __( 'Booking accepted', 'ltl-bookings' ),
                'declineSuccess' => __( 'Booking declined', 'ltl-bookings' ),
                'error' => __( 'An error occurred', 'ltl-bookings' )
            ]
        ] );

        ob_start();
        ?>
        <div class="ltlb-staff-portal">
            <div class="ltlb-portal-header">
                <h2><?php esc_html_e( 'Staff Portal', 'ltl-bookings' ); ?></h2>
                <p><?php echo esc_html( sprintf( __( 'Welcome, %s', 'ltl-bookings' ), wp_get_current_user()->display_name ) ); ?></p>
            </div>

            <div class="ltlb-portal-tabs">
                <button class="ltlb-tab-btn active" data-tab="schedule">
                    <?php esc_html_e( 'My Schedule', 'ltl-bookings' ); ?>
                </button>
                <button class="ltlb-tab-btn" data-tab="pending">
                    <?php esc_html_e( 'Pending Bookings', 'ltl-bookings' ); ?>
                </button>
                <button class="ltlb-tab-btn" data-tab="availability">
                    <?php esc_html_e( 'Availability', 'ltl-bookings' ); ?>
                </button>
            </div>

            <div class="ltlb-portal-content">
                <div class="ltlb-tab-content active" id="tab-schedule">
                    <?php echo $this->render_schedule( $user_id ); ?>
                </div>
                <div class="ltlb-tab-content" id="tab-pending">
                    <?php echo $this->render_pending_bookings( $user_id ); ?>
                </div>
                <div class="ltlb-tab-content" id="tab-availability">
                    <?php echo $this->render_availability_settings( $user_id ); ?>
                </div>
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
            <h3><?php esc_html_e( 'Staff Login', 'ltl-bookings' ); ?></h3>
            <?php wp_login_form( [
                'redirect' => get_permalink(),
                'label_log_in' => __( 'Log In', 'ltl-bookings' )
            ] ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render staff schedule
     * 
     * @param int $staff_id
     * @return string Schedule HTML
     */
    private function render_schedule( int $staff_id ): string {
        $appointments = $this->get_staff_appointments( $staff_id, 'upcoming' );

        if ( empty( $appointments ) ) {
            return '<p class="ltlb-no-data">' . esc_html__( 'No upcoming appointments', 'ltl-bookings' ) . '</p>';
        }

        ob_start();
        ?>
        <div class="ltlb-schedule-list">
            <?php foreach ( $appointments as $appointment ) : ?>
                <div class="ltlb-schedule-card">
                    <div class="ltlb-schedule-time">
                        <strong><?php echo esc_html( date_i18n( 
                            get_option( 'date_format' ), 
                            strtotime( $appointment->start_at ) 
                        ) ); ?></strong>
                        <span><?php echo esc_html( date_i18n( 
                            get_option( 'time_format' ), 
                            strtotime( $appointment->start_at ) 
                        ) ); ?> - <?php echo esc_html( date_i18n( 
                            get_option( 'time_format' ), 
                            strtotime( $appointment->end_at ) 
                        ) ); ?></span>
                    </div>

                    <div class="ltlb-schedule-details">
                        <h4><?php echo esc_html( $appointment->service_name ); ?></h4>
                        
                        <?php if ( ! empty( $appointment->customer_name ) ) : ?>
                        <div class="ltlb-detail">
                            <span class="ltlb-label"><?php esc_html_e( 'Customer:', 'ltl-bookings' ); ?></span>
                            <span><?php echo esc_html( $appointment->customer_name ); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $appointment->customer_phone ) ) : ?>
                        <div class="ltlb-detail">
                            <span class="ltlb-label"><?php esc_html_e( 'Phone:', 'ltl-bookings' ); ?></span>
                            <span><?php echo esc_html( $appointment->customer_phone ); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $appointment->location_name ) ) : ?>
                        <div class="ltlb-detail">
                            <span class="ltlb-label"><?php esc_html_e( 'Location:', 'ltl-bookings' ); ?></span>
                            <span><?php echo esc_html( $appointment->location_name ); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="ltlb-detail">
                            <span class="ltlb-status ltlb-status-<?php echo esc_attr( $appointment->status ); ?>">
                                <?php echo esc_html( ucfirst( $appointment->status ) ); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render pending bookings
     * 
     * @param int $staff_id
     * @return string Pending bookings HTML
     */
    private function render_pending_bookings( int $staff_id ): string {
        $appointments = $this->get_staff_appointments( $staff_id, 'pending' );

        if ( empty( $appointments ) ) {
            return '<p class="ltlb-no-data">' . esc_html__( 'No pending bookings', 'ltl-bookings' ) . '</p>';
        }

        ob_start();
        ?>
        <div class="ltlb-pending-list">
            <?php foreach ( $appointments as $appointment ) : ?>
                <div class="ltlb-pending-card" data-booking-id="<?php echo esc_attr( $appointment->id ); ?>">
                    <div class="ltlb-pending-header">
                        <h4><?php echo esc_html( $appointment->service_name ); ?></h4>
                        <span class="ltlb-pending-date">
                            <?php echo esc_html( date_i18n( 
                                get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), 
                                strtotime( $appointment->start_at ) 
                            ) ); ?>
                        </span>
                    </div>

                    <div class="ltlb-pending-details">
                        <p><strong><?php esc_html_e( 'Customer:', 'ltl-bookings' ); ?></strong> 
                           <?php echo esc_html( $appointment->customer_name ); ?></p>
                        
                        <?php if ( ! empty( $appointment->customer_email ) ) : ?>
                        <p><strong><?php esc_html_e( 'Email:', 'ltl-bookings' ); ?></strong> 
                           <?php echo esc_html( $appointment->customer_email ); ?></p>
                        <?php endif; ?>

                        <?php if ( ! empty( $appointment->notes ) ) : ?>
                        <p><strong><?php esc_html_e( 'Notes:', 'ltl-bookings' ); ?></strong> 
                           <?php echo esc_html( $appointment->notes ); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="ltlb-pending-actions">
                        <button class="ltlb-btn ltlb-btn-accept" data-booking-id="<?php echo esc_attr( $appointment->id ); ?>">
                            <?php esc_html_e( 'Accept', 'ltl-bookings' ); ?>
                        </button>
                        <button class="ltlb-btn ltlb-btn-decline" data-booking-id="<?php echo esc_attr( $appointment->id ); ?>">
                            <?php esc_html_e( 'Decline', 'ltl-bookings' ); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render availability settings
     * 
     * @param int $staff_id
     * @return string Availability form HTML
     */
    private function render_availability_settings( int $staff_id ): string {
        $availability = $this->get_staff_availability( $staff_id );
        $days = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];

        ob_start();
        ?>
        <div class="ltlb-availability-settings">
            <h3><?php esc_html_e( 'Set Your Availability', 'ltl-bookings' ); ?></h3>
            
            <form id="ltlb-availability-form">
                <?php foreach ( $days as $index => $day ) : 
                    $day_num = $index + 1;
                    $hours = $availability[$day_num] ?? null;
                ?>
                <div class="ltlb-availability-day">
                    <label>
                        <input type="checkbox" 
                               name="available[<?php echo $day_num; ?>]" 
                               value="1"
                               <?php checked( ! empty( $hours ) ); ?>>
                        <strong><?php echo esc_html( ucfirst( $day ) ); ?></strong>
                    </label>

                    <div class="ltlb-availability-times" 
                         <?php echo empty( $hours ) ? 'style="display:none;"' : ''; ?>>
                        <input type="time" 
                               name="start_time[<?php echo $day_num; ?>]" 
                               value="<?php echo esc_attr( $hours->start_time ?? '09:00' ); ?>">
                        <span>-</span>
                        <input type="time" 
                               name="end_time[<?php echo $day_num; ?>]" 
                               value="<?php echo esc_attr( $hours->end_time ?? '17:00' ); ?>">
                    </div>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="ltlb-btn ltlb-btn-primary">
                    <?php esc_html_e( 'Save Availability', 'ltl-bookings' ); ?>
                </button>
            </form>

            <div class="ltlb-time-off">
                <h3><?php esc_html_e( 'Request Time Off', 'ltl-bookings' ); ?></h3>
                <form id="ltlb-time-off-form">
                    <div class="ltlb-form-row">
                        <label>
                            <?php esc_html_e( 'From:', 'ltl-bookings' ); ?>
                            <input type="date" name="time_off_start" required>
                        </label>
                        <label>
                            <?php esc_html_e( 'To:', 'ltl-bookings' ); ?>
                            <input type="date" name="time_off_end" required>
                        </label>
                    </div>
                    <label>
                        <?php esc_html_e( 'Reason (optional):', 'ltl-bookings' ); ?>
                        <textarea name="time_off_reason" rows="2"></textarea>
                    </label>
                    <button type="submit" class="ltlb-btn ltlb-btn-secondary">
                        <?php esc_html_e( 'Request Time Off', 'ltl-bookings' ); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get staff appointments
     * 
     * @param int $staff_id
     * @param string $type Type: upcoming, pending
     * @return array Appointments
     */
    private function get_staff_appointments( int $staff_id, string $type ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $services_table = $wpdb->prefix . 'ltlb_services';
        $locations_table = $wpdb->prefix . 'ltlb_locations';

        $now = current_time( 'mysql' );

        $where = "a.staff_id = %d";
        $params = [ $staff_id ];

        switch ( $type ) {
            case 'upcoming':
                $where .= " AND a.start_at > %s AND a.status IN ('confirmed')";
                $params[] = $now;
                break;
            case 'pending':
                $where .= " AND a.status = 'pending'";
                break;
        }

        $query = "SELECT 
                    a.*,
                    s.name as service_name,
                    l.name as location_name,
                    u.display_name as customer_name,
                    u.user_email as customer_email,
                    um.meta_value as customer_phone
                  FROM $appointments_table a
                  LEFT JOIN $services_table s ON a.service_id = s.id
                  LEFT JOIN $locations_table l ON a.location_id = l.id
                  LEFT JOIN {$wpdb->users} u ON a.customer_id = u.ID
                  LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
                  WHERE $where
                  ORDER BY a.start_at ASC";

        return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
    }

    /**
     * Get staff availability
     * 
     * @param int $staff_id
     * @return array Availability by day
     */
    private function get_staff_availability( int $staff_id ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_staff_hours';
        
        $hours = $wpdb->get_results( $wpdb->prepare(
            "SELECT day_of_week, start_time, end_time FROM $table WHERE staff_id = %d",
            $staff_id
        ), OBJECT_K );

        return $hours;
    }

    /**
     * Check if user is staff member
     * 
     * @param int $user_id
     * @return bool Is staff
     */
    private function is_staff_member( int $user_id ): bool {
        // Check if user has staff role or capability
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return false;
        }

        // Check for admin or staff role
        if ( in_array( 'administrator', $user->roles ) || in_array( 'ltlb_staff', $user->roles ) ) {
            return true;
        }

        // Check if user has any staff assignments
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_staff_hours';
        $has_hours = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE staff_id = %d",
            $user_id
        ) );

        return intval( $has_hours ) > 0;
    }

    /**
     * AJAX: Get schedule
     */
    public function ajax_get_schedule() {
        check_ajax_referer( 'ltlb_staff_portal', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Not logged in', 'ltl-bookings' ) ] );
        }

        $staff_id = get_current_user_id();
        $type = sanitize_text_field( $_POST['type'] ?? 'upcoming' );
        
        $appointments = $this->get_staff_appointments( $staff_id, $type );

        wp_send_json_success( [ 'appointments' => $appointments ] );
    }

    /**
     * AJAX: Update availability
     */
    public function ajax_update_availability() {
        check_ajax_referer( 'ltlb_staff_portal', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Not logged in', 'ltl-bookings' ) ] );
        }

        $staff_id = get_current_user_id();
        
        if ( ! $this->is_staff_member( $staff_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Access denied', 'ltl-bookings' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_staff_hours';

        // Clear existing hours
        $wpdb->delete( $table, [ 'staff_id' => $staff_id ], [ '%d' ] );

        // Insert new hours
        $available = $_POST['available'] ?? [];
        $start_times = $_POST['start_time'] ?? [];
        $end_times = $_POST['end_time'] ?? [];

        foreach ( $available as $day => $enabled ) {
            if ( ! empty( $enabled ) && isset( $start_times[$day] ) && isset( $end_times[$day] ) ) {
                $wpdb->insert( $table, [
                    'staff_id' => $staff_id,
                    'day_of_week' => intval( $day ),
                    'start_time' => sanitize_text_field( $start_times[$day] ),
                    'end_time' => sanitize_text_field( $end_times[$day] )
                ], [ '%d', '%d', '%s', '%s' ] );
            }
        }

        wp_send_json_success( [ 'message' => __( 'Availability updated', 'ltl-bookings' ) ] );
    }

    /**
     * AJAX: Accept booking
     */
    public function ajax_accept_booking() {
        check_ajax_referer( 'ltlb_staff_portal', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Not logged in', 'ltl-bookings' ) ] );
        }

        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        $staff_id = get_current_user_id();

        if ( ! $this->verify_staff_booking( $booking_id, $staff_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Access denied', 'ltl-bookings' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_appointments';

        $wpdb->update(
            $table,
            [ 'status' => 'confirmed', 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $booking_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        do_action( 'ltlb_booking_accepted_by_staff', $booking_id, $staff_id );

        wp_send_json_success( [ 'message' => __( 'Booking accepted', 'ltl-bookings' ) ] );
    }

    /**
     * AJAX: Decline booking
     */
    public function ajax_decline_booking() {
        check_ajax_referer( 'ltlb_staff_portal', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Not logged in', 'ltl-bookings' ) ] );
        }

        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        $reason = sanitize_text_field( $_POST['reason'] ?? '' );
        $staff_id = get_current_user_id();

        if ( ! $this->verify_staff_booking( $booking_id, $staff_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Access denied', 'ltl-bookings' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_appointments';

        $wpdb->update(
            $table,
            [ 'status' => 'cancelled', 'notes' => $reason, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $booking_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        do_action( 'ltlb_booking_declined_by_staff', $booking_id, $staff_id, $reason );

        wp_send_json_success( [ 'message' => __( 'Booking declined', 'ltl-bookings' ) ] );
    }

    /**
     * Verify staff booking ownership
     * 
     * @param int $booking_id
     * @param int $staff_id
     * @return bool Is assigned to staff
     */
    private function verify_staff_booking( int $booking_id, int $staff_id ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_appointments';
        $assigned_staff = $wpdb->get_var( $wpdb->prepare(
            "SELECT staff_id FROM $table WHERE id = %d",
            $booking_id
        ) );

        return intval( $assigned_staff ) === $staff_id;
    }
}

// Initialize
new LTLB_Staff_Portal();
