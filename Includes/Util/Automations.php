<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Automations {
	private const OPTION_RULES = 'lazy_automation_rules';
	private const OPTION_LOCK = 'lazy_automation_lock';
	private const OPTION_LOGS = 'lazy_automation_logs';

	public static function get_rules(): array {
		$rules = get_option( self::OPTION_RULES, [] );
		return is_array( $rules ) ? $rules : [];
	}

	public static function save_rules( array $rules ): void {
		update_option( self::OPTION_RULES, array_values( $rules ) );
	}

	public static function get_logs( string $rule_id = '', int $limit = 100 ): array {
		$limit = max( 1, min( 500, $limit ) );
		$logs = get_option( self::OPTION_LOGS, [] );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}

		// Newest first.
		$logs = array_values( array_reverse( $logs ) );
		if ( $rule_id !== '' ) {
			$logs = array_values( array_filter( $logs, static function( $row ) use ( $rule_id ) {
				return is_array( $row ) && (string) ( $row['rule_id'] ?? '' ) === $rule_id;
			} ) );
		}
		return array_slice( $logs, 0, $limit );
	}

	private static function add_log( array $rule, array $result, int $run_ts ): void {
		$logs = get_option( self::OPTION_LOGS, [] );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}

		$rule_id = isset( $rule['id'] ) ? sanitize_text_field( (string) $rule['id'] ) : '';
		$type = isset( $rule['type'] ) ? sanitize_key( (string) $rule['type'] ) : '';
		$success = ! empty( $result['success'] );
		$message = isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '';

		$logs[] = [
			'ts' => (int) $run_ts,
			'rule_id' => $rule_id,
			'type' => $type,
			'success' => $success ? 1 : 0,
			'message' => $message,
			'data' => $result,
		];

		// Keep bounded.
		$max = 500;
		if ( count( $logs ) > $max ) {
			$logs = array_slice( $logs, -1 * $max );
		}
		update_option( self::OPTION_LOGS, $logs );
	}

	public static function run_due_rules(): void {
		// Simple lock to avoid overlapping cron runs.
		$lock = get_transient( self::OPTION_LOCK );
		if ( $lock ) return;
		set_transient( self::OPTION_LOCK, 1, 10 * MINUTE_IN_SECONDS );

		$rules = self::get_rules();
		if ( empty( $rules ) ) {
			delete_transient( self::OPTION_LOCK );
			return;
		}

		$now = current_time( 'timestamp' );
		$updated = false;

		foreach ( $rules as &$rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			$next_run = isset( $rule['next_run_ts'] ) ? intval( $rule['next_run_ts'] ) : 0;
			if ( $next_run > 0 && $next_run > $now ) {
				continue;
			}

			$res = self::run_rule( $rule );
			$rule['last_run_ts'] = $now;
			$rule['last_result'] = $res;
			$rule['next_run_ts'] = self::compute_next_run( $rule, $now );
			self::add_log( $rule, $res, $now );

			$updated = true;
		}

		if ( $updated ) {
			self::save_rules( $rules );
		}

		delete_transient( self::OPTION_LOCK );
	}

	private static function compute_next_run( array $rule, int $now_ts ): int {
		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$now = new DateTimeImmutable( '@' . $now_ts );
		$now = $now->setTimezone( $tz );

		$schedule = isset( $rule['schedule'] ) ? sanitize_key( (string) $rule['schedule'] ) : '';
		if ( $schedule === '' ) {
			// Back-compat: default to daily.
			$schedule = 'daily';
		}

		if ( $schedule === 'minutely' ) {
			$interval = isset( $rule['interval_min'] ) ? intval( $rule['interval_min'] ) : 60;
			$interval = max( 1, min( 1440, $interval ) );
			return $now_ts + ( $interval * MINUTE_IN_SECONDS );
		}

		if ( $schedule === 'hourly' ) {
			$minute = isset( $rule['minute'] ) ? intval( $rule['minute'] ) : 0;
			$minute = max( 0, min( 59, $minute ) );
			$current_hour = $now->setTime( (int) $now->format( 'H' ), $minute, 0 );
			$next = $current_hour;
			if ( $current_hour->getTimestamp() <= $now->getTimestamp() ) {
				$next = $current_hour->modify( '+1 hour' );
			}
			return $next->getTimestamp();
		}

		$hhmm = isset( $rule['time_hhmm'] ) ? sanitize_text_field( (string) $rule['time_hhmm'] ) : '09:00';

		if ( ! preg_match( '/^\d{2}:\d{2}$/', $hhmm ) ) {
			$hhmm = '09:00';
		}
		[ $hh, $mm ] = array_map( 'intval', explode( ':', $hhmm ) );

		if ( $schedule === 'weekly' ) {
			$weekday = isset( $rule['weekday'] ) ? intval( $rule['weekday'] ) : 1; // 1=Mon .. 7=Sun
			$weekday = max( 1, min( 7, $weekday ) );

			// Find next occurrence of weekday at HH:MM.
			$start = $now->setTime( $hh, $mm, 0 );
			for ( $i = 0; $i <= 7; $i++ ) {
				$candidate = $start->modify( '+' . $i . ' day' );
				if ( intval( $candidate->format( 'N' ) ) !== $weekday ) {
					continue;
				}
				if ( $candidate->getTimestamp() <= $now->getTimestamp() ) {
					continue;
				}
				return $candidate->getTimestamp();
			}
			// Fallback: next week same weekday.
			$next = $start->modify( '+7 day' );
			return $next->getTimestamp();
		}

		if ( $schedule === 'monthly' ) {
			$day = isset( $rule['day_of_month'] ) ? intval( $rule['day_of_month'] ) : 1;
			// Avoid invalid dates; keep within 1..28.
			$day = max( 1, min( 28, $day ) );

			$year = intval( $now->format( 'Y' ) );
			$month = intval( $now->format( 'm' ) );
			$candidate = new DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hh, $mm ), $tz );
			if ( $candidate->getTimestamp() <= $now->getTimestamp() ) {
				$candidate = $candidate->modify( 'first day of next month' );
				$year = intval( $candidate->format( 'Y' ) );
				$month = intval( $candidate->format( 'm' ) );
				$candidate = new DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hh, $mm ), $tz );
			}
			return $candidate->getTimestamp();
		}

		// Daily.
		$today = $now->setTime( $hh, $mm, 0 );
		$next = $today;
		if ( $today->getTimestamp() <= $now->getTimestamp() ) {
			$next = $today->modify( '+1 day' );
		}
		return $next->getTimestamp();
	}

	private static function effective_mode( array $rule ): string {
		$mode = isset( $rule['mode'] ) ? sanitize_key( (string) $rule['mode'] ) : 'inherit';
		if ( ! in_array( $mode, [ 'inherit', 'auto', 'hitl' ], true ) ) {
			$mode = 'inherit';
		}
		if ( $mode === 'inherit' ) {
			return class_exists( 'LTLB_AI_Outbox' ) && LTLB_AI_Outbox::is_hitl_mode() ? 'hitl' : 'auto';
		}
		return $mode;
	}

	private static function run_rule( array $rule ): array {
		$type = isset( $rule['type'] ) ? sanitize_key( (string) $rule['type'] ) : '';
		if ( $type === 'payment_reminder' ) {
			return self::run_payment_reminders( $rule );
		}
		if ( $type === 'invoice_send' ) {
			return self::run_invoices( $rule );
		}
		if ( $type === 'overdue_reminder' ) {
			return self::run_overdue_reminders( $rule );
		}
		if ( $type === 'insights_report' ) {
			return self::run_insights_report( $rule );
		}
		return [ 'success' => false, 'message' => __( 'Unknown rule type.', 'ltl-bookings' ) ];
	}

	public static function generate_ai_insights_now(): array {
		$report = self::build_insights_bundle_report();
		if ( $report === '' ) {
			return [ 'success' => false, 'message' => __( 'Could not generate report.', 'ltl-bookings' ) ];
		}

		$id = LTLB_AI_Outbox::create_draft( 'insight_report', '', $report, [ 'range' => 'daily+overall' ], '' );
		if ( ! $id ) {
			return [ 'success' => false, 'message' => __( 'Could not create report draft.', 'ltl-bookings' ) ];
		}

		if ( LTLB_AI_Outbox::is_hitl_mode() ) {
			return [ 'success' => true, 'message' => __( 'Report draft created in Outbox.', 'ltl-bookings' ), 'id' => $id ];
		}

		$res = LTLB_AI_Outbox::approve_and_execute( $id );
		if ( ! empty( $res['success'] ) ) {
			update_option( 'lazy_ai_last_report', [ 'created_at' => current_time( 'mysql' ), 'report' => $report ] );
		}
		return $res;
	}

	private static function run_payment_reminders( array $rule ): array {
		$mode = self::effective_mode( $rule );
		$days_before = isset( $rule['days_before'] ) ? max( 0, intval( $rule['days_before'] ) ) : 2;
		$limit = isset( $rule['limit'] ) ? max( 1, min( 200, intval( $rule['limit'] ) ) ) : 50;
		$template_id = isset( $rule['template_id'] ) ? sanitize_text_field( (string) $rule['template_id'] ) : 'payment_reminder_default';
		$templates = get_option( 'lazy_reply_templates', [] );
		if ( ! is_array( $templates ) ) $templates = [];
		$template = null;
		foreach ( $templates as $tpl ) {
			if ( (string) ( $tpl['id'] ?? '' ) === $template_id ) {
				$template = $tpl;
				break;
			}
		}

		$repo = new LTLB_AppointmentRepository();
		$from = current_time( 'mysql' );
		$to = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( $days_before * DAY_IN_SECONDS ) );

		// We only have basic filters in repository; fetch candidates and filter in PHP.
		$candidates = $repo->get_all( [ 'from' => date( 'Y-m-d 00:00:00' ), 'to' => $to, 'limit' => $limit, 'offset' => 0 ] );
		if ( empty( $candidates ) ) {
			return [ 'success' => true, 'message' => __( 'No candidates.', 'ltl-bookings' ), 'sent' => 0 ];
		}

		$cust_repo = new LTLB_CustomerRepository();
		$svc_repo = new LTLB_ServiceRepository();
		$sent = 0;
		$skipped = 0;

		$today_key = date( 'Y-m-d', current_time( 'timestamp' ) );

		foreach ( $candidates as $appt ) {
			if ( $sent >= $limit ) break;

			$payment_status = isset( $appt['payment_status'] ) ? sanitize_key( (string) $appt['payment_status'] ) : 'free';
			if ( $payment_status !== 'unpaid' ) {
				$skipped++;
				continue;
			}

			$appt_id = intval( $appt['id'] ?? 0 );
			if ( $appt_id <= 0 ) {
				$skipped++;
				continue;
			}

			// De-dupe: one reminder per appointment per day.
			$dedupe_key = 'ltlb_reminder_' . $appt_id . '_' . $today_key;
			if ( get_transient( $dedupe_key ) ) {
				$skipped++;
				continue;
			}

			$customer = $cust_repo->get_by_id( intval( $appt['customer_id'] ?? 0 ) );
			if ( ! $customer || empty( $customer['email'] ) || ! is_email( $customer['email'] ) ) {
				$skipped++;
				continue;
			}

			$service = $svc_repo->get_by_id( intval( $appt['service_id'] ?? 0 ) );
			$service_name = $service && ! empty( $service['name'] ) ? $service['name'] : __( 'Service', 'ltl-bookings' );

			$amount_cents = isset( $appt['amount_cents'] ) ? intval( $appt['amount_cents'] ) : 0;
			$currency = ! empty( $appt['currency'] ) ? sanitize_text_field( (string) $appt['currency'] ) : 'EUR';
			$amount_label = $amount_cents > 0 ? number_format( $amount_cents / 100, 2 ) . ' ' . $currency : '';

			$placeholders = [
				'{first_name}' => sanitize_text_field( (string) ( $customer['first_name'] ?? '' ) ),
				'{service_name}' => (string) $service_name,
				'{start_time}' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) ( $appt['start_at'] ?? $from ) ) ),
				'{amount}' => $amount_label,
			];

			$subject_tpl = is_array( $template ) && isset( $template['subject'] ) ? (string) $template['subject'] : __( 'Payment reminder: {service_name}', 'ltl-bookings' );
			$body_tpl = is_array( $template ) && isset( $template['body'] ) ? (string) $template['body'] : __( "Hello {first_name},\n\nThis is a reminder that your booking is awaiting payment.\n\nService: {service_name}\nDate: {start_time}\nAmount: {amount}\n\nThank you!", 'ltl-bookings' );

			$subject = strtr( $subject_tpl, $placeholders );
			$body = strtr( $body_tpl, $placeholders );

			$res = self::send_email_via_outbox(
				$mode,
				(string) $customer['email'],
				$subject,
				nl2br( esc_html( $body ) ),
				[ 'appointment_id' => $appt_id, 'rule_type' => 'payment_reminder' ]
			);

			if ( ! empty( $res['success'] ) ) {
				$sent++;
				set_transient( $dedupe_key, 1, DAY_IN_SECONDS );
			} else {
				$skipped++;
			}
		}

		return [ 'success' => true, 'message' => __( 'Reminders processed.', 'ltl-bookings' ), 'sent' => $sent, 'skipped' => $skipped ];
	}

	private static function run_invoices( array $rule ): array {
		$mode = self::effective_mode( $rule );
		$days_before = isset( $rule['days_before'] ) ? max( 0, intval( $rule['days_before'] ) ) : 0;
		$limit = isset( $rule['limit'] ) ? max( 1, min( 200, intval( $rule['limit'] ) ) ) : 50;
		$template_id = isset( $rule['template_id'] ) ? sanitize_text_field( (string) $rule['template_id'] ) : 'invoice_default';

		$templates = get_option( 'lazy_reply_templates', [] );
		if ( ! is_array( $templates ) ) $templates = [];
		$template = null;
		foreach ( $templates as $tpl ) {
			if ( (string) ( $tpl['id'] ?? '' ) === $template_id ) {
				$template = $tpl;
				break;
			}
		}

		$repo = new LTLB_AppointmentRepository();
		$to = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( $days_before * DAY_IN_SECONDS ) );
		$candidates = $repo->get_all( [ 'from' => date( 'Y-m-d 00:00:00' ), 'to' => $to, 'limit' => $limit, 'offset' => 0 ] );
		if ( empty( $candidates ) ) {
			return [ 'success' => true, 'message' => __( 'No candidates.', 'ltl-bookings' ), 'sent' => 0 ];
		}

		$cust_repo = new LTLB_CustomerRepository();
		$svc_repo = new LTLB_ServiceRepository();
		$sent = 0;
		$skipped = 0;
		$today_key = date( 'Y-m-d', current_time( 'timestamp' ) );

		foreach ( $candidates as $appt ) {
			if ( $sent >= $limit ) break;
			$payment_status = isset( $appt['payment_status'] ) ? sanitize_key( (string) $appt['payment_status'] ) : 'free';
			$amount_cents = isset( $appt['amount_cents'] ) ? intval( $appt['amount_cents'] ) : 0;
			if ( $payment_status !== 'unpaid' || $amount_cents <= 0 ) {
				$skipped++;
				continue;
			}
			$appt_id = intval( $appt['id'] ?? 0 );
			if ( $appt_id <= 0 ) {
				$skipped++;
				continue;
			}
			$dedupe_key = 'ltlb_invoice_' . $appt_id . '_' . $today_key;
			if ( get_transient( $dedupe_key ) ) {
				$skipped++;
				continue;
			}
			$customer = $cust_repo->get_by_id( intval( $appt['customer_id'] ?? 0 ) );
			if ( ! $customer || empty( $customer['email'] ) || ! is_email( $customer['email'] ) ) {
				$skipped++;
				continue;
			}
			$service = $svc_repo->get_by_id( intval( $appt['service_id'] ?? 0 ) );
			$service_name = $service && ! empty( $service['name'] ) ? $service['name'] : __( 'Service', 'ltl-bookings' );
			$currency = ! empty( $appt['currency'] ) ? sanitize_text_field( (string) $appt['currency'] ) : 'EUR';
			$amount_label = number_format( $amount_cents / 100, 2 ) . ' ' . $currency;

			$placeholders = [
				'{first_name}' => sanitize_text_field( (string) ( $customer['first_name'] ?? '' ) ),
				'{service_name}' => (string) $service_name,
				'{start_time}' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) ( $appt['start_at'] ?? '' ) ) ),
				'{amount}' => $amount_label,
				'{booking_id}' => (string) $appt_id,
			];

			$subject_tpl = is_array( $template ) && isset( $template['subject'] ) ? (string) $template['subject'] : __( 'Invoice for booking #{booking_id}', 'ltl-bookings' );
			$body_tpl = is_array( $template ) && isset( $template['body'] ) ? (string) $template['body'] : __( "Hello {first_name},\n\nHere is your invoice for booking #{booking_id}.\n\nService: {service_name}\nDate: {start_time}\nAmount: {amount}\n\nThank you!", 'ltl-bookings' );

			$subject = strtr( $subject_tpl, $placeholders );
			$body = strtr( $body_tpl, $placeholders );

			$res = self::send_email_via_outbox(
				$mode,
				(string) $customer['email'],
				$subject,
				nl2br( esc_html( $body ) ),
				[ 'appointment_id' => $appt_id, 'rule_type' => 'invoice_send' ]
			);
			if ( ! empty( $res['success'] ) ) {
				$sent++;
				set_transient( $dedupe_key, 1, DAY_IN_SECONDS );
			} else {
				$skipped++;
			}
		}

		return [ 'success' => true, 'message' => __( 'Invoices processed.', 'ltl-bookings' ), 'sent' => $sent, 'skipped' => $skipped ];
	}

	private static function run_overdue_reminders( array $rule ): array {
		$mode = self::effective_mode( $rule );
		$limit = isset( $rule['limit'] ) ? max( 1, min( 200, intval( $rule['limit'] ) ) ) : 50;
		$template_id = isset( $rule['template_id'] ) ? sanitize_text_field( (string) $rule['template_id'] ) : 'overdue_reminder_default';

		$templates = get_option( 'lazy_reply_templates', [] );
		if ( ! is_array( $templates ) ) $templates = [];
		$template = null;
		foreach ( $templates as $tpl ) {
			if ( (string) ( $tpl['id'] ?? '' ) === $template_id ) {
				$template = $tpl;
				break;
			}
		}

		$repo = new LTLB_AppointmentRepository();
		$now_ts = current_time( 'timestamp' );
		$from = date( 'Y-m-d 00:00:00', strtotime( '-365 days', $now_ts ) );
		$to = date( 'Y-m-d H:i:s', $now_ts );

		$candidates = $repo->get_all( [ 'from' => $from, 'to' => $to, 'limit' => $limit, 'offset' => 0 ] );
		if ( empty( $candidates ) ) {
			return [ 'success' => true, 'message' => __( 'No candidates.', 'ltl-bookings' ), 'sent' => 0 ];
		}

		$cust_repo = new LTLB_CustomerRepository();
		$svc_repo = new LTLB_ServiceRepository();
		$sent = 0;
		$skipped = 0;
		$today_key = date( 'Y-m-d', $now_ts );

		foreach ( $candidates as $appt ) {
			if ( $sent >= $limit ) break;
			$payment_status = isset( $appt['payment_status'] ) ? sanitize_key( (string) $appt['payment_status'] ) : 'free';
			$amount_cents = isset( $appt['amount_cents'] ) ? intval( $appt['amount_cents'] ) : 0;
			if ( $payment_status !== 'unpaid' || $amount_cents <= 0 ) {
				$skipped++;
				continue;
			}
			$appt_id = intval( $appt['id'] ?? 0 );
			if ( $appt_id <= 0 ) {
				$skipped++;
				continue;
			}
			$dedupe_key = 'ltlb_overdue_' . $appt_id . '_' . $today_key;
			if ( get_transient( $dedupe_key ) ) {
				$skipped++;
				continue;
			}
			$customer = $cust_repo->get_by_id( intval( $appt['customer_id'] ?? 0 ) );
			if ( ! $customer || empty( $customer['email'] ) || ! is_email( $customer['email'] ) ) {
				$skipped++;
				continue;
			}
			$service = $svc_repo->get_by_id( intval( $appt['service_id'] ?? 0 ) );
			$service_name = $service && ! empty( $service['name'] ) ? $service['name'] : __( 'Service', 'ltl-bookings' );
			$currency = ! empty( $appt['currency'] ) ? sanitize_text_field( (string) $appt['currency'] ) : 'EUR';
			$amount_label = number_format( $amount_cents / 100, 2 ) . ' ' . $currency;

			$placeholders = [
				'{first_name}' => sanitize_text_field( (string) ( $customer['first_name'] ?? '' ) ),
				'{service_name}' => (string) $service_name,
				'{start_time}' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) ( $appt['start_at'] ?? '' ) ) ),
				'{amount}' => $amount_label,
				'{booking_id}' => (string) $appt_id,
			];

			$subject_tpl = is_array( $template ) && isset( $template['subject'] ) ? (string) $template['subject'] : __( 'Overdue payment for booking #{booking_id}', 'ltl-bookings' );
			$body_tpl = is_array( $template ) && isset( $template['body'] ) ? (string) $template['body'] : __( "Hello {first_name},\n\nOur records show an overdue payment for booking #{booking_id}.\n\nService: {service_name}\nDate: {start_time}\nAmount: {amount}\n\nIf you have already paid, please ignore this message. Thank you!", 'ltl-bookings' );

			$subject = strtr( $subject_tpl, $placeholders );
			$body = strtr( $body_tpl, $placeholders );

			$res = self::send_email_via_outbox(
				$mode,
				(string) $customer['email'],
				$subject,
				nl2br( esc_html( $body ) ),
				[ 'appointment_id' => $appt_id, 'rule_type' => 'overdue_reminder' ]
			);
			if ( ! empty( $res['success'] ) ) {
				$sent++;
				set_transient( $dedupe_key, 1, DAY_IN_SECONDS );
			} else {
				$skipped++;
			}
		}

		return [ 'success' => true, 'message' => __( 'Overdue reminders processed.', 'ltl-bookings' ), 'sent' => $sent, 'skipped' => $skipped ];
	}

	private static function send_email_via_outbox( string $mode, string $to, string $subject, string $body, array $metadata = [] ): array {
		if ( ! class_exists( 'LTLB_AI_Outbox' ) ) {
			return [ 'success' => false, 'message' => __( 'Outbox not available.', 'ltl-bookings' ) ];
		}
		$metadata = array_merge( $metadata, [ 'to' => $to, 'subject' => $subject, 'body' => $body ] );
		$id = LTLB_AI_Outbox::create_draft( 'email', '', '', $metadata, '' );
		if ( ! $id ) {
			return [ 'success' => false, 'message' => __( 'Could not create outbox draft.', 'ltl-bookings' ) ];
		}
		if ( $mode === 'hitl' ) {
			return [ 'success' => true, 'message' => __( 'Draft created in Outbox.', 'ltl-bookings' ), 'id' => $id ];
		}
		return LTLB_AI_Outbox::approve_and_execute( $id );
	}

	private static function run_insights_report( array $rule ): array {
		return self::generate_ai_insights_now();
	}

	private static function build_insights_bundle_report(): string {
		if ( ! class_exists( 'LTLB_Analytics' ) ) {
			return '';
		}

		$settings = get_option( 'lazy_settings', [] );
		if ( ! is_array( $settings ) ) $settings = [];
		$template_mode = isset( $settings['template_mode'] ) ? (string) $settings['template_mode'] : 'service';
		$template_mode = $template_mode === 'hotel' ? 'hotel' : 'service';
		$profit_margin = isset( $settings['profit_margin_percent'] ) ? max( 0, min( 100, intval( $settings['profit_margin_percent'] ) ) ) : 100;

		$analytics = LTLB_Analytics::instance();

		$today = date( 'Y-m-d' );
		$today_counts = $analytics->get_count_by_status( $today, $today );
		$today_revenue = $analytics->get_revenue( $today, $today );
		$today_profit = floatval( $today_revenue['total'] ?? 0 ) * ( $profit_margin / 100 );

		$start_30 = date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_30 = date( 'Y-m-d' );
		$range_counts = $analytics->get_count_by_status( $start_30, $end_30 );
		$range_top = $analytics->get_top_services( 5, $start_30, $end_30 );
		$range_revenue = $analytics->get_revenue( $start_30, $end_30 );
		$range_profit = floatval( $range_revenue['total'] ?? 0 ) * ( $profit_margin / 100 );

		$lines = [];
		$lines[] = 'Daily Report (' . $today . '):';
		$lines[] = 'Confirmed: ' . intval( $today_counts['confirmed'] ?? 0 );
		$lines[] = 'Pending: ' . intval( $today_counts['pending'] ?? 0 );
		if ( $template_mode === 'hotel' && class_exists( 'LTLB_Finance' ) ) {
			$fin_today = LTLB_Finance::hotel_financials_cents( $today, $today );
			$lines[] = 'Revenue (gross): ' . LTLB_Finance::format_money_from_cents( intval( $fin_today['revenue_cents'] ?? 0 ) );
			$lines[] = 'Fees: ' . LTLB_Finance::format_money_from_cents( intval( $fin_today['fees_cents'] ?? 0 ) );
			$lines[] = 'Room costs: ' . LTLB_Finance::format_money_from_cents( intval( $fin_today['room_costs_cents'] ?? 0 ) );
			$lines[] = 'Gross profit: ' . LTLB_Finance::format_money_from_cents( intval( $fin_today['gross_profit_cents'] ?? 0 ) );
		} else {
			$lines[] = 'Revenue (gross): ' . number_format( floatval( $today_revenue['total'] ?? 0 ), 2 );
			$lines[] = 'Estimated profit (@' . $profit_margin . '%): ' . number_format( $today_profit, 2 );
		}

		$lines[] = '';
		$lines[] = 'Overall Report (Last 30 days):';
		$lines[] = 'Confirmed: ' . intval( $range_counts['confirmed'] ?? 0 );
		$lines[] = 'Pending: ' . intval( $range_counts['pending'] ?? 0 );
		$lines[] = 'Cancelled: ' . intval( $range_counts['cancelled'] ?? 0 );
		if ( $template_mode === 'hotel' && class_exists( 'LTLB_Finance' ) ) {
			$fin_range = LTLB_Finance::hotel_financials_cents( $start_30, $end_30 );
			$lines[] = 'Revenue (gross): ' . LTLB_Finance::format_money_from_cents( intval( $fin_range['revenue_cents'] ?? 0 ) );
			$lines[] = 'Fees: ' . LTLB_Finance::format_money_from_cents( intval( $fin_range['fees_cents'] ?? 0 ) );
			$lines[] = 'Room costs: ' . LTLB_Finance::format_money_from_cents( intval( $fin_range['room_costs_cents'] ?? 0 ) );
			$lines[] = 'Gross profit: ' . LTLB_Finance::format_money_from_cents( intval( $fin_range['gross_profit_cents'] ?? 0 ) );
		} else {
			$lines[] = 'Revenue (gross): ' . number_format( floatval( $range_revenue['total'] ?? 0 ), 2 );
			$lines[] = 'Estimated profit (@' . $profit_margin . '%): ' . number_format( $range_profit, 2 );
		}

		if ( ! empty( $range_top ) ) {
			$lines[] = '';
			$lines[] = 'Top services:';
			foreach ( $range_top as $svc ) {
				$lines[] = '- ' . (string) ( $svc['name'] ?? '' ) . ': ' . intval( $svc['count'] ?? 0 );
			}
		}

		return implode( "\n", $lines );
	}
}
