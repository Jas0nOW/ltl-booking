<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_BookingService {
	public static function create_hotel_booking_from_submission( array $data ): int|WP_Error {
		// Block booking in the past (server-side)
		$ls = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls ) ) $ls = [];
		$checkin_time = $ls['hotel_checkin_time'] ?? '15:00';
		$checkin_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::combine_date_time( (string) $data['checkin'], (string) $checkin_time ) : null;
		if ( $checkin_dt && class_exists( 'LTLB_Time' ) ) {
			$now = new DateTimeImmutable( 'now', LTLB_Time::wp_timezone() );
			if ( $checkin_dt < $now ) {
				return new WP_Error( 'past_date', __( 'Das gewählte Anreise-Datum liegt in der Vergangenheit.', 'ltl-bookings' ) );
			}
		}

		$lock_key = LTLB_LockManager::build_hotel_lock_key(
			intval( $data['service_id'] ),
			(string) $data['checkin'],
			(string) $data['checkout'],
			! empty( $data['resource_id'] ) ? intval( $data['resource_id'] ) : null
		);

		$result = LTLB_LockManager::with_lock( $lock_key, function() use ( $data ) {
			$engine = new HotelEngine();
			return $engine->create_hotel_booking( $data );
		} );

		if ( $result === false ) {
			LTLB_Logger::warn( 'Hotel booking lock timeout', [
				'service_id' => intval( $data['service_id'] ),
				'checkin' => (string) $data['checkin'],
				'checkout' => (string) $data['checkout'],
			] );
			return new WP_Error( 'lock_timeout', __( 'Eine andere Buchung wird gerade verarbeitet. Bitte versuche es erneut.', 'ltl-bookings' ) );
		}
		if ( is_wp_error( $result ) ) {
			LTLB_Logger::error( 'Hotel booking creation failed: ' . $result->get_error_message(), [
				'service_id' => intval( $data['service_id'] ),
				'email' => (string) $data['email' ],
			] );
			return $result;
		}

		$appt_id = intval( $result );
		LTLB_Logger::info( 'Hotel booking created successfully', [
			'appointment_id' => $appt_id,
			'service_id' => intval( $data['service_id'] ),
			'email' => (string) $data['email' ],
		] );
		return $appt_id;
	}

	public static function create_service_booking_from_submission( array $data ): int|WP_Error {
		$service_repo = new LTLB_ServiceRepository();
		$service = $service_repo->get_by_id( $data['service_id'] );
		$duration = $service && isset( $service['duration_min'] ) ? intval( $service['duration_min'] ) : 60;

		$start_dt = LTLB_Time::parse_date_and_time( $data['date'], $data['time'] );
		if ( ! $start_dt ) {
			return new WP_Error( 'invalid_date', __( 'Ungültiges Datum/Uhrzeit.', 'ltl-bookings' ) );
		}

		$now = new DateTimeImmutable( 'now', LTLB_Time::wp_timezone() );
		if ( $start_dt < $now ) {
			return new WP_Error( 'past_date', __( 'Die gewählte Zeit liegt in der Vergangenheit.', 'ltl-bookings' ) );
		}

		$end_dt = $start_dt->modify( '+' . intval( $duration ) . ' minutes' );
		$start_at_sql = LTLB_Time::format_wp_datetime( $start_dt );
		$end_at_sql = LTLB_Time::format_wp_datetime( $end_dt );

		$appointment_repo = new LTLB_AppointmentRepository();
		$customer_repo = new LTLB_CustomerRepository();

		$lock_key = LTLB_LockManager::build_service_lock_key( $data['service_id'], $start_at_sql, $data['resource_id'] ?: null );

		$result = LTLB_LockManager::with_lock( $lock_key, function() use ( $appointment_repo, $customer_repo, $data, $start_at_sql, $end_at_sql, $start_dt, $end_dt ) {
			if ( $appointment_repo->has_conflict( $start_at_sql, $end_at_sql, $data['service_id'], null ) ) {
				return new WP_Error( 'conflict', __( 'Der gewählte Zeitslot ist bereits belegt.', 'ltl-bookings' ) );
			}

			$customer_id = $customer_repo->upsert_by_email( [
				'email'      => $data['email'],
				'first_name' => $data['first'],
				'last_name'  => $data['last'],
				'phone'      => $data['phone'],
			] );

			if ( ! $customer_id ) {
				return new WP_Error( 'customer_error', __( 'Kunde konnte nicht gespeichert werden.', 'ltl-bookings' ) );
			}

			$ls = get_option( 'lazy_settings', [] );
			if ( ! is_array( $ls ) ) {
				$ls = [];
			}
			$default_status = $ls['default_status'] ?? 'pending';

			$appt_id = $appointment_repo->create( [
				'service_id'  => $data['service_id'],
				'customer_id' => $customer_id,
				'start_at'    => $start_dt,
				'end_at'      => $end_dt,
				'status'      => $default_status,
				'timezone'    => LTLB_Time::get_site_timezone_string(),
			] );

			if ( is_wp_error( $appt_id ) ) {
				return $appt_id;
			}

			return [
				'appointment_id' => intval( $appt_id ),
				'customer_id' => intval( $customer_id ),
				'default_status' => (string) $default_status,
			];
		} );

		if ( $result === false ) {
			LTLB_Logger::warn( 'Booking lock timeout', [ 'service_id' => $data['service_id'], 'start' => $start_at_sql ] );
			return new WP_Error( 'lock_timeout', __( 'Eine andere Buchung wird gerade verarbeitet. Bitte versuche es erneut.', 'ltl-bookings' ) );
		}

		if ( is_wp_error( $result ) ) {
			LTLB_Logger::error( 'Booking creation failed: ' . $result->get_error_message(), [ 'service_id' => $data['service_id'], 'email' => $data['email'] ] );
			return $result;
		}

		if ( ! is_array( $result ) || empty( $result['appointment_id'] ) ) {
			return new WP_Error( 'booking_failed', __( 'Buchung konnte nicht erstellt werden.', 'ltl-bookings' ) );
		}

		$appt_id = intval( $result['appointment_id'] );
		$customer_id = intval( $result['customer_id'] ?? 0 );
		$default_status = (string) ( $result['default_status'] ?? 'pending' );

		LTLB_Logger::info( 'Booking created successfully', [ 'appointment_id' => $appt_id, 'service_id' => $data['service_id'], 'email' => $data['email'] ] );

		$service_resources_repo = new LTLB_ServiceResourcesRepository();
		$resource_repo = new LTLB_ResourceRepository();
		$appt_resource_repo = new LTLB_AppointmentResourcesRepository();

		$allowed_resources = $service_resources_repo->get_resources_for_service( intval( $data['service_id'] ) );
		if ( empty( $allowed_resources ) ) {
			$all = $resource_repo->get_all();
			$allowed_resources = array_map(function($r){ return intval($r['id']); }, $all );
		}

		$ls = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls ) ) $ls = [];
		$include_pending = ! empty( $ls['pending_blocks'] );
		$blocked_counts = $appt_resource_repo->get_blocked_resources( $start_at_sql, $end_at_sql, $include_pending );

		$chosen = isset($data['resource_id']) ? intval($data['resource_id']) : 0;
		if ( $chosen > 0 && in_array($chosen, $allowed_resources, true) ) {
			$res = $resource_repo->get_by_id( $chosen );
			if ( $res ) {
				$cap = intval($res['capacity'] ?? 1);
				$used = isset($blocked_counts[$chosen]) ? intval($blocked_counts[$chosen]) : 0;
				if ( $used < $cap ) {
					$appt_resource_repo->set_resource_for_appointment( intval($appt_id), $chosen );
				}
			}
		} else {
			foreach ( $allowed_resources as $rid ) {
				$res = $resource_repo->get_by_id( intval($rid) );
				if ( ! $res ) continue;
				$capacity = intval( $res['capacity'] ?? 1 );
				$used = isset( $blocked_counts[ $rid ] ) ? intval( $blocked_counts[ $rid ] ) : 0;
				if ( $used < $capacity ) {
					$appt_resource_repo->set_resource_for_appointment( intval($appt_id), intval($rid) );
					break;
				}
			}
		}

		$service = $service_repo->get_by_id( $data['service_id'] );
		$customer = $customer_id > 0 ? ( new LTLB_CustomerRepository() )->get_by_id( $customer_id ) : null;

		if ( class_exists( 'LTLB_Mailer' ) ) {
			LTLB_Mailer::send_booking_notifications( $appt_id, $service ?: [], $customer ?: [], $start_at_sql, $end_at_sql, $default_status );
		}

		return $appt_id;
	}
}
