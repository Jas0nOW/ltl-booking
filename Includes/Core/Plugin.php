<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Plugin {

    public function run(): void {
        add_action('init', [ $this, 'on_init' ]);
        add_action('admin_menu', [ $this, 'register_admin_menu' ]);
        add_action('rest_api_init', [ $this, 'register_rest_routes' ]);
        
        // Load required classes
        $this->load_classes();
    }

    private function load_classes(): void {

        // Utilities
        require_once LTLB_PATH . 'includes/Util/Sanitizer.php';
        require_once LTLB_PATH . 'includes/Util/Time.php';

        // Repositories
        require_once LTLB_PATH . 'includes/Repository/ServiceRepository.php';
        require_once LTLB_PATH . 'includes/Repository/CustomerRepository.php';
        require_once LTLB_PATH . 'includes/Repository/AppointmentRepository.php';

        // Admin pages
        require_once LTLB_PATH . 'admin/Pages/DashboardPage.php';
        require_once LTLB_PATH . 'admin/Pages/ServicesPage.php';
        require_once LTLB_PATH . 'admin/Pages/CustomersPage.php';
        require_once LTLB_PATH . 'admin/Pages/AppointmentsPage.php';
        require_once LTLB_PATH . 'admin/Pages/SettingsPage.php';
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

        // Design (placeholder page)
        add_submenu_page(
            'ltlb_dashboard',
            'Design',
            'Design',
            'manage_options',
            'ltlb_design',
            [ $this, 'render_settings_page' ]
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

    public function register_rest_routes(): void {
        // Phase 1: REST-Routen registrieren
    }
}