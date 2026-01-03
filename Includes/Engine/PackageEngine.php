<?php
/**
 * Package Engine
 * 
 * Manages service packages like 5-session cards, 10-visit passes, and subscriptions.
 * Tracks remaining balance, expiration dates, and usage history.
 * Supports staff/service scope restrictions.
 *
 * @package LTL_Bookings
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LTLB_Package_Engine {
    
    /**
     * Initialize package system
     */
    public static function init(): void {
        // Filters
        add_filter( 'ltlb_booking_payment_options', [ __CLASS__, 'add_package_payment_option' ], 10, 2 );
        add_filter( 'ltlb_calculate_booking_price', [ __CLASS__, 'apply_package_discount' ], 10, 2 );
        
        // Actions
        add_action( 'ltlb_booking_confirmed', [ __CLASS__, 'redeem_package_credit' ], 10, 1 );
        add_action( 'ltlb_booking_cancelled', [ __CLASS__, 'refund_package_credit' ], 10, 1 );
        
        // Admin
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ], 20 );
        
        // AJAX
        add_action( 'wp_ajax_ltlb_create_package', [ __CLASS__, 'ajax_create_package' ] );
        add_action( 'wp_ajax_ltlb_purchase_package', [ __CLASS__, 'ajax_purchase_package' ] );
        add_action( 'wp_ajax_nopriv_ltlb_purchase_package', [ __CLASS__, 'ajax_purchase_package' ] );
        add_action( 'wp_ajax_ltlb_get_customer_packages', [ __CLASS__, 'ajax_get_customer_packages' ] );
        add_action( 'wp_ajax_nopriv_ltlb_get_customer_packages', [ __CLASS__, 'ajax_get_customer_packages' ] );
        
        // Shortcodes
        add_shortcode( 'ltlb_packages', [ __CLASS__, 'render_packages_list' ] );
        add_shortcode( 'ltlb_my_packages', [ __CLASS__, 'render_customer_packages' ] );
        
        // Cron for expiration warnings
        add_action( 'ltlb_check_package_expiration', [ __CLASS__, 'check_expiring_packages' ] );
        
        if ( ! wp_next_scheduled( 'ltlb_check_package_expiration' ) ) {
            wp_schedule_event( time(), 'daily', 'ltlb_check_package_expiration' );
        }
    }
    
    /**
     * Create package definition
     *
     * @param array $package_data Package configuration
     * @return int|false Package ID or false
     */
    public static function create_package( array $package_data ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_packages';
        
        $result = $wpdb->insert( $table, [
            'name' => sanitize_text_field( $package_data['name'] ?? '' ),
            'description' => sanitize_textarea_field( $package_data['description'] ?? '' ),
            'credits' => intval( $package_data['credits'] ?? 0 ),
            'price_cents' => intval( $package_data['price_cents'] ?? 0 ),
            'currency' => sanitize_text_field( $package_data['currency'] ?? 'EUR' ),
            'validity_days' => intval( $package_data['validity_days'] ?? 0 ),
            'service_scope' => sanitize_text_field( $package_data['service_scope'] ?? 'all' ), // 'all', 'specific'
            'allowed_service_ids' => wp_json_encode( $package_data['allowed_service_ids'] ?? [] ),
            'staff_scope' => sanitize_text_field( $package_data['staff_scope'] ?? 'all' ), // 'all', 'specific'
            'allowed_staff_ids' => wp_json_encode( $package_data['allowed_staff_ids'] ?? [] ),
            'is_active' => isset( $package_data['is_active'] ) ? intval( $package_data['is_active'] ) : 1,
            'created_at' => current_time( 'mysql' )
        ], [ '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ] );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Purchase package for customer
     *
     * @param int $package_id Package definition ID
     * @param int $customer_id Customer ID
     * @param array $payment_data Payment information
     * @return int|false Customer package ID or false
     */
    public static function purchase_package( int $package_id, int $customer_id, array $payment_data = [] ) {
        global $wpdb;
        
        $package = self::get_package( $package_id );
        
        if ( ! $package || ! $package['is_active'] ) {
            return false;
        }
        
        $customer_packages_table = $wpdb->prefix . 'ltlb_customer_packages';
        
        $expires_at = null;
        if ( $package['validity_days'] > 0 ) {
            $expires_at = date( 'Y-m-d H:i:s', strtotime( '+' . $package['validity_days'] . ' days' ) );
        }
        
        $result = $wpdb->insert( $customer_packages_table, [
            'package_id' => $package_id,
            'customer_id' => $customer_id,
            'credits_total' => $package['credits'],
            'credits_remaining' => $package['credits'],
            'expires_at' => $expires_at,
            'payment_status' => $payment_data['status'] ?? 'paid',
            'payment_method' => $payment_data['method'] ?? '',
            'payment_ref' => $payment_data['ref'] ?? '',
            'purchased_at' => current_time( 'mysql' )
        ], [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ] );
        
        if ( ! $result ) {
            return false;
        }
        
        $customer_package_id = $wpdb->insert_id;
        
        do_action( 'ltlb_package_purchased', $customer_package_id, $package_id, $customer_id );
        
        // Send confirmation email
        self::send_purchase_confirmation( $customer_package_id );
        
        return $customer_package_id;
    }
    
    /**
     * Redeem package credit for booking
     *
     * @param int $booking_id Booking ID
     * @return bool Success
     */
    public static function redeem_package_credit( int $booking_id ): bool {
        global $wpdb;
        
        $booking = ( new LTLB_AppointmentRepository() )->get_by_id( $booking_id );
        
        if ( ! $booking ) {
            return false;
        }
        
        // Check if booking used a package
        $package_id = intval( $booking['package_id'] ?? 0 );
        
        if ( ! $package_id ) {
            return false;
        }
        
        $customer_packages_table = $wpdb->prefix . 'ltlb_customer_packages';
        $usage_table = $wpdb->prefix . 'ltlb_package_usage';
        
        // Get customer package
        $customer_package = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$customer_packages_table} WHERE id = %d",
            $package_id
        ), ARRAY_A );
        
        if ( ! $customer_package || $customer_package['credits_remaining'] <= 0 ) {
            return false;
        }
        
        // Check expiration
        if ( $customer_package['expires_at'] && strtotime( $customer_package['expires_at'] ) < time() ) {
            return false;
        }
        
        // Deduct credit
        $wpdb->update(
            $customer_packages_table,
            [ 'credits_remaining' => $customer_package['credits_remaining'] - 1 ],
            [ 'id' => $package_id ],
            [ '%d' ],
            [ '%d' ]
        );
        
        // Log usage
        $wpdb->insert( $usage_table, [
            'customer_package_id' => $package_id,
            'booking_id' => $booking_id,
            'credits_used' => 1,
            'used_at' => current_time( 'mysql' )
        ], [ '%d', '%d', '%d', '%s' ] );
        
        do_action( 'ltlb_package_credit_redeemed', $package_id, $booking_id );
        
        return true;
    }
    
    /**
     * Refund package credit when booking is cancelled
     *
     * @param int $booking_id Booking ID
     * @return bool Success
     */
    public static function refund_package_credit( int $booking_id ): bool {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'ltlb_package_usage';
        $customer_packages_table = $wpdb->prefix . 'ltlb_customer_packages';
        
        // Find usage record
        $usage = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$usage_table} WHERE booking_id = %d",
            $booking_id
        ), ARRAY_A );
        
        if ( ! $usage ) {
            return false;
        }
        
        $customer_package_id = intval( $usage['customer_package_id'] );
        $credits_used = intval( $usage['credits_used'] );
        
        // Refund credit
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$customer_packages_table} SET credits_remaining = credits_remaining + %d WHERE id = %d",
            $credits_used, $customer_package_id
        ) );
        
        // Delete usage record
        $wpdb->delete( $usage_table, [ 'id' => $usage['id'] ], [ '%d' ] );
        
        do_action( 'ltlb_package_credit_refunded', $customer_package_id, $booking_id );
        
        return true;
    }
    
    /**
     * Get package definition
     */
    public static function get_package( int $package_id ): ?array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_packages';
        
        $package = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $package_id
        ), ARRAY_A );
        
        if ( ! $package ) {
            return null;
        }
        
        // Decode JSON fields
        $package['allowed_service_ids'] = json_decode( $package['allowed_service_ids'] ?? '[]', true );
        $package['allowed_staff_ids'] = json_decode( $package['allowed_staff_ids'] ?? '[]', true );
        
        return $package;
    }
    
    /**
     * Get active packages for customer
     *
     * @param int $customer_id Customer ID
     * @return array Active packages
     */
    public static function get_customer_packages( int $customer_id ): array {
        global $wpdb;
        
        $customer_packages_table = $wpdb->prefix . 'ltlb_customer_packages';
        $packages_table = $wpdb->prefix . 'ltlb_packages';
        
        $query = $wpdb->prepare(
            "SELECT cp.*, p.name, p.description, p.service_scope, p.staff_scope
             FROM {$customer_packages_table} cp
             INNER JOIN {$packages_table} p ON cp.package_id = p.id
             WHERE cp.customer_id = %d 
             AND cp.credits_remaining > 0
             AND (cp.expires_at IS NULL OR cp.expires_at > NOW())
             AND cp.payment_status = 'paid'
             ORDER BY cp.expires_at ASC",
            $customer_id
        );
        
        return $wpdb->get_results( $query, ARRAY_A ) ?: [];
    }
    
    /**
     * Check if package can be used for booking
     *
     * @param int $customer_package_id Customer package ID
     * @param array $booking_data Booking data
     * @return bool Can use
     */
    public static function can_use_for_booking( int $customer_package_id, array $booking_data ): bool {
        global $wpdb;
        
        $customer_packages_table = $wpdb->prefix . 'ltlb_customer_packages';
        
        $customer_package = $wpdb->get_row( $wpdb->prepare(
            "SELECT cp.*, p.service_scope, p.allowed_service_ids, p.staff_scope, p.allowed_staff_ids
             FROM {$customer_packages_table} cp
             INNER JOIN {$wpdb->prefix}ltlb_packages p ON cp.package_id = p.id
             WHERE cp.id = %d",
            $customer_package_id
        ), ARRAY_A );
        
        if ( ! $customer_package ) {
            return false;
        }
        
        // Check credits
        if ( $customer_package['credits_remaining'] <= 0 ) {
            return false;
        }
        
        // Check expiration
        if ( $customer_package['expires_at'] && strtotime( $customer_package['expires_at'] ) < time() ) {
            return false;
        }
        
        // Check service scope
        if ( $customer_package['service_scope'] === 'specific' ) {
            $allowed_service_ids = json_decode( $customer_package['allowed_service_ids'] ?? '[]', true );
            $booking_service_id = intval( $booking_data['service_id'] ?? 0 );
            
            if ( ! in_array( $booking_service_id, $allowed_service_ids, true ) ) {
                return false;
            }
        }
        
        // Check staff scope
        if ( $customer_package['staff_scope'] === 'specific' ) {
            $allowed_staff_ids = json_decode( $customer_package['allowed_staff_ids'] ?? '[]', true );
            $booking_staff_id = intval( $booking_data['staff_id'] ?? 0 );
            
            if ( $booking_staff_id && ! in_array( $booking_staff_id, $allowed_staff_ids, true ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get package usage history
     *
     * @param int $customer_package_id Customer package ID
     * @return array Usage records
     */
    public static function get_usage_history( int $customer_package_id ): array {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'ltlb_package_usage';
        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        
        $query = $wpdb->prepare(
            "SELECT u.*, a.date_start, a.date_end, a.service_id
             FROM {$usage_table} u
             INNER JOIN {$appointments_table} a ON u.booking_id = a.id
             WHERE u.customer_package_id = %d
             ORDER BY u.used_at DESC",
            $customer_package_id
        );
        
        return $wpdb->get_results( $query, ARRAY_A ) ?: [];
    }
    
    /**
     * Check for expiring packages and send warnings
     */
    public static function check_expiring_packages(): void {
        global $wpdb;
        
        $customer_packages_table = $wpdb->prefix . 'ltlb_customer_packages';
        
        // Find packages expiring in 7 days
        $expiring = $wpdb->get_results(
            "SELECT cp.*, c.email, c.first_name, p.name as package_name
             FROM {$customer_packages_table} cp
             INNER JOIN {$wpdb->prefix}ltlb_customers c ON cp.customer_id = c.id
             INNER JOIN {$wpdb->prefix}ltlb_packages p ON cp.package_id = p.id
             WHERE cp.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
             AND cp.credits_remaining > 0
             AND cp.expiration_warning_sent = 0",
            ARRAY_A
        );
        
        foreach ( $expiring as $package ) {
            self::send_expiration_warning( $package );
            
            // Mark as warned
            $wpdb->update(
                $customer_packages_table,
                [ 'expiration_warning_sent' => 1 ],
                [ 'id' => $package['id'] ],
                [ '%d' ],
                [ '%d' ]
            );
        }
    }
    
    /**
     * Send purchase confirmation email
     */
    private static function send_purchase_confirmation( int $customer_package_id ): void {
        global $wpdb;
        
        $customer_packages_table = $wpdb->prefix . 'ltlb_customer_packages';
        
        $data = $wpdb->get_row( $wpdb->prepare(
            "SELECT cp.*, c.email, c.first_name, p.name, p.credits, p.validity_days
             FROM {$customer_packages_table} cp
             INNER JOIN {$wpdb->prefix}ltlb_customers c ON cp.customer_id = c.id
             INNER JOIN {$wpdb->prefix}ltlb_packages p ON cp.package_id = p.id
             WHERE cp.id = %d",
            $customer_package_id
        ), ARRAY_A );
        
        if ( ! $data ) {
            return;
        }
        
        $to = $data['email'];
        $subject = sprintf( __( 'Your %s Package', 'ltl-bookings' ), $data['name'] );
        
        $message = sprintf(
            __( "Hi %s,\n\nThank you for purchasing the %s package!\n\nYou have %d credits available.\n\n", 'ltl-bookings' ),
            $data['first_name'],
            $data['name'],
            $data['credits']
        );
        
        if ( $data['expires_at'] ) {
            $message .= sprintf(
                __( "Valid until: %s\n\n", 'ltl-bookings' ),
                date_i18n( get_option( 'date_format' ), strtotime( $data['expires_at'] ) )
            );
        }
        
        $message .= __( "Best regards", 'ltl-bookings' );
        
        LTLB_Mailer::send( $to, $subject, $message );
    }
    
    /**
     * Send expiration warning email
     */
    private static function send_expiration_warning( array $package ): void {
        $to = $package['email'];
        $subject = sprintf( __( 'Your %s package expires soon', 'ltl-bookings' ), $package['package_name'] );
        
        $days_remaining = intval( ( strtotime( $package['expires_at'] ) - time() ) / 86400 );
        
        $message = sprintf(
            __( "Hi %s,\n\nYour %s package expires in %d days.\n\nYou still have %d credits remaining.\n\nDon't forget to use them!\n\nBest regards", 'ltl-bookings' ),
            $package['first_name'],
            $package['package_name'],
            $days_remaining,
            $package['credits_remaining']
        );
        
        LTLB_Mailer::send( $to, $subject, $message );
    }
    
    /**
     * Add package as payment option in booking flow
     */
    public static function add_package_payment_option( array $options, array $booking_data ): array {
        $customer_id = intval( $booking_data['customer_id'] ?? 0 );
        
        if ( ! $customer_id ) {
            return $options;
        }
        
        $packages = self::get_customer_packages( $customer_id );
        
        foreach ( $packages as $package ) {
            if ( self::can_use_for_booking( intval( $package['id'] ), $booking_data ) ) {
                $options['package_' . $package['id']] = sprintf(
                    __( 'Use Package: %s (%d credits left)', 'ltl-bookings' ),
                    $package['name'],
                    $package['credits_remaining']
                );
            }
        }
        
        return $options;
    }
    
    /**
     * Apply package discount
     */
    public static function apply_package_discount( int $price, array $booking_data ): int {
        $payment_method = $booking_data['payment_method'] ?? '';
        
        if ( strpos( (string) $payment_method, 'package_' ) === 0 ) {
            return 0; // Package covers full cost
        }
        
        return $price;
    }
    
    /**
     * Add admin menu
     */
    public static function add_menu(): void {
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Packages', 'ltl-bookings' ),
            __( 'Packages', 'ltl-bookings' ),
            'manage_options',
            'ltlb_packages',
            [ __CLASS__, 'render_admin_page' ]
        );
    }
    
    /**
     * Render admin page
     */
    public static function render_admin_page(): void {
        global $wpdb;
        
        $packages = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ltlb_packages ORDER BY name", ARRAY_A );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Packages', 'ltl-bookings' ); ?></h1>
            <button class="button button-primary" id="ltlb-create-package"><?php esc_html_e( 'Create Package', 'ltl-bookings' ); ?></button>
            
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'ltl-bookings' ); ?></th>
                        <th><?php esc_html_e( 'Credits', 'ltl-bookings' ); ?></th>
                        <th><?php esc_html_e( 'Price', 'ltl-bookings' ); ?></th>
                        <th><?php esc_html_e( 'Validity', 'ltl-bookings' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ltl-bookings' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $packages as $package ): ?>
                        <tr>
                            <td><strong><?php echo esc_html( $package['name'] ); ?></strong></td>
                            <td><?php echo esc_html( $package['credits'] ); ?></td>
                            <td><?php echo esc_html( number_format( $package['price_cents'] / 100, 2 ) . ' ' . $package['currency'] ); ?></td>
                            <td><?php echo esc_html( $package['validity_days'] > 0 ? $package['validity_days'] . ' days' : 'Unlimited' ); ?></td>
                            <td><?php echo $package['is_active'] ? '✓ Active' : '✗ Inactive'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render packages list shortcode
     */
    public static function render_packages_list( $atts ): string {
        global $wpdb;
        
        $packages = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ltlb_packages WHERE is_active = 1 ORDER BY price_cents",
            ARRAY_A
        );
        
        if ( empty( $packages ) ) {
            return '<p>' . __( 'No packages available.', 'ltl-bookings' ) . '</p>';
        }
        
        $output = '<div class="ltlb-packages-list">';
        
        foreach ( $packages as $package ) {
            $output .= '<div class="ltlb-package-card">';
            $output .= '<h3>' . esc_html( $package['name'] ) . '</h3>';
            $output .= '<p>' . esc_html( $package['description'] ) . '</p>';
            $output .= '<p class="ltlb-package-credits">' . esc_html( $package['credits'] ) . ' ' . __( 'credits', 'ltl-bookings' ) . '</p>';
            $output .= '<p class="ltlb-package-price">' . esc_html( number_format( $package['price_cents'] / 100, 2 ) . ' ' . $package['currency'] ) . '</p>';
            
            if ( $package['validity_days'] > 0 ) {
                $output .= '<p class="ltlb-package-validity">' . sprintf( __( 'Valid for %d days', 'ltl-bookings' ), $package['validity_days'] ) . '</p>';
            }
            
            $output .= '<button class="button ltlb-purchase-package" data-package-id="' . esc_attr( $package['id'] ) . '">' . __( 'Purchase', 'ltl-bookings' ) . '</button>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render customer's packages shortcode
     */
    public static function render_customer_packages( $atts ): string {
        $customer_id = get_current_user_id();
        
        if ( ! $customer_id ) {
            return '<p>' . __( 'Please log in to view your packages.', 'ltl-bookings' ) . '</p>';
        }
        
        $packages = self::get_customer_packages( $customer_id );
        
        if ( empty( $packages ) ) {
            return '<p>' . __( 'You don\'t have any active packages.', 'ltl-bookings' ) . '</p>';
        }
        
        $output = '<div class="ltlb-my-packages">';
        
        foreach ( $packages as $package ) {
            $output .= '<div class="ltlb-my-package-card">';
            $output .= '<h4>' . esc_html( $package['name'] ) . '</h4>';
            $output .= '<p>' . sprintf( __( '%d of %d credits remaining', 'ltl-bookings' ), $package['credits_remaining'], $package['credits_total'] ) . '</p>';
            
            if ( $package['expires_at'] ) {
                $output .= '<p>' . sprintf( __( 'Expires: %s', 'ltl-bookings' ), date_i18n( get_option( 'date_format' ), strtotime( $package['expires_at'] ) ) ) . '</p>';
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * AJAX handlers
     */
    public static function ajax_create_package(): void {
        check_ajax_referer( 'ltlb_packages', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        
        $package_data = $_POST['package'] ?? [];
        
        $package_id = self::create_package( $package_data );
        
        if ( $package_id ) {
            wp_send_json_success( [ 'package_id' => $package_id ] );
        } else {
            wp_send_json_error();
        }
    }
    
    public static function ajax_purchase_package(): void {
        $package_id = intval( $_POST['package_id'] ?? 0 );
        $customer_id = intval( $_POST['customer_id'] ?? get_current_user_id() );
        
        if ( ! $package_id || ! $customer_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request', 'ltl-bookings' ) ] );
        }
        
        $payment_data = [
            'status' => 'paid',
            'method' => sanitize_text_field( $_POST['payment_method'] ?? '' ),
            'ref' => sanitize_text_field( $_POST['payment_ref'] ?? '' )
        ];
        
        $customer_package_id = self::purchase_package( $package_id, $customer_id, $payment_data );
        
        if ( $customer_package_id ) {
            wp_send_json_success( [
                'message' => __( 'Package purchased successfully!', 'ltl-bookings' ),
                'customer_package_id' => $customer_package_id
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to purchase package', 'ltl-bookings' ) ] );
        }
    }
    
    public static function ajax_get_customer_packages(): void {
        $customer_id = intval( $_REQUEST['customer_id'] ?? get_current_user_id() );
        
        if ( ! $customer_id ) {
            wp_send_json_error();
        }
        
        $packages = self::get_customer_packages( $customer_id );
        
        wp_send_json_success( $packages );
    }
}

// Initialize
LTLB_Package_Engine::init();
