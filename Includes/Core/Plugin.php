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

        add_submenu_page(
            'ltlb_dashboard',
            'Services',
            'Services',
            'manage_options',
            'ltlb_services',
            [ $this, 'render_services_page' ]
        );
    }

    public function render_dashboard_page(): void {
        echo '<div class="wrap"><h1>LazyBookings Dashboard</h1></div>';
    }

    public function render_services_page(): void {
        $page = new LTLB_Admin_ServicesPage();
        $page->render();
    }

    public function register_rest_routes(): void {
        // Phase 1: REST-Routen registrieren
    }
}