<?php
/**
 * Admin List Filters
 * 
 * Provides unified filtering UI for all admin list pages (Appointments, Customers, Services, etc.)
 * with saved views, quick filters, and advanced search.
 *
 * @package LTL_Bookings
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LTLB_Admin_Filters {
    
    /**
     * Available filter definitions per page
     */
    private const FILTER_CONFIGS = [
        'appointments' => [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    '' => 'All Statuses',
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'cancelled' => 'Cancelled',
                    'completed' => 'Completed',
                    'no-show' => 'No-Show'
                ]
            ],
            'payment_status' => [
                'label' => 'Payment',
                'type' => 'select',
                'options' => [
                    '' => 'All Payments',
                    'paid' => 'Paid',
                    'unpaid' => 'Unpaid',
                    'free' => 'Free'
                ]
            ],
            'date_range' => [
                'label' => 'Date Range',
                'type' => 'daterange',
                'presets' => [
                    'today' => 'Today',
                    'tomorrow' => 'Tomorrow',
                    'this_week' => 'This Week',
                    'next_week' => 'Next Week',
                    'this_month' => 'This Month',
                    'custom' => 'Custom Range'
                ]
            ],
            'service_id' => [
                'label' => 'Service/Room',
                'type' => 'select_dynamic',
                'source' => 'services'
            ],
            'staff_id' => [
                'label' => 'Staff',
                'type' => 'select_dynamic',
                'source' => 'staff',
                'mode_visibility' => 'service' // Only in service mode
            ],
            'location_id' => [
                'label' => 'Location',
                'type' => 'select_dynamic',
                'source' => 'locations'
            ]
        ],
        'customers' => [
            'search' => [
                'label' => 'Search',
                'type' => 'text',
                'placeholder' => 'Name, Email, Phone...'
            ],
            'booking_count' => [
                'label' => 'Bookings',
                'type' => 'select',
                'options' => [
                    '' => 'Any',
                    '0' => 'No Bookings',
                    '1-5' => '1-5 Bookings',
                    '6-10' => '6-10 Bookings',
                    '11+' => '11+ Bookings'
                ]
            ],
            'created_date' => [
                'label' => 'Registered',
                'type' => 'daterange'
            ]
        ],
        'services' => [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    '' => 'All',
                    'active' => 'Active',
                    'inactive' => 'Inactive'
                ]
            ],
            'type' => [
                'label' => 'Type',
                'type' => 'select',
                'options' => [
                    '' => 'All Types',
                    'service' => 'Services',
                    'room' => 'Rooms'
                ]
            ]
        ]
    ];

    /**
     * Render filter bar for a specific page
     *
     * @param string $page Page identifier (appointments, customers, services)
     * @param array $current_filters Currently active filters
     */
    public static function render_filter_bar( string $page, array $current_filters = [] ): void {
        if ( ! isset( self::FILTER_CONFIGS[ $page ] ) ) {
            return;
        }

        $filters = self::FILTER_CONFIGS[ $page ];
        $mode = LTLB_Mode_Manager::get_current_mode();
        
        echo '<div class="ltlb-filter-bar">';
        echo '<form method="get" action="" class="ltlb-filters-form">';
        
        // Preserve page parameter
        echo '<input type="hidden" name="page" value="' . esc_attr( $_GET['page'] ?? '' ) . '">';
        
        echo '<div class="ltlb-filter-fields">';
        
        foreach ( $filters as $key => $config ) {
            // Check mode visibility
            if ( isset( $config['mode_visibility'] ) && $config['mode_visibility'] !== $mode ) {
                continue;
            }
            
            $value = $current_filters[ $key ] ?? '';
            
            echo '<div class="ltlb-filter-field">';
            echo '<label>' . esc_html__( $config['label'], 'ltl-bookings' ) . '</label>';
            
            switch ( $config['type'] ) {
                case 'select':
                    self::render_select( $key, $config['options'], $value );
                    break;
                    
                case 'select_dynamic':
                    self::render_dynamic_select( $key, $config['source'], $value );
                    break;
                    
                case 'daterange':
                    self::render_daterange( $key, $value, $config['presets'] ?? [] );
                    break;
                    
                case 'text':
                    self::render_text( $key, $value, $config['placeholder'] ?? '' );
                    break;
            }
            
            echo '</div>';
        }
        
        echo '</div>'; // .ltlb-filter-fields
        
        echo '<div class="ltlb-filter-actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Apply Filters', 'ltl-bookings' ) . '</button>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . ( $_GET['page'] ?? '' ) ) ) . '" class="button">' . esc_html__( 'Clear', 'ltl-bookings' ) . '</a>';
        echo '<button type="button" class="button ltlb-save-view-btn">' . esc_html__( 'Save View', 'ltl-bookings' ) . '</button>';
        echo '</div>';
        
        echo '</form>';
        
        // Saved views
        self::render_saved_views( $page );
        
        echo '</div>'; // .ltlb-filter-bar
        
        self::enqueue_scripts();
    }
    
    /**
     * Render standard select field
     */
    private static function render_select( string $name, array $options, string $value ): void {
        echo '<select name="' . esc_attr( $name ) . '" class="ltlb-filter-select">';
        foreach ( $options as $opt_value => $opt_label ) {
            $selected = ( $value === (string) $opt_value ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>';
            echo esc_html__( $opt_label, 'ltl-bookings' );
            echo '</option>';
        }
        echo '</select>';
    }
    
    /**
     * Render dynamic select (loads options from database)
     */
    private static function render_dynamic_select( string $name, string $source, string $value ): void {
        $options = self::get_dynamic_options( $source );
        
        echo '<select name="' . esc_attr( $name ) . '" class="ltlb-filter-select">';
        echo '<option value="">' . esc_html__( 'All', 'ltl-bookings' ) . '</option>';
        foreach ( $options as $opt ) {
            $selected = ( $value === (string) $opt['id'] ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $opt['id'] ) . '"' . $selected . '>';
            echo esc_html( $opt['name'] );
            echo '</option>';
        }
        echo '</select>';
    }
    
    /**
     * Render date range picker
     */
    private static function render_daterange( string $name, string $value, array $presets = [] ): void {
        echo '<div class="ltlb-daterange-wrapper">';
        
        if ( ! empty( $presets ) ) {
            echo '<select name="' . esc_attr( $name ) . '_preset" class="ltlb-daterange-preset">';
            foreach ( $presets as $preset_value => $preset_label ) {
                echo '<option value="' . esc_attr( $preset_value ) . '">';
                echo esc_html__( $preset_label, 'ltl-bookings' );
                echo '</option>';
            }
            echo '</select>';
        }
        
        // Ensure value is a string before explode
        $value = is_string( $value ) ? $value : '';
        $parts = explode( '|', $value );
        $from = $parts[0] ?? '';
        $to = $parts[1] ?? '';
        
        echo '<input type="date" name="' . esc_attr( $name ) . '_from" value="' . esc_attr( $from ) . '" class="ltlb-date-from" style="display:none;">';
        echo '<input type="date" name="' . esc_attr( $name ) . '_to" value="' . esc_attr( $to ) . '" class="ltlb-date-to" style="display:none;">';
        
        echo '</div>';
    }
    
    /**
     * Render text search field
     */
    private static function render_text( string $name, string $value, string $placeholder ): void {
        echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ';
        echo 'placeholder="' . esc_attr( $placeholder ) . '" class="ltlb-filter-text">';
    }
    
    /**
     * Get options for dynamic selects
     */
    private static function get_dynamic_options( string $source ): array {
        global $wpdb;
        
        switch ( $source ) {
            case 'services':
                $results = $wpdb->get_results(
                    "SELECT id, name FROM {$wpdb->prefix}ltlb_services WHERE is_active = 1 ORDER BY name",
                    ARRAY_A
                );
                return $results ?: [];
                
            case 'staff':
                $staff_users = get_users( [ 'role__in' => [ 'ltlb_staff', 'administrator' ] ] );
                return array_map( function( $user ) {
                    return [ 'id' => $user->ID, 'name' => $user->display_name ];
                }, $staff_users );
                
            case 'locations':
                $results = $wpdb->get_results(
                    "SELECT id, name FROM {$wpdb->prefix}ltlb_locations ORDER BY name",
                    ARRAY_A
                );
                return $results ?: [];
                
            default:
                return [];
        }
    }
    
    /**
     * Render saved views dropdown
     */
    private static function render_saved_views( string $page ): void {
        $views = get_option( 'ltlb_saved_views_' . $page, [] );
        
        if ( empty( $views ) ) {
            return;
        }
        
        echo '<div class="ltlb-saved-views">';
        echo '<label>' . esc_html__( 'Saved Views:', 'ltl-bookings' ) . '</label>';
        echo '<select class="ltlb-view-select" data-page="' . esc_attr( $page ) . '">';
        echo '<option value="">' . esc_html__( 'Select View...', 'ltl-bookings' ) . '</option>';
        
        foreach ( $views as $view_id => $view ) {
            echo '<option value="' . esc_attr( $view_id ) . '">';
            echo esc_html( $view['name'] );
            echo '</option>';
        }
        
        echo '</select>';
        echo '<button type="button" class="button ltlb-load-view-btn">' . esc_html__( 'Load', 'ltl-bookings' ) . '</button>';
        echo '<button type="button" class="button ltlb-delete-view-btn">' . esc_html__( 'Delete', 'ltl-bookings' ) . '</button>';
        echo '</div>';
    }
    
    /**
     * Apply filters to query
     *
     * @param array $filters Filter values from $_GET
     * @param string $page Page identifier
     * @return string SQL WHERE clause
     */
    public static function build_where_clause( array $filters, string $page ): string {
        global $wpdb;
        $where_parts = [];
        
        if ( $page === 'appointments' ) {
            if ( ! empty( $filters['status'] ) ) {
                $where_parts[] = $wpdb->prepare( "status = %s", $filters['status'] );
            }
            
            if ( ! empty( $filters['payment_status'] ) ) {
                $where_parts[] = $wpdb->prepare( "payment_status = %s", $filters['payment_status'] );
            }
            
            if ( ! empty( $filters['service_id'] ) ) {
                $where_parts[] = $wpdb->prepare( "service_id = %d", intval( $filters['service_id'] ) );
            }
            
            if ( ! empty( $filters['staff_id'] ) ) {
                $where_parts[] = $wpdb->prepare( "staff_id = %d", intval( $filters['staff_id'] ) );
            }
            
            if ( ! empty( $filters['location_id'] ) ) {
                $where_parts[] = $wpdb->prepare( "location_id = %d", intval( $filters['location_id'] ) );
            }
            
            // Date range
            if ( ! empty( $filters['date_range_preset'] ) ) {
                $date_clause = self::get_date_range_clause( $filters['date_range_preset'] );
                if ( $date_clause ) {
                    $where_parts[] = $date_clause;
                }
            } elseif ( ! empty( $filters['date_range_from'] ) || ! empty( $filters['date_range_to'] ) ) {
                if ( ! empty( $filters['date_range_from'] ) ) {
                    $where_parts[] = $wpdb->prepare( "date_start >= %s", $filters['date_range_from'] . ' 00:00:00' );
                }
                if ( ! empty( $filters['date_range_to'] ) ) {
                    $where_parts[] = $wpdb->prepare( "date_start <= %s", $filters['date_range_to'] . ' 23:59:59' );
                }
            }
        }
        
        if ( $page === 'customers' ) {
            if ( ! empty( $filters['search'] ) ) {
                $search = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
                $where_parts[] = $wpdb->prepare(
                    "(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)",
                    $search, $search, $search, $search
                );
            }
        }
        
        return ! empty( $where_parts ) ? ' WHERE ' . implode( ' AND ', $where_parts ) : '';
    }
    
    /**
     * Convert date preset to SQL clause
     */
    private static function get_date_range_clause( string $preset ): string {
        global $wpdb;
        $now = current_time( 'Y-m-d' );
        
        switch ( $preset ) {
            case 'today':
                return $wpdb->prepare( "DATE(date_start) = %s", $now );
                
            case 'tomorrow':
                $tomorrow = date( 'Y-m-d', strtotime( '+1 day', strtotime( $now ) ) );
                return $wpdb->prepare( "DATE(date_start) = %s", $tomorrow );
                
            case 'this_week':
                $week_start = date( 'Y-m-d', strtotime( 'this week monday', strtotime( $now ) ) );
                $week_end = date( 'Y-m-d', strtotime( 'this week sunday', strtotime( $now ) ) );
                return $wpdb->prepare( "DATE(date_start) BETWEEN %s AND %s", $week_start, $week_end );
                
            case 'next_week':
                $next_week_start = date( 'Y-m-d', strtotime( 'next week monday', strtotime( $now ) ) );
                $next_week_end = date( 'Y-m-d', strtotime( 'next week sunday', strtotime( $now ) ) );
                return $wpdb->prepare( "DATE(date_start) BETWEEN %s AND %s", $next_week_start, $next_week_end );
                
            case 'this_month':
                $month_start = date( 'Y-m-01', strtotime( $now ) );
                $month_end = date( 'Y-m-t', strtotime( $now ) );
                return $wpdb->prepare( "DATE(date_start) BETWEEN %s AND %s", $month_start, $month_end );
                
            default:
                return '';
        }
    }
    
    /**
     * Save a filter view
     */
    public static function save_view( string $page, string $name, array $filters ): bool {
        $views = get_option( 'ltlb_saved_views_' . $page, [] );
        $view_id = sanitize_key( $name );
        
        $views[ $view_id ] = [
            'name' => sanitize_text_field( $name ),
            'filters' => $filters,
            'created_at' => current_time( 'mysql' )
        ];
        
        return update_option( 'ltlb_saved_views_' . $page, $views );
    }
    
    /**
     * Delete a saved view
     */
    public static function delete_view( string $page, string $view_id ): bool {
        $views = get_option( 'ltlb_saved_views_' . $page, [] );
        
        if ( isset( $views[ $view_id ] ) ) {
            unset( $views[ $view_id ] );
            return update_option( 'ltlb_saved_views_' . $page, $views );
        }
        
        return false;
    }
    
    /**
     * Enqueue filter scripts and styles
     */
    private static function enqueue_scripts(): void {
        ?>
        <style>
            .ltlb-filter-bar {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .ltlb-filters-form {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                align-items: flex-end;
            }
            .ltlb-filter-fields {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                flex: 1;
            }
            .ltlb-filter-field {
                display: flex;
                flex-direction: column;
                min-width: 150px;
            }
            .ltlb-filter-field label {
                font-weight: 600;
                margin-bottom: 4px;
                font-size: 12px;
                color: #555;
            }
            .ltlb-filter-select,
            .ltlb-filter-text,
            .ltlb-daterange-preset {
                padding: 6px 10px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 13px;
            }
            .ltlb-filter-actions {
                display: flex;
                gap: 8px;
                align-items: flex-end;
            }
            .ltlb-saved-views {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .ltlb-saved-views label {
                font-weight: 600;
                font-size: 13px;
            }
            .ltlb-view-select {
                min-width: 200px;
            }
            .ltlb-daterange-wrapper {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
        </style>
        <script>
        jQuery(document).ready(function($) {
            // Date range preset handler
            $('.ltlb-daterange-preset').on('change', function() {
                var preset = $(this).val();
                var $from = $(this).siblings('.ltlb-date-from');
                var $to = $(this).siblings('.ltlb-date-to');
                
                if (preset === 'custom') {
                    $from.show();
                    $to.show();
                } else {
                    $from.hide();
                    $to.hide();
                }
            });
            
            // Save view
            $('.ltlb-save-view-btn').on('click', function() {
                var name = prompt('<?php echo esc_js( __( 'Enter view name:', 'ltl-bookings' ) ); ?>');
                if (!name) return;
                
                var filters = {};
                $(this).closest('form').serializeArray().forEach(function(field) {
                    if (field.name !== 'page') {
                        filters[field.name] = field.value;
                    }
                });
                
                $.post(ajaxurl, {
                    action: 'ltlb_save_filter_view',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_filters' ); ?>',
                    page: $('[name="page"]').val(),
                    name: name,
                    filters: filters
                }, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js( __( 'View saved!', 'ltl-bookings' ) ); ?>');
                        location.reload();
                    }
                });
            });
            
            // Load view
            $('.ltlb-load-view-btn').on('click', function() {
                var viewId = $('.ltlb-view-select').val();
                if (!viewId) return;
                
                $.get(ajaxurl, {
                    action: 'ltlb_get_filter_view',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_filters' ); ?>',
                    page: $('.ltlb-view-select').data('page'),
                    view_id: viewId
                }, function(response) {
                    if (response.success && response.data) {
                        // Apply filters to form
                        $.each(response.data.filters, function(name, value) {
                            $('[name="' + name + '"]').val(value);
                        });
                        // Submit form
                        $('.ltlb-filters-form').submit();
                    }
                });
            });
            
            // Delete view
            $('.ltlb-delete-view-btn').on('click', function() {
                var viewId = $('.ltlb-view-select').val();
                if (!viewId) return;
                
                if (!confirm('<?php echo esc_js( __( 'Delete this view?', 'ltl-bookings' ) ); ?>')) return;
                
                $.post(ajaxurl, {
                    action: 'ltlb_delete_filter_view',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_filters' ); ?>',
                    page: $('.ltlb-view-select').data('page'),
                    view_id: viewId
                }, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js( __( 'View deleted!', 'ltl-bookings' ) ); ?>');
                        location.reload();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Register AJAX handlers
     */
    public static function register_ajax_handlers(): void {
        add_action( 'wp_ajax_ltlb_save_filter_view', [ __CLASS__, 'ajax_save_view' ] );
        add_action( 'wp_ajax_ltlb_get_filter_view', [ __CLASS__, 'ajax_get_view' ] );
        add_action( 'wp_ajax_ltlb_delete_filter_view', [ __CLASS__, 'ajax_delete_view' ] );
    }
    
    public static function ajax_save_view(): void {
        check_ajax_referer( 'ltlb_filters', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        
        $page = sanitize_key( $_POST['page'] ?? '' );
        $name = sanitize_text_field( $_POST['name'] ?? '' );
        $filters = $_POST['filters'] ?? [];
        
        if ( self::save_view( $page, $name, $filters ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    public static function ajax_get_view(): void {
        check_ajax_referer( 'ltlb_filters', 'nonce' );
        
        $page = sanitize_key( $_GET['page'] ?? '' );
        $view_id = sanitize_key( $_GET['view_id'] ?? '' );
        
        $views = get_option( 'ltlb_saved_views_' . $page, [] );
        
        if ( isset( $views[ $view_id ] ) ) {
            wp_send_json_success( $views[ $view_id ] );
        } else {
            wp_send_json_error();
        }
    }
    
    public static function ajax_delete_view(): void {
        check_ajax_referer( 'ltlb_filters', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        
        $page = sanitize_key( $_POST['page'] ?? '' );
        $view_id = sanitize_key( $_POST['view_id'] ?? '' );
        
        if ( self::delete_view( $page, $view_id ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
}

// Register AJAX handlers
LTLB_Admin_Filters::register_ajax_handlers();
