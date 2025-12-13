<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_AppointmentResourcesRepository {

    private $table_name;
    private $appt_table;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'lazy_appointment_resources';
        $this->appt_table = $wpdb->prefix . 'lazy_appointments';
    }

    /**
     * Return map resource_id => used_seats (SUM of seats, not just COUNT)
     */
    public function get_blocked_resources(string $start_at, string $end_at, bool $include_pending = false): array {
        global $wpdb;

        $statuses = [ 'confirmed' ];
        if ( $include_pending ) $statuses[] = 'pending';

        $placeholders = implode( ',', array_fill(0, count($statuses), '%s') );

        $sql = "SELECT ar.resource_id AS resource_id, SUM(a.seats) AS used FROM {$this->table_name} ar JOIN {$this->appt_table} a ON a.id = ar.appointment_id WHERE a.start_at < %s AND a.end_at > %s AND a.status IN ($placeholders) GROUP BY ar.resource_id";

        $params = array_merge( [ $end_at, $start_at ], $statuses );
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
        $map = [];
        foreach ( $rows as $r ) {
            $map[ intval($r['resource_id']) ] = intval($r['used']);
        }
        return $map;
    }

    public function set_resource_for_appointment(int $appointment_id, int $resource_id): bool {
        global $wpdb;
        // remove any existing mapping for this appointment
        $wpdb->delete( $this->table_name, [ 'appointment_id' => $appointment_id ], [ '%d' ] );
        $now = current_time('mysql');
        $res = $wpdb->insert( $this->table_name, [ 'appointment_id' => $appointment_id, 'resource_id' => $resource_id, 'created_at' => $now, 'updated_at' => $now ], [ '%d','%d','%s','%s' ] );
        return $res !== false;
    }

    public function get_resource_for_appointment(int $appointment_id): ?int {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT resource_id FROM {$this->table_name} WHERE appointment_id = %d LIMIT 1", $appointment_id ), ARRAY_A );
        return $row ? intval($row['resource_id']) : null;
    }
}
