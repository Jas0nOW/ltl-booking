<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Import/Export Engine
 * 
 * Handles data import/export for:
 * - Customers
 * - Staff
 * - Services
 * - Rooms/Resources
 * - Bookings/Appointments
 * 
 * Supports: CSV, JSON
 * 
 * @package LazyBookings
 */
class LTLB_Import_Export {

    /**
     * Export customers to CSV
     * 
     * @return string CSV content
     */
    public function export_customers(): string {
        global $wpdb;

        $customers = $wpdb->get_results(
            "SELECT u.ID, u.user_email, u.display_name, u.user_registered,
                    MAX(CASE WHEN um.meta_key = 'first_name' THEN um.meta_value END) as first_name,
                    MAX(CASE WHEN um.meta_key = 'last_name' THEN um.meta_value END) as last_name,
                    MAX(CASE WHEN um.meta_key = 'billing_phone' THEN um.meta_value END) as phone
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
             WHERE u.ID IN (
                 SELECT DISTINCT customer_id FROM {$wpdb->prefix}ltlb_appointments
             )
             GROUP BY u.ID
             ORDER BY u.user_registered DESC"
        );

        $csv = "ID,Email,First Name,Last Name,Phone,Display Name,Registered Date\n";

        foreach ( $customers as $customer ) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s\n",
                $customer->ID,
                $this->escape_csv( $customer->user_email ),
                $this->escape_csv( $customer->first_name ),
                $this->escape_csv( $customer->last_name ),
                $this->escape_csv( $customer->phone ),
                $this->escape_csv( $customer->display_name ),
                $customer->user_registered
            );
        }

        return $csv;
    }

    /**
     * Export services to CSV
     * 
     * @return string CSV content
     */
    public function export_services(): string {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_services';
        $services = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY name ASC"
        );

        $csv = "ID,Name,Description,Duration (min),Price (cents),Capacity,Buffer (min),Color,Status\n";

        foreach ( $services as $service ) {
            $csv .= sprintf(
                "%d,%s,%s,%d,%d,%d,%d,%s,%s\n",
                $service->id,
                $this->escape_csv( $service->name ),
                $this->escape_csv( $service->description ?? '' ),
                $service->duration_minutes ?? 60,
                $service->price_cents ?? 0,
                $service->capacity ?? 1,
                $service->buffer_minutes ?? 0,
                $service->color ?? '#3498db',
                $service->is_active ? 'active' : 'inactive'
            );
        }

        return $csv;
    }

    /**
     * Export resources (rooms) to CSV
     * 
     * @return string CSV content
     */
    public function export_resources(): string {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_resources';
        $resources = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY name ASC"
        );

        $csv = "ID,Name,Type,Quantity,Location ID,Status\n";

        foreach ( $resources as $resource ) {
            $csv .= sprintf(
                "%d,%s,%s,%d,%d,%s\n",
                $resource->id,
                $this->escape_csv( $resource->name ),
                $resource->type ?? 'room',
                $resource->quantity ?? 1,
                $resource->location_id ?? 0,
                $resource->is_active ? 'active' : 'inactive'
            );
        }

        return $csv;
    }

    /**
     * Export appointments to CSV
     * 
     * @param string $start_date Optional start date filter
     * @param string $end_date Optional end date filter
     * @return string CSV content
     */
    public function export_appointments( string $start_date = '', string $end_date = '' ): string {
        global $wpdb;

        $appointments_table = $wpdb->prefix . 'ltlb_appointments';
        $services_table = $wpdb->prefix . 'ltlb_services';

        $where = "1=1";
        $params = [];

        if ( ! empty( $start_date ) ) {
            $where .= " AND a.start_at >= %s";
            $params[] = $start_date;
        }

        if ( ! empty( $end_date ) ) {
            $where .= " AND a.start_at <= %s";
            $params[] = $end_date;
        }

        $query = "SELECT 
                    a.*,
                    s.name as service_name,
                    u.user_email as customer_email,
                    u.display_name as customer_name,
                    staff.display_name as staff_name
                  FROM $appointments_table a
                  LEFT JOIN $services_table s ON a.service_id = s.id
                  LEFT JOIN {$wpdb->users} u ON a.customer_id = u.ID
                  LEFT JOIN {$wpdb->users} staff ON a.staff_id = staff.ID
                  WHERE $where
                  ORDER BY a.start_at DESC";

        $appointments = empty( $params ) 
            ? $wpdb->get_results( $query )
            : $wpdb->get_results( $wpdb->prepare( $query, $params ) );

        $csv = "ID,Customer Email,Customer Name,Service,Staff,Start,End,Status,Amount (cents),Payment Method,Notes\n";

        foreach ( $appointments as $apt ) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s,%d,%s,%s\n",
                $apt->id,
                $this->escape_csv( $apt->customer_email ),
                $this->escape_csv( $apt->customer_name ),
                $this->escape_csv( $apt->service_name ),
                $this->escape_csv( $apt->staff_name ),
                $apt->start_at,
                $apt->end_at,
                $apt->status,
                $apt->amount_cents ?? 0,
                $this->escape_csv( $apt->payment_method ?? '' ),
                $this->escape_csv( $apt->notes ?? '' )
            );
        }

        return $csv;
    }

    /**
     * Import customers from CSV
     * 
     * @param string $csv_content CSV content
     * @return array Import results
     */
    public function import_customers( string $csv_content ): array {
        $rows = $this->parse_csv( $csv_content );
        
        if ( empty( $rows ) ) {
            return [ 'success' => false, 'error' => __( 'Empty CSV file', 'ltl-bookings' ) ];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ( $rows as $index => $row ) {
            // Skip header
            if ( $index === 0 ) continue;

            if ( count( $row ) < 3 ) {
                $skipped++;
                continue;
            }

            $email = sanitize_email( $row[1] ?? '' );
            $first_name = sanitize_text_field( $row[2] ?? '' );
            $last_name = sanitize_text_field( $row[3] ?? '' );
            $phone = sanitize_text_field( $row[4] ?? '' );

            if ( empty( $email ) || ! is_email( $email ) ) {
                $errors[] = sprintf( __( 'Row %d: Invalid email', 'ltl-bookings' ), $index + 1 );
                $skipped++;
                continue;
            }

            // Check if user exists
            if ( email_exists( $email ) ) {
                $skipped++;
                continue;
            }

            // Create user
            $user_id = wp_create_user( 
                $email, 
                wp_generate_password(), 
                $email 
            );

            if ( is_wp_error( $user_id ) ) {
                $errors[] = sprintf( 
                    __( 'Row %d: %s', 'ltl-bookings' ), 
                    $index + 1, 
                    $user_id->get_error_message() 
                );
                $skipped++;
                continue;
            }

            // Update user meta
            if ( ! empty( $first_name ) ) {
                update_user_meta( $user_id, 'first_name', $first_name );
            }
            if ( ! empty( $last_name ) ) {
                update_user_meta( $user_id, 'last_name', $last_name );
            }
            if ( ! empty( $phone ) ) {
                update_user_meta( $user_id, 'billing_phone', $phone );
            }

            $imported++;
        }

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Import services from CSV
     * 
     * @param string $csv_content CSV content
     * @return array Import results
     */
    public function import_services( string $csv_content ): array {
        global $wpdb;

        $rows = $this->parse_csv( $csv_content );
        
        if ( empty( $rows ) ) {
            return [ 'success' => false, 'error' => __( 'Empty CSV file', 'ltl-bookings' ) ];
        }

        $table = $wpdb->prefix . 'ltlb_services';
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ( $rows as $index => $row ) {
            // Skip header
            if ( $index === 0 ) continue;

            if ( count( $row ) < 2 ) {
                $skipped++;
                continue;
            }

            $id = ! empty( $row[0] ) ? intval( $row[0] ) : 0;
            $name = sanitize_text_field( $row[1] ?? '' );
            $description = sanitize_textarea_field( $row[2] ?? '' );
            $duration = intval( $row[3] ?? 60 );
            $price = intval( $row[4] ?? 0 );
            $capacity = intval( $row[5] ?? 1 );
            $buffer = intval( $row[6] ?? 0 );
            $color = sanitize_hex_color( $row[7] ?? '#3498db' );
            $is_active = ( $row[8] ?? 'active' ) === 'active' ? 1 : 0;

            if ( empty( $name ) ) {
                $errors[] = sprintf( __( 'Row %d: Service name required', 'ltl-bookings' ), $index + 1 );
                $skipped++;
                continue;
            }

            $data = [
                'name' => $name,
                'description' => $description,
                'duration_minutes' => $duration,
                'price_cents' => $price,
                'capacity' => $capacity,
                'buffer_minutes' => $buffer,
                'color' => $color,
                'is_active' => $is_active
            ];

            // Check if service exists (by ID or name)
            $exists = false;
            if ( $id > 0 ) {
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", $id ) );
            }
            if ( ! $exists ) {
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE name = %s", $name ) );
            }

            if ( $exists ) {
                // Update existing
                $wpdb->update( $table, $data, [ 'id' => $exists ], 
                    [ '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d' ], 
                    [ '%d' ] 
                );
                $updated++;
            } else {
                // Insert new
                $data['created_at'] = current_time( 'mysql' );
                $wpdb->insert( $table, $data, 
                    [ '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s' ] 
                );
                $imported++;
            }
        }

        return [
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Import appointments from CSV
     * 
     * @param string $csv_content CSV content
     * @return array Import results
     */
    public function import_appointments( string $csv_content ): array {
        global $wpdb;

        $rows = $this->parse_csv( $csv_content );
        
        if ( empty( $rows ) ) {
            return [ 'success' => false, 'error' => __( 'Empty CSV file', 'ltl-bookings' ) ];
        }

        $table = $wpdb->prefix . 'ltlb_appointments';
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ( $rows as $index => $row ) {
            // Skip header
            if ( $index === 0 ) continue;

            if ( count( $row ) < 7 ) {
                $skipped++;
                continue;
            }

            $customer_email = sanitize_email( $row[1] ?? '' );
            $service_name = sanitize_text_field( $row[3] ?? '' );
            $start_at = sanitize_text_field( $row[5] ?? '' );
            $end_at = sanitize_text_field( $row[6] ?? '' );
            $status = sanitize_text_field( $row[7] ?? 'confirmed' );
            $amount_cents = intval( $row[8] ?? 0 );

            // Validate required fields
            if ( empty( $customer_email ) || empty( $start_at ) ) {
                $errors[] = sprintf( __( 'Row %d: Missing required fields', 'ltl-bookings' ), $index + 1 );
                $skipped++;
                continue;
            }

            // Get customer ID
            $customer = get_user_by( 'email', $customer_email );
            if ( ! $customer ) {
                $errors[] = sprintf( __( 'Row %d: Customer not found: %s', 'ltl-bookings' ), $index + 1, $customer_email );
                $skipped++;
                continue;
            }

            // Get service ID
            $service_table = $wpdb->prefix . 'ltlb_services';
            $service_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $service_table WHERE name = %s LIMIT 1",
                $service_name
            ) );

            if ( ! $service_id ) {
                $errors[] = sprintf( __( 'Row %d: Service not found: %s', 'ltl-bookings' ), $index + 1, $service_name );
                $skipped++;
                continue;
            }

            // Insert appointment
            $data = [
                'customer_id' => $customer->ID,
                'service_id' => $service_id,
                'start_at' => $start_at,
                'end_at' => $end_at,
                'status' => $status,
                'amount_cents' => $amount_cents,
                'created_at' => current_time( 'mysql' )
            ];

            $result = $wpdb->insert( $table, $data, [ '%d', '%d', '%s', '%s', '%s', '%d', '%s' ] );

            if ( $result ) {
                $imported++;
            } else {
                $errors[] = sprintf( __( 'Row %d: Database error', 'ltl-bookings' ), $index + 1 );
                $skipped++;
            }
        }

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Export to JSON
     * 
     * @param string $entity Entity type: customers, services, resources, appointments
     * @param array $filters Optional filters
     * @return string JSON content
     */
    public function export_to_json( string $entity, array $filters = [] ): string {
        $data = [];

        switch ( $entity ) {
            case 'customers':
                $data = $this->get_customers_data();
                break;
            case 'services':
                $data = $this->get_services_data();
                break;
            case 'resources':
                $data = $this->get_resources_data();
                break;
            case 'appointments':
                $data = $this->get_appointments_data( $filters );
                break;
        }

        return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Import from JSON
     * 
     * @param string $entity Entity type
     * @param string $json_content JSON content
     * @return array Import results
     */
    public function import_from_json( string $entity, string $json_content ): array {
        $data = json_decode( $json_content, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return [ 'success' => false, 'error' => __( 'Invalid JSON', 'ltl-bookings' ) ];
        }

        // Convert to CSV format and use existing import methods
        switch ( $entity ) {
            case 'services':
                return $this->import_services_from_array( $data );
            default:
                return [ 'success' => false, 'error' => __( 'Import not implemented for this entity', 'ltl-bookings' ) ];
        }
    }

    /**
     * Parse CSV content
     * 
     * @param string $csv_content
     * @return array Rows
     */
    private function parse_csv( string $csv_content ): array {
        $rows = [];
        $lines = explode( "\n", $csv_content );

        foreach ( $lines as $line ) {
            if ( empty( trim( $line ) ) ) continue;
            $rows[] = str_getcsv( $line );
        }

        return $rows;
    }

    /**
     * Escape CSV value
     * 
     * @param string $value
     * @return string Escaped value
     */
    private function escape_csv( $value ): string {
        $value = str_replace( '"', '""', $value );
        if ( strpos( $value, ',' ) !== false || strpos( $value, "\n" ) !== false ) {
            $value = '"' . $value . '"';
        }
        return $value;
    }

    /**
     * Get customers data for JSON export
     * 
     * @return array Customers
     */
    private function get_customers_data(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT u.ID, u.user_email, u.display_name, u.user_registered
             FROM {$wpdb->users} u
             WHERE u.ID IN (
                 SELECT DISTINCT customer_id FROM {$wpdb->prefix}ltlb_appointments
             )
             ORDER BY u.user_registered DESC",
            ARRAY_A
        );
    }

    /**
     * Get services data for JSON export
     * 
     * @return array Services
     */
    private function get_services_data(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_services';
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC", ARRAY_A );
    }

    /**
     * Get resources data for JSON export
     * 
     * @return array Resources
     */
    private function get_resources_data(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_resources';
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC", ARRAY_A );
    }

    /**
     * Get appointments data for JSON export
     * 
     * @param array $filters
     * @return array Appointments
     */
    private function get_appointments_data( array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_appointments';
        
        $where = "1=1";
        $params = [];
        
        if ( ! empty( $filters['start_date'] ) ) {
            $where .= " AND start_at >= %s";
            $params[] = $filters['start_date'];
        }
        
        if ( ! empty( $filters['end_date'] ) ) {
            $where .= " AND start_at <= %s";
            $params[] = $filters['end_date'];
        }

        $query = "SELECT * FROM $table WHERE $where ORDER BY start_at DESC";
        
        return empty( $params )
            ? $wpdb->get_results( $query, ARRAY_A )
            : $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );
    }

    /**
     * Import services from array
     * 
     * @param array $data
     * @return array Results
     */
    private function import_services_from_array( array $data ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_services';
        
        $imported = 0;
        $updated = 0;

        foreach ( $data as $service ) {
            $exists = ! empty( $service['id'] ) 
                ? $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", $service['id'] ) )
                : false;

            if ( $exists ) {
                $wpdb->update( $table, $service, [ 'id' => $exists ] );
                $updated++;
            } else {
                unset( $service['id'] );
                $wpdb->insert( $table, $service );
                $imported++;
            }
        }

        return [
            'success' => true,
            'imported' => $imported,
            'updated' => $updated
        ];
    }
}
