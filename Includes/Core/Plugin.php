<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Plugin {

    public function run(): void {
        add_action('init', [ $this, 'on_init' ]);
        // Ensure DB migrations run automatically when plugin version changes
        add_action('plugins_loaded', [ 'LTLB_DB_Migrator', 'maybe_migrate' ]);
        add_action('admin_menu', [ $this, 'register_admin_menu' ]);
        add_action('admin_notices', [ 'LTLB_Notices', 'render' ]);
        add_action('wp_head', [ $this, 'print_design_css_frontend' ]);
        add_action('admin_head', [ $this, 'print_design_css_admin' ]);
        add_action('rest_api_init', [ $this, 'register_rest_routes' ]);
        
        // Load required classes
        $this->load_classes();
    }

    private function load_classes(): void {

        // Utilities
        require_once LTLB_PATH . 'Includes/Util/Sanitizer.php';
        require_once LTLB_PATH . 'Includes/Util/Time.php';
        require_once LTLB_PATH . 'Includes/Util/Notices.php';
        require_once LTLB_PATH . 'Includes/Util/LockManager.php';
        require_once LTLB_PATH . 'Includes/Util/Logger.php';
        require_once LTLB_PATH . 'Includes/Util/Mailer.php';
        require_once LTLB_PATH . 'Includes/Util/Validator.php';
        require_once LTLB_PATH . 'Includes/Util/Availability.php';

        // Repositories
        require_once LTLB_PATH . 'Includes/Repository/ServiceRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/CustomerRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/AppointmentRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/ResourceRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/AppointmentResourcesRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/ServiceResourcesRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/StaffHoursRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/StaffExceptionsRepository.php';

        // Admin pages
        require_once LTLB_PATH . 'admin/Pages/DashboardPage.php';
        require_once LTLB_PATH . 'admin/Pages/ServicesPage.php';
        require_once LTLB_PATH . 'admin/Pages/CustomersPage.php';
        require_once LTLB_PATH . 'admin/Pages/AppointmentsPage.php';
        require_once LTLB_PATH . 'admin/Pages/SettingsPage.php';
        require_once LTLB_PATH . 'admin/Pages/DesignPage.php';
        require_once LTLB_PATH . 'admin/Pages/StaffPage.php';
        require_once LTLB_PATH . 'admin/Pages/ResourcesPage.php';
        require_once LTLB_PATH . 'admin/Pages/DiagnosticsPage.php';
        require_once LTLB_PATH . 'admin/Pages/PrivacyPage.php';
        // Admin: profile helpers
        require_once LTLB_PATH . 'Includes/Admin/StaffProfile.php';
        // Public: Shortcodes
        require_once LTLB_PATH . 'public/Shortcodes.php';
    }

    public function on_init(): void {
        // Initialize Shortcodes
        LTLB_Shortcodes::init();
        
        // instantiate profile handler
        if ( is_admin() ) {
            if ( class_exists('LTLB_Admin_StaffProfile') ) {
                new LTLB_Admin_StaffProfile();
            }
        }
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

        // Staff
        add_submenu_page(
            'ltlb_dashboard',
            'Staff',
            'Staff',
            'manage_options',
            'ltlb_staff',
            [ $this, 'render_staff_page' ]
        );

        // Resources
        add_submenu_page(
            'ltlb_dashboard',
            'Resources',
            'Resources',
            'manage_options',
            'ltlb_resources',
            [ $this, 'render_resources_page' ]
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

        // Diagnostics
        add_submenu_page(
            'ltlb_dashboard',
            'Diagnostics',
            'Diagnostics',
            'manage_options',
            'ltlb_diagnostics',
            [ $this, 'render_diagnostics_page' ]
        );

        // Privacy
        add_submenu_page(
            'ltlb_dashboard',
            'Privacy & GDPR',
            'Privacy',
            'manage_options',
            'ltlb_privacy',
            [ $this, 'render_privacy_page' ]
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

    public function render_staff_page(): void {
        if ( class_exists('LTLB_Admin_StaffPage') ) {
            $page = new LTLB_Admin_StaffPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>Staff</h1></div>';
    }

    public function render_resources_page(): void {
        if ( class_exists('LTLB_Admin_ResourcesPage') ) {
            $page = new LTLB_Admin_ResourcesPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>Resources</h1></div>';
    }

    public function render_diagnostics_page(): void {
        if ( class_exists('LTLB_DiagnosticsPage') ) {
            $page = new LTLB_DiagnosticsPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>Diagnostics</h1></div>';
    }

    public function render_privacy_page(): void {
        if ( class_exists('LTLB_PrivacyPage') ) {
            $page = new LTLB_PrivacyPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>Privacy</h1></div>';
    }

    public function register_rest_routes(): void {
        register_rest_route('ltlb/v1', '/availability', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_availability' ],
            'permission_callback' => function() { return true; }
        ]);
    }

    public function rest_availability( WP_REST_Request $request ) {
        $service_id = intval( $request->get_param('service_id') );
        $date = sanitize_text_field( $request->get_param('date') );
        if ( empty( $service_id ) || empty( $date ) ) {
            return new WP_REST_Response( [ 'error' => 'service_id and date required' ], 400 );
        }

        // instantiate availability and compute
        $avail = new Availability();

        $want_slots = $request->get_param('slots');
        $slot_step = intval( $request->get_param('slot_step') );
        if ( $want_slots ) {
            $step = $slot_step > 0 ? $slot_step : 15;
            $data = $avail->compute_time_slots( $service_id, $date, $step );
            return new WP_REST_Response( $data, 200 );
        }

        $data = $avail->compute_availability( $service_id, $date );
        return new WP_REST_Response( $data, 200 );
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

        echo "<style id=\"ltlb-design-vars\">:root{--lazy-bg:{$bg};--lazy-primary:{$primary};--lazy-text:{$text};--lazy-accent:{$accent};}</style>";
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

        echo "<style id=\"ltlb-design-vars-admin\">:root{--lazy-bg:{$bg};--lazy-primary:{$primary};--lazy-text:{$text};--lazy-accent:{$accent};}</style>";
    }
}