<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Plugin {

    public function run(): void {
        add_action('init', [ $this, 'on_init' ]);
        add_action('admin_menu', [ $this, 'register_admin_menu' ]);
        add_action('admin_notices', [ 'LTLB_Notices', 'render' ]);
        add_action('wp_head', [ $this, 'print_design_css_frontend' ]);
        add_action('admin_head', [ $this, 'print_design_css_admin' ]);
        add_action('rest_api_init', [ $this, 'register_rest_routes' ]);
        
        // Load required classes
        $this->load_classes();

        // Initialize shortcodes
        if ( class_exists('LTLB_Shortcodes') ) {
            LTLB_Shortcodes::init();
        }
    }

    private function load_classes(): void {

        // Utilities
        require_once LTLB_PATH . 'Includes/Util/Sanitizer.php';
        require_once LTLB_PATH . 'Includes/Util/Time.php';
        require_once LTLB_PATH . 'Includes/Util/Notices.php';

        // Engines
        require_once LTLB_PATH . 'Includes/Engine/EngineFactory.php';

        // Repositories
        require_once LTLB_PATH . 'Includes/Repository/ServiceRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/CustomerRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/AppointmentRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/ResourceRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/ServiceResourcesRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/AppointmentResourcesRepository.php';

        // Admin pages
        require_once LTLB_PATH . 'admin/Pages/DashboardPage.php';
        require_once LTLB_PATH . 'admin/Pages/ServicesPage.php';
        require_once LTLB_PATH . 'admin/Pages/CustomersPage.php';
        require_once LTLB_PATH . 'admin/Pages/AppointmentsPage.php';
        require_once LTLB_PATH . 'admin/Pages/SettingsPage.php';
        require_once LTLB_PATH . 'admin/Pages/DesignPage.php';

        // Public components
        require_once LTLB_PATH . 'public/Shortcodes.php';
    }

    public function on_init(): void {
        // Phase 1: Shortcodes/CPT/Assets registrieren
    }

    public function register_admin_menu(): void {
        add_menu_page(
            'LazyBookings',
            'LazyBookings',
            'manage_options',
            'ltlb_dashboard',
            [ $this, 'render_dashboard_page' ]
        );

        // Services
        add_submenu_page(
            'ltlb_dashboard',
            'Services',
            'Services',
            'manage_options',
            'ltlb_services',
            [ $this, 'render_services_page' ]
        );

        // Customers
        add_submenu_page(
            'ltlb_dashboard',
            'Customers',
            'Customers',
            'manage_options',
            'ltlb_customers',
            [ $this, 'render_customers_page' ]
        );

        // Appointments
        add_submenu_page(
            'ltlb_dashboard',
            'Appointments',
            'Appointments',
            'manage_options',
            'ltlb_appointments',
            [ $this, 'render_appointments_page' ]
        );

        // Settings
        add_submenu_page(
            'ltlb_dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'ltlb_settings',
            [ $this, 'render_settings_page' ]
        );

        // Design
        add_submenu_page(
            'ltlb_dashboard',
            'Design',
            'Design',
            'manage_options',
            'ltlb_design',
            [ $this, 'render_design_page' ]
        );
    }

    public function render_dashboard_page(): void {
        // Simple dashboard output
        if ( ! class_exists('LTLB_Admin_DashboardPage') ) {
            echo '<div class="wrap"><h1>LazyBookings Dashboard</h1></div>';
            return;
        }

        $page = new LTLB_Admin_DashboardPage();
        $page->render();
    }

    public function render_services_page(): void {
        $page = new LTLB_Admin_ServicesPage();
        $page->render();
    }

    public function render_customers_page(): void {
        if ( class_exists('LTLB_Admin_CustomersPage') ) {
            $page = new LTLB_Admin_CustomersPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>Customers</h1></div>';
    }

    public function render_appointments_page(): void {
        if ( class_exists('LTLB_Admin_AppointmentsPage') ) {
            $page = new LTLB_Admin_AppointmentsPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>Appointments</h1></div>';
    }

    public function render_settings_page(): void {
        if ( class_exists('LTLB_Admin_SettingsPage') ) {
            $page = new LTLB_Admin_SettingsPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>Settings</h1></div>';
    }

    public function render_design_page(): void {
        if ( class_exists('LTLB_Admin_DesignPage') ) {
            $page = new LTLB_Admin_DesignPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>Design</h1></div>';
    }

    public function register_rest_routes(): void {
        register_rest_route('ltlb/v1', '/time-slots', [
            'methods' => 'GET',
            'callback' => function( WP_REST_Request $request ) {
                $service_id = intval( $request->get_param('service_id') );
                $date = sanitize_text_field( $request->get_param('date') );
                $step = intval( $request->get_param('slot_step') );
                if ( empty($service_id) || empty($date) ) {
                    return new WP_REST_Response( [ 'error' => 'service_id and date required' ], 400 );
                }
                $engine = EngineFactory::get_engine();
                $data = $engine->get_time_slots( $service_id, $date, [ 'step' => $step ] );
                return new WP_REST_Response( $data, 200 );
            },
            'permission_callback' => function() { return true; }
        ]);

        register_rest_route('ltlb/v1', '/slot-resources', [
            'methods' => 'GET',
            'callback' => function( WP_REST_Request $request ) {
                $service_id = intval( $request->get_param('service_id') );
                $start = sanitize_text_field( $request->get_param('start') );
                if ( empty($service_id) || empty($start) ) {
                    return new WP_REST_Response( [ 'error' => 'Missing required parameters.' ], 400 );
                }
                // Only ServiceEngine supports slot-level resources currently
                $engine = EngineFactory::get_engine();
                if ( $engine instanceof ServiceEngine ) {
                    // reuse existing logic from previous implementation
                    $service_repo = new LTLB_ServiceRepository();
                    $service = $service_repo->get_by_id( $service_id );
                    if ( ! $service ) return new WP_REST_Response( [ 'error' => 'Invalid service' ], 400 );
                    $duration = intval( $service['duration_min'] ?? 60 );
                    $start_dt = DateTime::createFromFormat('Y-m-d H:i:s', $start);
                    if ( ! $start_dt ) return new WP_REST_Response( [ 'error' => 'Invalid start' ], 400 );
                    $end_dt = clone $start_dt;
                    $end_dt->modify('+' . $duration . ' minutes');

                    $service_resources_repo = new LTLB_ServiceResourcesRepository();
                    $resource_repo = new LTLB_ResourceRepository();
                    $appt_res_repo = new LTLB_AppointmentResourcesRepository();

                    $allowed = $service_resources_repo->get_resources_for_service( $service_id );
                    if ( empty( $allowed ) ) {
                        $all = $resource_repo->get_all();
                        $allowed = array_map(function($r){ return intval($r['id']); }, $all );
                    }

                    $include_pending = get_option('ltlb_pending_blocks', 0) ? true : false;
                    $blocked = $appt_res_repo->get_blocked_resources( $start_dt->format('Y-m-d H:i:s'), $end_dt->format('Y-m-d H:i:s'), $include_pending );

                    $resources = [];
                    $free_count = 0;
                    foreach ( $allowed as $rid ) {
                        $r = $resource_repo->get_by_id( intval($rid) );
                        if ( ! $r ) continue;
                        $capacity = intval( $r['capacity'] ?? 1 );
                        $used = isset( $blocked[$rid] ) ? intval( $blocked[$rid] ) : 0;
                        $available = max(0, $capacity - $used);
                        if ( $available > 0 ) $free_count += 1;
                        $resources[] = [ 'id' => intval($r['id']), 'name' => $r['name'], 'capacity' => $capacity, 'used' => $used, 'available' => $available ];
                    }

                    return new WP_REST_Response( [ 'free_resources_count' => $free_count, 'resources' => $resources ], 200 );
                }
                return new WP_REST_Response( [ 'error' => 'Slot resources not supported for current template mode' ], 400 );
            },
            'permission_callback' => function() { return true; }
        ]);

        register_rest_route('ltlb/v1', '/hotel-availability', [
            'methods' => 'GET',
            'callback' => function( WP_REST_Request $request ) {
                $service_id = intval( $request->get_param('service_id') );
                $checkin = sanitize_text_field( $request->get_param('checkin') );
                $checkout = sanitize_text_field( $request->get_param('checkout') );
                $guests = intval( $request->get_param('guests') ?? 1 );

                if ( empty($service_id) || empty($checkin) || empty($checkout) ) {
                    return new WP_REST_Response( [ 'error' => 'Missing required parameters: service_id, checkin, checkout' ], 400 );
                }

                $engine = EngineFactory::get_engine();
                if ( ! ( $engine instanceof HotelEngine ) ) {
                    return new WP_REST_Response( [ 'error' => 'Hotel availability not available in current mode' ], 400 );
                }

                $result = $engine->get_hotel_availability( $service_id, $checkin, $checkout, $guests );
                
                if ( isset($result['error']) ) {
                    return new WP_REST_Response( [ 'error' => $result['error'] ], 400 );
                }

                return new WP_REST_Response( $result, 200 );
            },
            'permission_callback' => function() { return true; }
        ]);
    }

    public function print_design_css_frontend(): void {
        if ( ! function_exists('has_shortcode') ) return;
        global $post;
        if ( empty( $post ) ) return;
        if ( ! has_shortcode( $post->post_content, 'lazy_book' ) ) return;

        $design = get_option( 'lazy_design', [] );
        if ( ! is_array( $design ) ) $design = [];

        $bg = $design['background'] ?? '#ffffff';
        $primary = $design['primary'] ?? '#2b7cff';
        $text = $design['text'] ?? '#222222';
        $accent = $design['accent'] ?? '#ffcc00';

        echo "<style id=\"ltlb-design-vars\">:root{--lazy-bg:${bg};--lazy-primary:${primary};--lazy-text:${text};--lazy-accent:${accent};}</style>";
    }

    public function print_design_css_admin(): void {
        if ( ! is_admin() ) return;
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        if ( ! $page || strpos( $page, 'ltlb_' ) !== 0 ) return;

        $design = get_option( 'lazy_design', [] );
        if ( ! is_array( $design ) ) $design = [];

        $bg = $design['background'] ?? '#ffffff';
        $primary = $design['primary'] ?? '#2b7cff';
        $text = $design['text'] ?? '#222222';
        $accent = $design['accent'] ?? '#ffcc00';

        echo "<style id=\"ltlb-design-vars-admin\">:root{--lazy-bg:${bg};--lazy-primary:${primary};--lazy-text:${text};--lazy-accent:${accent};}</style>";
    }
}