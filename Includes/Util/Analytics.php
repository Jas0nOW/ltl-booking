<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Analytics Engine
 * 
 * Provides appointment statistics, trends, and export functionality.
 */
class LTLB_Analytics {

    private static $instance = null;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get appointment count by status for a given date range
     */
    public function get_count_by_status( string $start_date, string $end_date ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM {$table} 
             WHERE DATE(start_at) BETWEEN %s AND %s 
             GROUP BY status",
            $start_date,
            $end_date
        ), ARRAY_A );

        $counts = [ 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0 ];
        foreach ( $results as $row ) {
            $status = $row['status'] ?? 'cancelled';
            $counts[ $status ] = intval( $row['count'] );
        }
        return $counts;
    }

    /**
     * Get top N services by appointment count
     */
    public function get_top_services( int $limit = 5, string $start_date = null, string $end_date = null ): array {
        global $wpdb;
        $appt_table = $wpdb->prefix . 'lazy_appointments';
        $svc_table = $wpdb->prefix . 'lazy_services';

        $where = '1=1';
        $params = [];

        if ( $start_date && $end_date ) {
            $where .= ' AND DATE(a.start_at) BETWEEN %s AND %s';
            $params[] = $start_date;
            $params[] = $end_date;
        }

        $sql = "SELECT s.id, s.name, COUNT(a.id) as count 
                FROM {$svc_table} s 
                LEFT JOIN {$appt_table} a ON s.id = a.service_id 
                WHERE {$where} 
                GROUP BY s.id 
                ORDER BY count DESC 
                LIMIT %d";

        $params[] = $limit;
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        return $results ?: [];
    }

    /**
     * Get peak hours (most bookings by hour of day)
     */
    public function get_peak_hours( string $start_date = null, string $end_date = null ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';

        $where = '1=1';
        $params = [];

        if ( $start_date && $end_date ) {
            $where .= ' AND DATE(start_at) BETWEEN %s AND %s';
            $params[] = $start_date;
            $params[] = $end_date;
        }

        $sql = "SELECT HOUR(start_at) as hour, COUNT(*) as count 
                FROM {$table} 
                WHERE {$where} 
                GROUP BY HOUR(start_at) 
                ORDER BY count DESC";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        return $results ?: [];
    }

    /**
     * Get total revenue (if pricing is tracked)
     */
    public function get_revenue( string $start_date, string $end_date, string $currency = 'EUR' ): array {
        global $wpdb;
        $appt_table = $wpdb->prefix . 'lazy_appointments';
        $svc_table = $wpdb->prefix . 'lazy_services';

        // Support legacy schemas if a price column exists; otherwise join services (current schema).
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$appt_table}", 0 );
        if ( ! is_array( $cols ) ) {
            $cols = [];
        }
		$has_amount_cents = in_array( 'amount_cents', $cols, true );
        $has_price = in_array( 'price', $cols, true );
        $has_price_cents = in_array( 'price_cents', $cols, true );

        $total = 0.0;
        if ( $has_amount_cents ) {
			$sum_cents = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(amount_cents) FROM {$appt_table} WHERE DATE(start_at) BETWEEN %s AND %s AND status = 'confirmed'",
				$start_date,
				$end_date
			) );
			$total = floatval( $sum_cents ?? 0 ) / 100.0;
        } elseif ( $has_price ) {
            $sum = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(price) FROM {$appt_table} WHERE DATE(start_at) BETWEEN %s AND %s AND status = 'confirmed'",
                $start_date,
                $end_date
            ) );
            $total = floatval( $sum ?? 0 );
        } elseif ( $has_price_cents ) {
            $sum_cents = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(price_cents) FROM {$appt_table} WHERE DATE(start_at) BETWEEN %s AND %s AND status = 'confirmed'",
                $start_date,
                $end_date
            ) );
            $total = floatval( $sum_cents ?? 0 ) / 100.0;
        } else {
            // Current schema: appointments reference services; use service price at query time.
            // Multiply by seats (group bookings).
            $sum_cents = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(COALESCE(s.price_cents, 0) * COALESCE(a.seats, 1))
                 FROM {$appt_table} a
                 LEFT JOIN {$svc_table} s ON a.service_id = s.id
                 WHERE DATE(a.start_at) BETWEEN %s AND %s AND a.status = 'confirmed'",
                $start_date,
                $end_date
            ) );
            $total = floatval( $sum_cents ?? 0 ) / 100.0;
        }

        return [
            'total' => round( $total, 2 ),
            'currency' => $currency,
            'period_start' => $start_date,
            'period_end' => $end_date,
        ];
    }

    /**
     * Export appointments to CSV
     */
    public function export_csv( string $start_date, string $end_date ): string {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';
        $svc_table = $wpdb->prefix . 'lazy_services';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.id, a.customer_name, a.customer_email, s.name as service, a.start_at, a.end_at, a.status 
             FROM {$table} a 
             LEFT JOIN {$svc_table} s ON a.service_id = s.id 
             WHERE DATE(a.start_at) BETWEEN %s AND %s 
             ORDER BY a.start_at DESC",
            $start_date,
            $end_date
        ), ARRAY_A );

        $csv = "ID,Customer Name,Email,Service,Start,End,Status\n";

        foreach ( $results as $row ) {
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",%s,%s,%s\n",
                $row['id'],
                sanitize_text_field( $row['customer_name'] ?? '' ),
                sanitize_email( $row['customer_email'] ?? '' ),
                sanitize_text_field( $row['service'] ?? '' ),
                $row['start_at'] ?? '',
                $row['end_at'] ?? '',
                $row['status'] ?? ''
            );
        }

        return $csv;
    }

    /**
     * Get daily appointment count for chart data
     */
    public function get_daily_counts( string $start_date, string $end_date ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(start_at) as date, COUNT(*) as count 
             FROM {$table} 
             WHERE DATE(start_at) BETWEEN %s AND %s 
             GROUP BY DATE(start_at) 
             ORDER BY date ASC",
            $start_date,
            $end_date
        ), ARRAY_A );

        $data = [];
        foreach ( $results as $row ) {
            $data[ $row['date'] ] = intval( $row['count'] );
        }

        return $data;
    }
}
