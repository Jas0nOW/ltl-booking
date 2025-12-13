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
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT resource_id FROM {$this->table_name} WHERE service_id = %d", $service_id ), ARRAY_A );
        if ( ! $rows ) return [];
        return array_map(function($r){ return intval($r['resource_id']); }, $rows );
    }

    public function set_resources_for_service(int $service_id, array $resource_ids): bool {
        global $wpdb;
        // simple replace: delete existing then insert
        $wpdb->delete( $this->table_name, [ 'service_id' => $service_id ], [ '%d' ] );
        foreach ( $resource_ids as $rid ) {
            $wpdb->insert( $this->table_name, [ 'service_id' => $service_id, 'resource_id' => intval($rid) ], [ '%d','%d' ] );
        }
        return true;
    }
}
