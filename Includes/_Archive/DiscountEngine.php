<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Discount Engine - Coupons/Gutscheine
 * 
 * Handles discount codes with:
 * - Fixed amount or percentage discounts
 * - Validity periods
 * - Usage limits (total & per customer)
 * - Minimum spend requirements
 * - Service/room scope restrictions
 * - Stacking rules
 * 
 * @package LazyBookings
 */
class LTLB_Discount_Engine {

    /**
     * Validate and apply coupon code
     * 
     * @param string $code Coupon code
     * @param array $booking_data Booking context
     * @return array|WP_Error Discount details or error
     */
    public function apply_coupon( string $code, array $booking_data ) {
        global $wpdb;
        
        $code = strtoupper( sanitize_text_field( $code ) );
        $table = $wpdb->prefix . 'ltlb_coupons';
        
        // Get coupon
        $coupon = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE code = %s AND is_active = 1",
            $code
        ), ARRAY_A );
        
        if ( ! $coupon ) {
            return new WP_Error( 'invalid_coupon', __( 'Invalid coupon code', 'ltl-bookings' ) );
        }
        
        // Validate coupon
        $validation = $this->validate_coupon( $coupon, $booking_data );
        
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }
        
        // Calculate discount
        $subtotal_cents = intval( $booking_data['subtotal_cents'] ?? 0 );
        $discount_cents = $this->calculate_discount( $coupon, $subtotal_cents );
        
        // Track usage
        $this->track_usage( $coupon['id'], $booking_data['customer_id'] ?? 0 );
        
        return [
            'code' => $code,
            'discount_cents' => $discount_cents,
            'type' => $coupon['discount_type'],
            'value' => $coupon['discount_value'],
            'description' => $coupon['description'] ?? ''
        ];
    }

    /**
     * Validate coupon against booking context
     * 
     * @param array $coupon
     * @param array $booking_data
     * @return true|WP_Error
     */
    private function validate_coupon( array $coupon, array $booking_data ) {
        // Check validity period
        $now = current_time( 'mysql' );
        
        if ( ! empty( $coupon['valid_from'] ) && $now < $coupon['valid_from'] ) {
            return new WP_Error( 'coupon_not_yet_valid', __( 'This coupon is not yet valid', 'ltl-bookings' ) );
        }
        
        if ( ! empty( $coupon['valid_until'] ) && $now > $coupon['valid_until'] ) {
            return new WP_Error( 'coupon_expired', __( 'This coupon has expired', 'ltl-bookings' ) );
        }
        
        // Check usage limits
        if ( $coupon['usage_limit'] > 0 && $coupon['usage_count'] >= $coupon['usage_limit'] ) {
            return new WP_Error( 'coupon_limit_reached', __( 'This coupon has reached its usage limit', 'ltl-bookings' ) );
        }
        
        // Check per-customer limit
        if ( $coupon['usage_limit_per_customer'] > 0 ) {
            $customer_id = intval( $booking_data['customer_id'] ?? 0 );
            if ( $customer_id > 0 ) {
                $customer_usage = $this->get_customer_usage( $coupon['id'], $customer_id );
                if ( $customer_usage >= $coupon['usage_limit_per_customer'] ) {
                    return new WP_Error( 'coupon_customer_limit', __( 'You have already used this coupon', 'ltl-bookings' ) );
                }
            }
        }
        
        // Check minimum spend
        $subtotal_cents = intval( $booking_data['subtotal_cents'] ?? 0 );
        if ( $coupon['min_spend_cents'] > 0 && $subtotal_cents < $coupon['min_spend_cents'] ) {
            $min_formatted = LTLB_Pricing_Engine::format_price( $coupon['min_spend_cents'] );
            return new WP_Error( 'coupon_min_spend', sprintf( __( 'Minimum spend of %s required', 'ltl-bookings' ), $min_formatted ) );
        }
        
        // Check service/room scope
        $service_id = intval( $booking_data['service_id'] ?? 0 );
        $room_id = intval( $booking_data['room_id'] ?? 0 );
        
        if ( ! empty( $coupon['service_ids'] ) ) {
            $allowed_services = array_map( 'intval', explode( ',', (string) ( $coupon['service_ids'] ?? '' ) ) );
            if ( ! in_array( $service_id, $allowed_services, true ) ) {
                return new WP_Error( 'coupon_not_applicable', __( 'This coupon is not valid for the selected service', 'ltl-bookings' ) );
            }
        }
        
        if ( ! empty( $coupon['room_ids'] ) ) {
            $allowed_rooms = array_map( 'intval', explode( ',', (string) ( $coupon['room_ids'] ?? '' ) ) );
            if ( ! in_array( $room_id, $allowed_rooms, true ) ) {
                return new WP_Error( 'coupon_not_applicable', __( 'This coupon is not valid for the selected room', 'ltl-bookings' ) );
            }
        }
        
        return true;
    }

    /**
     * Calculate discount amount
     * 
     * @param array $coupon
     * @param int $subtotal_cents
     * @return int Discount in cents
     */
    private function calculate_discount( array $coupon, int $subtotal_cents ): int {
        if ( $coupon['discount_type'] === 'fixed' ) {
            // Fixed amount discount
            $discount = intval( $coupon['discount_value'] );
        } else {
            // Percentage discount
            $percentage = floatval( $coupon['discount_value'] );
            $discount = round( $subtotal_cents * ( $percentage / 100 ) );
        }
        
        // Apply max discount limit
        if ( $coupon['max_discount_cents'] > 0 ) {
            $discount = min( $discount, intval( $coupon['max_discount_cents'] ) );
        }
        
        // Never discount more than subtotal
        $discount = min( $discount, $subtotal_cents );
        
        return max( 0, $discount );
    }

    /**
     * Track coupon usage
     * 
     * @param int $coupon_id
     * @param int $customer_id
     * @return void
     */
    private function track_usage( int $coupon_id, int $customer_id ): void {
        global $wpdb;
        
        // Increment usage count
        $coupons_table = $wpdb->prefix . 'ltlb_coupons';
        $wpdb->query( $wpdb->prepare(
            "UPDATE $coupons_table SET usage_count = usage_count + 1 WHERE id = %d",
            $coupon_id
        ) );
        
        // Log usage
        $usage_table = $wpdb->prefix . 'ltlb_coupon_usage';
        $wpdb->insert( $usage_table, [
            'coupon_id' => $coupon_id,
            'customer_id' => $customer_id,
            'used_at' => current_time( 'mysql' ),
        ], [ '%d', '%d', '%s' ] );
    }

    /**
     * Get customer usage count for coupon
     * 
     * @param int $coupon_id
     * @param int $customer_id
     * @return int Usage count
     */
    private function get_customer_usage( int $coupon_id, int $customer_id ): int {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'ltlb_coupon_usage';
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $usage_table WHERE coupon_id = %d AND customer_id = %d",
            $coupon_id,
            $customer_id
        ) );
        
        return intval( $count );
    }

    /**
     * Create new coupon
     * 
     * @param array $data Coupon data
     * @return int|WP_Error Coupon ID or error
     */
    public function create_coupon( array $data ) {
        global $wpdb;
        
        $code = strtoupper( sanitize_text_field( $data['code'] ?? '' ) );
        
        if ( empty( $code ) ) {
            return new WP_Error( 'invalid_code', __( 'Coupon code is required', 'ltl-bookings' ) );
        }
        
        // Check if code already exists
        $table = $wpdb->prefix . 'ltlb_coupons';
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE code = %s",
            $code
        ) );
        
        if ( $exists > 0 ) {
            return new WP_Error( 'code_exists', __( 'Coupon code already exists', 'ltl-bookings' ) );
        }
        
        // Insert coupon
        $result = $wpdb->insert( $table, [
            'code' => $code,
            'description' => sanitize_text_field( $data['description'] ?? '' ),
            'discount_type' => in_array( $data['discount_type'] ?? '', ['fixed', 'percent'], true ) ? $data['discount_type'] : 'percent',
            'discount_value' => floatval( $data['discount_value'] ?? 0 ),
            'max_discount_cents' => intval( $data['max_discount_cents'] ?? 0 ),
            'min_spend_cents' => intval( $data['min_spend_cents'] ?? 0 ),
            'valid_from' => ! empty( $data['valid_from'] ) ? $data['valid_from'] : null,
            'valid_until' => ! empty( $data['valid_until'] ) ? $data['valid_until'] : null,
            'usage_limit' => intval( $data['usage_limit'] ?? 0 ),
            'usage_limit_per_customer' => intval( $data['usage_limit_per_customer'] ?? 0 ),
            'service_ids' => ! empty( $data['service_ids'] ) ? implode( ',', array_map( 'intval', (array) $data['service_ids'] ) ) : null,
            'room_ids' => ! empty( $data['room_ids'] ) ? implode( ',', array_map( 'intval', (array) $data['room_ids'] ) ) : null,
            'is_active' => isset( $data['is_active'] ) ? intval( $data['is_active'] ) : 1,
            'created_at' => current_time( 'mysql' ),
        ], [ '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' ] );
        
        if ( ! $result ) {
            return new WP_Error( 'create_failed', __( 'Could not create coupon', 'ltl-bookings' ) );
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Get all active coupons
     * 
     * @return array Coupons
     */
    public function get_active_coupons(): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_coupons';
        $now = current_time( 'mysql' );
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table 
                 WHERE is_active = 1
                 AND (valid_from IS NULL OR valid_from <= %s)
                 AND (valid_until IS NULL OR valid_until >= %s)
                 ORDER BY created_at DESC",
                $now,
                $now
            ),
            ARRAY_A
        );
        
        return $results ?: [];
    }
}
