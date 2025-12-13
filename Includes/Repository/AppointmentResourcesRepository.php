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

    /**
     * Return an associative array resource_id => overlapping_appointment_count
     * for appointments that overlap the given interval. By default considers only 'confirmed' status,
     * set $include_pending=true to include 'pending' as blocking as well.
     *
     * @param string $start DATETIME string
     * @param string $end DATETIME string
     * @param bool $include_pending
     * @return array<int,int>
     */
    public function get_blocked_resources(string $start, string $end, bool $include_pending = false): array {
        global $wpdb;
        $appointments_table = $wpdb->prefix . 'lazy_appointments';

        $status_clause = $include_pending ? "a.status IN ('confirmed','pending')" : "a.status = 'confirmed'";

        $sql = "SELECT ar.resource_id, COUNT(*) as cnt
                FROM {$this->table_name} ar
                JOIN {$appointments_table} a ON ar.appointment_id = a.id
                WHERE a.start_at < %s AND a.end_at > %s AND {$status_clause}
                GROUP BY ar.resource_id";

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $end, $start ), ARRAY_A );
        $out = [];
        if ( $rows ) {
            foreach ( $rows as $r ) {
                $out[ intval($r['resource_id']) ] = intval($r['cnt']);
            }
        }
        return $out;
    }
}
