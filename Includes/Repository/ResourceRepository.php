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
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY id DESC",
            ARRAY_A
        );

        return $results ?: [];
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
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : null,
            'capacity' => isset($data['capacity']) ? intval($data['capacity']) : 1,
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $formats = ['%s','%s','%d','%d','%s','%s'];
        $res = $wpdb->insert( $this->table_name, $insert, $formats );
        if ( $res === false ) return false;
        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool {
        global $wpdb;

        $update = [];
        $formats = [];

        $allowed = ['name','description','capacity','is_active'];
        foreach ( $allowed as $col ) {
            if ( isset( $data[ $col ] ) ) {
                if ( in_array( $col, ['capacity','is_active'], true ) ) {
                    $update[ $col ] = intval( $data[ $col ] );
                    $formats[] = '%d';
                } else {
                    $update[ $col ] = sanitize_text_field( $data[ $col ] );
                    $formats[] = '%s';
                }
            }
        }

        if ( empty( $update ) ) return false;
        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $where = [ 'id' => $id ];
        $where_format = [ '%d' ];

        $res = $wpdb->update( $this->table_name, $update, $where, $formats, $where_format );
        return $res !== false;
    }

    public function soft_delete(int $id): bool {
        global $wpdb;
        $res = $wpdb->update( $this->table_name, [ 'is_active' => 0, 'updated_at' => current_time('mysql') ], [ 'id' => $id ], [ '%d', '%s' ], [ '%d' ] );
        return $res !== false;
    }
}
