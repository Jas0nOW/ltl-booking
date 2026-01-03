<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Email Notification System
 */
class LTLB_EmailNotifications {

    /**
     * Send booking confirmation email to customer
     */
    public static function send_customer_booking_confirmation( int $appointment_id ): bool {
        $appt_repo = new LTLB_AppointmentRepository();
        $appointment = $appt_repo->get_by_id( $appointment_id );
        
        if ( ! $appointment ) return false;

        $customer_email = $appointment['customer_email'] ?? '';
        if ( ! is_email( $customer_email ) ) return false;

        $settings = get_option( 'lazy_settings', [] );
        $site_name = get_bloginfo( 'name' );
        
        $subject = sprintf(
            __( 'Booking Confirmation - %s', 'ltl-bookings' ),
            $site_name
        );

        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( intval($appointment['service_id']) );
        $service_name = $service ? $service['name'] : __( 'Service', 'ltl-bookings' );

        $amount_cents = isset( $appointment['amount_cents'] ) ? intval( $appointment['amount_cents'] ) : 0;
        $currency = ! empty( $appointment['currency'] ) ? sanitize_text_field( (string) $appointment['currency'] ) : 'EUR';
        $price_label = $amount_cents > 0 ? number_format( $amount_cents / 100, 2 ) . ' ' . $currency : __( 'Free', 'ltl-bookings' );

        $tz_string = ! empty( $appointment['timezone'] ) ? (string) $appointment['timezone'] : ( class_exists( 'LTLB_Time' ) ? LTLB_Time::wp_timezone()->getName() : 'UTC' );
        $start_display = class_exists( 'LTLB_Time' ) ? LTLB_Time::format_local_display_from_utc_mysql( (string) ( $appointment['start_at'] ?? '' ), get_option('date_format') . ' ' . get_option('time_format'), $tz_string ) : (string) ( $appointment['start_at'] ?? '' );
        $end_display = class_exists( 'LTLB_Time' ) ? LTLB_Time::format_local_display_from_utc_mysql( (string) ( $appointment['end_at'] ?? '' ), get_option('time_format'), $tz_string ) : (string) ( $appointment['end_at'] ?? '' );

        $body = self::get_template( 'customer-booking-confirmation', [
            'customer_name' => $appointment['customer_name'] ?? '',
            'service_name' => $service_name,
            'start_time' => $start_display,
            'end_time' => $end_display,
            'status' => $appointment['status'] ?? 'pending',
            'price' => $price_label,
            'appointment_id' => $appointment_id,
        ] );

        return self::send_email( $customer_email, $subject, $body );
    }

    /**
     * Send booking notification email to admin
     */
    public static function send_admin_booking_notification( int $appointment_id ): bool {
        $appt_repo = new LTLB_AppointmentRepository();
        $appointment = $appt_repo->get_by_id( $appointment_id );
        
        if ( ! $appointment ) return false;

        $admin_email = get_option( 'admin_email' );
        $settings = get_option( 'lazy_settings', [] );
        $notify_email = $settings['admin_notification_email'] ?? $admin_email;

        if ( ! is_email( $notify_email ) ) return false;

        $site_name = get_bloginfo( 'name' );
        
        $subject = sprintf(
            __( 'New Booking Received - %s', 'ltl-bookings' ),
            $site_name
        );

        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( intval($appointment['service_id']) );
        $service_name = $service ? $service['name'] : __( 'Service', 'ltl-bookings' );

        $amount_cents = isset( $appointment['amount_cents'] ) ? intval( $appointment['amount_cents'] ) : 0;
        $currency = ! empty( $appointment['currency'] ) ? sanitize_text_field( (string) $appointment['currency'] ) : 'EUR';
        $price_label = $amount_cents > 0 ? number_format( $amount_cents / 100, 2 ) . ' ' . $currency : __( 'Free', 'ltl-bookings' );

        $tz_string = ! empty( $appointment['timezone'] ) ? (string) $appointment['timezone'] : ( class_exists( 'LTLB_Time' ) ? LTLB_Time::wp_timezone()->getName() : 'UTC' );
        $start_display = class_exists( 'LTLB_Time' ) ? LTLB_Time::format_local_display_from_utc_mysql( (string) ( $appointment['start_at'] ?? '' ), get_option('date_format') . ' ' . get_option('time_format'), $tz_string ) : (string) ( $appointment['start_at'] ?? '' );
        $end_display = class_exists( 'LTLB_Time' ) ? LTLB_Time::format_local_display_from_utc_mysql( (string) ( $appointment['end_at'] ?? '' ), get_option('time_format'), $tz_string ) : (string) ( $appointment['end_at'] ?? '' );

        $admin_url = admin_url( 'admin.php?page=ltlb_appointments&action=view&id=' . $appointment_id );

        $body = self::get_template( 'admin-booking-notification', [
            'customer_name' => $appointment['customer_name'] ?? '',
            'customer_email' => $appointment['customer_email'] ?? '',
            'customer_phone' => $appointment['customer_phone'] ?? '',
            'service_name' => $service_name,
            'start_time' => $start_display,
            'end_time' => $end_display,
            'status' => $appointment['status'] ?? 'pending',
            'price' => $price_label,
            'appointment_id' => $appointment_id,
            'admin_url' => $admin_url,
        ] );

        return self::send_email( $notify_email, $subject, $body );
    }

    /**
     * Send status change notification to customer
     */
    public static function send_status_change_notification( int $appointment_id, string $new_status ): bool {
        $appt_repo = new LTLB_AppointmentRepository();
        $appointment = $appt_repo->get_by_id( $appointment_id );
        
        if ( ! $appointment ) return false;

        $customer_email = $appointment['customer_email'] ?? '';
        if ( ! is_email( $customer_email ) ) return false;

        $site_name = get_bloginfo( 'name' );
        
        $status_labels = [
            'pending' => __( 'Pending', 'ltl-bookings' ),
            'confirmed' => __( 'Confirmed', 'ltl-bookings' ),
            'cancelled' => __( 'Cancelled', 'ltl-bookings' ),
            'completed' => __( 'Completed', 'ltl-bookings' ),
        ];

        $status_label = $status_labels[ $new_status ] ?? ucfirst( $new_status );

        $subject = sprintf(
            __( 'Booking Status Update: %s - %s', 'ltl-bookings' ),
            $status_label,
            $site_name
        );

        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( intval($appointment['service_id']) );
        $service_name = $service ? $service['name'] : __( 'Service', 'ltl-bookings' );

        $tz_string = ! empty( $appointment['timezone'] ) ? (string) $appointment['timezone'] : ( class_exists( 'LTLB_Time' ) ? LTLB_Time::wp_timezone()->getName() : 'UTC' );
        $start_display = class_exists( 'LTLB_Time' ) ? LTLB_Time::format_local_display_from_utc_mysql( (string) ( $appointment['start_at'] ?? '' ), get_option('date_format') . ' ' . get_option('time_format'), $tz_string ) : (string) ( $appointment['start_at'] ?? '' );

        $body = self::get_template( 'customer-status-change', [
            'customer_name' => $appointment['customer_name'] ?? '',
            'service_name' => $service_name,
            'start_time' => $start_display,
            'status' => $status_label,
            'new_status' => $new_status,
            'appointment_id' => $appointment_id,
        ] );

        return self::send_email( $customer_email, $subject, $body );
    }

    /**
     * Get email template
     */
    private static function get_template( string $template_name, array $data ): string {
        $site_name = get_bloginfo( 'name' );
        $site_url = home_url();

        $header = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .email-header { background: #2271b1; color: #ffffff; padding: 20px; text-align: center; }
        .email-header h1 { margin: 0; font-size: 24px; }
        .email-body { padding: 30px; }
        .email-body h2 { color: #2271b1; margin-top: 0; }
        .booking-details { background: #f9f9f9; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; }
        .booking-details p { margin: 8px 0; }
        .booking-details strong { display: inline-block; min-width: 120px; }
        .button { display: inline-block; padding: 12px 24px; background: #2271b1; color: #ffffff; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .email-footer { background: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>' . esc_html( $site_name ) . '</h1>
        </div>
        <div class="email-body">';

        $footer = '
        </div>
        <div class="email-footer">
            <p>&copy; ' . date('Y') . ' ' . esc_html( $site_name ) . '. ' . esc_html__( 'All rights reserved.', 'ltl-bookings' ) . '</p>
            <p><a href="' . esc_url( $site_url ) . '">' . esc_html( $site_url ) . '</a></p>
        </div>
    </div>
</body>
</html>';

        $content = '';

        switch ( $template_name ) {
            case 'customer-booking-confirmation':
                $content = sprintf(
                    '<h2>%s</h2>
                    <p>%s %s,</p>
                    <p>%s</p>
                    <div class="booking-details">
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> #%d</p>
                    </div>
                    <p>%s</p>',
                    esc_html__( 'Booking Confirmation', 'ltl-bookings' ),
                    esc_html__( 'Hello', 'ltl-bookings' ),
                    esc_html( $data['customer_name'] ),
                    esc_html__( 'Thank you for your booking! Here are the details:', 'ltl-bookings' ),
                    esc_html__( 'Service', 'ltl-bookings' ),
                    esc_html( $data['service_name'] ),
                    esc_html__( 'Date & Time', 'ltl-bookings' ),
                    esc_html( $data['start_time'] ) . ' - ' . esc_html( $data['end_time'] ),
                    esc_html__( 'Status', 'ltl-bookings' ),
                    esc_html( ucfirst( $data['status'] ) ),
                    esc_html__( 'Price', 'ltl-bookings' ),
                    esc_html( $data['price'] ),
                    esc_html__( 'Booking ID', 'ltl-bookings' ),
                    intval( $data['appointment_id'] ),
                    esc_html__( 'We will send you another email once your booking is confirmed.', 'ltl-bookings' )
                );
                break;

            case 'admin-booking-notification':
                $content = sprintf(
                    '<h2>%s</h2>
                    <p>%s</p>
                    <div class="booking-details">
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> #%d</p>
                    </div>
                    <a href="%s" class="button">%s</a>',
                    esc_html__( 'New Booking Received', 'ltl-bookings' ),
                    esc_html__( 'A new booking has been received and requires your attention.', 'ltl-bookings' ),
                    esc_html__( 'Customer', 'ltl-bookings' ),
                    esc_html( $data['customer_name'] ),
                    esc_html__( 'Email', 'ltl-bookings' ),
                    esc_html( $data['customer_email'] ),
                    esc_html__( 'Phone', 'ltl-bookings' ),
                    esc_html( $data['customer_phone'] ?: '—' ),
                    esc_html__( 'Service', 'ltl-bookings' ),
                    esc_html( $data['service_name'] ),
                    esc_html__( 'Date & Time', 'ltl-bookings' ),
                    esc_html( $data['start_time'] ) . ' - ' . esc_html( $data['end_time'] ),
                    esc_html__( 'Price', 'ltl-bookings' ),
                    esc_html( $data['price'] ),
                    esc_html__( 'Booking ID', 'ltl-bookings' ),
                    intval( $data['appointment_id'] ),
                    esc_url( $data['admin_url'] ),
                    esc_html__( 'View in Admin', 'ltl-bookings' )
                );
                break;

            case 'customer-status-change':
                $content = sprintf(
                    '<h2>%s</h2>
                    <p>%s %s,</p>
                    <p>%s <strong>%s</strong>.</p>
                    <div class="booking-details">
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> %s</p>
                        <p><strong>%s:</strong> #%d</p>
                    </div>',
                    esc_html__( 'Booking Status Update', 'ltl-bookings' ),
                    esc_html__( 'Hello', 'ltl-bookings' ),
                    esc_html( $data['customer_name'] ),
                    esc_html__( 'Your booking status has been updated to', 'ltl-bookings' ),
                    esc_html( $data['status'] ),
                    esc_html__( 'Service', 'ltl-bookings' ),
                    esc_html( $data['service_name'] ),
                    esc_html__( 'Date & Time', 'ltl-bookings' ),
                    esc_html( $data['start_time'] ),
                    esc_html__( 'Booking ID', 'ltl-bookings' ),
                    intval( $data['appointment_id'] )
                );
                break;

            default:
                $content = '<p>' . esc_html__( 'Email template not found.', 'ltl-bookings' ) . '</p>';
        }

        return $header . $content . $footer;
    }

    /**
     * Send email with proper headers
     */
    private static function send_email( string $to, string $subject, string $body ): bool {
        $settings = get_option( 'lazy_settings', [] );
        $from_name = $settings['mail_from_name'] ?? get_bloginfo('name');
        $from_email = $settings['mail_from_email'] ?? get_option('admin_email');
        $reply_to = $settings['mail_reply_to'] ?? '';

        $headers = [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        
        if ( ! empty( $reply_to ) && is_email( $reply_to ) ) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        if ( class_exists( 'LTLB_Mailer' ) && method_exists( 'LTLB_Mailer', 'wp_mail' ) ) {
            return LTLB_Mailer::wp_mail( $to, $subject, $body, $headers );
        }
        return wp_mail( $to, $subject, $body, $headers );
    }
}

