<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_AppointmentsPage {
    private static function payment_status_label( string $status ): string {
        $status = sanitize_key( $status );
        switch ( $status ) {
            case 'paid':
                return __( 'Paid', 'ltl-bookings' );
            case 'unpaid':
                return __( 'Unpaid', 'ltl-bookings' );
            case 'free':
                return __( 'Free', 'ltl-bookings' );
            default:
                return $status !== '' ? $status : __( '—', 'ltl-bookings' );
        }
    }

    private static function payment_method_label( string $method ): string {
        $method = sanitize_key( $method );
        switch ( $method ) {
            case 'stripe_card':
                return __( 'Card (Stripe)', 'ltl-bookings' );
            case 'paypal':
                return __( 'PayPal', 'ltl-bookings' );
            case 'klarna':
                return __( 'Klarna', 'ltl-bookings' );
            case 'cash':
                return __( 'Cash', 'ltl-bookings' );
            case 'pos_card':
                return __( 'Card (POS)', 'ltl-bookings' );
            case 'invoice':
                return __( 'Invoice', 'ltl-bookings' );
            case 'free':
                return __( 'Free', 'ltl-bookings' );
            case 'unpaid':
            case 'none':
                return __( 'Not selected', 'ltl-bookings' );
            default:
                return $method !== '' ? $method : __( '—', 'ltl-bookings' );
        }
    }

    private static function format_amount( $amount_cents, $currency ): string {
        $amount_cents = is_numeric( $amount_cents ) ? intval( $amount_cents ) : 0;
        $currency = is_string( $currency ) && $currency !== '' ? strtoupper( sanitize_text_field( $currency ) ) : 'EUR';
        $amount = number_format( max( 0, $amount_cents ) / 100, 2 );
        return $amount . ' ' . $currency;
    }

    private function render_detail( int $id ): void {
        $appointment_repo = new LTLB_AppointmentRepository();
        $service_repo = new LTLB_ServiceRepository();
        $customer_repo = new LTLB_CustomerRepository();

        $appt = $appointment_repo->get_by_id( $id );
        if ( ! $appt ) {
            LTLB_Notices::add( __( 'Appointment not found.', 'ltl-bookings' ), 'error' );
            wp_safe_redirect( admin_url( 'admin.php?page=ltlb_appointments' ) );
            exit;
        }

        $cust = $customer_repo->get_by_id( intval( $appt['customer_id'] ?? 0 ) );
        $svc = $service_repo->get_by_id( intval( $appt['service_id'] ?? 0 ) );

        // Track recently viewed appointments for the dashboard widget.
        $recent_ids = get_user_meta( get_current_user_id(), 'ltlb_recently_viewed_appointments', true );
        if ( ! is_array( $recent_ids ) ) {
            $recent_ids = [];
        }
        $recent_ids = array_values( array_filter( array_map( 'intval', $recent_ids ), static function( $v ) { return $v > 0; } ) );
        $recent_ids = array_values( array_diff( $recent_ids, [ $id ] ) );
        array_unshift( $recent_ids, $id );
        $recent_ids = array_slice( $recent_ids, 0, 15 );
        update_user_meta( get_current_user_id(), 'ltlb_recently_viewed_appointments', $recent_ids );

        $cust_name = $cust ? trim( (string) ( $cust['first_name'] ?? '' ) . ' ' . (string) ( $cust['last_name'] ?? '' ) ) : '';
        $cust_email = $cust ? (string) ( $cust['email'] ?? '' ) : '';
        $svc_name = $svc ? (string) ( $svc['name'] ?? '' ) : '';

        $payment_status = (string) ( $appt['payment_status'] ?? '' );
        $payment_method = (string) ( $appt['payment_method'] ?? '' );
        $payment_ref = (string) ( $appt['payment_ref'] ?? '' );
        $paid_at_raw = (string) ( $appt['paid_at'] ?? '' );
        $paid_at_label = $paid_at_raw ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $paid_at_raw ) ) : '—';
        $amount_label = self::format_amount( $appt['amount_cents'] ?? 0, $appt['currency'] ?? 'EUR' );

        $notes = $cust ? (string) ( $cust['notes'] ?? '' ) : '';
        $invoice_line = '';
        if ( $notes !== '' ) {
            $lines = preg_split( '/\r\n|\r|\n/', $notes );
            if ( is_array( $lines ) ) {
                foreach ( $lines as $line ) {
                    $line = trim( (string) $line );
                    if ( stripos( (string) $line, 'Invoice:' ) === 0 ) {
                        $invoice_line = $line;
                        break;
                    }
                }
            }
        }

        $back_url = admin_url( 'admin.php?page=ltlb_appointments' );
        $appt_tz = ! empty( $appt['timezone'] ) ? (string) $appt['timezone'] : ( class_exists( 'LTLB_Time' ) ? LTLB_Time::wp_timezone()->getName() : 'UTC' );
        $start_display = (string) ( $appt['start_at'] ?? '' );
        $end_display = (string) ( $appt['end_at'] ?? '' );
        if ( class_exists( 'LTLB_DateTime' ) ) {
            $start_display = LTLB_DateTime::format_local_display_from_utc_mysql( (string) ( $appt['start_at'] ?? '' ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $appt_tz );
            $end_display = LTLB_DateTime::format_local_display_from_utc_mysql( (string) ( $appt['end_at'] ?? '' ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $appt_tz );
        }
        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_appointments'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__( 'Appointment', 'ltl-bookings' ); ?> #<?php echo esc_html( (string) $id ); ?></h1>
            <a class="ltlb-btn ltlb-btn--small ltlb-btn--secondary" href="<?php echo esc_url( $back_url ); ?>"><?php echo esc_html__( 'Back to Appointments', 'ltl-bookings' ); ?></a>
            <hr class="wp-header-end">

            <?php LTLB_Admin_Component::card_start( __( 'Overview', 'ltl-bookings' ) ); ?>
                <table class="form-table"><tbody>
                    <tr><th><?php echo esc_html__( 'Customer', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $cust_name !== '' ? $cust_name : '—' ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Email', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $cust_email !== '' ? $cust_email : '—' ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Service', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $svc_name !== '' ? $svc_name : '—' ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Start', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $start_display ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'End', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $end_display ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Status', 'ltl-bookings' ); ?></th><td><?php echo esc_html( (string) ( $appt['status'] ?? '' ) ); ?></td></tr>
                </tbody></table>
            <?php LTLB_Admin_Component::card_end(); ?>

            <?php
            $refund_info = [];
            $can_refund = false;
            if ( class_exists( 'LTLB_PaymentStatusSync' ) ) {
                $refund_info = LTLB_PaymentStatusSync::get_refund_info( $id );
                $can_refund = LTLB_PaymentStatusSync::can_refund( $id );
            }
            $refund_status = (string) ( $refund_info['refund_status'] ?? 'none' );
            $refund_amount = (int) ( $refund_info['refund_amount_cents'] ?? 0 );
            $refunded_at = (string) ( $refund_info['refunded_at'] ?? '' );
            $refund_ref = (string) ( $refund_info['refund_ref'] ?? '' );
            $refund_reason = (string) ( $refund_info['refund_reason'] ?? '' );
            ?>
            <?php LTLB_Admin_Component::card_start( __( 'Payment', 'ltl-bookings' ) ); ?>
                <table class="form-table"><tbody>
                    <tr><th><?php echo esc_html__( 'Amount', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $amount_label ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Payment status', 'ltl-bookings' ); ?></th><td><?php echo esc_html( self::payment_status_label( $payment_status ) ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Payment method', 'ltl-bookings' ); ?></th><td><?php echo esc_html( self::payment_method_label( $payment_method ) ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Paid at', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $paid_at_label ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Reference', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $payment_ref !== '' ? $payment_ref : '—' ); ?></td></tr>
                    <?php if ( $invoice_line !== '' ) : ?>
                        <tr><th><?php echo esc_html__( 'Invoice details', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $invoice_line ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $refund_status !== 'none' ) : ?>
                        <tr><th><?php echo esc_html__( 'Refund status', 'ltl-bookings' ); ?></th><td><?php echo esc_html( ucfirst( $refund_status ) ); ?></td></tr>
                        <tr><th><?php echo esc_html__( 'Refund amount', 'ltl-bookings' ); ?></th><td><?php echo esc_html( self::format_amount( $refund_amount, $appt['currency'] ?? 'EUR' ) ); ?></td></tr>
                        <?php if ( $refunded_at !== '' ) : ?>
                            <tr><th><?php echo esc_html__( 'Refunded at', 'ltl-bookings' ); ?></th><td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $refunded_at ) ) ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $refund_ref !== '' ) : ?>
                            <tr><th><?php echo esc_html__( 'Refund reference', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $refund_ref ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $refund_reason !== '' ) : ?>
                            <tr><th><?php echo esc_html__( 'Refund reason', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $refund_reason ); ?></td></tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody></table>
                <?php if ( $can_refund ) : ?>
                    <div style="margin-top: 16px;">
                        <button type="button" class="ltlb-btn ltlb-btn--danger" id="ltlb-refund-btn"><?php echo esc_html__( 'Process Refund', 'ltl-bookings' ); ?></button>
                        <div id="ltlb-refund-form" style="display: none; margin-top: 12px;">
                            <label>
                                <?php echo esc_html__( 'Refund amount (cents)', 'ltl-bookings' ); ?>:
                                <input type="number" id="ltlb-refund-amount" value="<?php echo esc_attr( (string) ( $appt['amount_cents'] ?? 0 ) ); ?>" min="1" max="<?php echo esc_attr( (string) ( $appt['amount_cents'] ?? 0 ) ); ?>" style="width: 150px; margin-left: 8px;">
                            </label>
                            <label style="margin-left: 16px;">
                                <?php echo esc_html__( 'Reason', 'ltl-bookings' ); ?>:
                                <input type="text" id="ltlb-refund-reason" value="requested_by_customer" style="width: 200px; margin-left: 8px;">
                            </label>
                            <button type="button" class="ltlb-btn ltlb-btn--primary" id="ltlb-refund-submit" style="margin-left: 16px;"><?php echo esc_html__( 'Confirm Refund', 'ltl-bookings' ); ?></button>
                            <button type="button" class="ltlb-btn ltlb-btn--secondary" id="ltlb-refund-cancel" style="margin-left: 8px;"><?php echo esc_html__( 'Cancel', 'ltl-bookings' ); ?></button>
                            <span id="ltlb-refund-status" style="margin-left: 16px; font-weight: 600;"></span>
                        </div>
                    </div>
                    <script>
                    (function() {
                        const btn = document.getElementById('ltlb-refund-btn');
                        const form = document.getElementById('ltlb-refund-form');
                        const submit = document.getElementById('ltlb-refund-submit');
                        const cancel = document.getElementById('ltlb-refund-cancel');
                        const status = document.getElementById('ltlb-refund-status');
                        const amountInput = document.getElementById('ltlb-refund-amount');
                        const reasonInput = document.getElementById('ltlb-refund-reason');

                        if (!btn || !form || !submit || !cancel) return;

                        btn.addEventListener('click', function() {
                            form.style.display = 'block';
                            btn.style.display = 'none';
                        });

                        cancel.addEventListener('click', function() {
                            form.style.display = 'none';
                            btn.style.display = 'inline-block';
                            status.textContent = '';
                        });

                        submit.addEventListener('click', function() {
                            const amount = parseInt(amountInput.value, 10);
                            const reason = reasonInput.value.trim();
                            if (isNaN(amount) || amount <= 0) {
                                status.textContent = '<?php echo esc_js( __( 'Invalid amount', 'ltl-bookings' ) ); ?>';
                                status.style.color = '#dc3232';
                                return;
                            }

                            submit.disabled = true;
                            status.textContent = '<?php echo esc_js( __( 'Processing...', 'ltl-bookings' ) ); ?>';
                            status.style.color = '#0073aa';

                            fetch('<?php echo esc_url( rest_url( 'ltl-bookings/v1/admin/appointments/' . $id . '/refund' ) ); ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
                                },
                                body: JSON.stringify({ amount, reason })
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    status.textContent = '<?php echo esc_js( __( 'Refund successful! Reloading...', 'ltl-bookings' ) ); ?>';
                                    status.style.color = '#46b450';
                                    setTimeout(() => { location.reload(); }, 1500);
                                } else {
                                    status.textContent = data.message || '<?php echo esc_js( __( 'Refund failed', 'ltl-bookings' ) ); ?>';
                                    status.style.color = '#dc3232';
                                    submit.disabled = false;
                                }
                            })
                            .catch(err => {
                                status.textContent = '<?php echo esc_js( __( 'Network error', 'ltl-bookings' ) ); ?>';
                                status.style.color = '#dc3232';
                                submit.disabled = false;
                            });
                        });
                    })();
                    </script>
                <?php endif; ?>
            <?php LTLB_Admin_Component::card_end(); ?>

            <?php if ( $notes !== '' ) : ?>
                <?php LTLB_Admin_Component::card_start( __( 'Customer notes', 'ltl-bookings' ) ); ?>
                    <textarea class="large-text" rows="6" readonly><?php echo esc_textarea( $notes ); ?></textarea>
                <?php LTLB_Admin_Component::card_end(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

	public function render(): void {
        if ( ! current_user_can('view_bookings') && ! current_user_can('manage_bookings') ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }

		$appointment_repo = new LTLB_AppointmentRepository();
		$service_repo = new LTLB_ServiceRepository();
		$customer_repo = new LTLB_CustomerRepository();

        $settings = get_option( 'lazy_settings', [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        $template_mode = isset( $settings['template_mode'] ) ? (string) $settings['template_mode'] : 'service';
        $is_hotel_mode = $template_mode === 'hotel';
        $default_status = isset( $settings['default_status'] ) ? sanitize_key( (string) $settings['default_status'] ) : 'pending';
        if ( ! in_array( $default_status, [ 'pending', 'confirmed', 'cancelled' ], true ) ) {
            $default_status = 'pending';
        }

        $view_action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : 'list';
        if ( $view_action === 'add' ) {
            // Handle create.
            if ( isset( $_POST['ltlb_appointment_save'] ) ) {
                if ( ! check_admin_referer( 'ltlb_appointment_save_action', 'ltlb_appointment_nonce' ) ) {
                    wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
                }
                if ( ! current_user_can( 'manage_bookings' ) ) {
                    wp_die( esc_html__( 'You do not have permission to create bookings.', 'ltl-bookings' ) );
                }

                $service_id = isset( $_POST['service_id'] ) ? intval( $_POST['service_id'] ) : 0;
                $status = isset( $_POST['status'] ) ? sanitize_key( (string) $_POST['status'] ) : $default_status;
                if ( ! in_array( $status, [ 'pending', 'confirmed', 'cancelled' ], true ) ) {
                    $status = $default_status;
                }
                $seats = isset( $_POST['seats'] ) ? max( 1, intval( $_POST['seats'] ) ) : 1;
                $skip_conflict_check = ! empty( $_POST['skip_conflict_check'] );

                $email = sanitize_email( (string) ( $_POST['customer_email'] ?? '' ) );
                $first_name = isset( $_POST['customer_first_name'] ) ? sanitize_text_field( (string) $_POST['customer_first_name'] ) : '';
                $last_name = isset( $_POST['customer_last_name'] ) ? sanitize_text_field( (string) $_POST['customer_last_name'] ) : '';
                $phone = isset( $_POST['customer_phone'] ) ? sanitize_text_field( (string) $_POST['customer_phone'] ) : '';
                $notes = isset( $_POST['customer_notes'] ) ? wp_kses_post( (string) $_POST['customer_notes'] ) : '';

                if ( $service_id <= 0 ) {
                    LTLB_Notices::add( __( 'Please select a service.', 'ltl-bookings' ), 'error' );
                    wp_safe_redirect( admin_url( 'admin.php?page=ltlb_appointments&action=add' ) );
                    exit;
                }
                if ( ! $email || ! is_email( $email ) ) {
                    LTLB_Notices::add( __( 'Please enter a valid customer email.', 'ltl-bookings' ), 'error' );
                    wp_safe_redirect( admin_url( 'admin.php?page=ltlb_appointments&action=add' ) );
                    exit;
                }

                $customer_id = $customer_repo->upsert_by_email( [
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'phone' => $phone,
                    'notes' => $notes,
                ] );
                if ( ! $customer_id ) {
                    LTLB_Notices::add( __( 'Could not save customer. Please try again.', 'ltl-bookings' ), 'error' );
                    wp_safe_redirect( admin_url( 'admin.php?page=ltlb_appointments&action=add' ) );
                    exit;
                }

                $start_dt = null;
                $end_dt = null;
                if ( $is_hotel_mode ) {
                    $checkin = isset( $_POST['checkin'] ) ? sanitize_text_field( (string) $_POST['checkin'] ) : '';
                    $checkout = isset( $_POST['checkout'] ) ? sanitize_text_field( (string) $_POST['checkout'] ) : '';
                    $start_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::create_datetime_immutable( $checkin . ' 00:00:00' ) : null;
                    $end_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::create_datetime_immutable( $checkout . ' 00:00:00' ) : null;
                } else {
                    $start_raw = isset( $_POST['start_at'] ) ? sanitize_text_field( (string) $_POST['start_at'] ) : '';
                    $end_raw = isset( $_POST['end_at'] ) ? sanitize_text_field( (string) $_POST['end_at'] ) : '';
                    // Ensure strings before using str_replace to avoid null deprecation
                    $start_raw = is_string( $start_raw ) ? str_replace( 'T', ' ', (string) $start_raw ) : '';
                    $end_raw = is_string( $end_raw ) ? str_replace( 'T', ' ', (string) $end_raw ) : '';
                    $start_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::create_datetime_immutable( $start_raw ) : null;
                    $end_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::create_datetime_immutable( $end_raw ) : null;
                }

                if ( ! ( $start_dt instanceof DateTimeInterface ) || ! ( $end_dt instanceof DateTimeInterface ) ) {
                    LTLB_Notices::add( __( 'Please enter valid start and end dates.', 'ltl-bookings' ), 'error' );
                    wp_safe_redirect( admin_url( 'admin.php?page=ltlb_appointments&action=add' ) );
                    exit;
                }
                if ( $end_dt <= $start_dt ) {
                    LTLB_Notices::add( __( 'End date/time must be after start.', 'ltl-bookings' ), 'error' );
                    wp_safe_redirect( admin_url( 'admin.php?page=ltlb_appointments&action=add' ) );
                    exit;
                }

                $res = $appointment_repo->create( [
                    'service_id' => $service_id,
                    'customer_id' => (int) $customer_id,
                    'status' => $status,
                    'seats' => $seats,
                    'start_at' => $start_dt,
                    'end_at' => $end_dt,
                    'skip_conflict_check' => $skip_conflict_check ? 1 : 0,
                ] );

                if ( is_wp_error( $res ) ) {
                    LTLB_Notices::add( $res->get_error_message(), 'error' );
                    wp_safe_redirect( admin_url( 'admin.php?page=ltlb_appointments&action=add' ) );
                    exit;
                }

                LTLB_Notices::add( __( 'Appointment created.', 'ltl-bookings' ), 'success' );
                wp_safe_redirect( admin_url( 'admin.php?page=ltlb_appointments' ) );
                exit;
            }

            $all_services = $service_repo->get_all();
            $page_title = $is_hotel_mode ? __( 'Add New Booking', 'ltl-bookings' ) : __( 'Add New Appointment', 'ltl-bookings' );
            ?>
            <div class="wrap ltlb-admin">
                <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_appointments'); } ?>
                <h1 class="wp-heading-inline"><?php echo esc_html( $page_title ); ?></h1>
                <hr class="wp-header-end">
                <div class="ltlb-card" style="max-width:900px;">
                    <form method="post">
                        <?php wp_nonce_field( 'ltlb_appointment_save_action', 'ltlb_appointment_nonce' ); ?>
                        <input type="hidden" name="ltlb_appointment_save" value="1" />
                        <table class="form-table"><tbody>
                            <tr>
                                <th><label for="ltlb_service_id"><?php echo esc_html__( 'Service', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <select name="service_id" id="ltlb_service_id" required>
                                        <option value=""><?php echo esc_html__( 'Select…', 'ltl-bookings' ); ?></option>
                                        <?php foreach ( $all_services as $svc ) :
                                            $sid = intval( $svc['id'] ?? 0 );
                                            $sname = (string) ( $svc['name'] ?? '' );
                                            if ( $sid <= 0 ) continue;
                                        ?>
                                            <option value="<?php echo esc_attr( (string) $sid ); ?>"><?php echo esc_html( $sname ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>

                            <?php if ( $is_hotel_mode ) : ?>
                            <tr>
                                <th><label for="ltlb_checkin"><?php echo esc_html__( 'Check-in', 'ltl-bookings' ); ?></label></th>
                                <td><input type="date" id="ltlb_checkin" name="checkin" required></td>
                            </tr>
                            <tr>
                                <th><label for="ltlb_checkout"><?php echo esc_html__( 'Check-out', 'ltl-bookings' ); ?></label></th>
                                <td><input type="date" id="ltlb_checkout" name="checkout" required></td>
                            </tr>
                            <?php else : ?>
                            <tr>
                                <th><label for="ltlb_start_at"><?php echo esc_html__( 'Start', 'ltl-bookings' ); ?></label></th>
                                <td><input type="datetime-local" id="ltlb_start_at" name="start_at" required></td>
                            </tr>
                            <tr>
                                <th><label for="ltlb_end_at"><?php echo esc_html__( 'End', 'ltl-bookings' ); ?></label></th>
                                <td><input type="datetime-local" id="ltlb_end_at" name="end_at" required></td>
                            </tr>
                            <?php endif; ?>

                            <tr>
                                <th><label for="ltlb_seats"><?php echo esc_html( $is_hotel_mode ? __( 'Rooms', 'ltl-bookings' ) : __( 'Seats', 'ltl-bookings' ) ); ?></label></th>
                                <td><input type="number" id="ltlb_seats" name="seats" class="small-text" min="1" value="1"></td>
                            </tr>
                            <tr>
                                <th><label for="ltlb_status"><?php echo esc_html__( 'Status', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <select name="status" id="ltlb_status">
                                        <option value="pending" <?php selected( $default_status, 'pending' ); ?>><?php echo esc_html__( 'Pending', 'ltl-bookings' ); ?></option>
                                        <option value="confirmed" <?php selected( $default_status, 'confirmed' ); ?>><?php echo esc_html__( 'Confirmed', 'ltl-bookings' ); ?></option>
                                        <option value="cancelled" <?php selected( $default_status, 'cancelled' ); ?>><?php echo esc_html__( 'Cancelled', 'ltl-bookings' ); ?></option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="ltlb_customer_email"><?php echo esc_html__( 'Customer Email', 'ltl-bookings' ); ?></label></th>
                                <td><input type="email" id="ltlb_customer_email" name="customer_email" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="ltlb_customer_first"><?php echo esc_html__( 'First name', 'ltl-bookings' ); ?></label></th>
                                <td><input type="text" id="ltlb_customer_first" name="customer_first_name" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="ltlb_customer_last"><?php echo esc_html__( 'Last name', 'ltl-bookings' ); ?></label></th>
                                <td><input type="text" id="ltlb_customer_last" name="customer_last_name" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="ltlb_customer_phone"><?php echo esc_html__( 'Phone', 'ltl-bookings' ); ?></label></th>
                                <td><input type="text" id="ltlb_customer_phone" name="customer_phone" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="ltlb_customer_notes"><?php echo esc_html__( 'Notes', 'ltl-bookings' ); ?></label></th>
                                <td><textarea id="ltlb_customer_notes" name="customer_notes" class="large-text" rows="4"></textarea></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__( 'Conflict Check', 'ltl-bookings' ); ?></th>
                                <td>
                                    <label><input type="checkbox" name="skip_conflict_check" value="1"> <?php echo esc_html__( 'Skip availability conflict check (not recommended)', 'ltl-bookings' ); ?></label>
                                </td>
                            </tr>
                        </tbody></table>
                        <p class="submit">
                            <?php submit_button( esc_html__( 'Create', 'ltl-bookings' ), 'primary', 'submit', false ); ?>
                            <a class="ltlb-btn ltlb-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ltlb_appointments' ) ); ?>"><?php echo esc_html__( 'Cancel', 'ltl-bookings' ); ?></a>
                        </p>
                    </form>
                </div>
            </div>
            <?php
            return;
        }

        if ( $view_action === 'view' ) {
            $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
            if ( $id <= 0 ) {
                LTLB_Notices::add( __( 'Invalid appointment.', 'ltl-bookings' ), 'error' );
                wp_safe_redirect( admin_url( 'admin.php?page=ltlb_appointments' ) );
                exit;
            }
            $this->render_detail( $id );
            return;
        }

		// Handle bulk actions
		if (isset($_POST['action']) && $_POST['action'] !== '-1' && isset($_POST['appointment_ids']) && !empty($_POST['appointment_ids'])) {
			if (!check_admin_referer('ltlb_appointments_bulk_action')) {
				wp_die('Security check failed');
			}
			$action = (string) sanitize_text_field($_POST['action']);
			$ids = array_map('intval', $_POST['appointment_ids']);

			if ($action === 'delete') {
                global $wpdb;
                $appt_table = $wpdb->prefix . 'lazy_appointments';
                $ar_table = $wpdb->prefix . 'lazy_appointment_resources';
                $ids = array_values( array_filter( $ids, static function( $v ) { return $v > 0; } ) );
                if ( empty( $ids ) ) {
                    LTLB_Notices::add( __( 'No appointments selected.', 'ltl-bookings' ), 'error' );
                } else {
                    $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
                    $wpdb->query( $wpdb->prepare( "DELETE FROM {$ar_table} WHERE appointment_id IN ({$placeholders})", ...$ids ) );
                    $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$appt_table} WHERE id IN ({$placeholders})", ...$ids ) );
                    if ( $deleted === false ) {
                        LTLB_Notices::add( __( 'Could not delete appointments. Please try again.', 'ltl-bookings' ), 'error' );
                    } else {
                        LTLB_Notices::add( sprintf( _n( '%d appointment deleted.', '%d appointments deleted.', count( $ids ), 'ltl-bookings' ), count( $ids ) ), 'success' );
                    }
                }
			} else if (strpos((string)$action, 'set_status_') === 0) {
				$status = str_replace('set_status_', '', (string)$action);
				if (in_array((string)$status, ['pending', 'confirmed', 'cancelled'])) {
					$appointment_repo->update_status_bulk($ids, $status);
					LTLB_Notices::add(count($ids) . ' ' . __('appointments updated.', 'ltl-bookings'), 'success');
				}
			}
			wp_safe_redirect(remove_query_arg(['action', 'paged'], wp_get_referer()));
			exit;
		}

		// Get filters from URL
		$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
		$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
		$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
		$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
		$customer_search = isset($_GET['customer_search']) ? sanitize_text_field($_GET['customer_search']) : '';

		$filters = [];
		if (!empty($date_from)) $filters['from'] = $date_from . ' 00:00:00';
		if (!empty($date_to)) $filters['to'] = $date_to . ' 23:59:59';
		if (!empty($status)) $filters['status'] = $status;
		if ($service_id > 0) $filters['service_id'] = $service_id;
		if (!empty($customer_search)) $filters['customer_search'] = $customer_search;

		$per_page = 20;
		$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		$offset = ($current_page - 1) * $per_page;

		$total_appointments = $appointment_repo->get_count($filters);
		$appointments = $appointment_repo->get_all(array_merge($filters, ['limit' => $per_page, 'offset' => $offset]));

		$all_services = $service_repo->get_all();
		?>
        <div class="wrap ltlb-admin">
            <?php LTLB_Admin_Header::render('ltlb_appointments'); ?>
            
            <!-- Page Header with Actions -->
            <div class="ltlb-page-header">
                <div class="ltlb-page-header__content">
                    <h1 class="ltlb-page-header__title">
                        <?php echo esc_html__( 'Appointments', 'ltl-bookings' ); ?>
                    </h1>
                    <p class="ltlb-page-header__subtitle">
                        <?php echo esc_html__( 'Manage all your bookings and appointments', 'ltl-bookings' ); ?>
                    </p>
                </div>
                <div class="ltlb-page-header__actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ltlb_appointments&action=add' ) ); ?>" class="ltlb-btn ltlb-btn--primary">
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        <?php echo esc_html__( 'Add New', 'ltl-bookings' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ltlb_calendar' ) ); ?>" class="ltlb-btn ltlb-btn--secondary">
                        <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                        <?php echo esc_html__( 'Calendar', 'ltl-bookings' ); ?>
                    </a>
                </div>
            </div>
            
            <form method="post">
                <?php LTLB_Admin_Component::card_start(''); ?>
                    <div class="ltlb-table-toolbar">
                        <div class="ltlb-table-toolbar__bulk-actions" role="group" aria-label="<?php esc_attr_e( 'Bulk actions toolbar', 'ltl-bookings' ); ?>">
							<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Choose action to apply to selected appointments', 'ltl-bookings' ); ?></label>
                            <select name="action" id="bulk-action-selector-top" aria-describedby="bulk-action-help">
                                <option value="-1"><?php esc_html_e( 'Bulk Actions', 'ltl-bookings' ); ?></option>
                                <option value="set_status_confirmed"><?php esc_html_e( 'Change status to confirmed', 'ltl-bookings' ); ?></option>
                                <option value="set_status_pending"><?php esc_html_e( 'Change status to pending', 'ltl-bookings' ); ?></option>
                                <option value="set_status_cancelled"><?php esc_html_e( 'Change status to cancelled', 'ltl-bookings' ); ?></option>
                                <option value="delete"><?php esc_html_e( 'Delete', 'ltl-bookings' ); ?></option>
                            </select>
                            <?php submit_button( esc_html__( 'Apply', 'ltl-bookings' ), 'action', '', false, [ 'aria-label' => esc_attr__( 'Apply bulk action to selected appointments', 'ltl-bookings' ) ] ); ?>
                            <span id="bulk-action-help" class="screen-reader-text"><?php esc_html_e( 'Select appointments using checkboxes, choose an action, then click Apply', 'ltl-bookings' ); ?></span>
                        </div>
                        <div class="ltlb-table-toolbar__export">
							<button type="button" class="ltlb-btn ltlb-btn--secondary ltlb-btn--small ltlb-column-toggle-btn" id="ltlb-column-toggle-btn" aria-label="<?php esc_attr_e( 'Show/hide columns', 'ltl-bookings' ); ?>">
                                <span class="dashicons dashicons-visibility"></span>
								<?php esc_html_e( 'Manage columns', 'ltl-bookings' ); ?>
                            </button>
                            <a href="<?php echo esc_url( LTLB_ICS_Export::get_feed_url() ); ?>" class="ltlb-btn ltlb-btn--secondary ltlb-btn--small" target="_blank">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php esc_html_e('Calendar Feed (iCal)', 'ltl-bookings'); ?>
                            </a>
                        </div>
                        <form method="get">
                            <input type="hidden" name="page" value="ltlb_appointments">
                            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                            <select name="status">
                                <option value=""><?php echo esc_html__( 'All Statuses', 'ltl-bookings' ); ?></option>
                                <option value="pending" <?php selected($status, 'pending'); ?>><?php echo esc_html__( 'Pending', 'ltl-bookings' ); ?></option>
                                <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php echo esc_html__( 'Confirmed', 'ltl-bookings' ); ?></option>
                                <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php echo esc_html__( 'Cancelled', 'ltl-bookings' ); ?></option>
                            </select>
                            <button type="submit" class="ltlb-btn ltlb-btn--secondary">
                                <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                                <?php echo esc_html__( 'Filter', 'ltl-bookings' ); ?>
                            </button>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ltlb_appointments' ) ); ?>" class="ltlb-btn ltlb-btn--ghost">
                                <span class="dashicons dashicons-undo" aria-hidden="true"></span>
                                <?php echo esc_html__( 'Reset', 'ltl-bookings' ); ?>
                            </a>
                        </form>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td id="cb" class="manage-column column-cb check-column">
                                    <label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'ltl-bookings' ); ?></label>
                                    <input id="cb-select-all-1" type="checkbox">
                                </td>
                                <th scope="col" class="manage-column" data-column="customer"><?php echo esc_html__( 'Customer', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column" data-column="service"><?php echo esc_html__( 'Service', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column" data-column="start"><?php echo esc_html__( 'Start', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column" data-column="end"><?php echo esc_html__( 'End', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column" data-column="status"><?php echo esc_html__( 'Status', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column" data-column="payment"><?php echo esc_html__( 'Payment', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column" data-column="amount"><?php echo esc_html__( 'Amount', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column" data-column="paid"><?php echo esc_html__( 'Paid at', 'ltl-bookings' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="9">
                                        <?php 
                                        LTLB_Admin_Component::empty_state(
                                            __('No Appointments Found', 'ltl-bookings'),
                                            __('There are no appointments matching your current filters.', 'ltl-bookings'),
                                            __('Clear Filters', 'ltl-bookings'),
                                            admin_url('admin.php?page=ltlb_appointments'),
                                            'dashicons-calendar-alt'
                                        ); 
                                        ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appointment): 
                                    $customer = $customer_repo->get_by_id($appointment['customer_id']);
                                    $service = $service_repo->get_by_id($appointment['service_id']);
                                    $view_url = admin_url( 'admin.php?page=ltlb_appointments&action=view&id=' . intval( $appointment['id'] ) );
                                    $payment_status = (string) ( $appointment['payment_status'] ?? '' );
                                    $payment_method = (string) ( $appointment['payment_method'] ?? '' );
                                    $paid_at_raw = (string) ( $appointment['paid_at'] ?? '' );
                                    $paid_at_label = $paid_at_raw ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $paid_at_raw ) ) : '—';
                                    $amount_label = self::format_amount( $appointment['amount_cents'] ?? 0, $appointment['currency'] ?? 'EUR' );
                                    $tz_string = ! empty( $appointment['timezone'] ) ? (string) $appointment['timezone'] : ( class_exists( 'LTLB_Time' ) ? LTLB_Time::wp_timezone()->getName() : 'UTC' );
                                    $start_display = (string) ( $appointment['start_at'] ?? '' );
                                    $end_display = (string) ( $appointment['end_at'] ?? '' );
                                    if ( class_exists( 'LTLB_DateTime' ) ) {
                                        $start_display = LTLB_DateTime::format_local_display_from_utc_mysql( (string) ( $appointment['start_at'] ?? '' ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $tz_string );
                                        $end_display = LTLB_DateTime::format_local_display_from_utc_mysql( (string) ( $appointment['end_at'] ?? '' ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $tz_string );
                                    }
                                ?>
                                    <tr>
                                        <th scope="row" class="check-column">
                                            <input type="checkbox" name="appointment_ids[]" value="<?php echo esc_attr( $appointment['id'] ); ?>">
                                        </th>
                                        <td data-column="customer"><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $customer ? ( trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) ) ?: '—' ) : '—' ); ?></a></td>
                                        <td data-column="service"><?php echo esc_html( $service ? ( (string) ( $service['name'] ?? '' ) ?: '—' ) : '—' ); ?></td>
                                        <td data-column="start"><?php echo esc_html( $start_display ); ?></td>
                                        <td data-column="end"><?php echo esc_html( $end_display ); ?></td>
                                        <td data-column="status"><span class="ltlb-status-badge status-<?php echo esc_attr($appointment['status']); ?>"><?php echo esc_html(ucfirst($appointment['status'])); ?></span></td>
                                        <td data-column="payment"><?php echo esc_html( self::payment_status_label( $payment_status ) . ' • ' . self::payment_method_label( $payment_method ) ); ?></td>
                                        <td data-column="amount"><?php echo esc_html( $amount_label ); ?></td>
                                        <td data-column="paid"><?php echo esc_html( $paid_at_label ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php LTLB_Admin_Component::pagination($total_appointments, $per_page); ?>
                <?php LTLB_Admin_Component::card_end(); ?>
                <?php wp_nonce_field('ltlb_appointments_bulk_action'); ?>
            </form>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('cb-select-all-1');
                const checkboxes = document.querySelectorAll('input[name="appointment_ids[]"]');
                if (selectAll) {
                    selectAll.addEventListener('change', function(e) {
                        checkboxes.forEach(cb => cb.checked = e.target.checked);
                    });
                }

                // Column toggle functionality
                const columnToggleBtn = document.getElementById('ltlb-column-toggle-btn');
                if (columnToggleBtn) {
                    const columns = ['customer', 'service', 'start', 'end', 'status', 'payment', 'amount', 'paid'];
                    const storageKey = 'ltlb_appointments_visible_columns';
                    
                    // Load saved preferences
                    let visibleColumns = localStorage.getItem(storageKey);
                    if (visibleColumns) {
                        visibleColumns = JSON.parse(visibleColumns);
                    } else {
                        visibleColumns = columns; // All visible by default
                    }
                    
                    // Apply saved state
                    applyColumnVisibility(visibleColumns);
                    
                    // Create toggle menu
                    columnToggleBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        let menu = document.getElementById('ltlb-column-toggle-menu');
                        
                        if (menu) {
                            menu.remove();
                            return;
                        }
                        
                        menu = document.createElement('div');
                        menu.id = 'ltlb-column-toggle-menu';
                        menu.className = 'ltlb-column-toggle-menu';
                        menu.setAttribute('role', 'menu');
                        
                        columns.forEach(col => {
                            const label = document.createElement('label');
                            label.className = 'ltlb-column-toggle-item';
                            
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.value = col;
                            checkbox.checked = visibleColumns.includes(col);
                            
                            const colName = col.charAt(0).toUpperCase() + col.slice(1);
                            const text = document.createTextNode(colName);
                            
                            checkbox.addEventListener('change', function() {
                                if (this.checked) {
                                    if (!visibleColumns.includes(col)) {
                                        visibleColumns.push(col);
                                    }
                                } else {
                                    visibleColumns = visibleColumns.filter(c => c !== col);
                                }
                                localStorage.setItem(storageKey, JSON.stringify(visibleColumns));
                                applyColumnVisibility(visibleColumns);
                            });
                            
                            label.appendChild(checkbox);
                            label.appendChild(text);
                            menu.appendChild(label);
                        });
                        
                        columnToggleBtn.parentElement.style.position = 'relative';
                        columnToggleBtn.parentElement.appendChild(menu);
                        
                        // Close menu when clicking outside
                        setTimeout(() => {
                            document.addEventListener('click', function closeMenu(e) {
                                if (!menu.contains(e.target) && e.target !== columnToggleBtn) {
                                    menu.remove();
                                    document.removeEventListener('click', closeMenu);
                                }
                            });
                        }, 0);
                    });
                }
                
                function applyColumnVisibility(visibleColumns) {
                    const table = document.querySelector('.wp-list-table');
                    if (!table) return;
					
                    const allColumns = ['customer', 'service', 'start', 'end', 'status', 'payment', 'amount', 'paid'];
                    allColumns.forEach(col => {
                        const isVisible = visibleColumns.includes(col);
                        const headers = table.querySelectorAll(`th[data-column="${col}"]`);
                        const cells = table.querySelectorAll(`td[data-column="${col}"]`);
                        
                        headers.forEach(h => h.style.display = isVisible ? '' : 'none');
                        cells.forEach(c => c.style.display = isVisible ? '' : 'none');
                    });
                }
            });
        </script>
		<?php
	}
}

