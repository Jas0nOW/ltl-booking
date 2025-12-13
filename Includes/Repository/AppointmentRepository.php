<?php
if ( ! defined('ABSPATH') ) exit;

use WP_Error;

class LTLB_AppointmentRepository {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'lazy_appointments';
	}

	/**
	 * Get all appointments with optional filters: from, to, status, service_id
	 * Returns ARRAY_A
	 *
	 * @param array $filters
	 * @return array
	 */
	public function get_all(array $filters = []): array {
		global $wpdb;

		$where = [];
		$params = [];

		if ( ! empty( $filters['from'] ) ) {
			$where[] = "start_at >= %s";
			$params[] = $filters['from'];
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[] = "end_at <= %s";
			$params[] = $filters['to'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[] = "status = %s";
			$params[] = $filters['status'];
		}
		if ( ! empty( $filters['service_id'] ) ) {
			$where[] = "service_id = %d";
			$params[] = intval( $filters['service_id'] );
		}

		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = ' AND ' . implode(' AND ', $where);
		}

		$sql = "SELECT * FROM {$this->table_name} WHERE 1=1 {$where_sql} ORDER BY start_at DESC";

		if ( empty( $params ) ) {
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		}

		return $rows ?: [];
	}

	/**
	 * Creates a new appointment.
	 *
	 * @param array $data The appointment data.
	 * @return int|WP_Error The new appointment ID on success, or a WP_Error object on failure.
	 */
	public function create(array $data) {
		global $wpdb;

		$now = current_time('mysql');
		$insert = [
			'service_id' => isset($data['service_id']) ? intval($data['service_id']) : 0,
			'customer_id' => isset($data['customer_id']) ? intval($data['customer_id']) : 0,
			'staff_user_id' => isset($data['staff_user_id']) ? intval($data['staff_user_id']) : null,
			'start_at' => '',
			'end_at' => '',
			'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'pending',
			'timezone' => isset($data['timezone']) ? sanitize_text_field($data['timezone']) : LTLB_Time::get_site_timezone_string(),
			'created_at' => $now,
			'updated_at' => $now,
		];
		// start_at / end_at may be DateTimeInterface or strings
		if ( isset( $data['start_at'] ) ) {
			if ( $data['start_at'] instanceof DateTimeInterface ) {
				$insert['start_at'] = LTLB_Time::format_wp_datetime( $data['start_at'] );
			} else {
				$insert['start_at'] = sanitize_text_field( $data['start_at'] );
			}
		}
		if ( isset( $data['end_at'] ) ) {
			if ( $data['end_at'] instanceof DateTimeInterface ) {
				$insert['end_at'] = LTLB_Time::format_wp_datetime( $data['end_at'] );
			} else {
				$insert['end_at'] = sanitize_text_field( $data['end_at'] );
			}
		}
		// Determine which statuses should block a slot. By default only 'confirmed'.
		$blocking_statuses = [ 'confirmed' ];
		if ( get_option( 'ltlb_pending_blocks', false ) ) {
			$blocking_statuses[] = 'pending';
		}

		// Basic lock to reduce race conditions: attempt to add an option as a mutex.
		$lock_key = 'ltlb_lock_' . md5( $insert['service_id'] . '|' . $insert['start_at'] . '|' . $insert['end_at'] );
		$got_lock = add_option( $lock_key, 1, '', 'no' );
		if ( $got_lock === false ) {
			// someone else is inserting for this exact slot
			return new WP_Error( 'db_lock', __( 'Could not acquire a database lock for this appointment slot.', 'ltl-bookings' ) );
		}

		try {
			// final conflict check immediately before insert
			if ( $this->has_conflict( $insert['start_at'], $insert['end_at'], intval( $insert['service_id'] ), intval( $insert['staff_user_id'] ), $blocking_statuses ) ) {
				return new WP_Error( 'conflict', __( 'This time slot is no longer available.', 'ltl-bookings' ) );
			}

			$formats = ['%d','%d','%d','%s','%s','%s','%s','%s','%s'];
			$res = $wpdb->insert( $this->table_name, $insert, $formats );
			if ( $res === false ) {
				return new WP_Error( 'db_error', __( 'Could not save the appointment to the database.', 'ltl-bookings' ) );
			}
			$appointment_id = (int) $wpdb->insert_id;

			// Send email notifications
			list( $service, $customer ) = $this->_get_service_and_customer( $insert['service_id'], $insert['customer_id'] );
			if ( $service && $customer ) {
				LTLB_Mailer::send_booking_notifications( $appointment_id, $service, $customer, $insert['start_at'], $insert['end_at'], $insert['status'] );
			}

			return $appointment_id;
		} finally {
			// release lock
			delete_option( $lock_key );
		}
	}

	/**
	 * Updates the status of an appointment.
	 *
	 * @param int    $id     The appointment ID.
	 * @param string $status The new status.
	 * @return bool True on success, false on failure.
	 */
	public function update_status(int $id, string $status): bool {
		global $wpdb;
		$res = $wpdb->update( $this->table_name, [ 'status' => sanitize_text_field($status), 'updated_at' => current_time('mysql') ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );
		return $res !== false;
	}

	/**
	 * Simple overlap/conflict check for a given service and staff member.
	 * Returns true if there is a conflict.
	 *
	 * @param string $start_at
	 * @param string $end_at
	 * @param int $service_id
	 * @param int|null $staff_user_id
	 * @param array $blocking_statuses
	 * @return bool
	 */
	public function has_conflict(string $start_at, string $end_at, int $service_id, ?int $staff_user_id, array $blocking_statuses = ['confirmed']): bool {
		global $wpdb;

		if ( empty( $blocking_statuses ) ) {
			return false;
		}

		// build placeholders for IN()
		$placeholders = implode( ',', array_fill( 0, count( $blocking_statuses ), '%s' ) );

		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE status IN ($placeholders) AND start_at < %s AND end_at > %s";

		$params = array_merge( $blocking_statuses, [ $end_at, $start_at ] );

		// Add service_id and staff_user_id to the query if they are provided
		if ( $service_id > 0 ) {
			$sql .= " AND service_id = %d";
			$params[] = $service_id;
		}
		if ( $staff_user_id > 0 ) {
			$sql .= " AND staff_user_id = %d";
			$params[] = $staff_user_id;
		}

		$count = $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
		return intval( $count ) > 0;
	}

	/**
	 * Retrieves the service and customer for an appointment.
	 *
	 * @param int $service_id
	 * @param int $customer_id
	 * @return array An array containing the service and customer.
	 */
	private function _get_service_and_customer( int $service_id, int $customer_id ): array {
		$service_repo = new LTLB_ServiceRepository();
		$customer_repo = new LTLB_CustomerRepository();
		$service = $service_repo->get_by_id( $service_id );
		$customer = $customer_repo->get_by_id( $customer_id );

		return [ $service, $customer ];
	}
}

