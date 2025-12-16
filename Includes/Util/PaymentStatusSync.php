<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Payment Status Sync Helper
 * 
 * Ensures payment status changes are consistently reflected in:
 * - Appointment payment_status & refund fields
 * - Appointment status (business logic: paid/refunded)
 * - Audit log
 */
class LTLB_PaymentStatusSync {

    /**
     * Mark appointment as paid
     * 
     * @param int $appointment_id Appointment ID
     * @param string $payment_ref External payment reference (Stripe PI, PayPal Order ID, etc.)
     * @param string $payment_method Payment method used
     * @return bool Success
     */
    public static function mark_as_paid( int $appointment_id, string $payment_ref, string $payment_method ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';

        $result = $wpdb->update(
            $table,
            [
                'payment_status' => 'paid',
                'paid_at' => current_time( 'mysql' ),
                'payment_ref' => $payment_ref,
                'payment_method' => $payment_method,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $appointment_id ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( $result !== false && class_exists( 'LTLB_AuditLog' ) ) {
            LTLB_AuditLog::log(
                'appointment_paid',
                'appointment',
                $appointment_id,
                [
                    'payment_ref' => $payment_ref,
                    'payment_method' => $payment_method,
                ]
            );
        }

        return $result !== false;
    }

    /**
     * Process a refund and update appointment accordingly
     * 
     * @param int $appointment_id Appointment ID
     * @param int $refund_amount_cents Amount refunded in cents
     * @param string $refund_ref External refund reference (Stripe refund ID, PayPal refund ID)
     * @param string $refund_reason Optional reason
     * @param bool $is_partial Is this a partial refund?
     * @return bool Success
     */
    public static function mark_as_refunded( int $appointment_id, int $refund_amount_cents, string $refund_ref, string $refund_reason = '', bool $is_partial = false ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';

        // Get appointment to check current state
        $appointment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $appointment_id ), ARRAY_A );
        if ( ! $appointment ) {
            return false;
        }

        $original_amount = intval( $appointment['amount_cents'] ?? 0 );
        $already_refunded = intval( $appointment['refund_amount_cents'] ?? 0 );
        $total_refunded = $already_refunded + $refund_amount_cents;

        // Determine refund status
        $refund_status = 'partial';
        if ( $total_refunded >= $original_amount ) {
            $refund_status = 'full';
        }

        // Update appointment with refund details
        $result = $wpdb->update(
            $table,
            [
                'refund_status' => $refund_status,
                'refund_amount_cents' => $total_refunded,
                'refunded_at' => current_time( 'mysql' ),
                'refund_ref' => $refund_ref, // Store most recent refund ref
                'refund_reason' => $refund_reason,
                'payment_status' => $refund_status === 'full' ? 'refunded' : 'paid', // Keep 'paid' for partial
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $appointment_id ],
            [ '%s', '%d', '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( $result !== false && class_exists( 'LTLB_AuditLog' ) ) {
            LTLB_AuditLog::log(
                'appointment_refunded',
                'appointment',
                $appointment_id,
                [
                    'refund_amount_cents' => $refund_amount_cents,
                    'total_refunded' => $total_refunded,
                    'refund_status' => $refund_status,
                    'refund_ref' => $refund_ref,
                    'refund_reason' => $refund_reason,
                ]
            );
        }

        // If full refund, transition booking status appropriately
        if ( $refund_status === 'full' ) {
            // Try to use state machine for proper transition
            if ( class_exists( 'LTLB_BookingStatus' ) ) {
                $current_status = $appointment['status'] ?? 'pending';
                $target_status = LTLB_BookingStatus::STATUS_REFUNDED;
                
                // Check if transition is valid
                if ( LTLB_BookingStatus::can_transition( $current_status, $target_status ) ) {
                    LTLB_BookingStatus::transition( $appointment_id, $target_status, 'Full refund processed' );
                }
            } else {
                // Fallback: direct status update (legacy)
                $wpdb->update(
                    $table,
                    [
                        'status' => 'cancelled',
                        'updated_at' => current_time( 'mysql' ),
                    ],
                    [ 'id' => $appointment_id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );

                if ( class_exists( 'LTLB_AuditLog' ) ) {
                    LTLB_AuditLog::log(
                        'appointment_cancelled',
                        'appointment',
                        $appointment_id,
                        [ 'reason' => 'Full refund processed' ]
                    );
                }
            }
        }

        return $result !== false;
    }

    /**
     * Get refund-related fields for an appointment
     * 
     * @param int $appointment_id Appointment ID
     * @return array|null Refund data or null if not found
     */
    public static function get_refund_info( int $appointment_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';

        $data = $wpdb->get_row( $wpdb->prepare(
            "SELECT payment_status, refund_status, refund_amount_cents, refunded_at, refund_ref, refund_reason, amount_cents
            FROM {$table}
            WHERE id = %d",
            $appointment_id
        ), ARRAY_A );

        return $data ?: null;
    }

    /**
     * Check if an appointment can be refunded
     * 
     * @param int $appointment_id Appointment ID
     * @return array ['can_refund' => bool, 'reason' => string, 'refundable_amount' => int]
     */
    public static function can_refund( int $appointment_id ): array {
        $info = self::get_refund_info( $appointment_id );
        if ( ! $info ) {
            return [ 'can_refund' => false, 'reason' => 'Appointment not found', 'refundable_amount' => 0 ];
        }

        $payment_status = (string) ( $info['payment_status'] ?? '' );
        if ( $payment_status !== 'paid' && $payment_status !== 'refunded' ) {
            return [ 'can_refund' => false, 'reason' => 'Appointment not paid', 'refundable_amount' => 0 ];
        }

        $refund_status = (string) ( $info['refund_status'] ?? 'none' );
        if ( $refund_status === 'full' ) {
            return [ 'can_refund' => false, 'reason' => 'Already fully refunded', 'refundable_amount' => 0 ];
        }

        $original_amount = intval( $info['amount_cents'] ?? 0 );
        $already_refunded = intval( $info['refund_amount_cents'] ?? 0 );
        $refundable = $original_amount - $already_refunded;

        if ( $refundable <= 0 ) {
            return [ 'can_refund' => false, 'reason' => 'No refundable amount remaining', 'refundable_amount' => 0 ];
        }

        return [ 'can_refund' => true, 'reason' => '', 'refundable_amount' => $refundable ];
    }
}
