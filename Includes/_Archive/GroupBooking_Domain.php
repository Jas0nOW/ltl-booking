<?php
/**
 * Group Bookings
 * 
 * Handles group bookings with multiple participants.
 * Manages capacity, participant data, and check-in lists.
 * Perfect for classes, courses, tours, and group activities.
 *
 * @package LTL_Bookings
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LTLB_Group_Booking {
    
    /**
     * Initialize group bookings
     */
    public static function init(): void {
        // Filters
        add_filter( 'ltlb_booking_capacity_check', [ __CLASS__, 'check_capacity' ], 10, 3 );
        add_filter( 'ltlb_booking_price_calculation', [ __CLASS__, 'calculate_group_price' ], 10, 2 );
        
        // Actions
        add_action( 'ltlb_booking_created', [ __CLASS__, 'save_participants' ], 10, 1 );
        add_action( 'ltlb_booking_updated', [ __CLASS__, 'save_participants' ], 10, 1 );
        
        // Admin
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ], 20 );
        add_action( 'ltlb_appointment_detail_meta_box', [ __CLASS__, 'render_participants_box' ], 10, 1 );
        
        // AJAX
        add_action( 'wp_ajax_ltlb_add_participant', [ __CLASS__, 'ajax_add_participant' ] );
        add_action( 'wp_ajax_ltlb_remove_participant', [ __CLASS__, 'ajax_remove_participant' ] );
        add_action( 'wp_ajax_ltlb_check_in_participant', [ __CLASS__, 'ajax_check_in' ] );
        add_action( 'wp_ajax_ltlb_export_check_in_list', [ __CLASS__, 'ajax_export_check_in_list' ] );
        
        // Frontend
        add_shortcode( 'ltlb_group_booking_form', [ __CLASS__, 'render_booking_form' ] );
    }
    
    /**
     * Check if service has enough capacity
     *
     * @param bool $has_capacity Current capacity check result
     * @param int $service_id Service/Room ID
     * @param array $booking_data Booking data including participants
     * @return bool True if capacity available
     */
    public static function check_capacity( bool $has_capacity, int $service_id, array $booking_data ): bool {
        if ( ! $has_capacity ) {
            return false; // Already full
        }
        
        $service = ( new LTLB_ServiceRepository() )->get_by_id( $service_id );
        
        if ( ! $service ) {
            return false;
        }
        
        $max_capacity = intval( $service['max_capacity'] ?? 0 );
        
        if ( $max_capacity === 0 ) {
            return true; // Unlimited capacity
        }
        
        // Get current bookings for this slot
        $date_start = $booking_data['date_start'] ?? '';
        $date_end = $booking_data['date_end'] ?? '';
        
        if ( ! $date_start ) {
            return false;
        }
        
        $existing_participants = self::get_slot_participant_count( $service_id, $date_start, $date_end );
        $new_participants = count( $booking_data['participants'] ?? [] );
        
        return ( $existing_participants + $new_participants ) <= $max_capacity;
    }
    
    /**
     * Get current participant count for a slot
     */
    private static function get_slot_participant_count( int $service_id, string $date_start, string $date_end = '' ): int {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $participants_table = $wpdb->prefix . 'ltlb_booking_participants';
        
        // Get confirmed bookings for this slot
        $query = $wpdb->prepare(
            "SELECT a.id FROM {$appointments_table} a 
             WHERE a.service_id = %d 
             AND a.date_start = %s 
             AND a.status IN ('confirmed', 'pending')",
            $service_id, $date_start
        );
        
        $booking_ids = $wpdb->get_col( $query );
        
        if ( empty( $booking_ids ) ) {
            return 0;
        }
        
        // Count participants
        $placeholders = implode( ',', array_fill( 0, count( $booking_ids ), '%d' ) );
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$participants_table} WHERE booking_id IN ({$placeholders})",
            ...$booking_ids
        ) );
        
        return intval( $count );
    }
    
    /**
     * Calculate price for group booking
     *
     * @param int $price Current price in cents
     * @param array $booking_data Booking data
     * @return int Adjusted price
     */
    public static function calculate_group_price( int $price, array $booking_data ): int {
        $service = ( new LTLB_ServiceRepository() )->get_by_id( intval( $booking_data['service_id'] ?? 0 ) );
        
        if ( ! $service ) {
            return $price;
        }
        
        $pricing_type = $service['pricing_type'] ?? 'fixed';
        
        if ( $pricing_type === 'per_person' ) {
            $participant_count = count( $booking_data['participants'] ?? [] );
            return $price * max( 1, $participant_count );
        }
        
        return $price;
    }
    
    /**
     * Save participants when booking is created/updated
     */
    public static function save_participants( int $booking_id ): void {
        if ( ! isset( $_POST['participants'] ) || ! is_array( $_POST['participants'] ) ) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_booking_participants';
        
        // Delete existing participants
        $wpdb->delete( $table, [ 'booking_id' => $booking_id ], [ '%d' ] );
        
        // Insert new participants
        foreach ( $_POST['participants'] as $participant ) {
            $wpdb->insert( $table, [
                'booking_id' => $booking_id,
                'first_name' => sanitize_text_field( $participant['first_name'] ?? '' ),
                'last_name' => sanitize_text_field( $participant['last_name'] ?? '' ),
                'email' => sanitize_email( $participant['email'] ?? '' ),
                'phone' => sanitize_text_field( $participant['phone'] ?? '' ),
                'age' => intval( $participant['age'] ?? 0 ),
                'notes' => sanitize_textarea_field( $participant['notes'] ?? '' ),
                'checked_in' => 0,
                'created_at' => current_time( 'mysql' )
            ], [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ] );
        }
    }
    
    /**
     * Get participants for booking
     *
     * @param int $booking_id Booking ID
     * @return array Participants
     */
    public static function get_participants( int $booking_id ): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_booking_participants';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY id",
            $booking_id
        ), ARRAY_A ) ?: [];
    }
    
    /**
     * Add participant to booking
     *
     * @param int $booking_id Booking ID
     * @param array $participant_data Participant data
     * @return int|false Participant ID or false
     */
    public static function add_participant( int $booking_id, array $participant_data ) {
        global $wpdb;
        
        // Check capacity
        $booking = ( new LTLB_AppointmentRepository() )->get_by_id( $booking_id );
        if ( ! $booking ) {
            return false;
        }
        
        $service_id = intval( $booking['service_id'] );
        $service = ( new LTLB_ServiceRepository() )->get_by_id( $service_id );
        
        if ( ! $service ) {
            return false;
        }
        
        $max_capacity = intval( $service['max_capacity'] ?? 0 );
        
        if ( $max_capacity > 0 ) {
            $current_count = self::get_slot_participant_count(
                $service_id,
                $booking['date_start'],
                $booking['date_end'] ?? ''
            );
            
            if ( $current_count >= $max_capacity ) {
                return false; // Full
            }
        }
        
        // Insert participant
        $table = $wpdb->prefix . 'ltlb_booking_participants';
        
        $result = $wpdb->insert( $table, [
            'booking_id' => $booking_id,
            'first_name' => sanitize_text_field( $participant_data['first_name'] ?? '' ),
            'last_name' => sanitize_text_field( $participant_data['last_name'] ?? '' ),
            'email' => sanitize_email( $participant_data['email'] ?? '' ),
            'phone' => sanitize_text_field( $participant_data['phone'] ?? '' ),
            'age' => intval( $participant_data['age'] ?? 0 ),
            'notes' => sanitize_textarea_field( $participant_data['notes'] ?? '' ),
            'checked_in' => 0,
            'created_at' => current_time( 'mysql' )
        ], [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ] );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Remove participant
     *
     * @param int $participant_id Participant ID
     * @return bool Success
     */
    public static function remove_participant( int $participant_id ): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_booking_participants';
        
        return (bool) $wpdb->delete( $table, [ 'id' => $participant_id ], [ '%d' ] );
    }
    
    /**
     * Check in participant
     *
     * @param int $participant_id Participant ID
     * @return bool Success
     */
    public static function check_in( int $participant_id ): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_booking_participants';
        
        return (bool) $wpdb->update(
            $table,
            [
                'checked_in' => 1,
                'checked_in_at' => current_time( 'mysql' )
            ],
            [ 'id' => $participant_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );
    }
    
    /**
     * Get check-in list for service/date
     *
     * @param int $service_id Service ID
     * @param string $date Date
     * @return array Check-in data
     */
    public static function get_check_in_list( int $service_id, string $date ): array {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $participants_table = $wpdb->prefix . 'ltlb_booking_participants';
        
        $query = $wpdb->prepare(
            "SELECT p.*, a.date_start, a.date_end, a.status as booking_status
             FROM {$participants_table} p
             INNER JOIN {$appointments_table} a ON p.booking_id = a.id
             WHERE a.service_id = %d 
             AND DATE(a.date_start) = %s
             AND a.status IN ('confirmed', 'pending')
             ORDER BY p.last_name, p.first_name",
            $service_id, $date
        );
        
        return $wpdb->get_results( $query, ARRAY_A ) ?: [];
    }
    
    /**
     * Render participants meta box in admin
     */
    public static function render_participants_box( int $booking_id ): void {
        $participants = self::get_participants( $booking_id );
        
        ?>
        <div class="ltlb-participants-box">
            <h3><?php esc_html_e( 'Participants', 'ltl-bookings' ); ?></h3>
            
            <?php if ( empty( $participants ) ): ?>
                <p><?php esc_html_e( 'No participants registered.', 'ltl-bookings' ); ?></p>
            <?php else: ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Phone', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Age', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Checked In', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'ltl-bookings' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $participants as $participant ): ?>
                            <tr data-participant-id="<?php echo esc_attr( $participant['id'] ); ?>">
                                <td><?php echo esc_html( $participant['first_name'] . ' ' . $participant['last_name'] ); ?></td>
                                <td><?php echo esc_html( $participant['email'] ); ?></td>
                                <td><?php echo esc_html( $participant['phone'] ); ?></td>
                                <td><?php echo esc_html( $participant['age'] ?: '—' ); ?></td>
                                <td>
                                    <?php if ( $participant['checked_in'] ): ?>
                                        <span style="color: green;">✓ <?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $participant['checked_in_at'] ) ) ); ?></span>
                                    <?php else: ?>
                                        <button class="button ltlb-check-in-btn" data-id="<?php echo esc_attr( $participant['id'] ); ?>">
                                            <?php esc_html_e( 'Check In', 'ltl-bookings' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="button ltlb-remove-participant-btn" data-id="<?php echo esc_attr( $participant['id'] ); ?>">
                                        <?php esc_html_e( 'Remove', 'ltl-bookings' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <button class="button button-primary ltlb-add-participant-btn" data-booking-id="<?php echo esc_attr( $booking_id ); ?>" style="margin-top: 10px;">
                <?php esc_html_e( 'Add Participant', 'ltl-bookings' ); ?>
            </button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.ltlb-check-in-btn').on('click', function() {
                var $btn = $(this);
                var id = $btn.data('id');
                
                $.post(ajaxurl, {
                    action: 'ltlb_check_in_participant',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_participants' ); ?>',
                    participant_id: id
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
            
            $('.ltlb-remove-participant-btn').on('click', function() {
                if (!confirm('<?php echo esc_js( __( 'Remove this participant?', 'ltl-bookings' ) ); ?>')) return;
                
                var id = $(this).data('id');
                
                $.post(ajaxurl, {
                    action: 'ltlb_remove_participant',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_participants' ); ?>',
                    participant_id: id
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
            
            $('.ltlb-add-participant-btn').on('click', function() {
                // Would open modal for adding participant
                alert('<?php echo esc_js( __( 'Add participant modal would open here', 'ltl-bookings' ) ); ?>');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add admin menu for check-in list
     */
    public static function add_menu(): void {
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Check-In List', 'ltl-bookings' ),
            __( 'Check-In List', 'ltl-bookings' ),
            'ltlb_manage_bookings',
            'ltlb_check_in',
            [ __CLASS__, 'render_check_in_page' ]
        );
    }
    
    /**
     * Render check-in list page
     */
    public static function render_check_in_page(): void {
        $service_id = intval( $_GET['service_id'] ?? 0 );
        $date = sanitize_text_field( $_GET['date'] ?? current_time( 'Y-m-d' ) );
        
        $services = ( new LTLB_ServiceRepository() )->get_all();
        $check_in_list = $service_id ? self::get_check_in_list( $service_id, $date ) : [];
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Check-In List', 'ltl-bookings' ); ?></h1>
            
            <form method="get" action="">
                <input type="hidden" name="page" value="ltlb_check_in">
                
                <label><?php esc_html_e( 'Service/Class:', 'ltl-bookings' ); ?></label>
                <select name="service_id" required>
                    <option value=""><?php esc_html_e( 'Select...', 'ltl-bookings' ); ?></option>
                    <?php foreach ( $services as $service ): ?>
                        <option value="<?php echo esc_attr( $service['id'] ); ?>" <?php selected( $service_id, $service['id'] ); ?>>
                            <?php echo esc_html( $service['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label><?php esc_html_e( 'Date:', 'ltl-bookings' ); ?></label>
                <input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" required>
                
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Show List', 'ltl-bookings' ); ?></button>
            </form>
            
            <?php if ( $service_id && ! empty( $check_in_list ) ): ?>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( '#', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Name', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Phone', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Time', 'ltl-bookings' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ltl-bookings' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ( $check_in_list as $participant ): ?>
                            <tr class="<?php echo $participant['checked_in'] ? 'ltlb-checked-in' : ''; ?>">
                                <td><?php echo esc_html( $i++ ); ?></td>
                                <td><strong><?php echo esc_html( $participant['first_name'] . ' ' . $participant['last_name'] ); ?></strong></td>
                                <td><?php echo esc_html( $participant['email'] ); ?></td>
                                <td><?php echo esc_html( $participant['phone'] ); ?></td>
                                <td><?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $participant['date_start'] ) ) ); ?></td>
                                <td>
                                    <?php if ( $participant['checked_in'] ): ?>
                                        <span style="color: green; font-weight: bold;">✓ <?php esc_html_e( 'Checked In', 'ltl-bookings' ); ?></span>
                                    <?php else: ?>
                                        <button class="button ltlb-quick-check-in" data-id="<?php echo esc_attr( $participant['id'] ); ?>">
                                            <?php esc_html_e( 'Check In', 'ltl-bookings' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <button class="button" id="ltlb-export-check-in" data-service="<?php echo esc_attr( $service_id ); ?>" data-date="<?php echo esc_attr( $date ); ?>" style="margin-top: 10px;">
                    <?php esc_html_e( 'Export to CSV', 'ltl-bookings' ); ?>
                </button>
            <?php elseif ( $service_id ): ?>
                <p style="margin-top: 20px;"><?php esc_html_e( 'No participants for this date.', 'ltl-bookings' ); ?></p>
            <?php endif; ?>
        </div>
        
        <style>
            .ltlb-checked-in {
                background: #f0f9ff !important;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.ltlb-quick-check-in').on('click', function() {
                var $btn = $(this);
                var id = $btn.data('id');
                
                $.post(ajaxurl, {
                    action: 'ltlb_check_in_participant',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_participants' ); ?>',
                    participant_id: id
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
            
            $('#ltlb-export-check-in').on('click', function() {
                var service = $(this).data('service');
                var date = $(this).data('date');
                window.location.href = ajaxurl + '?action=ltlb_export_check_in_list&service_id=' + service + '&date=' + date + '&nonce=' + '<?php echo wp_create_nonce( 'ltlb_export' ); ?>';
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render frontend booking form with participants
     */
    public static function render_booking_form( $atts ): string {
        // Implementation would be added
        return '<div class="ltlb-group-booking-form">' . __( 'Group booking form would render here', 'ltl-bookings' ) . '</div>';
    }
    
    /**
     * AJAX handlers
     */
    public static function ajax_add_participant(): void {
        check_ajax_referer( 'ltlb_participants', 'nonce' );
        
        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        $participant_data = $_POST['participant'] ?? [];
        
        $participant_id = self::add_participant( $booking_id, $participant_data );
        
        if ( $participant_id ) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    public static function ajax_remove_participant(): void {
        check_ajax_referer( 'ltlb_participants', 'nonce' );
        
        $participant_id = intval( $_POST['participant_id'] ?? 0 );
        
        if ( self::remove_participant( $participant_id ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    public static function ajax_check_in(): void {
        check_ajax_referer( 'ltlb_participants', 'nonce' );
        
        $participant_id = intval( $_POST['participant_id'] ?? 0 );
        
        if ( self::check_in( $participant_id ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    public static function ajax_export_check_in_list(): void {
        check_ajax_referer( 'ltlb_export', 'nonce' );
        
        $service_id = intval( $_GET['service_id'] ?? 0 );
        $date = sanitize_text_field( $_GET['date'] ?? '' );
        
        $list = self::get_check_in_list( $service_id, $date );
        
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="check-in-list-' . $date . '.csv"' );
        
        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'Name', 'Email', 'Phone', 'Time', 'Checked In' ] );
        
        foreach ( $list as $participant ) {
            fputcsv( $output, [
                $participant['first_name'] . ' ' . $participant['last_name'],
                $participant['email'],
                $participant['phone'],
                date_i18n( get_option( 'time_format' ), strtotime( $participant['date_start'] ) ),
                $participant['checked_in'] ? 'Yes' : 'No'
            ] );
        }
        
        fclose( $output );
        exit;
    }
}

// Initialize
LTLB_Group_Booking::init();
