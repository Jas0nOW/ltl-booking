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
			'description' => array_key_exists('description', $data) ? wp_kses_post($data['description']) : null,
			'capacity' => isset($data['capacity']) ? intval($data['capacity']) : 1,
			'cost_per_night_cents' => isset($data['cost_per_night_cents']) ? max(0, intval($data['cost_per_night_cents'])) : 0,
			'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
			'created_at' => $now,
			'updated_at' => $now,
		];
		$formats = [ '%s', '%s', '%d', '%d', '%d', '%s', '%s' ];
		$res = $wpdb->insert( $this->table_name, $insert, $formats );
		if ( $res === false ) return false;
		return (int) $wpdb->insert_id;
	}

	public function update(int $id, array $data): bool {
		global $wpdb;
		$id = intval($id);
		if ( $id <= 0 ) return false;

		$update = [
			'updated_at' => current_time('mysql'),
		];
		$formats = [
			'%s',
		];

		if ( array_key_exists('name', $data) ) {
			$update['name'] = sanitize_text_field((string) $data['name']);
			$formats[] = '%s';
		}
		if ( array_key_exists('description', $data) ) {
			$update['description'] = wp_kses_post((string) $data['description']);
			$formats[] = '%s';
		}
		if ( array_key_exists('capacity', $data) ) {
			$update['capacity'] = max(1, intval($data['capacity']));
			$formats[] = '%d';
		}
		if ( array_key_exists('cost_per_night_cents', $data) ) {
			$update['cost_per_night_cents'] = max(0, intval($data['cost_per_night_cents']));
			$formats[] = '%d';
		}
		if ( array_key_exists('is_active', $data) ) {
			$update['is_active'] = intval($data['is_active']) ? 1 : 0;
			$formats[] = '%d';
		}

		if ( count($update) <= 1 ) {
			return true;
		}

		$res = $wpdb->update( $this->table_name, $update, [ 'id' => $id ], $formats, [ '%d' ] );
		return $res !== false;
	}
}

