<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_ResourceRepository {
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'lazy_resources';
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

	public function create(array $data) {
		global $wpdb;
		$now = current_time('mysql');
		$insert = [
			'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
			'capacity' => isset($data['capacity']) ? intval($data['capacity']) : 1,
			'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
			'created_at' => $now,
			'updated_at' => $now,
		];
		$res = $wpdb->insert( $this->table_name, $insert, [ '%s', '%d', '%d', '%s', '%s' ] );
		if ( $res === false ) return false;
		return (int) $wpdb->insert_id;
	}
}

