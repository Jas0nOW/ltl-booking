<?php
if ( ! defined('ABSPATH') ) exit;

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

		$formats = ['%d','%d','%d','%s','%s','%s','%s','%s','%s'];
		$res = $wpdb->insert( $this->table_name, $insert, $formats );
		if ( $res === false ) return false;
		return (int) $wpdb->insert_id;
	}

	public function update_status(int $id, string $status): bool {
		global $wpdb;
		$res = $wpdb->update( $this->table_name, [ 'status' => sanitize_text_field($status), 'updated_at' => current_time('mysql') ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );
		return $res !== false;
	}

	/**
	 * Simple overlap/conflict check for a given service.
	 * Returns true if there is a conflict.
	 */
	public function has_conflict(string $start_at, string $end_at, int $service_id): bool {
		global $wpdb;
		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE service_id = %d AND status != %s AND start_at < %s AND end_at > %s";
		$count = $wpdb->get_var( $wpdb->prepare( $sql, $service_id, 'canceled', $end_at, $start_at ) );
		return intval( $count ) > 0;
	}
}

