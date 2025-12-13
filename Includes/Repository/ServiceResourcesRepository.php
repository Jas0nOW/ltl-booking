<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_ServiceResourcesRepository {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'lazy_service_resources';
    }

    public function get_resources_for_service(int $service_id): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare("SELECT resource_id FROM {$this->table_name} WHERE service_id = %d", $service_id), ARRAY_A );
        if ( ! $rows ) return [];
        return array_map(function($r){ return (int) $r['resource_id']; }, $rows );
    }

    public function set_resources_for_service(int $service_id, array $resource_ids): bool {
        global $wpdb;
        // Begin transaction-like behavior: delete existing, insert new
        $table = $this->table_name;
        $res1 = $wpdb->delete( $table, [ 'service_id' => $service_id ], [ '%d' ] );
        // Insert new mappings
        if ( empty($resource_ids) ) return true;
        foreach ( $resource_ids as $rid ) {
            $rid = intval($rid);
            $wpdb->insert( $table, [ 'service_id' => $service_id, 'resource_id' => $rid ], [ '%d', '%d' ] );
        }
        return true;
    }

    public function delete_for_service(int $service_id): bool {
        global $wpdb;
        $res = $wpdb->delete( $this->table_name, [ 'service_id' => $service_id ], [ '%d' ] );
        return $res !== false;
    }
}
