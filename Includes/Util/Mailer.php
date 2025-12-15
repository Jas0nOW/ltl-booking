<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Mailer {
    private static bool $smtp_context_enabled = false;

    public static function init(): void {
        add_action( 'phpmailer_init', [ __CLASS__, 'configure_phpmailer' ] );
    }

    /**
     * Send an email through wp_mail() while marking it as a LazyBookings email.
     * This allows SMTP settings to be applied only to LazyBookings mails when configured.
     */
    public static function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ): bool {
        $prev = self::$smtp_context_enabled;
        self::$smtp_context_enabled = true;
        try {
            return (bool) wp_mail( $to, $subject, $message, $headers, $attachments );
        } finally {
            self::$smtp_context_enabled = $prev;
        }
    }

    /**
     * Configure PHPMailer for SMTP when enabled in LazyBookings settings.
     * Scope is configurable: global (all wp_mail) or plugin-only (LazyBookings emails).
     */
    public static function configure_phpmailer( $phpmailer ): void {
        $settings = get_option( 'lazy_settings', [] );
        if ( ! is_array( $settings ) ) {
            return;
        }
        if ( empty( $settings['smtp_enabled'] ) ) {
            return;
        }

        $scope = isset( $settings['smtp_scope'] ) ? sanitize_key( (string) $settings['smtp_scope'] ) : 'global';
        if ( $scope !== 'global' && $scope !== 'plugin' ) {
            $scope = 'global';
        }
        if ( $scope === 'plugin' && ! self::$smtp_context_enabled ) {
            return;
        }

        $host = isset( $settings['smtp_host'] ) ? sanitize_text_field( (string) $settings['smtp_host'] ) : '';
        $port = isset( $settings['smtp_port'] ) ? intval( $settings['smtp_port'] ) : 0;
        $encryption = isset( $settings['smtp_encryption'] ) ? sanitize_key( (string) $settings['smtp_encryption'] ) : '';
        $auth = ! empty( $settings['smtp_auth'] );
        $username = isset( $settings['smtp_username'] ) ? sanitize_text_field( (string) $settings['smtp_username'] ) : '';

        $mail_keys = get_option( 'lazy_mail_keys', [] );
        if ( ! is_array( $mail_keys ) ) {
            $mail_keys = [];
        }
        $password = isset( $mail_keys['smtp_password'] ) ? (string) $mail_keys['smtp_password'] : '';

        if ( $host === '' || $port <= 0 ) {
            return;
        }

        try {
            $phpmailer->isSMTP();
            $phpmailer->Host = $host;
            $phpmailer->Port = $port;
            $phpmailer->SMTPAuth = $auth;

            if ( $encryption === 'tls' || $encryption === 'ssl' ) {
                $phpmailer->SMTPSecure = $encryption;
            }

            if ( $auth ) {
                $phpmailer->Username = $username;
                $phpmailer->Password = $password;
            }

            $from_email = $settings['mail_from_email'] ?? '';
            $from_name = $settings['mail_from_name'] ?? '';
            if ( is_string( $from_email ) ) {
                $from_email = sanitize_email( $from_email );
            }
            if ( is_string( $from_name ) ) {
                $from_name = sanitize_text_field( $from_name );
            }
            if ( $from_email && is_email( $from_email ) ) {
                $phpmailer->setFrom( $from_email, $from_name ?: '' , false );
            }
        } catch ( Throwable $e ) {
            // Never break the page because of SMTP config.
            if ( class_exists( 'LTLB_Logger' ) ) {
                LTLB_Logger::error( 'SMTP init failed: ' . $e->getMessage() );
            }
        }
    }

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

        $results['admin'] = self::wp_mail( $admin_to, $subj, $body, $headers );

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

            $results['customer'] = self::wp_mail( $customer['email'], $csubj, $cbody, $headers );
        }

        return $results;
    }
}
