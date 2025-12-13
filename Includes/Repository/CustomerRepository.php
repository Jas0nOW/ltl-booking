<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_CustomerRepository {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'lazy_customers';
	}

	public function get_all(): array {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$this->table_name} ORDER BY id DESC", ARRAY_A );
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
}

