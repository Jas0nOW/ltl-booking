<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_AppointmentRepository {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'lazy_appointments';
	}

	/**
	 * Get all appointments with optional filters: from, to, status, service_id, customer_search
	 * Returns ARRAY_A
	 *
	 * @param array $filters
	 * @return array
	 */
	public function get_all(array $filters = []): array {
		global $wpdb;

		$customers_table = $wpdb->prefix . 'lazy_customers';

		$where = [];
		$params = [];
		$join = '';

		// Customer search (email or name)
		if ( ! empty( $filters['customer_search'] ) ) {
			$join = " LEFT JOIN {$customers_table} c ON {$this->table_name}.customer_id = c.id";
			$search = '%' . $wpdb->esc_like( $filters['customer_search'] ) . '%';
			$where[] = "(c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s)";
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
		}

		if ( ! empty( $filters['from'] ) ) {
			$where[] = "{$this->table_name}.start_at >= %s";
			$params[] = $filters['from'];
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[] = "{$this->table_name}.end_at <= %s";
			$params[] = $filters['to'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[] = "{$this->table_name}.status = %s";
			$params[] = $filters['status'];
		}
		if ( ! empty( $filters['service_id'] ) ) {
			$where[] = "{$this->table_name}.service_id = %d";
			$params[] = intval( $filters['service_id'] );
		}

		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = ' AND ' . implode(' AND ', $where);
		}

		$limit_sql = '';
		if (isset($filters['limit']) && isset($filters['offset'])) {
			$limit_sql = 'LIMIT %d OFFSET %d';
			$params[] = intval($filters['limit']);
			$params[] = intval($filters['offset']);
		}

		$sql = "SELECT {$this->table_name}.* FROM {$this->table_name} {$join} WHERE 1=1 {$where_sql} ORDER BY {$this->table_name}.start_at DESC {$limit_sql}";

		if ( empty( $params ) ) {
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		}

		return $rows ?: [];
	}

	public function get_count_by_status( string $status ): int {
		global $wpdb;
		$status = sanitize_key( $status );
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s", $status ) );
		return (int) $count;
	}

	public function get_count_for_today(): int {
		global $wpdb;
		$today_start = date( 'Y-m-d 00:00:00' );
		$today_end = date( 'Y-m-d 23:59:59' );
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} WHERE start_at >= %s AND start_at <= %s AND status != 'cancelled'", $today_start, $today_end ) );
		return (int) $count;
	}

	public function get_count_check_ins_today(): int {
		global $wpdb;
		$today = date('Y-m-d');
		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(start_at) = %s AND status = 'confirmed'";
		$count = $wpdb->get_var($wpdb->prepare($sql, $today));
		return (int) $count;
	}

	public function get_count_check_outs_today(): int {
		global $wpdb;
		$today = date('Y-m-d');
		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(end_at) = %s AND status = 'confirmed'";
		$count = $wpdb->get_var($wpdb->prepare($sql, $today));
		return (int) $count;
	}

	public function get_count_occupied_rooms_today(): int {
		global $wpdb;
		$today = date('Y-m-d');
		$sql = "SELECT COUNT(DISTINCT resource_id) 
                FROM {$wpdb->prefix}lazy_appointment_resources ar
                JOIN {$this->table_name} a ON ar.appointment_id = a.id
                WHERE %s >= DATE(a.start_at) AND %s < DATE(a.end_at) AND a.status = 'confirmed'";
		$count = $wpdb->get_var($wpdb->prepare($sql, $today, $today));
		return (int) $count;
	}

	public function get_count_occupied_rooms_on_date( string $date_yyyy_mm_dd, bool $include_pending = false ): int {
		global $wpdb;
		$date_yyyy_mm_dd = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_yyyy_mm_dd ) ? $date_yyyy_mm_dd : date( 'Y-m-d' );
		$status_sql = $include_pending ? "AND a.status IN ('confirmed','pending')" : "AND a.status = 'confirmed'";
		$sql = "SELECT COUNT(DISTINCT resource_id)
			FROM {$wpdb->prefix}lazy_appointment_resources ar
			JOIN {$this->table_name} a ON ar.appointment_id = a.id
			WHERE %s >= DATE(a.start_at)
			AND %s < DATE(a.end_at)
			{$status_sql}";
		$count = $wpdb->get_var( $wpdb->prepare( $sql, $date_yyyy_mm_dd, $date_yyyy_mm_dd ) );
		return (int) $count;
	}

	/**
	 * Count requested rooms for bookings that overlap the given date but have no room assigned.
	 * Uses appointment.seats as the requested room count in hotel mode.
	 */
	public function get_count_unassigned_room_bookings_on_date( string $date_yyyy_mm_dd, bool $include_pending = false ): int {
		global $wpdb;
		$date_yyyy_mm_dd = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_yyyy_mm_dd ) ? $date_yyyy_mm_dd : date( 'Y-m-d' );
		$status_sql = $include_pending ? "a.status IN ('confirmed','pending')" : "a.status = 'confirmed'";
		$ar_table = $wpdb->prefix . 'lazy_appointment_resources';
		$sql = "SELECT COALESCE(SUM(a.seats), 0)
			FROM {$this->table_name} a
			LEFT JOIN {$ar_table} ar ON ar.appointment_id = a.id
			WHERE %s >= DATE(a.start_at)
			AND %s < DATE(a.end_at)
			AND {$status_sql}
			AND ar.appointment_id IS NULL";
		$count = $wpdb->get_var( $wpdb->prepare( $sql, $date_yyyy_mm_dd, $date_yyyy_mm_dd ) );
		return (int) $count;
	}

	public function get_count(array $filters = []): int {
		global $wpdb;
		// This logic needs to mirror get_all() to count correctly with filters.
		$where = [];
		$params = [];
		$join = '';
		$customers_table = $wpdb->prefix . 'lazy_customers';

		if ( ! empty( $filters['customer_search'] ) ) {
			$join = " LEFT JOIN {$customers_table} c ON {$this->table_name}.customer_id = c.id";
			$search = '%' . $wpdb->esc_like( $filters['customer_search'] ) . '%';
			$where[] = "(c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s)";
			$params = array_merge($params, [$search, $search, $search]);
		}
		if ( ! empty( $filters['from'] ) ) {
			$where[] = "{$this->table_name}.start_at >= %s";
			$params[] = $filters['from'];
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[] = "{$this->table_name}.end_at <= %s";
			$params[] = $filters['to'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[] = "{$this->table_name}.status = %s";
			$params[] = $filters['status'];
		}
		if ( ! empty( $filters['service_id'] ) ) {
			$where[] = "{$this->table_name}.service_id = %d";
			$params[] = intval( $filters['service_id'] );
		}

		$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
		
		$sql = "SELECT COUNT({$this->table_name}.id) FROM {$this->table_name} {$join} {$where_sql}";
		
		if ( empty( $params ) ) {
			$count = $wpdb->get_var( $sql );
		} else {
			$count = $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
		}
		return (int) $count;
	}

	/**
	 * Fetch appointments for calendar view including service + customer names.
	 *
	 * @param string $from_mysql 'Y-m-d H:i:s'
	 * @param string $to_mysql   'Y-m-d H:i:s'
	 * @return array
	 */
	public function get_calendar_rows( string $from_mysql, string $to_mysql ): array {
		global $wpdb;
		$customers_table = $wpdb->prefix . 'lazy_customers';
		$services_table = $wpdb->prefix . 'lazy_services';

		$sql = "
			SELECT a.*, 
				COALESCE(s.name, '') AS service_name,
				COALESCE(c.first_name, '') AS customer_first_name,
				COALESCE(c.last_name, '') AS customer_last_name,
				COALESCE(c.email, '') AS customer_email
			FROM {$this->table_name} a
			LEFT JOIN {$services_table} s ON a.service_id = s.id
			LEFT JOIN {$customers_table} c ON a.customer_id = c.id
			WHERE a.start_at < %s AND a.end_at > %s
			ORDER BY a.start_at ASC
		";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $to_mysql, $from_mysql ), ARRAY_A );
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
		$service_id = isset($data['service_id']) ? intval($data['service_id']) : 0;
		$customer_id = isset($data['customer_id']) ? intval($data['customer_id']) : 0;
		$staff_user_id = ( isset( $data['staff_user_id'] ) && $data['staff_user_id'] !== null && $data['staff_user_id'] !== '' ) ? intval( $data['staff_user_id'] ) : null;

		$skip_conflict_check = ! empty( $data['skip_conflict_check'] );

		$service_repo = new LTLB_ServiceRepository();
		$service = $service_id ? $service_repo->get_by_id( $service_id ) : null;
		$seats = isset($data['seats']) ? max( 1, intval($data['seats']) ) : 1;

		// Allow deterministic overrides (e.g., hotel: price_per_night * nights).
		$amount_cents = null;
		if ( array_key_exists( 'amount_cents', $data ) ) {
			$amount_cents = max( 0, intval( $data['amount_cents'] ) );
		}
		if ( $amount_cents === null ) {
			$unit = $service && isset( $service['price_cents'] ) ? intval( $service['price_cents'] ) : 0;
			$amount_cents = max( 0, $unit * $seats );
		}

		$currency = 'EUR';
		if ( array_key_exists( 'currency', $data ) && is_string( $data['currency'] ) && $data['currency'] !== '' ) {
			$currency = sanitize_text_field( (string) $data['currency'] );
		} elseif ( $service && ! empty( $service['currency'] ) ) {
			$currency = sanitize_text_field( (string) $service['currency'] );
		}
		$payment_status = $amount_cents > 0 ? 'unpaid' : 'free';
		$payment_method = 'none';
		if ( array_key_exists( 'payment_method', $data ) && is_string( $data['payment_method'] ) && $data['payment_method'] !== '' ) {
			$payment_method = sanitize_key( (string) $data['payment_method'] );
		} else {
			$payment_method = $payment_status === 'free' ? 'free' : 'unpaid';
		}

		$insert = [
			'service_id' => $service_id,
			'customer_id' => $customer_id,
			'staff_user_id' => $staff_user_id,
			'start_at' => '',
			'end_at' => '',
			'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'pending',
			'amount_cents' => $amount_cents,
			'currency' => $currency,
			'payment_status' => $payment_status,
			'payment_method' => $payment_method,
			'timezone' => isset($data['timezone']) ? sanitize_text_field($data['timezone']) : LTLB_Time::get_site_timezone_string(),
			'seats' => $seats,
			'created_at' => $now,
			'updated_at' => $now,
		];
		if ( $staff_user_id === null || $staff_user_id <= 0 ) {
			unset( $insert['staff_user_id'] );
		}
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
		$ls = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls ) ) $ls = [];
		if ( ! empty( $ls['pending_blocks'] ) ) {
			$blocking_statuses[] = 'pending';
		}

		$lock_key = 'appointment_' . $service_id . '_' . ( $staff_user_id ? $staff_user_id : 'none' ) . '_' . $insert['start_at'] . '_' . $insert['end_at'];
		$result = LTLB_LockManager::with_lock( $lock_key, function() use ( $wpdb, $insert, $blocking_statuses, $staff_user_id, $skip_conflict_check ) {
			// final conflict check immediately before insert
			if ( ! $skip_conflict_check ) {
				if ( $this->has_conflict( $insert['start_at'], $insert['end_at'], intval( $insert['service_id'] ), $staff_user_id, $blocking_statuses ) ) {
					return new WP_Error( 'conflict', __( 'This time slot is no longer available.', 'ltl-bookings' ) );
				}
			}

			$formats = [ '%d', '%d' ];
			if ( array_key_exists( 'staff_user_id', $insert ) ) {
				$formats[] = '%d';
			}
			$formats = array_merge( $formats, [ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ] );

			$res = $wpdb->insert( $this->table_name, $insert, $formats );
			if ( $res === false ) {
				return new WP_Error( 'db_error', __( 'Could not save the appointment to the database.', 'ltl-bookings' ) );
			}
			return (int) $wpdb->insert_id;
		} );

		if ( $result === false ) {
			return new WP_Error( 'lock_timeout', __( 'Another booking is in progress. Please try again.', 'ltl-bookings' ) );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$appointment_id = (int) $result;

		// Send email notifications
		if ( ! empty( $data['skip_mailer'] ) ) {
			return $appointment_id;
		}
		list( $service, $customer ) = $this->_get_service_and_customer( $insert['service_id'], $insert['customer_id'] );
		$should_send_notifications = true;
		$payment_engine = class_exists( 'LTLB_PaymentEngine' ) ? LTLB_PaymentEngine::instance() : null;
		if ( $payment_engine && method_exists( $payment_engine, 'is_enabled' ) && $payment_engine->is_enabled() ) {
			$service_price_cents = is_array( $service ) ? intval( $service['price_cents'] ?? 0 ) : 0;
			if ( $service_price_cents > 0 ) {
				$should_send_notifications = false;
			}
		}
		if ( $should_send_notifications && $service && $customer ) {
			LTLB_Mailer::send_booking_notifications( $appointment_id, $service, $customer, $insert['start_at'], $insert['end_at'], $insert['status'] );
		}

		return $appointment_id;
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

	public function update_status_bulk(array $ids, string $status): bool {
		global $wpdb;
		if (empty($ids)) {
			return false;
		}
		$ids_placeholder = implode(', ', array_fill(0, count($ids), '%d'));
		$sql = $wpdb->prepare(
			"UPDATE {$this->table_name} SET status = %s, updated_at = %s WHERE id IN ($ids_placeholder)",
			array_merge([sanitize_text_field($status), current_time('mysql')], $ids)
		);
		$result = $wpdb->query($sql);
		return $result !== false;
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
	public function has_conflict(string $start_at, string $end_at, int $service_id, ?int $staff_user_id, array $blocking_statuses = ['confirmed'], ?int $exclude_appointment_id = null): bool {
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
		if ( $exclude_appointment_id && $exclude_appointment_id > 0 ) {
			$sql .= " AND id != %d";
			$params[] = $exclude_appointment_id;
		}

		$count = $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
		return intval( $count ) > 0;
	}

	/**
	 * Get count for date range (for week-over-week comparisons)
	 *
	 * @param string $from Start date (Y-m-d H:i:s)
	 * @param string $to End date (Y-m-d H:i:s)
	 * @return int
	 */
	public function get_count_by_date_range( string $from, string $to ): int {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE start_at >= %s AND start_at < %s",
			$from,
			$to
		) );
		return (int) $count;
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

	/**
	 * Get a single appointment by ID
	 *
	 * @param int $id
	 * @return array|null
	 */
	public function get_by_id( int $id ): ?array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		);

		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		if ( isset( $row['amount_cents'] ) ) {
			$row['price'] = floatval( intval( $row['amount_cents'] ) ) / 100;
		}
		return $row;
	}

	/**
	 * Update appointment start and end times (for drag/drop on calendar)
	 *
	 * @param int $id
	 * @param string $start_at (format: Y-m-d H:i:s)
	 * @param string $end_at (format: Y-m-d H:i:s)
	 * @return bool
	 */
	public function update_times( int $id, string $start_at, string $end_at ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table_name,
			[
				'start_at' => $start_at,
				'end_at' => $end_at,
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return $result !== false;
	}
}

