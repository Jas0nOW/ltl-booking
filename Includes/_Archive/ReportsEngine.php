<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Reports & Analytics Engine
 * 
 * Provides business intelligence reports:
 * - Occupancy rates
 * - Revenue analysis
 * - Conversion tracking
 * - No-show statistics
 * - Top services/rooms
 * - Staff utilization
 * 
 * @package LazyBookings
 */
class LTLB_Reports_Engine {

    /**
     * Get occupancy report
     * 
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @param int $location_id Optional location filter
     * @return array Occupancy data
     */
    public function get_occupancy_report( string $start_date, string $end_date, int $location_id = 0 ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $resources_table = $wpdb->prefix . 'ltlb_resources';

        $where = "a.start_at >= %s AND a.start_at <= %s AND a.status IN ('confirmed', 'completed')";
        $params = [ $start_date, $end_date ];

        if ( $location_id > 0 ) {
            $where .= " AND a.location_id = %d";
            $params[] = $location_id;
        }

        // Get total bookings
        $total_bookings = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table a WHERE $where",
            $params
        ) );

        // Get available capacity (room-nights or time slots)
        $total_capacity = $this->calculate_total_capacity( $start_date, $end_date, $location_id );

        $occupancy_rate = $total_capacity > 0 ? ( $total_bookings / $total_capacity ) * 100 : 0;

        // Daily breakdown
        $daily_query = "SELECT 
                DATE(a.start_at) as date,
                COUNT(*) as bookings
            FROM $appointments_table a
            WHERE $where
            GROUP BY DATE(a.start_at)
            ORDER BY date ASC";

        $daily_data = $wpdb->get_results( $wpdb->prepare( $daily_query, $params ) );

        return [
            'total_bookings' => intval( $total_bookings ),
            'total_capacity' => intval( $total_capacity ),
            'occupancy_rate' => round( $occupancy_rate, 2 ),
            'daily_breakdown' => $daily_data
        ];
    }

    /**
     * Get revenue report
     * 
     * @param string $start_date
     * @param string $end_date
     * @param int $location_id
     * @return array Revenue data
     */
    public function get_revenue_report( string $start_date, string $end_date, int $location_id = 0 ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';

        $where = "start_at >= %s AND start_at <= %s AND status IN ('confirmed', 'completed')";
        $params = [ $start_date, $end_date ];

        if ( $location_id > 0 ) {
            $where .= " AND location_id = %d";
            $params[] = $location_id;
        }

        // Total revenue
        $total_revenue = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount_cents), 0) FROM $appointments_table WHERE $where",
            $params
        ) );

        // Revenue by service
        $services_table = $wpdb->prefix . 'ltlb_services';
        $by_service = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                s.name as service_name,
                COUNT(a.id) as bookings,
                COALESCE(SUM(a.amount_cents), 0) as revenue
             FROM $appointments_table a
             LEFT JOIN $services_table s ON a.service_id = s.id
             WHERE $where
             GROUP BY a.service_id
             ORDER BY revenue DESC",
            $params
        ) );

        // Revenue by payment method
        $by_payment_method = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                payment_method,
                COUNT(*) as bookings,
                COALESCE(SUM(amount_cents), 0) as revenue
             FROM $appointments_table
             WHERE $where AND payment_method IS NOT NULL
             GROUP BY payment_method",
            $params
        ) );

        // Daily revenue trend
        $daily_revenue = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                DATE(start_at) as date,
                COUNT(*) as bookings,
                COALESCE(SUM(amount_cents), 0) as revenue
             FROM $appointments_table
             WHERE $where
             GROUP BY DATE(start_at)
             ORDER BY date ASC",
            $params
        ) );

        return [
            'total_revenue_cents' => intval( $total_revenue ),
            'by_service' => $by_service,
            'by_payment_method' => $by_payment_method,
            'daily_trend' => $daily_revenue
        ];
    }

    /**
     * Get conversion report
     * 
     * @param string $start_date
     * @param string $end_date
     * @return array Conversion data
     */
    public function get_conversion_report( string $start_date, string $end_date ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';

        $where = "created_at >= %s AND created_at <= %s";
        $params = [ $start_date, $end_date ];

        // Total inquiries (all statuses)
        $total_inquiries = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE $where",
            $params
        ) );

        // Confirmed bookings
        $confirmed = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE $where AND status IN ('confirmed', 'completed')",
            $params
        ) );

        // Cancelled
        $cancelled = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE $where AND status = 'cancelled'",
            $params
        ) );

        // Pending
        $pending = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE $where AND status = 'pending'",
            $params
        ) );

        $conversion_rate = $total_inquiries > 0 ? ( $confirmed / $total_inquiries ) * 100 : 0;

        return [
            'total_inquiries' => intval( $total_inquiries ),
            'confirmed' => intval( $confirmed ),
            'pending' => intval( $pending ),
            'cancelled' => intval( $cancelled ),
            'conversion_rate' => round( $conversion_rate, 2 )
        ];
    }

    /**
     * Get no-show report
     * 
     * @param string $start_date
     * @param string $end_date
     * @return array No-show data
     */
    public function get_noshow_report( string $start_date, string $end_date ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';

        $where = "start_at >= %s AND start_at < %s";
        $params = [ $start_date, $end_date ];

        // Total confirmed bookings in period
        $total_confirmed = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE $where AND status = 'confirmed'",
            $params
        ) );

        // No-shows (confirmed but never marked completed and past)
        $no_shows = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table 
             WHERE $where 
             AND status = 'confirmed' 
             AND end_at < %s",
            array_merge( $params, [ current_time( 'mysql' ) ] )
        ) );

        $noshow_rate = $total_confirmed > 0 ? ( $no_shows / $total_confirmed ) * 100 : 0;

        // No-shows by customer
        $by_customer = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                u.display_name,
                u.user_email,
                COUNT(*) as noshow_count
             FROM $appointments_table a
             LEFT JOIN {$wpdb->users} u ON a.customer_id = u.ID
             WHERE $where 
             AND a.status = 'confirmed'
             AND a.end_at < %s
             GROUP BY a.customer_id
             HAVING noshow_count > 1
             ORDER BY noshow_count DESC
             LIMIT 10",
            array_merge( $params, [ current_time( 'mysql' ) ] )
        ) );

        return [
            'total_confirmed' => intval( $total_confirmed ),
            'no_shows' => intval( $no_shows ),
            'noshow_rate' => round( $noshow_rate, 2 ),
            'repeat_offenders' => $by_customer
        ];
    }

    /**
     * Get top services report
     * 
     * @param string $start_date
     * @param string $end_date
     * @param int $limit
     * @return array Top services
     */
    public function get_top_services( string $start_date, string $end_date, int $limit = 10 ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $services_table = $wpdb->prefix . 'ltlb_services';

        $top_services = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                s.name as service_name,
                COUNT(a.id) as booking_count,
                COALESCE(SUM(a.amount_cents), 0) as total_revenue
             FROM $appointments_table a
             LEFT JOIN $services_table s ON a.service_id = s.id
             WHERE a.start_at >= %s 
             AND a.start_at <= %s 
             AND a.status IN ('confirmed', 'completed')
             GROUP BY a.service_id
             ORDER BY booking_count DESC
             LIMIT %d",
            $start_date,
            $end_date,
            $limit
        ) );

        return $top_services;
    }

    /**
     * Get staff utilization report
     * 
     * @param string $start_date
     * @param string $end_date
     * @return array Staff utilization data
     */
    public function get_staff_utilization( string $start_date, string $end_date ): array {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';

        $utilization = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                u.display_name as staff_name,
                COUNT(a.id) as booking_count,
                COALESCE(SUM(a.amount_cents), 0) as revenue_generated,
                AVG(TIMESTAMPDIFF(MINUTE, a.start_at, a.end_at)) as avg_duration_minutes
             FROM $appointments_table a
             LEFT JOIN {$wpdb->users} u ON a.staff_id = u.ID
             WHERE a.start_at >= %s 
             AND a.start_at <= %s 
             AND a.status IN ('confirmed', 'completed')
             AND a.staff_id IS NOT NULL
             GROUP BY a.staff_id
             ORDER BY booking_count DESC",
            $start_date,
            $end_date
        ) );

        return $utilization;
    }

    /**
     * Get dashboard summary
     * 
     * @param string $period Period: today, week, month, year
     * @return array Summary data
     */
    public function get_dashboard_summary( string $period = 'month' ): array {
        switch ( $period ) {
            case 'today':
                $start_date = current_time( 'Y-m-d' );
                $end_date = current_time( 'Y-m-d' );
                break;
            case 'week':
                $start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
                $end_date = current_time( 'Y-m-d' );
                break;
            case 'year':
                $start_date = date( 'Y-01-01' );
                $end_date = date( 'Y-12-31' );
                break;
            case 'month':
            default:
                $start_date = date( 'Y-m-01' );
                $end_date = date( 'Y-m-t' );
                break;
        }

        $revenue = $this->get_revenue_report( $start_date, $end_date );
        $conversion = $this->get_conversion_report( $start_date, $end_date );
        $occupancy = $this->get_occupancy_report( $start_date, $end_date );

        return [
            'period' => $period,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'total_revenue_cents' => $revenue['total_revenue_cents'],
            'total_bookings' => $conversion['confirmed'],
            'conversion_rate' => $conversion['conversion_rate'],
            'occupancy_rate' => $occupancy['occupancy_rate']
        ];
    }

    /**
     * Calculate total capacity
     * 
     * @param string $start_date
     * @param string $end_date
     * @param int $location_id
     * @return int Total capacity (slots or room-nights)
     */
    private function calculate_total_capacity( string $start_date, string $end_date, int $location_id = 0 ): int {
        global $wpdb;

        // Get number of days in range
        $start = new DateTime( $start_date );
        $end = new DateTime( $end_date );
        $days = $end->diff( $start )->days + 1;

        // Get total resources (rooms/services)
        $resources_table = $wpdb->prefix . 'ltlb_resources';
        
        $where = "1=1";
        $params = [];
        
        if ( $location_id > 0 ) {
            $where .= " AND location_id = %d";
            $params[] = $location_id;
        }

        $query = "SELECT COALESCE(SUM(quantity), 0) FROM $resources_table WHERE $where";
        
        $total_resources = $wpdb->get_var( 
            empty( $params ) ? $query : $wpdb->prepare( $query, $params )
        );

        // Capacity = resources × days
        return intval( $total_resources ) * $days;
    }

    /**
     * Export report to CSV
     * 
     * @param string $report_type Report type
     * @param array $data Report data
     * @return string CSV content
     */
    public function export_to_csv( string $report_type, array $data ): string {
        $csv = '';

        switch ( $report_type ) {
            case 'revenue':
                $csv .= "Date,Bookings,Revenue\n";
                foreach ( $data['daily_trend'] as $row ) {
                    $csv .= sprintf( "%s,%d,%d\n", $row->date, $row->bookings, $row->revenue );
                }
                break;

            case 'staff_utilization':
                $csv .= "Staff Name,Bookings,Revenue,Avg Duration (min)\n";
                foreach ( $data as $row ) {
                    $csv .= sprintf( "%s,%d,%d,%.1f\n", 
                        $row->staff_name, 
                        $row->booking_count, 
                        $row->revenue_generated,
                        $row->avg_duration_minutes
                    );
                }
                break;

            default:
                $csv .= "Export not implemented for this report type\n";
        }

        return $csv;
    }
}
