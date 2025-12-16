<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * WooCommerce Integration
 * 
 * Features:
 * - Optional routing of bookings as WooCommerce products
 * - Order status synchronization
 * - Tax logic integration
 * - Cart and checkout integration
 * - Product creation from services
 * 
 * @package LazyBookings
 */
class LTLB_WooCommerce_Integration {

    /**
     * Constructor
     */
    public function __construct() {
        if ( ! $this->is_woocommerce_active() ) {
            return;
        }

        // Hooks
        add_action( 'woocommerce_order_status_changed', [ $this, 'sync_order_status' ], 10, 3 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_booking_meta_to_order_item' ], 10, 4 );
        add_filter( 'woocommerce_cart_item_name', [ $this, 'modify_cart_item_name' ], 10, 3 );
        add_action( 'woocommerce_thankyou', [ $this, 'complete_booking_after_payment' ], 10, 1 );
        
        // Admin
        add_filter( 'woocommerce_order_item_get_formatted_meta_data', [ $this, 'format_booking_meta' ], 10, 2 );
    }

    /**
     * Check if WooCommerce is active
     * 
     * @return bool
     */
    private function is_woocommerce_active(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Check if WooCommerce integration is enabled
     * 
     * @return bool
     */
    public function is_enabled(): bool {
        return get_option( 'ltlb_woocommerce_enabled', '0' ) === '1';
    }

    /**
     * Create WooCommerce product from service
     * 
     * @param int $service_id
     * @return int|WP_Error Product ID or error
     */
    public function create_product_from_service( int $service_id ) {
        global $wpdb;

        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'not_enabled', __( 'WooCommerce integration is not enabled', 'ltl-bookings' ) );
        }

        $services_table = $wpdb->prefix . 'ltlb_services';
        $service = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $services_table WHERE id = %d",
            $service_id
        ) );

        if ( ! $service ) {
            return new WP_Error( 'service_not_found', __( 'Service not found', 'ltl-bookings' ) );
        }

        // Check if product already exists
        $existing_product_id = get_post_meta( $service_id, '_ltlb_woo_product_id', true );
        if ( $existing_product_id && get_post( $existing_product_id ) ) {
            return intval( $existing_product_id );
        }

        // Create WooCommerce product
        $product = new WC_Product_Simple();
        $product->set_name( $service->name );
        $product->set_description( $service->description ?? '' );
        $product->set_regular_price( $service->price / 100 ); // Convert cents to currency
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        $product->set_catalog_visibility( 'hidden' ); // Hide from shop catalog
        
        // Save product
        $product_id = $product->save();

        if ( ! $product_id ) {
            return new WP_Error( 'product_creation_failed', __( 'Failed to create WooCommerce product', 'ltl-bookings' ) );
        }

        // Link product to service
        update_post_meta( $product_id, '_ltlb_service_id', $service_id );
        update_post_meta( $service_id, '_ltlb_woo_product_id', $product_id );

        return $product_id;
    }

    /**
     * Add booking to WooCommerce cart
     * 
     * @param int $appointment_id Appointment ID
     * @return bool|WP_Error Success or error
     */
    public function add_booking_to_cart( int $appointment_id ) {
        global $wpdb;

        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'not_enabled', __( 'WooCommerce integration is not enabled', 'ltl-bookings' ) );
        }

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $appointments_table WHERE id = %d",
            $appointment_id
        ) );

        if ( ! $appointment ) {
            return new WP_Error( 'appointment_not_found', __( 'Appointment not found', 'ltl-bookings' ) );
        }

        // Get or create product
        $product_id = $this->create_product_from_service( $appointment->service_id );
        
        if ( is_wp_error( $product_id ) ) {
            return $product_id;
        }

        // Build cart item data
        $cart_item_data = [
            'ltlb_appointment_id' => $appointment_id,
            'ltlb_start_at' => $appointment->start_at,
            'ltlb_end_at' => $appointment->end_at,
            'ltlb_staff_id' => $appointment->staff_id
        ];

        // Add to cart
        $cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, [], $cart_item_data );

        if ( ! $cart_item_key ) {
            return new WP_Error( 'cart_add_failed', __( 'Failed to add booking to cart', 'ltl-bookings' ) );
        }

        // Update appointment with pending payment status
        $wpdb->update(
            $appointments_table,
            [ 'status' => 'pending_payment' ],
            [ 'id' => $appointment_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return true;
    }

    /**
     * Modify cart item display name
     * 
     * @param string $name Item name
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified name
     */
    public function modify_cart_item_name( string $name, array $cart_item, string $cart_item_key ): string {
        if ( empty( $cart_item['ltlb_appointment_id'] ) ) {
            return $name;
        }

        $start = $cart_item['ltlb_start_at'] ?? '';
        if ( $start ) {
            $formatted_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start ) );
            $name .= '<br><small>' . sprintf( __( 'Date: %s', 'ltl-bookings' ), $formatted_date ) . '</small>';
        }

        return $name;
    }

    /**
     * Add booking metadata to order item
     * 
     * @param WC_Order_Item_Product $item
     * @param string $cart_item_key
     * @param array $values
     * @param WC_Order $order
     */
    public function add_booking_meta_to_order_item( $item, string $cart_item_key, array $values, $order ) {
        if ( empty( $values['ltlb_appointment_id'] ) ) {
            return;
        }

        $item->add_meta_data( '_ltlb_appointment_id', $values['ltlb_appointment_id'], true );
        $item->add_meta_data( __( 'Appointment Date', 'ltl-bookings' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $values['ltlb_start_at'] ) ), true );
        
        if ( ! empty( $values['ltlb_staff_id'] ) ) {
            $staff = get_userdata( $values['ltlb_staff_id'] );
            if ( $staff ) {
                $item->add_meta_data( __( 'Staff Member', 'ltl-bookings' ), $staff->display_name, true );
            }
        }
    }

    /**
     * Format booking metadata display in admin
     * 
     * @param array $formatted_meta
     * @param WC_Order_Item $item
     * @return array
     */
    public function format_booking_meta( array $formatted_meta, $item ): array {
        foreach ( $formatted_meta as $key => $meta ) {
            if ( $meta->key === '_ltlb_appointment_id' ) {
                unset( $formatted_meta[ $key ] ); // Hide internal ID
            }
        }
        return $formatted_meta;
    }

    /**
     * Synchronize order status to appointment
     * 
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     */
    public function sync_order_status( int $order_id, string $old_status, string $new_status ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $appointment_id = $item->get_meta( '_ltlb_appointment_id' );
            
            if ( ! $appointment_id ) {
                continue;
            }

            $this->update_appointment_status_from_order( $appointment_id, $new_status, $order );
        }
    }

    /**
     * Update appointment status based on order status
     * 
     * @param int $appointment_id
     * @param string $order_status
     * @param WC_Order $order
     */
    private function update_appointment_status_from_order( int $appointment_id, string $order_status, $order ) {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';

        // Map WooCommerce order status to appointment status
        $status_map = [
            'pending' => 'pending_payment',
            'processing' => 'confirmed',
            'completed' => 'confirmed',
            'on-hold' => 'pending_payment',
            'cancelled' => 'cancelled',
            'refunded' => 'cancelled',
            'failed' => 'cancelled'
        ];

        $appointment_status = $status_map[ $order_status ] ?? 'pending';

        // Update appointment
        $wpdb->update(
            $appointments_table,
            [ 
                'status' => $appointment_status,
                'payment_status' => $order->is_paid() ? 'paid' : 'pending',
                'updated_at' => current_time( 'mysql' )
            ],
            [ 'id' => $appointment_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        // Store order ID reference
        update_post_meta( $appointment_id, '_ltlb_woo_order_id', $order->get_id() );

        // Trigger notifications
        if ( $appointment_status === 'confirmed' ) {
            do_action( 'ltlb_appointment_confirmed', $appointment_id );
        } elseif ( $appointment_status === 'cancelled' ) {
            do_action( 'ltlb_appointment_cancelled', $appointment_id );
        }
    }

    /**
     * Complete booking after successful payment
     * 
     * @param int $order_id
     */
    public function complete_booking_after_payment( int $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order || ! $order->is_paid() ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $appointment_id = $item->get_meta( '_ltlb_appointment_id' );
            
            if ( ! $appointment_id ) {
                continue;
            }

            // Generate invoice if enabled
            if ( class_exists( 'LTLB_Invoice_Engine' ) ) {
                $invoice_engine = new LTLB_Invoice_Engine();
                $invoice_engine->generate_invoice( $appointment_id );
            }

            // Send confirmation email
            do_action( 'ltlb_send_confirmation_email', $appointment_id );
        }
    }

    /**
     * Get appointment's WooCommerce order
     * 
     * @param int $appointment_id
     * @return WC_Order|null
     */
    public function get_appointment_order( int $appointment_id ): ?WC_Order {
        $order_id = get_post_meta( $appointment_id, '_ltlb_woo_order_id', true );
        
        if ( ! $order_id ) {
            return null;
        }

        return wc_get_order( $order_id );
    }

    /**
     * Calculate tax for booking using WooCommerce tax rules
     * 
     * @param int $appointment_id
     * @return array Tax breakdown
     */
    public function calculate_booking_tax( int $appointment_id ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $appointments_table WHERE id = %d",
            $appointment_id
        ) );

        if ( ! $appointment ) {
            return [ 'subtotal' => 0, 'tax' => 0, 'total' => 0 ];
        }

        // Get product
        $product_id = get_post_meta( $appointment->service_id, '_ltlb_woo_product_id', true );
        
        if ( ! $product_id ) {
            return [
                'subtotal' => $appointment->price,
                'tax' => 0,
                'total' => $appointment->price
            ];
        }

        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return [
                'subtotal' => $appointment->price,
                'tax' => 0,
                'total' => $appointment->price
            ];
        }

        // Calculate tax using WooCommerce
        $price = $appointment->price / 100;
        $tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
        $taxes = WC_Tax::calc_tax( $price, $tax_rates, false );
        $tax_amount = array_sum( $taxes );

        return [
            'subtotal' => $appointment->price,
            'tax' => intval( $tax_amount * 100 ), // Convert to cents
            'total' => $appointment->price + intval( $tax_amount * 100 ),
            'tax_breakdown' => $taxes
        ];
    }

    /**
     * Sync all services to WooCommerce products
     * 
     * @return array Results
     */
    public function sync_all_services(): array {
        global $wpdb;

        $services_table = $wpdb->prefix . 'ltlb_services';
        $services = $wpdb->get_results( "SELECT id FROM $services_table WHERE is_active = 1" );

        $results = [
            'created' => 0,
            'updated' => 0,
            'errors' => []
        ];

        foreach ( $services as $service ) {
            $result = $this->create_product_from_service( $service->id );
            
            if ( is_wp_error( $result ) ) {
                $results['errors'][] = $result->get_error_message();
            } else {
                $existing = get_post_meta( $service->id, '_ltlb_woo_product_id', true );
                if ( $existing ) {
                    $results['updated']++;
                } else {
                    $results['created']++;
                }
            }
        }

        return $results;
    }

    /**
     * Remove WooCommerce integration for service
     * 
     * @param int $service_id
     * @return bool Success
     */
    public function remove_product_link( int $service_id ): bool {
        $product_id = get_post_meta( $service_id, '_ltlb_woo_product_id', true );
        
        if ( $product_id ) {
            delete_post_meta( $product_id, '_ltlb_service_id' );
            delete_post_meta( $service_id, '_ltlb_woo_product_id' );
        }

        return true;
    }

    /**
     * Get booking checkout URL
     * 
     * @param int $appointment_id
     * @return string|WP_Error Checkout URL or error
     */
    public function get_checkout_url( int $appointment_id ) {
        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'not_enabled', __( 'WooCommerce integration is not enabled', 'ltl-bookings' ) );
        }

        $result = $this->add_booking_to_cart( $appointment_id );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return wc_get_checkout_url();
    }

    /**
     * Check if appointment has associated order
     * 
     * @param int $appointment_id
     * @return bool
     */
    public function has_order( int $appointment_id ): bool {
        $order_id = get_post_meta( $appointment_id, '_ltlb_woo_order_id', true );
        return ! empty( $order_id );
    }

    /**
     * Get order payment status
     * 
     * @param int $appointment_id
     * @return string|null Payment status
     */
    public function get_payment_status( int $appointment_id ): ?string {
        $order = $this->get_appointment_order( $appointment_id );
        
        if ( ! $order ) {
            return null;
        }

        return $order->get_status();
    }
}
