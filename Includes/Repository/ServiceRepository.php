<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_ServiceRepository {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'lazy_services';
    }

    /**
     * Get all services sorted by ID DESC
     *
     * @return array
     */
    public function get_all(): array {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY id DESC",
            ARRAY_A
        );

        return $results ?: [];
    }
}
