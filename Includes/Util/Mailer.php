<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Mailer {

    public static function replace_placeholders(string $template, array $placeholders): string {
        $search = [];
        $replace = [];
        foreach ( $placeholders as $key => $val ) {
            $search[] = '{' . $key . '}';
            $replace[] = $val;
        }
        return str_replace( $search, $replace, $template );
    }

    public static function send_booking_notifications( int $appointment_id, array $service, array $customer, string $start_at, string $end_at, string $status, int $seats = 1 ): array {
        $results = [];

        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) $ls = [];

        $from_name = $ls['mail_from_name'] ?? '';
        $from_addr = $ls['mail_from_email'] ?? get_option('admin_email');
        $reply_to = $ls['mail_reply_to'] ?? '';

        $admin_to = get_option('admin_email');
        $admin_subject = $ls['mail_admin_subject'] ?? ($ls['mail_admin_template_subject'] ?? '');
        $admin_body = $ls['mail_admin_template'] ?? '';

        $customer_send = ! empty( $ls['mail_customer_enabled'] ) ? 1 : 0;
        $customer_subject = $ls['mail_customer_subject'] ?? ($ls['mail_customer_template_subject'] ?? '');
        $customer_body = $ls['mail_customer_template'] ?? '';

        $placeholders = [
            'service' => $service['name'] ?? '',
            'start' => $start_at,
            'end' => $end_at,
            'name' => trim( ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '') ),
            'email' => $customer['email'] ?? '',
            'phone' => $customer['phone'] ?? '',
            'status' => $status,
            'appointment_id' => (string) $appointment_id,
            'seats' => (string) $seats,
        ];

        // admin email
        if ( empty( $admin_subject ) ) {
            $admin_subject = sprintf( 'New booking: %s - %s', $placeholders['service'], $placeholders['start'] );
        }
        if ( empty( $admin_body ) ) {
			$admin_body = "A new booking was created:\n\nService: {service}\nStart: {start}\nEnd: {end}\nSeats/Guests: {seats}\nCustomer: {name} <{email}>\nPhone: {phone}\nStatus: {status}\nAppointment ID: {appointment_id}";
        }

        $subj = self::replace_placeholders( $admin_subject, $placeholders );
        $body = self::replace_placeholders( $admin_body, $placeholders );

        $headers = [];
        if ( ! empty( $from_name ) || ! empty( $from_addr ) ) {
            $headers[] = 'From: ' . ( $from_name ? $from_name : '' ) . ' <' . $from_addr . '>';
        }
        if ( ! empty( $reply_to ) && is_email( $reply_to ) ) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        $results['admin'] = wp_mail( $admin_to, $subj, $body, $headers );

        // customer email
        if ( $customer_send && ! empty( $customer['email'] ) ) {
            if ( empty( $customer_subject ) ) {
                $customer_subject = sprintf( 'Your booking %s on %s', $placeholders['service'], $placeholders['start'] );
            }
            if ( empty( $customer_body ) ) {
				$customer_body = "Hello {name},\n\nThank you for your booking. Details:\nService: {service}\nStart: {start}\nEnd: {end}\nSeats/Guests: {seats}\nStatus: {status}\nAppointment ID: {appointment_id}\n\nRegards";
            }

            $csubj = self::replace_placeholders( $customer_subject, $placeholders );
            $cbody = self::replace_placeholders( $customer_body, $placeholders );

            $results['customer'] = wp_mail( $customer['email'], $csubj, $cbody, $headers );
        }

        return $results;
    }
}
