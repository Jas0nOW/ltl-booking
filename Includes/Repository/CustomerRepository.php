<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_CustomerRepository {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'lazy_customers';
	}

	public function get_count(): int {
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
		return (int) $count;
	}

	/**
	 * Get all customers with optional limit/offset
	 *
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 */
	public function get_all( int $limit = 0, int $offset = 0 ): array {
		global $wpdb;
		$sql = "SELECT * FROM {$this->table_name} ORDER BY id DESC";
		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( " LIMIT %d", $limit );
			if ( $offset > 0 ) {
				$sql .= $wpdb->prepare( " OFFSET %d", $offset );
			}
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return $rows ?: [];
	}

	public function get_by_id(int $id): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	public function get_by_email(string $email): ?array {
		global $wpdb;
		$email_s = sanitize_email( $email );
		if ( empty( $email_s ) ) return null;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE email = %s", $email_s ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Insert or update customer by email. Returns ID on success.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public function upsert_by_email(array $data) {
		global $wpdb;

		$email = isset($data['email']) ? sanitize_email($data['email']) : '';
		if ( empty( $email ) ) return false;

		$existing = $this->get_by_email( $email );
		$now = current_time('mysql');

		$record = [
			'email' => $email,
			'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : null,
			'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : null,
			'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : null,
			'notes' => isset($data['notes']) ? wp_kses_post($data['notes']) : null,
			'updated_at' => $now,
		];

		if ( $existing ) {
			$res = $wpdb->update( $this->table_name, $record, [ 'id' => $existing['id'] ], ['%s','%s','%s','%s','%s'], ['%d'] );
			return $res === false ? false : (int) $existing['id'];
		}

		$record['created_at'] = $now;
		$formats = ['%s','%s','%s','%s','%s','%s'];
		$res = $wpdb->insert( $this->table_name, $record, $formats );
		if ( $res === false ) return false;
		return (int) $wpdb->insert_id;
	}

	public function update_by_id( int $id, array $data ): bool {
		global $wpdb;
		if ( $id <= 0 ) return false;

		$update = [];
		$formats = [];

		if ( isset( $data['email'] ) ) {
			$email = sanitize_email( (string) $data['email'] );
			if ( empty( $email ) ) return false;
			$update['email'] = $email;
			$formats[] = '%s';
		}
		if ( array_key_exists( 'first_name', $data ) ) {
			$update['first_name'] = $data['first_name'] !== null ? sanitize_text_field( (string) $data['first_name'] ) : null;
			$formats[] = '%s';
		}
		if ( array_key_exists( 'last_name', $data ) ) {
			$update['last_name'] = $data['last_name'] !== null ? sanitize_text_field( (string) $data['last_name'] ) : null;
			$formats[] = '%s';
		}
		if ( array_key_exists( 'phone', $data ) ) {
			$update['phone'] = $data['phone'] !== null ? sanitize_text_field( (string) $data['phone'] ) : null;
			$formats[] = '%s';
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$update['notes'] = $data['notes'] !== null ? wp_kses_post( (string) $data['notes'] ) : null;
			$formats[] = '%s';
		}

		if ( empty( $update ) ) return false;
		$update['updated_at'] = current_time('mysql');
		$formats[] = '%s';

		$res = $wpdb->update( $this->table_name, $update, [ 'id' => $id ], $formats, [ '%d' ] );
		return $res !== false;
	}

	/**
	 * Export customers as CSV array
	 *
	 * @return array
	 */
	public function get_all_for_export(): array {
		global $wpdb;
		$sql = "SELECT email, first_name, last_name, phone, notes, created_at FROM {$this->table_name} ORDER BY id DESC";
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return $rows ?: [];
	}
}

