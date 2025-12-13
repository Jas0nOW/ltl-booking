<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_AppointmentResourcesRepository {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'lazy_appointment_resources';
    }

    public function get_resource_for_appointment(int $appointment_id): ?int {
        global $wpdb;
        $resource_id = $wpdb->get_var( $wpdb->prepare( "SELECT resource_id FROM {$this->table_name} WHERE appointment_id = %d", $appointment_id ) );
        return $resource_id ? (int) $resource_id : null;
    }

    public function set_resource_for_appointment(int $appointment_id, int $resource_id): bool {
        global $wpdb;
        // This will replace the existing resource for the appointment if there is one
        $res = $wpdb->replace( $this->table_name, [ 'appointment_id' => $appointment_id, 'resource_id' => $resource_id ], [ '%d', '%d' ] );
        return $res !== false;
    }

    public function get_blocked_resources(string $start, string $end): array {
        global $wpdb;
        $appointments_table = $wpdb->prefix . 'lazy_appointments';

        $sql = "SELECT ar.resource_id
                FROM {$this->table_name} ar
                JOIN {$appointments_table} a ON ar.appointment_id = a.id
                WHERE a.start_at < %s AND a.end_at > %s AND a.status IN ('confirmed', 'pending')";

        $results = $wpdb->get_col( $wpdb->prepare( $sql, $end, $start ) );

        return array_map( 'intval', $results );
    }
}
