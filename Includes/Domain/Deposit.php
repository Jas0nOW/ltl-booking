<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Deposit/Payment Schedule Domain Entity
 * 
 * Represents payment schedules for bookings:
 * - Deposits (e.g. 20% now, 80% later)
 * - Payment installments
 * - Due dates
 * - Payment status tracking
 * 
 * @package LazyBookings
 */
class LTLB_Deposit {

    /**
     * Create deposit schedule for a booking
     * 
     * @param int $appointment_id
     * @param array $schedule Schedule configuration
     * @return array|WP_Error Payment schedule or error
     */
    public static function create_schedule( int $appointment_id, array $schedule ): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_payment_schedule';
        
        // Validate appointment exists
        $appointment_table = $wpdb->prefix . 'ltlb_appointments';
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, total_price_cents, status FROM $appointment_table WHERE id = %d",
            $appointment_id
        ) );
        
        if ( ! $appointment ) {
            return new WP_Error( 'invalid_appointment', __( 'Appointment not found', 'ltl-bookings' ) );
        }
        
        $total_cents = intval( $appointment->total_price_cents );
        
        // Default: 20% deposit, 80% due later
        $deposit_percent = isset( $schedule['deposit_percent'] ) ? floatval( $schedule['deposit_percent'] ) : 20;
        $deposit_cents = round( $total_cents * ( $deposit_percent / 100 ) );
        $remaining_cents = $total_cents - $deposit_cents;
        
        // Due dates
        $deposit_due = $schedule['deposit_due'] ?? date( 'Y-m-d H:i:s' ); // Now
        $remaining_due = $schedule['remaining_due'] ?? null; // Set by admin or default to checkin date
        
        // Create payment schedule records
        $payments = [];
        
        // Payment 1: Deposit
        $wpdb->insert( $table, [
            'appointment_id' => $appointment_id,
            'sequence' => 1,
            'amount_cents' => $deposit_cents,
            'due_date' => $deposit_due,
            'payment_type' => 'deposit',
            'status' => 'pending',
            'created_at' => current_time( 'mysql' ),
        ], [ '%d', '%d', '%d', '%s', '%s', '%s', '%s' ] );
        
        $payments[] = [
            'id' => $wpdb->insert_id,
            'sequence' => 1,
            'amount_cents' => $deposit_cents,
            'due_date' => $deposit_due,
            'type' => 'deposit',
            'status' => 'pending'
        ];
        
        // Payment 2: Remaining balance
        if ( $remaining_cents > 0 ) {
            $wpdb->insert( $table, [
                'appointment_id' => $appointment_id,
                'sequence' => 2,
                'amount_cents' => $remaining_cents,
                'due_date' => $remaining_due,
                'payment_type' => 'balance',
                'status' => 'pending',
                'created_at' => current_time( 'mysql' ),
            ], [ '%d', '%d', '%d', '%s', '%s', '%s', '%s' ] );
            
            $payments[] = [
                'id' => $wpdb->insert_id,
                'sequence' => 2,
                'amount_cents' => $remaining_cents,
                'due_date' => $remaining_due,
                'type' => 'balance',
                'status' => 'pending'
            ];
        }
        
        return $payments;
    }

    /**
     * Get payment schedule for appointment
     * 
     * @param int $appointment_id
     * @return array Payment schedule items
     */
    public static function get_schedule( int $appointment_id ): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_payment_schedule';
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE appointment_id = %d ORDER BY sequence ASC",
            $appointment_id
        ), ARRAY_A );
        
        return $results ?: [];
    }

    /**
     * Mark payment as paid
     * 
     * @param int $payment_id
     * @param string $payment_method
     * @param string $transaction_ref
     * @return bool Success
     */
    public static function mark_as_paid( int $payment_id, string $payment_method = '', string $transaction_ref = '' ): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_payment_schedule';
        
        $result = $wpdb->update(
            $table,
            [
                'status' => 'paid',
                'paid_at' => current_time( 'mysql' ),
                'payment_method' => $payment_method,
                'transaction_ref' => $transaction_ref,
            ],
            [ 'id' => $payment_id ],
            [ '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        // Check if all payments are complete
        $payment = $wpdb->get_row( $wpdb->prepare(
            "SELECT appointment_id FROM $table WHERE id = %d",
            $payment_id
        ) );
        
        if ( $payment ) {
            self::check_complete_payment( intval( $payment->appointment_id ) );
        }
        
        return $result !== false;
    }

    /**
     * Check if all payments are complete and update appointment status
     * 
     * @param int $appointment_id
     * @return void
     */
    private static function check_complete_payment( int $appointment_id ): void {
        global $wpdb;
        
        $schedule_table = $wpdb->prefix . 'ltlb_payment_schedule';
        $appointment_table = $wpdb->prefix . 'ltlb_appointments';
        
        // Check if all payments are paid
        $pending_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $schedule_table WHERE appointment_id = %d AND status = 'pending'",
            $appointment_id
        ) );
        
        if ( $pending_count == 0 ) {
            // All payments complete - update appointment status to 'paid'
            $wpdb->update(
                $appointment_table,
                [ 'status' => 'paid' ],
                [ 'id' => $appointment_id ],
                [ '%s' ],
                [ '%d' ]
            );
            
            // Trigger action for external integrations
            do_action( 'ltlb_appointment_fully_paid', $appointment_id );
        }
    }

    /**
     * Get pending payments (due soon)
     * 
     * @param int $days_ahead Look ahead N days
     * @return array Pending payments
     */
    public static function get_pending_payments( int $days_ahead = 7 ): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_payment_schedule';
        $appointment_table = $wpdb->prefix . 'ltlb_appointments';
        
        $future_date = date( 'Y-m-d H:i:s', strtotime( "+{$days_ahead} days" ) );
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT ps.*, a.customer_id, a.total_price_cents 
             FROM $table ps
             JOIN $appointment_table a ON ps.appointment_id = a.id
             WHERE ps.status = 'pending' 
             AND ps.due_date IS NOT NULL
             AND ps.due_date <= %s
             ORDER BY ps.due_date ASC",
            $future_date
        ), ARRAY_A );
        
        return $results ?: [];
    }

    /**
     * Send payment reminder email
     * 
     * @param int $payment_id
     * @return bool Success
     */
    public static function send_reminder( int $payment_id ): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_payment_schedule';
        
        $payment = $wpdb->get_row( $wpdb->prepare(
            "SELECT ps.*, a.customer_id, a.start_datetime, a.service_id
             FROM $table ps
             JOIN {$wpdb->prefix}ltlb_appointments a ON ps.appointment_id = a.id
             WHERE ps.id = %d",
            $payment_id
        ) );
        
        if ( ! $payment || $payment->status !== 'pending' ) {
            return false;
        }
        
        // Get customer email
        $customer_table = $wpdb->prefix . 'ltlb_customers';
        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT email, name FROM $customer_table WHERE id = %d",
            $payment->customer_id
        ) );
        
        if ( ! $customer || ! is_email( $customer->email ) ) {
            return false;
        }
        
        // Prepare email
        $subject = sprintf(
            __( 'Payment Reminder: %s due', 'ltl-bookings' ),
            LTLB_Pricing_Engine::format_price( $payment->amount_cents )
        );
        
        $body = sprintf(
            __( 'Hello %s,<br><br>This is a reminder that your payment of %s is due on %s.<br><br>Thank you!', 'ltl-bookings' ),
            esc_html( $customer->name ),
            LTLB_Pricing_Engine::format_price( $payment->amount_cents ),
            date_i18n( get_option( 'date_format' ), strtotime( $payment->due_date ) )
        );
        
        // Send email
        if ( class_exists( 'LTLB_Mailer' ) ) {
            return LTLB_Mailer::wp_mail(
                $customer->email,
                $subject,
                $body,
                [ 'Content-Type: text/html; charset=UTF-8' ]
            );
        }
        
        return wp_mail( $customer->email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    /**
     * Get payment status summary
     * 
     * @param int $appointment_id
     * @return array Status summary
     */
    public static function get_payment_status( int $appointment_id ): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_payment_schedule';
        
        $schedule = self::get_schedule( $appointment_id );
        
        if ( empty( $schedule ) ) {
            return [
                'total_cents' => 0,
                'paid_cents' => 0,
                'pending_cents' => 0,
                'is_fully_paid' => false,
                'payments' => []
            ];
        }
        
        $total_cents = 0;
        $paid_cents = 0;
        
        foreach ( $schedule as $payment ) {
            $amount = intval( $payment['amount_cents'] );
            $total_cents += $amount;
            
            if ( $payment['status'] === 'paid' ) {
                $paid_cents += $amount;
            }
        }
        
        $pending_cents = $total_cents - $paid_cents;
        
        return [
            'total_cents' => $total_cents,
            'paid_cents' => $paid_cents,
            'pending_cents' => $pending_cents,
            'is_fully_paid' => $pending_cents === 0,
            'payments' => $schedule
        ];
    }
}
