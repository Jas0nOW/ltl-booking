<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Plugin {
    private const OPTION_CALENDAR_STATUS_COLORS = 'ltlb_calendar_status_colors';

    public function run(): void {
        add_action('init', [ $this, 'on_init' ]);
        // Ensure DB migrations run automatically when plugin version changes
        add_action('plugins_loaded', [ 'LTLB_DB_Migrator', 'maybe_migrate' ]);
        add_action('admin_menu', [ $this, 'register_admin_menu' ]);
        add_action('admin_notices', [ 'LTLB_Notices', 'render' ]);
        add_action('wp_head', [ $this, 'print_design_css_frontend' ]);
        add_action('admin_head', [ $this, 'print_design_css_admin' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);
        add_action('rest_api_init', [ $this, 'register_rest_routes' ]);
		add_action( 'template_redirect', [ $this, 'handle_payment_return' ] );
		add_action( 'ltlb_retention_cleanup', [ 'LTLB_Retention', 'run' ] );
        add_action( 'ltlb_automation_runner', [ 'LTLB_Automations', 'run_due_rules' ] );
        add_action('admin_init', [ $this, 'handle_csv_export' ]);
        add_action('wp_ajax_ltlb_test_ai_connection', [ $this, 'handle_test_ai_connection' ]);
        $this->ensure_retention_cron();
		$this->ensure_automation_cron();

		// Per-user admin language setting
		add_action( 'admin_post_ltlb_set_admin_lang', [ $this, 'handle_set_admin_lang' ] );
        
        // Load required classes
        $this->load_classes();

        if ( class_exists( 'LTLB_Mailer' ) && method_exists( 'LTLB_Mailer', 'init' ) ) {
            LTLB_Mailer::init();
        }

		if ( class_exists('LTLB_I18n') ) {
			LTLB_I18n::init();
		}
    }

    private function ensure_retention_cron(): void {
        if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
            return;
        }
        if ( ! wp_next_scheduled( 'ltlb_retention_cleanup' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ltlb_retention_cleanup' );
        }
    }

    private function ensure_automation_cron(): void {
        if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
            return;
        }
        if ( ! wp_next_scheduled( 'ltlb_automation_runner' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'ltlb_automation_runner' );
        }
    }

    private function load_classes(): void {

        // Utilities
        require_once LTLB_PATH . 'Includes/Util/Sanitizer.php';
        require_once LTLB_PATH . 'Includes/Util/Time.php';
        require_once LTLB_PATH . 'Includes/Util/Notices.php';
        require_once LTLB_PATH . 'Includes/Util/LockManager.php';
        require_once LTLB_PATH . 'Includes/Util/Logger.php';
        require_once LTLB_PATH . 'Includes/Util/Mailer.php';
        require_once LTLB_PATH . 'Includes/Util/BookingService.php';
		require_once LTLB_PATH . 'Includes/Util/I18n.php';
        require_once LTLB_PATH . 'Includes/Util/Retention.php';
        require_once LTLB_PATH . 'Includes/Util/Analytics.php';
        require_once LTLB_PATH . 'Includes/Util/Finance.php';
        require_once LTLB_PATH . 'Includes/Util/EmailNotifications.php';
        require_once LTLB_PATH . 'Includes/Util/BookingStatus.php';
        require_once LTLB_PATH . 'Includes/Util/ICS_Export.php';
        require_once LTLB_PATH . 'Includes/Util/RoleManager.php';
        require_once LTLB_PATH . 'Includes/Util/AIOutbox.php';
		require_once LTLB_PATH . 'Includes/Util/Automations.php';

        // Domain
        require_once LTLB_PATH . 'Includes/Domain/Appointment.php';

        // Repositories
        require_once LTLB_PATH . 'Includes/Repository/ServiceRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/CustomerRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/AppointmentRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/ResourceRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/AppointmentResourcesRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/ServiceResourcesRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/StaffHoursRepository.php';
        require_once LTLB_PATH . 'Includes/Repository/StaffExceptionsRepository.php';

		// Availability depends on repositories.
		require_once LTLB_PATH . 'Includes/Util/Availability.php';

        // Admin pages
        require_once LTLB_PATH . 'admin/Components/AdminHeader.php';
        require_once LTLB_PATH . 'admin/Components/Component.php';
        require_once LTLB_PATH . 'admin/Pages/AppointmentsDashboardPage.php';
        require_once LTLB_PATH . 'admin/Pages/HotelDashboardPage.php';
        require_once LTLB_PATH . 'admin/Pages/ServicesPage.php';
        require_once LTLB_PATH . 'admin/Pages/CustomersPage.php';
        require_once LTLB_PATH . 'admin/Pages/AppointmentsPage.php';
        require_once LTLB_PATH . 'admin/Pages/CalendarPage.php';
        require_once LTLB_PATH . 'admin/Pages/SettingsPage.php';
        require_once LTLB_PATH . 'admin/Pages/DesignPage.php';
        require_once LTLB_PATH . 'admin/Pages/AIPage.php';
        require_once LTLB_PATH . 'admin/Pages/OutboxPage.php';
        require_once LTLB_PATH . 'admin/Pages/RoomAssistantPage.php';
        require_once LTLB_PATH . 'admin/Pages/AutomationsPage.php';
        require_once LTLB_PATH . 'admin/Pages/ReplyTemplatesPage.php';
        require_once LTLB_PATH . 'admin/Pages/StaffPage.php';
        require_once LTLB_PATH . 'admin/Pages/ResourcesPage.php';
        require_once LTLB_PATH . 'admin/Pages/DiagnosticsPage.php';
        require_once LTLB_PATH . 'admin/Pages/PrivacyPage.php';
        
        // Booking Engine (Hotel mode)
        require_once LTLB_PATH . 'Includes/Engine/BookingEngineInterface.php';
        require_once LTLB_PATH . 'Includes/Engine/HotelEngine.php';
        require_once LTLB_PATH . 'Includes/Engine/PaymentEngine.php';
        require_once LTLB_PATH . 'Includes/Engine/AIProviderInterface.php';
        require_once LTLB_PATH . 'Includes/Engine/AIGemini.php';
        require_once LTLB_PATH . 'Includes/Engine/AIFactory.php';
        // Admin: profile helpers
        require_once LTLB_PATH . 'Includes/Admin/StaffProfile.php';
        // Public: Shortcodes
        require_once LTLB_PATH . 'public/Shortcodes.php';
        
        // WP-CLI commands
        if ( defined('WP_CLI') && WP_CLI ) {
            require_once LTLB_PATH . 'Includes/CLI/DoctorCommand.php';
            require_once LTLB_PATH . 'Includes/CLI/MigrateCommand.php';
            require_once LTLB_PATH . 'Includes/CLI/SeedCommand.php';
        }
    }

    public function on_init(): void {
        // Initialize Shortcodes
        LTLB_Shortcodes::init();

		// Ensure roles/caps exist even on existing installs (idempotent).
		if ( class_exists( 'LTLB_Role_Manager' ) ) {
			LTLB_Role_Manager::register_roles();
			LTLB_Role_Manager::register_capabilities();
		}
        
        // ICS Feed Handler
        $this->handle_ics_feed();
        
        // Register WP-CLI commands
        if ( defined('WP_CLI') && WP_CLI ) {
            WP_CLI::add_command( 'ltlb doctor', 'LTLB_CLI_DoctorCommand' );
            WP_CLI::add_command( 'ltlb migrate', 'LTLB_CLI_MigrateCommand' );
            WP_CLI::add_command( 'ltlb seed', 'LTLB_CLI_SeedCommand' );
        }
        
        // instantiate profile handler
        if ( is_admin() ) {
            if ( class_exists('LTLB_Admin_StaffProfile') ) {
                new LTLB_Admin_StaffProfile();
            }

            // Handle template mode switch
            if ( isset( $_GET['ltlb_template_mode'] ) ) {
                $new_mode = sanitize_text_field( $_GET['ltlb_template_mode'] );
                if ( in_array( $new_mode, [ 'service', 'hotel' ] ) ) {
                    $settings = get_option( 'lazy_settings', [] );
                    if ( ! is_array( $settings ) ) {
                        $settings = [];
                    }
                    $settings['template_mode'] = $new_mode;
                    update_option( 'lazy_settings', $settings );

                    // Redirect to remove the query arg
                    wp_safe_redirect( remove_query_arg( 'ltlb_template_mode' ) );
                    exit;
                }
            }
        }
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __( 'LazyBookings', 'ltl-bookings' ),
            __( 'LazyBookings', 'ltl-bookings' ),
            'view_ai_reports',
            'ltlb_dashboard',
            [ $this, 'render_dashboard_page' ],
            'dashicons-calendar-alt',
            26
        );

		$settings = get_option( 'lazy_settings', [] );
		$template_mode = is_array( $settings ) && isset( $settings['template_mode'] ) ? $settings['template_mode'] : 'service';
		$is_hotel_frontend = $template_mode === 'hotel';


        // Dashboard (explicit label + first submenu item)
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Dashboard', 'ltl-bookings' ),
            __( 'Dashboard', 'ltl-bookings' ),
            'view_ai_reports',
            'ltlb_dashboard',
            [ $this, 'render_dashboard_page' ]
        );

        // Appointments / Bookings
		$appointments_label = $template_mode === 'hotel' ? __( 'Bookings', 'ltl-bookings' ) : __( 'Appointments', 'ltl-bookings' );
        add_submenu_page(
            'ltlb_dashboard',
			$appointments_label,
			$appointments_label,
            'manage_options',
            'ltlb_appointments',
            [ $this, 'render_appointments_page' ]
        );

        // Calendar
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Calendar', 'ltl-bookings' ),
            __( 'Calendar', 'ltl-bookings' ),
            'manage_options',
            'ltlb_calendar',
            [ $this, 'render_calendar_page' ]
        );

        // Customers / Guests
        $customers_label = $template_mode === 'hotel' ? __( 'Guests', 'ltl-bookings' ) : __( 'Customers', 'ltl-bookings' );
        add_submenu_page(
            'ltlb_dashboard',
            $customers_label,
            $customers_label,
            'manage_options',
            'ltlb_customers',
            [ $this, 'render_customers_page' ]
        );


        // Services (context-aware label)
        $services_label = $template_mode === 'hotel' ? __( 'Room Types', 'ltl-bookings' ) : __( 'Services', 'ltl-bookings' );
        add_submenu_page(
            'ltlb_dashboard',
            $services_label,
            $services_label,
            'manage_options',
            'ltlb_services',
            [ $this, 'render_services_page' ]
        );

		if ($template_mode === 'service') {
			// Staff
			add_submenu_page(
				'ltlb_dashboard',
				__( 'Staff', 'ltl-bookings' ),
				__( 'Staff', 'ltl-bookings' ),
				'manage_options',
				'ltlb_staff',
				[ $this, 'render_staff_page' ]
			);
		}

        // Resources (context-aware label)
        $resources_label = $template_mode === 'hotel' ? __( 'Rooms', 'ltl-bookings' ) : __( 'Resources', 'ltl-bookings' );
        add_submenu_page(
            'ltlb_dashboard',
            $resources_label,
            $resources_label,
            'manage_options',
            'ltlb_resources',
            [ $this, 'render_resources_page' ]
        );

        // Settings
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Settings', 'ltl-bookings' ),
            __( 'Settings', 'ltl-bookings' ),
            'manage_options',
            'ltlb_settings',
            [ $this, 'render_settings_page' ]
        );

        // Design
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Design', 'ltl-bookings' ),
            __( 'Design', 'ltl-bookings' ),
            'manage_options',
            'ltlb_design',
            [ $this, 'render_design_page' ]
        );

		// AI & Automations
		add_submenu_page(
			'ltlb_dashboard',
			__( 'AI & Automations', 'ltl-bookings' ),
			__( 'AI', 'ltl-bookings' ),
			'manage_ai_settings',
			'ltlb_ai',
			[ $this, 'render_ai_page' ]
		);

        // Outbox (Draft Center)
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Outbox', 'ltl-bookings' ),
            __( 'Outbox', 'ltl-bookings' ),
            'approve_ai_drafts',
            'ltlb_outbox',
            [ $this, 'render_outbox_page' ]
        );

        // Hotel: Smart Room Assistant
        if ( $template_mode === 'hotel' ) {
            add_submenu_page(
                'ltlb_dashboard',
                __( 'Smart Room Assistant', 'ltl-bookings' ),
                __( 'Room Assistant', 'ltl-bookings' ),
                'approve_ai_drafts',
                'ltlb_room_assistant',
                [ $this, 'render_room_assistant_page' ]
            );
        }

        // Automation rules
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Automations', 'ltl-bookings' ),
            __( 'Automations', 'ltl-bookings' ),
            'manage_ai_settings',
            'ltlb_automations',
            [ $this, 'render_automations_page' ]
        );

        // Reply templates
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Reply Templates', 'ltl-bookings' ),
            __( 'Templates', 'ltl-bookings' ),
            'manage_ai_settings',
            'ltlb_reply_templates',
            [ $this, 'render_reply_templates_page' ]
        );

		// Diagnostics
		add_submenu_page(
			'ltlb_dashboard',
			__( 'Diagnostics', 'ltl-bookings' ),
			__( 'Diagnostics', 'ltl-bookings' ),
			'manage_options',
			'ltlb_diagnostics',
			[ $this, 'render_diagnostics_page' ]
		);

        // Privacy
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Privacy & GDPR', 'ltl-bookings' ),
            __( 'Privacy', 'ltl-bookings' ),
            'manage_options',
            'ltlb_privacy',
            [ $this, 'render_privacy_page' ]
        );
    }

    public function render_dashboard_page(): void {
        $settings = get_option( 'lazy_settings', [] );
        $template_mode = is_array( $settings ) && isset( $settings['template_mode'] ) ? $settings['template_mode'] : 'service';

        if ( $template_mode === 'hotel' ) {
            if ( class_exists('LTLB_Admin_HotelDashboardPage') ) {
                $page = new LTLB_Admin_HotelDashboardPage();
                $page->render();
                return;
            }
        } 
        
        if ( class_exists('LTLB_Admin_AppointmentsDashboardPage') ) {
            $page = new LTLB_Admin_AppointmentsDashboardPage();
            $page->render();
            return;
        }


        echo '<div class="wrap"><h1>' . esc_html__( 'LazyBookings Dashboard', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_services_page(): void {
        $page = new LTLB_Admin_ServicesPage();
        $page->render();
    }

    public function render_ai_page(): void {
        if ( class_exists('LTLB_Admin_AIPage') ) {
            $page = new LTLB_Admin_AIPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('AI & Automations', 'ltl-bookings') . '</h1></div>';
    }

    public function render_outbox_page(): void {
        if ( class_exists('LTLB_Admin_OutboxPage') ) {
            $page = new LTLB_Admin_OutboxPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Outbox', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_room_assistant_page(): void {
        if ( class_exists( 'LTLB_Admin_RoomAssistantPage' ) ) {
            $page = new LTLB_Admin_RoomAssistantPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Smart Room Assistant', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_automations_page(): void {
        if ( class_exists('LTLB_Admin_AutomationsPage') ) {
            $page = new LTLB_Admin_AutomationsPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Automations', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_reply_templates_page(): void {
        if ( class_exists('LTLB_Admin_ReplyTemplatesPage') ) {
            $page = new LTLB_Admin_ReplyTemplatesPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Reply Templates', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_customers_page(): void {
        if ( class_exists('LTLB_Admin_CustomersPage') ) {
            $page = new LTLB_Admin_CustomersPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Customers', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_appointments_page(): void {
        if ( class_exists('LTLB_Admin_AppointmentsPage') ) {
            $page = new LTLB_Admin_AppointmentsPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Appointments', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_calendar_page(): void {
        if ( class_exists('LTLB_Admin_CalendarPage') ) {
            $page = new LTLB_Admin_CalendarPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Calendar', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_settings_page(): void {
        if ( class_exists('LTLB_Admin_SettingsPage') ) {
            $page = new LTLB_Admin_SettingsPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Settings', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_design_page(): void {
        if ( class_exists('LTLB_Admin_DesignPage') ) {
            $page = new LTLB_Admin_DesignPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Design', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_staff_page(): void {
        if ( class_exists('LTLB_Admin_StaffPage') ) {
            $page = new LTLB_Admin_StaffPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Staff', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_resources_page(): void {
        if ( class_exists('LTLB_Admin_ResourcesPage') ) {
            $page = new LTLB_Admin_ResourcesPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Resources', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_diagnostics_page(): void {
        if ( class_exists('LTLB_DiagnosticsPage') ) {
            $page = new LTLB_DiagnosticsPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Diagnostics', 'ltl-bookings' ) . '</h1></div>';
    }

    public function render_privacy_page(): void {
        if ( class_exists('LTLB_PrivacyPage') ) {
            $page = new LTLB_PrivacyPage();
            $page->render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Privacy', 'ltl-bookings' ) . '</h1></div>';
    }

    public function register_rest_routes(): void {
        register_rest_route('ltlb/v1', '/availability', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_availability' ],
            'permission_callback' => function() { return true; }
        ]);

        // Admin: calendar + appointment CRUD
        register_rest_route('ltlb/v1', '/admin/calendar/events', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_admin_calendar_events' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);

        // Admin: calendar UI settings (status colors)
        register_rest_route( 'ltlb/v1', '/admin/calendar/colors', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_admin_calendar_colors_get' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ] );
        register_rest_route( 'ltlb/v1', '/admin/calendar/colors', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_admin_calendar_colors_update' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ] );
        register_rest_route('ltlb/v1', '/admin/appointments/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_admin_appointment_get' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);
        register_rest_route('ltlb/v1', '/admin/appointments/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [ $this, 'rest_admin_appointment_delete' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);
        register_rest_route('ltlb/v1', '/admin/appointments/(?P<id>\d+)/move', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_admin_appointment_move' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);
        register_rest_route('ltlb/v1', '/admin/appointments/(?P<id>\\d+)/status', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_admin_appointment_status' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);
        register_rest_route('ltlb/v1', '/admin/customers/(?P<id>\\d+)', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_admin_customer_update' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);

        // Admin: hotel calendar helpers (occupancy + room assistant)
        register_rest_route('ltlb/v1', '/admin/calendar/occupancy', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_admin_calendar_occupancy' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);

		register_rest_route('ltlb/v1', '/admin/calendar/rooms', [
			'methods' => 'GET',
			'callback' => [ $this, 'rest_admin_calendar_rooms' ],
			'permission_callback' => [ $this, 'rest_admin_permission' ],
		]);
        register_rest_route('ltlb/v1', '/admin/appointments/(?P<id>\\d+)/room-suggestions', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_admin_appointment_room_suggestions' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);
        register_rest_route('ltlb/v1', '/admin/appointments/(?P<id>\\d+)/assign-room', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_admin_appointment_assign_room' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);
        register_rest_route('ltlb/v1', '/admin/appointments/(?P<id>\\d+)/propose-room', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_admin_appointment_propose_room' ],
            'permission_callback' => [ $this, 'rest_admin_permission' ],
        ]);

        // Payments: Stripe webhook for Checkout (no auth, verified via signature).
        register_rest_route( 'ltlb/v1', '/payments/stripe/webhook', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_stripe_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_payment_return(): void {
        if ( is_admin() ) {
            return;
        }
        if ( empty( $_GET['ltlb_payment_return'] ) ) {
            return;
        }

        $provider = isset( $_GET['provider'] ) ? sanitize_key( (string) wp_unslash( $_GET['provider'] ) ) : '';
        $appointment_id = isset( $_GET['appointment_id'] ) ? intval( $_GET['appointment_id'] ) : 0;
        $status = isset( $_GET['status'] ) ? sanitize_key( (string) wp_unslash( $_GET['status'] ) ) : '';
        $retry_url = function_exists( 'get_permalink' ) ? get_permalink() : home_url( '/' );

        // PayPal: capture on return (server-side). No webhook required for MVP.
        if ( $provider === 'paypal' ) {
            $headline = '';
            $message = '';
            if ( $status === 'cancel' ) {
                $headline = __( 'Payment cancelled', 'ltl-bookings' );
                $message = __( 'Your booking was created but payment was cancelled. You can try again.', 'ltl-bookings' );
            } elseif ( $status === 'success' ) {
                $token = '';
                if ( isset( $_GET['token'] ) ) {
                    $token = sanitize_text_field( (string) wp_unslash( $_GET['token'] ) );
                } elseif ( isset( $_GET['paypal_order_id'] ) ) {
                    $token = sanitize_text_field( (string) wp_unslash( $_GET['paypal_order_id'] ) );
                }

                if ( $token !== '' && class_exists( 'LTLB_PaymentEngine' ) ) {
                    $appt_repo = class_exists( 'LTLB_AppointmentRepository' ) ? new LTLB_AppointmentRepository() : null;

                    // Bind the PayPal order token to the correct appointment.
                    $target_id = $appointment_id;
                    $existing = $appt_repo ? $appt_repo->get_by_id( $target_id ) : null;
                    $existing_ref = is_array( $existing ) ? (string) ( $existing['payment_ref'] ?? '' ) : '';
                    $existing_method = is_array( $existing ) ? sanitize_key( (string) ( $existing['payment_method'] ?? '' ) ) : '';
                    if ( ! $existing || $existing_method !== 'paypal' || ( $existing_ref !== '' && $existing_ref !== $token ) ) {
                        global $wpdb;
                        $table = $wpdb->prefix . 'lazy_appointments';
                        $found_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE payment_method = 'paypal' AND payment_ref = %s ORDER BY id DESC LIMIT 1", $token ) );
                        if ( $found_id > 0 ) {
                            $target_id = $found_id;
                            $existing = $appt_repo ? $appt_repo->get_by_id( $target_id ) : null;
                            $existing_ref = is_array( $existing ) ? (string) ( $existing['payment_ref'] ?? '' ) : '';
                            $existing_method = is_array( $existing ) ? sanitize_key( (string) ( $existing['payment_method'] ?? '' ) ) : '';
                        }
                    }

                    if ( ! $existing || $existing_method !== 'paypal' || $existing_ref !== $token ) {
                        $headline = __( 'Payment processing', 'ltl-bookings' );
                        $message = __( 'We received your return from PayPal, but could not match the payment to your booking. Please contact us if you were charged.', 'ltl-bookings' );
                    } elseif ( (string) ( $existing['payment_status'] ?? '' ) === 'paid' ) {
                        $headline = __( 'Payment received', 'ltl-bookings' );
                        $message = __( 'Thank you! Your payment has been confirmed and your booking is confirmed.', 'ltl-bookings' );
                    } else {
                        $engine = LTLB_PaymentEngine::instance();
                        $res = method_exists( $engine, 'capture_paypal_order' ) ? $engine->capture_paypal_order( $token ) : [ 'success' => false ];
                        if ( is_array( $res ) && ! empty( $res['success'] ) ) {
                            global $wpdb;
                            $table = $wpdb->prefix . 'lazy_appointments';
                            $ref = (string) $token;
                            $wpdb->update(
                                $table,
                                [
                                    'status' => 'confirmed',
                                    'payment_status' => 'paid',
                                    'payment_method' => 'paypal',
                                    'payment_ref' => $ref,
                                    'paid_at' => current_time( 'mysql' ),
                                    'updated_at' => current_time( 'mysql' ),
                                ],
                                [ 'id' => $target_id ],
                                [ '%s', '%s', '%s', '%s', '%s', '%s' ],
                                [ '%d' ]
                            );

                            if ( class_exists( 'LTLB_EmailNotifications' ) ) {
                                LTLB_EmailNotifications::send_customer_booking_confirmation( intval( $target_id ) );
                                LTLB_EmailNotifications::send_admin_booking_notification( intval( $target_id ) );
                            }

                            $headline = __( 'Payment received', 'ltl-bookings' );
                            $message = __( 'Thank you! Your payment has been confirmed and your booking is confirmed.', 'ltl-bookings' );
                        } else {
                            $headline = __( 'Payment processing', 'ltl-bookings' );
                            $message = __( 'We received your return from PayPal, but could not confirm the payment. Your booking is still pending. Please try again or contact us if you were charged.', 'ltl-bookings' );
                        }
                    }
                } else {
                    $headline = __( 'Payment status', 'ltl-bookings' );
                    $message = __( 'Your payment status is being processed.', 'ltl-bookings' );
                }
            } else {
                $headline = __( 'Payment status', 'ltl-bookings' );
                $message = __( 'Your payment status is being processed.', 'ltl-bookings' );
            }

            status_header( 200 );
            nocache_headers();
            header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
            echo '<!doctype html><html><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
            echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
            echo '<title>' . esc_html( get_bloginfo( 'name' ) ) . '</title>';
            echo '</head><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;">';
            echo '<h1>' . esc_html( $headline ) . '</h1>';
            echo '<p>' . esc_html( $message ) . '</p>';
            if ( $status === 'cancel' ) {
                echo '<p>' . esc_html__( 'You may close this page and try booking again.', 'ltl-bookings' ) . '</p>';
                echo '<p><a href="' . esc_url( $retry_url ) . '">' . esc_html__( 'Return to booking page', 'ltl-bookings' ) . '</a></p>';
            }
            echo '</body></html>';
            exit;
        }

        $headline = '';
        $message = '';
        if ( $status === 'success' ) {
            $headline = __( 'Payment received', 'ltl-bookings' );
            $message = __( 'Thank you! Your payment return was received. We will confirm your booking after the payment is verified.', 'ltl-bookings' );
        } elseif ( $status === 'cancel' ) {
            $headline = __( 'Payment cancelled', 'ltl-bookings' );
            $message = __( 'Your booking was created but payment was cancelled. You can try again.', 'ltl-bookings' );
        } else {
            $headline = __( 'Payment status', 'ltl-bookings' );
            $message = __( 'Your payment status is being processed.', 'ltl-bookings' );
        }

        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
        echo '<!doctype html><html><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html( get_bloginfo( 'name' ) ) . '</title>';
        echo '</head><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;">';
        echo '<h1>' . esc_html( $headline ) . '</h1>';
        echo '<p>' . esc_html( $message ) . '</p>';
        if ( $status === 'cancel' ) {
            echo '<p>' . esc_html__( 'You may close this page and try booking again.', 'ltl-bookings' ) . '</p>';
            echo '<p><a href="' . esc_url( $retry_url ) . '">' . esc_html__( 'Return to booking page', 'ltl-bookings' ) . '</a></p>';
        }
        echo '</body></html>';
        exit;
    }

    public function handle_stripe_webhook( WP_REST_Request $request ): WP_REST_Response {
        $keys = get_option( 'lazy_payment_keys', [] );
        if ( ! is_array( $keys ) ) {
            $keys = [];
        }
        $secret = (string) ( $keys['stripe_webhook_secret'] ?? '' );
        if ( $secret === '' ) {
            return new WP_REST_Response( [ 'error' => 'webhook_not_configured' ], 400 );
        }

        $payload = (string) $request->get_body();
        $sig_header = (string) $request->get_header( 'stripe-signature' );
        if ( $payload === '' || $sig_header === '' ) {
            return new WP_REST_Response( [ 'error' => 'missing_signature' ], 400 );
        }

        $parts = array_map( 'trim', explode( ',', $sig_header ) );
        $timestamp = 0;
        $v1_sigs = [];
        foreach ( $parts as $p ) {
            if ( strpos( $p, '=' ) === false ) continue;
            list( $k, $v ) = array_map( 'trim', explode( '=', $p, 2 ) );
            if ( $k === 't' ) {
                $timestamp = (int) $v;
            } elseif ( $k === 'v1' ) {
                $v1_sigs[] = $v;
            }
        }
        if ( $timestamp <= 0 || empty( $v1_sigs ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid_signature_header' ], 400 );
        }
        if ( abs( time() - $timestamp ) > 300 ) {
            return new WP_REST_Response( [ 'error' => 'signature_timestamp_out_of_tolerance' ], 400 );
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac( 'sha256', $signed_payload, $secret );
        $valid = false;
        foreach ( $v1_sigs as $sig ) {
            if ( hash_equals( $expected, $sig ) ) {
                $valid = true;
                break;
            }
        }
        if ( ! $valid ) {
            return new WP_REST_Response( [ 'error' => 'signature_verification_failed' ], 400 );
        }

        $event = json_decode( $payload, true );
        if ( ! is_array( $event ) || empty( $event['type'] ) || empty( $event['data']['object'] ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid_payload' ], 400 );
        }

        $type = (string) $event['type'];
        $obj = $event['data']['object'];
        $appointment_id = 0;
        if ( is_array( $obj ) ) {
            if ( ! empty( $obj['metadata']['appointment_id'] ) ) {
                $appointment_id = intval( $obj['metadata']['appointment_id'] );
            } elseif ( ! empty( $obj['client_reference_id'] ) ) {
                $appointment_id = intval( $obj['client_reference_id'] );
            }
        }
        if ( $appointment_id <= 0 ) {
            return new WP_REST_Response( [ 'ok' => true ], 200 );
        }

        $appt_repo = class_exists( 'LTLB_AppointmentRepository' ) ? new LTLB_AppointmentRepository() : null;
        $existing = $appt_repo ? $appt_repo->get_by_id( $appointment_id ) : null;
        if ( is_array( $existing ) ) {
            $existing_payment_status = (string) ( $existing['payment_status'] ?? '' );
            if ( $existing_payment_status === 'paid' ) {
                return new WP_REST_Response( [ 'ok' => true ], 200 );
            }
        }

        if ( $type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded' ) {
            $payment_intent = is_array( $obj ) && ! empty( $obj['payment_intent'] ) ? (string) $obj['payment_intent'] : '';
            $session_id = is_array( $obj ) && ! empty( $obj['id'] ) ? (string) $obj['id'] : '';
            $ref = $payment_intent !== '' ? $payment_intent : $session_id;

            $method_to_set = 'stripe_card';
            if ( is_array( $existing ) ) {
                $existing_method = sanitize_key( (string) ( $existing['payment_method'] ?? '' ) );
                if ( $existing_method !== '' && $existing_method !== 'none' && $existing_method !== 'unpaid' ) {
                    $method_to_set = $existing_method;
                }
            }

            global $wpdb;
            $table = $wpdb->prefix . 'lazy_appointments';
            $wpdb->update(
                $table,
                [
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'payment_method' => $method_to_set,
                    'payment_ref' => $ref,
                    'paid_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => $appointment_id ],
                [ '%s', '%s', '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );

            if ( class_exists( 'LTLB_EmailNotifications' ) ) {
                LTLB_EmailNotifications::send_customer_booking_confirmation( intval( $appointment_id ) );
                LTLB_EmailNotifications::send_admin_booking_notification( intval( $appointment_id ) );
            }
        }

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    private function is_hotel_mode(): bool {
        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) {
            $ls = [];
        }
        return ( $ls['template_mode'] ?? 'service' ) === 'hotel';
    }

    private function pending_blocks_enabled(): bool {
        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) {
            $ls = [];
        }
        return ! empty( $ls['pending_blocks'] );
    }

    public function rest_admin_permission(): bool {
        return current_user_can('manage_options');
    }

    private function sanitize_hex_color( $value, string $fallback ): string {
        $s = is_string( $value ) ? trim( $value ) : '';
        if ( $s === '' ) return $fallback;
        if ( preg_match( '/^#[0-9A-Fa-f]{6}$/', $s ) ) return strtolower( $s );
        if ( preg_match( '/^[0-9A-Fa-f]{6}$/', $s ) ) return '#' . strtolower( $s );
        return $fallback;
    }

    private function get_calendar_status_colors(): array {
        $defaults = [
            'confirmed' => '#2271b1',
            'pending' => '#2271b1',
            'cancelled' => '#ccd0d4',
        ];

        $saved = get_option( self::OPTION_CALENDAR_STATUS_COLORS, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }

        return [
            'confirmed' => $this->sanitize_hex_color( $saved['confirmed'] ?? null, $defaults['confirmed'] ),
            'pending' => $this->sanitize_hex_color( $saved['pending'] ?? null, $defaults['pending'] ),
            'cancelled' => $this->sanitize_hex_color( $saved['cancelled'] ?? null, $defaults['cancelled'] ),
        ];
    }

    public function rest_admin_calendar_colors_get( WP_REST_Request $request ): WP_REST_Response {
        return $this->rest_ok( [
            'ok' => true,
            'colors' => $this->get_calendar_status_colors(),
        ] );
    }

    public function rest_admin_calendar_colors_update( WP_REST_Request $request ): WP_REST_Response {
        $colors = $request->get_param( 'colors' );
        if ( ! is_array( $colors ) ) {
            return $this->rest_error( 400, 'invalid_colors', 'colors must be an object' );
        }

        $defaults = $this->get_calendar_status_colors();
        $next = [
            'confirmed' => $this->sanitize_hex_color( $colors['confirmed'] ?? null, $defaults['confirmed'] ),
            'pending' => $this->sanitize_hex_color( $colors['pending'] ?? null, $defaults['pending'] ),
            'cancelled' => $this->sanitize_hex_color( $colors['cancelled'] ?? null, $defaults['cancelled'] ),
        ];

        update_option( self::OPTION_CALENDAR_STATUS_COLORS, $next, false );
        return $this->rest_ok( [
            'ok' => true,
            'colors' => $next,
        ] );
    }

    private function rest_error( int $status, string $error, string $message = '', array $data = [] ): WP_REST_Response {
        $payload = [
            'ok' => false,
            'error' => $error,
        ];
        if ( $message !== '' ) {
            $payload['message'] = $message;
        }
        if ( ! empty( $data ) ) {
            $payload['data'] = $data;
        }
        return new WP_REST_Response( $payload, $status );
    }

    private function rest_ok( array $payload = [], int $status = 200 ): WP_REST_Response {
        return new WP_REST_Response( $payload, $status );
    }

    public function rest_admin_calendar_events( WP_REST_Request $request ): WP_REST_Response {
        $start = sanitize_text_field( (string) $request->get_param('start') );
        $end = sanitize_text_field( (string) $request->get_param('end') );
        if ( empty( $start ) || empty( $end ) ) {
            return new WP_REST_Response( [ 'error' => 'start and end required' ], 400 );
        }

        $start_dt = LTLB_Time::create_datetime_immutable( $start );
        $end_dt = LTLB_Time::create_datetime_immutable( $end );
        if ( ! $start_dt || ! $end_dt ) {
            return new WP_REST_Response( [ 'error' => 'invalid start/end' ], 400 );
        }

        $repo = new LTLB_AppointmentRepository();
        $rows = $repo->get_calendar_rows( LTLB_Time::format_wp_datetime( $start_dt ), LTLB_Time::format_wp_datetime( $end_dt ) );

        // Map appointments to assigned rooms (hotel mode uses this for per-room calendar rendering).
        global $wpdb;
        $resource_ids_by_appointment = [];
        $appt_ids = [];
        foreach ( $rows as $r ) {
            $aid = intval( $r['id'] ?? 0 );
            if ( $aid > 0 ) {
                $appt_ids[ $aid ] = true;
            }
        }
        $appt_ids = array_keys( $appt_ids );
        if ( ! empty( $appt_ids ) ) {
            $in = implode( ',', array_fill( 0, count( $appt_ids ), '%d' ) );
            $ar_table = $wpdb->prefix . 'lazy_appointment_resources';
            $sql = $wpdb->prepare( "SELECT appointment_id, resource_id FROM {$ar_table} WHERE appointment_id IN ({$in})", ...$appt_ids );
            $assigned = $wpdb->get_results( $sql, ARRAY_A );
            foreach ( $assigned as $a ) {
                $aid = intval( $a['appointment_id'] ?? 0 );
                $rid = intval( $a['resource_id'] ?? 0 );
                if ( $aid <= 0 || $rid <= 0 ) continue;
                if ( ! isset( $resource_ids_by_appointment[ $aid ] ) ) {
                    $resource_ids_by_appointment[ $aid ] = [];
                }
                $resource_ids_by_appointment[ $aid ][ $rid ] = true;
            }
        }

        $events = [];
        foreach ( $rows as $row ) {
            $start_iso = $row['start_at'];
            $end_iso = $row['end_at'];
            $start_dt_row = isset( $row['start_at'] ) ? LTLB_Time::create_datetime_immutable( (string) $row['start_at'] ) : null;
            $end_dt_row = isset( $row['end_at'] ) ? LTLB_Time::create_datetime_immutable( (string) $row['end_at'] ) : null;
            if ( $start_dt_row ) {
                $start_iso = $start_dt_row->format( DATE_ATOM );
            }
            if ( $end_dt_row ) {
                $end_iso = $end_dt_row->format( DATE_ATOM );
            }

            $customer_name = trim( ( $row['customer_first_name'] ?? '' ) . ' ' . ( $row['customer_last_name'] ?? '' ) );

            // If the appointment spans multiple dates, show a nights hint (useful for hotel stays).
            $nights_suffix = '';
            if ( $start_dt_row && $end_dt_row ) {
                $start_date = $start_dt_row->format( 'Y-m-d' );
                $end_date = $end_dt_row->format( 'Y-m-d' );
                if ( $end_date > $start_date ) {
                    $nights = LTLB_Time::nights_between( $start_date, $end_date );
                    if ( $nights >= 1 ) {
                        /* translators: %d = number of nights */
                        $nights_suffix = ' (' . sprintf( _n( '%d night', '%d nights', $nights, 'ltl-bookings' ), $nights ) . ')';
                    }
                }
            }
            $title_parts = [];
            if ( ! empty( $row['service_name'] ) ) $title_parts[] = $row['service_name'];
            if ( ! empty( $customer_name ) ) $title_parts[] = $customer_name;
            $title = ! empty( $title_parts ) ? implode( ' – ', $title_parts ) : sprintf( __( 'Appointment #%d', 'ltl-bookings' ), intval( $row['id'] ) );
            if ( $nights_suffix !== '' ) {
                $title .= $nights_suffix;
            }

            $events[] = [
                'id' => (string) intval( $row['id'] ),
                'title' => $title,
                'start' => $start_iso,
                'end' => $end_iso,
                'extendedProps' => [
                    'status' => $row['status'] ?? '',
                    'service_id' => intval( $row['service_id'] ?? 0 ),
                    'customer_id' => intval( $row['customer_id'] ?? 0 ),
                    'customer_email' => $row['customer_email'] ?? '',
					'seats' => intval( $row['seats'] ?? 1 ),
					'resource_ids' => array_map( 'intval', array_keys( $resource_ids_by_appointment[ intval( $row['id'] ) ] ?? [] ) ),
                ],
            ];
        }

        return new WP_REST_Response( $events, 200 );
    }

    public function rest_admin_appointment_get( WP_REST_Request $request ): WP_REST_Response {
        $id = intval( $request->get_param('id') );
        if ( $id <= 0 ) {
			return $this->rest_error( 400, 'invalid_id', 'Invalid appointment id' );
        }

        $appointments = new LTLB_AppointmentRepository();
        $include_pending = $this->pending_blocks_enabled();
        $services = new LTLB_ServiceRepository();
        $customers = new LTLB_CustomerRepository();

        $appointment = $appointments->get_by_id( $id );
        if ( ! $appointment ) {
			return $this->rest_error( 404, 'not_found', 'Appointment not found' );
        }

        $service = ! empty( $appointment['service_id'] ) ? $services->get_by_id( intval( $appointment['service_id'] ) ) : null;
        $customer = ! empty( $appointment['customer_id'] ) ? $customers->get_by_id( intval( $appointment['customer_id'] ) ) : null;

        return $this->rest_ok( [
            'appointment' => $appointment,
            'service' => $service,
            'customer' => $customer,
        ], 200 );
    }

    public function rest_admin_appointment_delete( WP_REST_Request $request ): WP_REST_Response {
        $id = intval( $request->get_param('id') );
        if ( $id <= 0 ) {
            return $this->rest_error( 400, 'invalid_id', 'Invalid appointment id' );
        }
        $repo = new LTLB_AppointmentRepository();
		$existing = $repo->get_by_id( $id );
		if ( ! $existing ) {
            return $this->rest_error( 404, 'not_found', 'Appointment not found' );
		}
        $ok = $repo->delete( $id );
		if ( ! $ok ) {
            return $this->rest_error( 500, 'delete_failed', 'Could not delete appointment' );
		}
        return $this->rest_ok( [ 'ok' => true ], 200 );
    }

    public function rest_admin_appointment_move( WP_REST_Request $request ): WP_REST_Response {
        $id = intval( $request->get_param('id') );
        $start = sanitize_text_field( (string) $request->get_param('start') );
        $end = sanitize_text_field( (string) $request->get_param('end') );
        if ( $id <= 0 || empty( $start ) || empty( $end ) ) {
			return $this->rest_error( 400, 'missing_params', 'id, start, end required' );
        }

        $start_dt = LTLB_Time::create_datetime_immutable( $start );
        $end_dt = LTLB_Time::create_datetime_immutable( $end );
        if ( ! $start_dt || ! $end_dt ) {
			return $this->rest_error( 400, 'invalid_datetime', 'invalid start/end' );
        }
        if ( $end_dt <= $start_dt ) {
			return $this->rest_error( 400, 'invalid_range', 'end must be after start' );
        }

        $repo = new LTLB_AppointmentRepository();
        $existing = $repo->get_by_id( $id );
        if ( ! $existing ) {
			return $this->rest_error( 404, 'not_found', 'Appointment not found' );
        }

        // Determine which statuses should block a slot. By default only 'confirmed'.
        $blocking_statuses = [ 'confirmed' ];
        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) $ls = [];
        if ( ! empty( $ls['pending_blocks'] ) ) {
            $blocking_statuses[] = 'pending';
        }

        $service_id = intval( $existing['service_id'] ?? 0 );
        $staff_user_id = isset( $existing['staff_user_id'] ) ? intval( $existing['staff_user_id'] ) : null;
        if ( $repo->has_conflict( LTLB_Time::format_wp_datetime( $start_dt ), LTLB_Time::format_wp_datetime( $end_dt ), $service_id, $staff_user_id, $blocking_statuses, $id ) ) {
			// Keep error string 'conflict' for admin UI.
			return $this->rest_error( 409, 'conflict', 'This time slot conflicts with an existing booking.' );
        }

        $ok = $repo->update_times( $id, LTLB_Time::format_wp_datetime( $start_dt ), LTLB_Time::format_wp_datetime( $end_dt ) );
        if ( ! $ok ) {
			return $this->rest_error( 500, 'update_failed', 'Could not update appointment' );
        }
		return $this->rest_ok( [ 'ok' => true ], 200 );
    }

    public function rest_admin_appointment_status( WP_REST_Request $request ): WP_REST_Response {
        $id = intval( $request->get_param('id') );
        $status = sanitize_key( (string) $request->get_param('status') );
        if ( $id <= 0 || empty( $status ) ) {
			return $this->rest_error( 400, 'missing_params', 'id and status required' );
        }
        $allowed = class_exists( 'LTLB_Appointment' ) ? LTLB_Appointment::allowed_statuses() : [ 'pending', 'confirmed', 'cancelled' ];
        if ( ! in_array( $status, $allowed, true ) ) {
			return $this->rest_error( 400, 'invalid_status', 'Invalid status', [ 'allowed' => $allowed ] );
        }
        $repo = new LTLB_AppointmentRepository();
        $existing = $repo->get_by_id( $id );
        if ( ! $existing ) {
			return $this->rest_error( 404, 'not_found', 'Appointment not found' );
        }
        $ok = $repo->update_status( $id, $status );
        if ( ! $ok ) {
			return $this->rest_error( 500, 'update_failed', 'Could not update appointment status' );
        }
		return $this->rest_ok( [ 'ok' => true ], 200 );
    }

    public function rest_admin_customer_update( WP_REST_Request $request ): WP_REST_Response {
        $id = intval( $request->get_param('id') );
        if ( $id <= 0 ) {
			return $this->rest_error( 400, 'invalid_id', 'Invalid customer id' );
        }

        $data = [];
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = $request->get_body_params();
        }
        if ( ! is_array( $params ) ) {
            $params = [];
        }

        $fields = [ 'email', 'first_name', 'last_name', 'phone', 'notes' ];
        foreach ( $fields as $f ) {
            if ( array_key_exists( $f, $params ) ) {
                $data[ $f ] = $params[ $f ];
            }
        }
        if ( empty( $data ) ) {
            return $this->rest_error( 400, 'no_fields', 'No fields to update' );
        }

        // Sanitize + validate fields
        if ( array_key_exists( 'email', $data ) ) {
            $email = sanitize_email( (string) $data['email'] );
            if ( $email !== '' && ! is_email( $email ) ) {
                return $this->rest_error( 400, 'invalid_email', 'Invalid email address' );
            }
            $data['email'] = $email;
        }
        foreach ( [ 'first_name', 'last_name', 'phone' ] as $f ) {
            if ( array_key_exists( $f, $data ) ) {
                $data[ $f ] = sanitize_text_field( (string) $data[ $f ] );
            }
        }
        if ( array_key_exists( 'notes', $data ) ) {
            $data['notes'] = sanitize_textarea_field( (string) $data['notes'] );
        }

        $repo = new LTLB_CustomerRepository();
        $existing = $repo->get_by_id( $id );
        if ( ! $existing ) {
			return $this->rest_error( 404, 'not_found', 'Customer not found' );
        }
        $ok = $repo->update_by_id( $id, $data );
        if ( ! $ok ) {
			return $this->rest_error( 500, 'update_failed', 'Could not update customer' );
        }
		return $this->rest_ok( [ 'ok' => true ], 200 );
    }

    public function rest_admin_calendar_occupancy( WP_REST_Request $request ): WP_REST_Response {
        if ( ! $this->is_hotel_mode() ) {
            return $this->rest_ok( [ 'days' => [] ], 200 );
        }

        $start = sanitize_text_field( (string) $request->get_param('start') );
        $end = sanitize_text_field( (string) $request->get_param('end') );
        if ( empty( $start ) || empty( $end ) ) {
            return $this->rest_error( 400, 'missing_params', 'start and end required' );
        }

        $start_dt = LTLB_Time::create_datetime_immutable( $start );
        $end_dt = LTLB_Time::create_datetime_immutable( $end );
        if ( ! $start_dt || ! $end_dt ) {
            return $this->rest_error( 400, 'invalid_datetime', 'invalid start/end' );
        }

        $resources_repo = new LTLB_ResourceRepository();
        $resources = array_filter( $resources_repo->get_all(), function( $r ) {
            return ! empty( $r['is_active'] );
        } );
        $total_rooms = count( $resources );

        $appointments = new LTLB_AppointmentRepository();
        $include_pending = $this->pending_blocks_enabled();

        $cur = $start_dt->setTime( 0, 0, 0 );
        $end_day = $end_dt->setTime( 0, 0, 0 );
        $days = [];
        // FullCalendar's end is exclusive; iterate while cur < end.
        while ( $cur < $end_day ) {
            $date = $cur->format( 'Y-m-d' );
            $occupied_assigned = $appointments->get_count_occupied_rooms_on_date( $date, $include_pending );
            $occupied_unassigned = $appointments->get_count_unassigned_room_bookings_on_date( $date, $include_pending );
            $occupied = min( $total_rooms, max( 0, (int) $occupied_assigned + (int) $occupied_unassigned ) );
            $rate = $total_rooms > 0 ? (int) round( ( $occupied / $total_rooms ) * 100 ) : 0;
            $days[] = [
                'date' => $date,
                'occupied' => (int) $occupied,
                'total' => (int) $total_rooms,
                'rate' => (int) $rate,
                'assigned' => (int) $occupied_assigned,
                'unassigned' => (int) $occupied_unassigned,
            ];
            $cur = $cur->modify( '+1 day' );
        }

        return $this->rest_ok( [ 'days' => $days ], 200 );
    }

    private function infer_room_type_code( array $service ): string {
        $max_adults = isset( $service['max_adults'] ) ? (int) $service['max_adults'] : 2;
        $max_children = isset( $service['max_children'] ) ? (int) $service['max_children'] : 0;
        if ( $max_adults <= 1 && $max_children <= 0 ) return 'EZ';
        if ( $max_adults === 2 && $max_children <= 0 ) return 'DZ';
        if ( $max_adults >= 2 && $max_children >= 1 ) return 'Fam';
        if ( $max_adults > 2 ) return 'Fam';
        return '';
    }

    public function rest_admin_calendar_rooms( WP_REST_Request $request ): WP_REST_Response {
        if ( ! $this->is_hotel_mode() ) {
            return $this->rest_ok( [ 'rooms' => [] ], 200 );
        }

        $resources_repo = new LTLB_ResourceRepository();
        $service_repo = new LTLB_ServiceRepository();
        $sr_repo = new LTLB_ServiceResourcesRepository();

        $resources = array_values( array_filter( $resources_repo->get_all(), function( $r ) {
            return ! empty( $r['is_active'] );
        } ) );
        $services = array_values( array_filter( $service_repo->get_all(), function( $s ) {
            return ! empty( $s['is_active'] );
        } ) );

        $service_by_id = [];
        foreach ( $services as $s ) {
            $service_by_id[ (int) ( $s['id'] ?? 0 ) ] = $s;
        }

        // Build resource_id => [service_id, ...]
        global $wpdb;
        $map_table = $wpdb->prefix . 'lazy_service_resources';
        $rows = $wpdb->get_results( "SELECT service_id, resource_id FROM {$map_table}", ARRAY_A );
        $service_ids_by_resource = [];
        foreach ( $rows as $row ) {
            $rid = (int) ( $row['resource_id'] ?? 0 );
            $sid = (int) ( $row['service_id'] ?? 0 );
            if ( $rid <= 0 || $sid <= 0 ) continue;
            if ( ! isset( $service_ids_by_resource[ $rid ] ) ) {
                $service_ids_by_resource[ $rid ] = [];
            }
            $service_ids_by_resource[ $rid ][ $sid ] = true;
        }

        $rooms = [];
        foreach ( $resources as $r ) {
            $rid = (int) ( $r['id'] ?? 0 );
            $type_names = [];
            $type_code = '';
            $first_service = null;
            $capacity = (int) ( $r['capacity'] ?? 1 );
            if ( $rid > 0 && isset( $service_ids_by_resource[ $rid ] ) ) {
                foreach ( array_keys( $service_ids_by_resource[ $rid ] ) as $sid ) {
                    if ( isset( $service_by_id[ (int) $sid ] ) ) {
                        $s = $service_by_id[ (int) $sid ];
                        if ( $first_service === null ) {
                            $first_service = $s;
                        }
                        $name = isset( $s['name'] ) ? (string) $s['name'] : '';
                        if ( $name !== '' ) {
                            $type_names[] = $name;
                        }
                    }
                }
            }
            if ( $first_service !== null ) {
                $type_code = $this->infer_room_type_code( $first_service );
            }
            // Hard fallback so every room ends up in EZ/DZ/Fam.
            if ( $type_code !== 'EZ' && $type_code !== 'DZ' && $type_code !== 'Fam' ) {
                if ( $capacity <= 1 ) {
                    $type_code = 'EZ';
                } elseif ( $capacity === 2 ) {
                    $type_code = 'DZ';
                } else {
                    $type_code = 'Fam';
                }
            }

            $rooms[] = [
                'id' => (string) $rid,
                'name' => (string) ( $r['name'] ?? '' ),
                'capacity' => $capacity,
                'typeNames' => array_values( array_unique( $type_names ) ),
                'typeCode' => $type_code,
            ];
        }

        usort( $rooms, function( $a, $b ) {
            return strnatcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
        } );

        return $this->rest_ok( [ 'rooms' => $rooms ], 200 );
    }

    private function compute_room_suggestions( int $appointment_id, array $appointment ): array {
        $start_at = isset( $appointment['start_at'] ) ? (string) $appointment['start_at'] : '';
        $end_at = isset( $appointment['end_at'] ) ? (string) $appointment['end_at'] : '';
        if ( $start_at === '' || $end_at === '' ) {
            return [ 'assigned' => null, 'candidates' => [] ];
        }

        $guests = isset( $appointment['seats'] ) ? max( 1, intval( $appointment['seats'] ) ) : 1;
        $service_id = isset( $appointment['service_id'] ) ? intval( $appointment['service_id'] ) : 0;

        $resources_repo = new LTLB_ResourceRepository();
        $ar_repo = new LTLB_AppointmentResourcesRepository();
        $service_resources = new LTLB_ServiceResourcesRepository();

        $assigned_id = $ar_repo->get_resource_for_appointment( $appointment_id );
        $assigned = null;
        if ( $assigned_id ) {
            $assigned = $resources_repo->get_by_id( (int) $assigned_id );
        }

        $include_pending = $this->pending_blocks_enabled();
        $blocked = $ar_repo->get_blocked_resources( $start_at, $end_at, $include_pending );
        if ( $assigned_id ) {
            $rid = (int) $assigned_id;
            if ( isset( $blocked[ $rid ] ) ) {
                $blocked[ $rid ] = max( 0, (int) $blocked[ $rid ] - $guests );
            }
        }

        $allowed_ids = [];
        if ( $service_id > 0 ) {
            $allowed_ids = $service_resources->get_resources_for_service( $service_id );
        }
        $allowed_lookup = [];
        foreach ( $allowed_ids as $rid ) {
            $allowed_lookup[ (int) $rid ] = true;
        }

        $resources = $resources_repo->get_all();
        $candidates = [];
        foreach ( $resources as $r ) {
            $rid = intval( $r['id'] ?? 0 );
            if ( $rid <= 0 ) continue;
            if ( empty( $r['is_active'] ) ) continue;
            if ( ! empty( $allowed_lookup ) && empty( $allowed_lookup[ $rid ] ) ) continue;

            $capacity = isset( $r['capacity'] ) ? max( 1, intval( $r['capacity'] ) ) : 1;
            $used = isset( $blocked[ $rid ] ) ? (int) $blocked[ $rid ] : 0;
            $available = max( 0, $capacity - $used );
            if ( $available < $guests ) continue;

            $candidates[] = [
                'id' => $rid,
                'name' => (string) ( $r['name'] ?? '' ),
                'capacity' => $capacity,
                'available' => $available,
                'leftover' => $available - $guests,
            ];
        }

        usort( $candidates, function( $a, $b ) {
            $la = (int) ( $a['leftover'] ?? 0 );
            $lb = (int) ( $b['leftover'] ?? 0 );
            if ( $la !== $lb ) return $la <=> $lb;
            return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
        } );

        return [
            'assigned' => $assigned,
            'assigned_id' => $assigned_id ? (int) $assigned_id : 0,
            'guests' => $guests,
            'candidates' => $candidates,
        ];
    }

    public function rest_admin_appointment_room_suggestions( WP_REST_Request $request ): WP_REST_Response {
        if ( ! $this->is_hotel_mode() ) {
            return $this->rest_error( 400, 'not_hotel_mode', 'Room suggestions require hotel mode.' );
        }
        $id = intval( $request->get_param('id') );
        if ( $id <= 0 ) {
            return $this->rest_error( 400, 'invalid_id', 'Invalid appointment id' );
        }

        $appointments = new LTLB_AppointmentRepository();
        $appointment = $appointments->get_by_id( $id );
        if ( ! $appointment ) {
            return $this->rest_error( 404, 'not_found', 'Appointment not found' );
        }

        $computed = $this->compute_room_suggestions( $id, $appointment );
        $best_id = 0;
        if ( ! empty( $computed['candidates'] ) ) {
            $best_id = (int) ( $computed['candidates'][0]['id'] ?? 0 );
        }

        return $this->rest_ok( [
            'ok' => true,
            'appointment_id' => $id,
            'guests' => (int) ( $computed['guests'] ?? 1 ),
            'assigned' => $computed['assigned'],
            'assigned_id' => (int) ( $computed['assigned_id'] ?? 0 ),
            'best_id' => $best_id,
            'candidates' => $computed['candidates'],
        ], 200 );
    }

    public function rest_admin_appointment_assign_room( WP_REST_Request $request ): WP_REST_Response {
        if ( ! $this->is_hotel_mode() ) {
            return $this->rest_error( 400, 'not_hotel_mode', 'Room assignment requires hotel mode.' );
        }
        $id = intval( $request->get_param('id') );
        if ( $id <= 0 ) {
            return $this->rest_error( 400, 'invalid_id', 'Invalid appointment id' );
        }

        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = $request->get_body_params();
        }
        if ( ! is_array( $params ) ) {
            $params = [];
        }

        $resource_id = isset( $params['resource_id'] ) ? intval( $params['resource_id'] ) : 0;
        if ( $resource_id <= 0 ) {
            return $this->rest_error( 400, 'missing_params', 'resource_id required' );
        }

        $appointments = new LTLB_AppointmentRepository();
        $appointment = $appointments->get_by_id( $id );
        if ( ! $appointment ) {
            return $this->rest_error( 404, 'not_found', 'Appointment not found' );
        }

        $resources = new LTLB_ResourceRepository();
        $resource = $resources->get_by_id( $resource_id );
        if ( ! $resource || empty( $resource['is_active'] ) ) {
            return $this->rest_error( 404, 'resource_not_found', 'Resource not found' );
        }

        $ar_repo = new LTLB_AppointmentResourcesRepository();
        $ok = $ar_repo->set_resource_for_appointment( $id, $resource_id );
        if ( ! $ok ) {
            return $this->rest_error( 500, 'assign_failed', 'Could not assign room' );
        }

        return $this->rest_ok( [
            'ok' => true,
            'appointment_id' => $id,
            'resource_id' => $resource_id,
        ], 200 );
    }

    public function rest_admin_appointment_propose_room( WP_REST_Request $request ): WP_REST_Response {
        if ( ! $this->is_hotel_mode() ) {
            return $this->rest_error( 400, 'not_hotel_mode', 'Room proposals require hotel mode.' );
        }
        if ( ! class_exists( 'LTLB_AIOutbox' ) ) {
            return $this->rest_error( 500, 'outbox_missing', 'AI Outbox not available.' );
        }
        $id = intval( $request->get_param('id') );
        if ( $id <= 0 ) {
            return $this->rest_error( 400, 'invalid_id', 'Invalid appointment id' );
        }

        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = $request->get_body_params();
        }
        if ( ! is_array( $params ) ) {
            $params = [];
        }
        $resource_id = isset( $params['resource_id'] ) ? intval( $params['resource_id'] ) : 0;

        $appointments = new LTLB_AppointmentRepository();
        $appointment = $appointments->get_by_id( $id );
        if ( ! $appointment ) {
            return $this->rest_error( 404, 'not_found', 'Appointment not found' );
        }

        if ( $resource_id <= 0 ) {
            $computed = $this->compute_room_suggestions( $id, $appointment );
            if ( ! empty( $computed['candidates'] ) ) {
                $resource_id = (int) ( $computed['candidates'][0]['id'] ?? 0 );
            }
        }
        if ( $resource_id <= 0 ) {
            return $this->rest_error( 400, 'no_suggestion', 'No suitable room found.' );
        }

        $resources = new LTLB_ResourceRepository();
        $resource = $resources->get_by_id( $resource_id );
        if ( ! $resource || empty( $resource['is_active'] ) ) {
            return $this->rest_error( 404, 'resource_not_found', 'Resource not found' );
        }

        $outbox = new LTLB_AIOutbox();
        $res = $outbox->queue_or_execute_assign_room( $id, $resource_id, [
            'source' => 'calendar',
        ] );

        return $this->rest_ok( [
            'ok' => true,
            'appointment_id' => $id,
            'resource_id' => $resource_id,
            'outbox' => $res,
        ], 200 );
    }

    public function rest_availability( WP_REST_Request $request ) {
        $service_id = intval( $request->get_param('service_id') );
        $date = sanitize_text_field( (string) $request->get_param('date') );
        if ( $service_id <= 0 || empty( $date ) ) {
            return new WP_REST_Response( [ 'error' => 'service_id and date required' ], 400 );
        }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid date' ], 400 );
        }
        if ( class_exists( 'LTLB_Time' ) && ! LTLB_Time::create_datetime_immutable( $date ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid date' ], 400 );
        }

        // Optional lightweight rate limit (disabled by default)
        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) $ls = [];
        if ( ! empty( $ls['rate_limit_enabled'] ) ) {
            $per_min = isset( $ls['rate_limit_per_minute'] ) ? max( 1, intval( $ls['rate_limit_per_minute'] ) ) : 60;
            $ip = '';
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
            }
            if ( $ip ) {
                $key = 'ltlb_rate_avail_' . md5( $ip );
                $count = (int) get_transient( $key );
                if ( $count >= $per_min ) {
                    return new WP_REST_Response( [ 'error' => 'rate_limited' ], 429 );
                }
                set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
            }
        }

        // instantiate availability and compute
        $avail = new Availability();

        $want_slots = $request->get_param('slots');
        $slot_step = intval( $request->get_param('slot_step') );
        $step = $slot_step > 0 ? $slot_step : 15;
        if ( $want_slots ) {
            $data = $avail->compute_time_slots( $service_id, $date, $step );
            return new WP_REST_Response( $data, 200 );
        }

        $data = $avail->compute_availability( $service_id, $date, $step );
        return new WP_REST_Response( $data, 200 );
    }

    public function enqueue_admin_assets(): void {
        if ( ! is_admin() ) return;
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        if ( ! $page || strpos( $page, 'ltlb_' ) !== 0 ) return;

        $debug_assets = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
        $admin_css_ver = LTLB_VERSION;
        if ( $debug_assets ) {
            $mtime = @filemtime( LTLB_PATH . 'assets/css/admin.css' );
            if ( $mtime ) {
                $admin_css_ver = (string) $mtime;
            }
        }

        wp_enqueue_style( 'ltlb-admin-css', LTLB_URL . 'assets/css/admin.css', [], $admin_css_ver );

        if ( $page === 'ltlb_ai' ) {
            $ai_ver = LTLB_VERSION;
            if ( $debug_assets ) {
                $mtime = @filemtime( LTLB_PATH . 'assets/js/admin-ai.js' );
                if ( $mtime ) {
                    $ai_ver = (string) $mtime;
                }
            }
            wp_enqueue_script( 'ltlb-admin-ai', LTLB_URL . 'assets/js/admin-ai.js', [], $ai_ver, true );
            wp_localize_script( 'ltlb-admin-ai', 'ltlbAdminAI', [
                'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
                'nonce' => wp_create_nonce( 'ltlb_ai_nonce' ),
                'i18n' => [
                    'testing' => __( 'Testing…', 'ltl-bookings' ),
                    'successFallback' => __( 'Connection OK.', 'ltl-bookings' ),
                    'errorFallback' => __( 'Connection failed.', 'ltl-bookings' ),
                ],
            ] );
        }

        // Wizard JS
        if ($page === 'ltlb_services' && isset($_GET['action'])) {
            $wizard_ver = LTLB_VERSION;
            if ( $debug_assets ) {
                $mtime = @filemtime( LTLB_PATH . 'assets/js/admin-wizard.js' );
                if ( $mtime ) {
                    $wizard_ver = (string) $mtime;
                }
            }
            wp_enqueue_script( 'ltlb-admin-wizard', LTLB_URL . 'assets/js/admin-wizard.js', [], $wizard_ver, true );
        }

        if ( $page === 'ltlb_calendar' ) {
            $ls = get_option( 'lazy_settings', [] );
            if ( ! is_array( $ls ) ) {
                $ls = [];
            }
            $wh_start = isset( $ls['working_hours_start'] ) ? max( 0, min( 23, intval( $ls['working_hours_start'] ) ) ) : null;
            $wh_end = isset( $ls['working_hours_end'] ) ? max( 0, min( 23, intval( $ls['working_hours_end'] ) ) ) : null;
            $template_mode = isset( $ls['template_mode'] ) ? (string) $ls['template_mode'] : 'service';

            // Ensure admin locale matches the per-user language switch in the plugin header.
            // Fall back to WordPress user/site locale if our helper isn't available.
            $user_locale = ( class_exists( 'LTLB_I18n' ) && method_exists( 'LTLB_I18n', 'get_user_admin_locale' ) )
                ? LTLB_I18n::get_user_admin_locale()
                : ( function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale() );
            $fc_locale = is_string( $user_locale ) && $user_locale !== '' ? strtolower( substr( $user_locale, 0, 2 ) ) : 'en';

            $fc_ver = '6.1.11';
            $fc_vendor_dir = LTLB_PATH . 'assets/vendor/fullcalendar/6.1.11/';
            $fc_vendor_url = LTLB_URL . 'assets/vendor/fullcalendar/6.1.11/';
            $has_local_fc = file_exists( $fc_vendor_dir . 'index.global.min.js' ) && file_exists( $fc_vendor_dir . 'locales-all.global.min.js' );

            if ( $has_local_fc ) {
                // FullCalendar global bundle injects its own CSS via <style data-fullcalendar>.
                wp_enqueue_script( 'ltlb-fullcalendar', $fc_vendor_url . 'index.global.min.js', [], $fc_ver, true );
                wp_enqueue_script( 'ltlb-fullcalendar-locales', $fc_vendor_url . 'locales-all.global.min.js', [ 'ltlb-fullcalendar' ], $fc_ver, true );
            } else {
                // CDN fallback (kept for safety if local vendor files are missing).
                wp_enqueue_script( 'ltlb-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js', [], $fc_ver, true );
                wp_enqueue_script( 'ltlb-fullcalendar-locales', 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales-all.global.min.js', [ 'ltlb-fullcalendar' ], $fc_ver, true );
            }

            wp_enqueue_script( 'wp-api-fetch' );

            $calendar_ver = LTLB_VERSION;
            if ( $debug_assets ) {
                $mtime = @filemtime( LTLB_PATH . 'assets/js/admin-calendar.js' );
                if ( $mtime ) {
                    $calendar_ver = (string) $mtime;
                }
            }
            wp_enqueue_script( 'ltlb-admin-calendar', LTLB_URL . 'assets/js/admin-calendar.js', [ 'ltlb-fullcalendar', 'ltlb-fullcalendar-locales', 'wp-api-fetch' ], $calendar_ver, true );
            wp_localize_script( 'ltlb-admin-calendar', 'ltlbAdminCalendar', [
                'restBase' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'workingHoursStart' => $wh_start,
                'workingHoursEnd' => $wh_end,
                'locale' => $fc_locale,
                'templateMode' => $template_mode,
				'statusColors' => $this->get_calendar_status_colors(),
                'i18n' => [
                    // Use English msgids so the built-in de_DE dictionary can translate consistently.
                    'today' => __( 'Today', 'ltl-bookings' ),
                    'month' => __( 'Month', 'ltl-bookings' ),
                    'week' => __( 'Week', 'ltl-bookings' ),
                    'day' => __( 'Day', 'ltl-bookings' ),
                    'pending' => __( 'Pending', 'ltl-bookings' ),
                    'confirmed' => __( 'Confirmed', 'ltl-bookings' ),
                    'cancelled' => __( 'Cancelled', 'ltl-bookings' ),
                    'id' => __( 'ID', 'ltl-bookings' ),
                    'status' => __( 'Status', 'ltl-bookings' ),
                    'start' => __( 'Start', 'ltl-bookings' ),
                    'end' => __( 'End', 'ltl-bookings' ),
                    'service' => __( 'Service', 'ltl-bookings' ),
                    'customer' => __( 'Customer', 'ltl-bookings' ),
                    'first_name' => __( 'First name', 'ltl-bookings' ),
                    'last_name' => __( 'Last name', 'ltl-bookings' ),
                    'email' => __( 'Email', 'ltl-bookings' ),
                    'phone' => __( 'Phone', 'ltl-bookings' ),
                    'notes' => __( 'Notes', 'ltl-bookings' ),
                    'save' => __( 'Save', 'ltl-bookings' ),
                    'save_customer' => __( 'Save Customer', 'ltl-bookings' ),
                    'delete_appointment' => __( 'Delete Appointment', 'ltl-bookings' ),
                    'open_appointments' => __( 'Open Appointments List', 'ltl-bookings' ),
                    'confirm_delete' => __( 'Delete this appointment?', 'ltl-bookings' ),
                    'customer_saved' => __( 'Customer saved.', 'ltl-bookings' ),
                    'could_not_load_details' => __( 'Could not load appointment details.', 'ltl-bookings' ),
                    'could_not_update_appointment' => __( 'Could not update appointment.', 'ltl-bookings' ),
                    'could_not_update_status' => __( 'Could not update status.', 'ltl-bookings' ),
                    'could_not_delete_appointment' => __( 'Could not delete appointment.', 'ltl-bookings' ),
                    'could_not_save_customer' => __( 'Could not save customer.', 'ltl-bookings' ),
                    'conflict_message' => __( 'This time slot conflicts with an existing booking.', 'ltl-bookings' ),
                    'no_customer_data' => __( 'No customer data.', 'ltl-bookings' ),
                    'loading_details' => __( 'Loading appointment details…', 'ltl-bookings' ),
                    'details_loaded' => __( 'Appointment details loaded.', 'ltl-bookings' ),
                    'status_updated' => __( 'Status updated.', 'ltl-bookings' ),
                    'appointment_updated' => __( 'Appointment updated.', 'ltl-bookings' ),
                    'appointment_deleted' => __( 'Appointment deleted.', 'ltl-bookings' ),
                    'occupancy' => __( 'Occupancy', 'ltl-bookings' ),
                    'rooms' => __( 'Rooms', 'ltl-bookings' ),
                    'room' => __( 'Room', 'ltl-bookings' ),
                    'room_assignment' => __( 'Room Assignment', 'ltl-bookings' ),
                    'assigned_room' => __( 'Assigned room', 'ltl-bookings' ),
                    'unassigned' => __( 'Unassigned', 'ltl-bookings' ),
					'other' => __( 'Other', 'ltl-bookings' ),
                    'suggested_room' => __( 'Suggested room', 'ltl-bookings' ),
                    'choose_room' => __( 'Choose room', 'ltl-bookings' ),
                    'assign_room' => __( 'Assign room', 'ltl-bookings' ),
                    'propose_room' => __( 'Propose via Outbox', 'ltl-bookings' ),
                    'loading_room' => __( 'Loading room suggestions…', 'ltl-bookings' ),
                    'room_assigned' => __( 'Room assigned.', 'ltl-bookings' ),
                    'room_proposed' => __( 'Room proposal sent to Outbox.', 'ltl-bookings' ),
                    'could_not_load_room' => __( 'Could not load room suggestions.', 'ltl-bookings' ),
                    'could_not_assign_room' => __( 'Could not assign room.', 'ltl-bookings' ),
                    'could_not_propose_room' => __( 'Could not propose room.', 'ltl-bookings' ),
					'change_color' => __( 'Change color', 'ltl-bookings' ),
					'color_saved' => __( 'Color saved.', 'ltl-bookings' ),
					'could_not_save_color' => __( 'Could not save color.', 'ltl-bookings' ),
					'no_rooms' => __( 'No rooms found.', 'ltl-bookings' ),
					'could_not_load_rooms' => __( 'Could not load rooms.', 'ltl-bookings' ),
                ],
            ] );
        }
    }

    public function handle_set_admin_lang(): void {
        if ( ! is_admin() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }
        check_admin_referer( 'ltlb_set_admin_lang' );

        $user_id = get_current_user_id();
        $locale = isset( $_POST['ltlb_admin_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['ltlb_admin_lang'] ) ) : 'en_US';
        if ( class_exists('LTLB_I18n') ) {
            LTLB_I18n::set_user_admin_locale( $user_id, $locale );
        }

        $redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
        if ( empty( $redirect ) ) {
            $redirect = wp_get_referer();
        }
        if ( empty( $redirect ) ) {
            $redirect = admin_url( 'admin.php?page=ltlb_dashboard' );
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    private function get_contrast_text_color( $hex_color ): string {
        $hex_color = is_string( $hex_color ) ? trim( $hex_color ) : '';
        if ( ! preg_match( '/^#?[0-9A-Fa-f]{6}$/', $hex_color ) ) {
            return '#ffffff';
        }

        // Remove # if present
        $hex = str_replace( '#', '', $hex_color );

        // Convert to RGB
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        // Calculate relative luminance (WCAG formula)
        $r = $r / 255.0;
        $g = $g / 255.0;
        $b = $b / 255.0;
        
        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // Return black for light backgrounds, white for dark backgrounds
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }

    public function print_design_css_frontend(): void {
        if ( is_admin() ) return;
        if ( ! function_exists('has_shortcode') ) return;
        global $post;
        if ( empty( $post ) ) return;
        $content = is_string( $post->post_content ?? null ) ? $post->post_content : '';
        $has_ltlb_shortcode = (
            has_shortcode( $content, 'lazy_book' ) ||
            has_shortcode( $content, 'lazy_book_calendar' ) ||
            has_shortcode( $content, 'lazy_book_bar' ) ||
            has_shortcode( $content, 'lazy_hotel_bar' ) ||
            has_shortcode( $content, 'lazy_book_widget' ) ||
            has_shortcode( $content, 'lazy_hotel_widget' ) ||
            has_shortcode( $content, 'lazy_services' ) ||
            has_shortcode( $content, 'lazy_room_types' ) ||
            has_shortcode( $content, 'lazy_testimonials' ) ||
            has_shortcode( $content, 'lazy_trust' )
        );
        if ( ! $has_ltlb_shortcode ) return;

        $design = get_option( 'lazy_design', [] );
        if ( ! is_array( $design ) ) $design = [];

        $bg = $design['background'] ?? '#ffffff';
        $primary = $design['primary'] ?? '#2b7cff';
        $primary_hover = $design['primary_hover'] ?? ($design['accent'] ?? '#ffcc00');
        $text = $design['text'] ?? '#222222';
        $accent = $design['accent'] ?? '#ffcc00';
        $secondary = $design['secondary'] ?? $primary;
        $secondary_hover = $design['secondary_hover'] ?? $secondary;
        $border_color = $design['border_color'] ?? '#cccccc';
        $panel_bg = $design['panel_background'] ?? 'transparent';
        $border_width = $design['border_width'] ?? 1;
        $border_radius = $design['border_radius'] ?? 4;
        $box_shadow_blur = $design['box_shadow_blur'] ?? 4;
        $box_shadow_spread = $design['box_shadow_spread'] ?? 0;
        $transition_duration = $design['transition_duration'] ?? 200;
        $enable_animations = $design['enable_animations'] ?? 1;
        $use_gradient = $design['use_gradient'] ?? 0;
        $use_auto_button_text = $design['auto_button_text'] ?? 1;
        $button_text = $design['button_text'] ?? '#ffffff';
        
        // Separate shadow controls
        $shadow_container = $design['shadow_container'] ?? 1;
        $shadow_button = $design['shadow_button'] ?? 1;
        $shadow_input = $design['shadow_input'] ?? 0;
        $shadow_card = $design['shadow_card'] ?? 1;
        
        $custom_css = $design['custom_css'] ?? '';

        $bg_final = $use_gradient ? "linear-gradient(135deg, {$primary}, {$accent})" : $bg;
        
        // Auto-calculate button text color based on primary color luminance
        if ( $use_auto_button_text ) {
            $button_text = $this->get_contrast_text_color( $primary );
        }

        // Secondary button hover fill text (always auto-contrast to stay readable)
        $secondary_text = $this->get_contrast_text_color( $secondary_hover );
        
        // Generate separate shadow values
        $shadow_container_val = $shadow_container ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';
        $shadow_button_val = $shadow_button ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';
        $shadow_input_val = $shadow_input ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.12)" : 'none';
        $shadow_card_val = $shadow_card ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';

        $selector = implode( ',', [
            '.ltlb-booking',
            '.ltlb-booking-bar',
            '.ltlb-booking-widget',
            '.ltlb-services-grid',
            '.ltlb-trust',
        ] );

        $surface_primary = ($panel_bg && $panel_bg !== 'transparent') ? $panel_bg : $bg;

        $css = "{$selector}{
            --lazy-bg:{$bg_final};
            --lazy-primary:{$primary};
            --lazy-primary-hover:{$primary_hover};
            --lazy-secondary:{$secondary};
            --lazy-secondary-hover:{$secondary_hover};
            --lazy-secondary-text:{$secondary_text};
            --lazy-text:{$text};
            --lazy-accent:{$accent};
            --lazy-accent-hover:{$primary_hover};
            --lazy-border-color:{$border_color};
            --lazy-panel-bg:{$panel_bg};
            --lazy-button-text:{$button_text};
            --lazy-border-width:{$border_width}px;
            --lazy-border-radius:{$border_radius}px;
            --lazy-shadow-container:{$shadow_container_val};
            --lazy-shadow-button:{$shadow_button_val};
            --lazy-shadow-input:{$shadow_input_val};
            --lazy-shadow-card:{$shadow_card_val};
            --lazy-transition-duration:" . ($enable_animations ? $transition_duration : 0) . "ms;

            /* Compatibility tokens used by some components */
            --lazy-bg-primary:{$surface_primary};
            --lazy-bg-secondary:{$surface_primary};
            --lazy-bg-tertiary:{$surface_primary};
            --lazy-text-primary:{$text};
            --lazy-text-secondary:{$text};
            --lazy-text-muted:{$text};
            --lazy-border-medium:{$border_color};
            --lazy-border-light:{$border_color};
            --lazy-border-strong:{$border_color};
            --lazy-shadow-sm:{$shadow_card_val};
            --lazy-shadow-md:{$shadow_card_val};
        }";

        echo "<style id=\"ltlb-design-vars\">{$css}</style>";
    }

    public function print_design_css_admin(): void {
        if ( ! is_admin() ) return;
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        if ( ! $page || strpos( $page, 'ltlb_' ) !== 0 ) return;

        $design = get_option( 'lazy_design', [] );
        if ( ! is_array( $design ) ) $design = [];

        // Allow separate admin palette (colors only).
        $design_backend = get_option( 'lazy_design_backend', [] );
        if ( ! is_array( $design_backend ) ) {
            $design_backend = [];
        }

        $color_fields = [
            'background',
            'primary',
            'primary_hover',
            'secondary',
            'secondary_hover',
            'text',
            'accent',
            'border_color',
            'panel_background',
            'button_text',
        ];
        foreach ( $color_fields as $key ) {
            if ( isset( $design_backend[ $key ] ) && is_string( $design_backend[ $key ] ) && $design_backend[ $key ] !== '' ) {
                $design[ $key ] = $design_backend[ $key ];
            }
        }

        $bg = $design['background'] ?? '#ffffff';
        $primary = $design['primary'] ?? '#2b7cff';
        $primary_hover = $design['primary_hover'] ?? ($design['accent'] ?? '#ffcc00');
        $text = $design['text'] ?? '#222222';
        $accent = $design['accent'] ?? '#ffcc00';
        $secondary = $design['secondary'] ?? $primary;
        $secondary_hover = $design['secondary_hover'] ?? $secondary;
        $border_color = $design['border_color'] ?? '#cccccc';
        $panel_bg = $design['panel_background'] ?? 'transparent';
        $border_width = $design['border_width'] ?? 1;
        $border_radius = $design['border_radius'] ?? 4;
        $box_shadow_blur = $design['box_shadow_blur'] ?? 4;
        $box_shadow_spread = $design['box_shadow_spread'] ?? 0;
        $transition_duration = $design['transition_duration'] ?? 200;
        $enable_animations = $design['enable_animations'] ?? 1;
        $use_gradient = $design['use_gradient'] ?? 0;
        $use_auto_button_text = $design['auto_button_text'] ?? 1;
        $button_text = $design['button_text'] ?? '#ffffff';
        
        // Separate shadow controls
        $shadow_container = $design['shadow_container'] ?? 1;
        $shadow_button = $design['shadow_button'] ?? 1;
        $shadow_input = $design['shadow_input'] ?? 0;
        $shadow_card = $design['shadow_card'] ?? 1;
        
        $custom_css = $design['custom_css'] ?? '';

        $bg_final = $use_gradient ? "linear-gradient(135deg, {$primary}, {$accent})" : $bg;

        if ( $use_auto_button_text ) {
            $button_text = $this->get_contrast_text_color( $primary );
        }

        $secondary_text = $this->get_contrast_text_color( $secondary_hover );
        
        // Generate separate shadow values
        $shadow_container_val = $shadow_container ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';
        $shadow_button_val = $shadow_button ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';
        $shadow_input_val = $shadow_input ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.12)" : 'none';
        $shadow_card_val = $shadow_card ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';

        // Scope vars to our admin wrapper (used across all LazyBookings pages).
        // This avoids affecting wp-admin UI globally.
        $surface_primary = ($panel_bg && $panel_bg !== 'transparent') ? $panel_bg : $bg;
        $css = ".ltlb-admin{
            --lazy-bg:{$bg_final};
            --lazy-primary:{$primary};
            --lazy-primary-hover:{$primary_hover};
            --lazy-secondary:{$secondary};
            --lazy-secondary-hover:{$secondary_hover};
            --lazy-secondary-text:{$secondary_text};
            --lazy-text:{$text};
            --lazy-accent:{$primary};
            --lazy-accent-hover:{$primary_hover};
            --lazy-border-color:{$border_color};
            --lazy-panel-bg:{$panel_bg};
            --lazy-button-text:{$button_text};
            --lazy-border-width:{$border_width}px;
            --lazy-border-radius:{$border_radius}px;
            --lazy-shadow-container:{$shadow_container_val};
            --lazy-shadow-button:{$shadow_button_val};
            --lazy-shadow-input:{$shadow_input_val};
            --lazy-shadow-card:{$shadow_card_val};
            --lazy-transition-duration:" . ($enable_animations ? $transition_duration : 0) . "ms;

            /* Map frontend palette into admin token set */
            --lazy-bg-primary:{$bg};
            --lazy-bg-secondary:{$surface_primary};
            --lazy-bg-tertiary:{$surface_primary};
            --lazy-text-primary:{$text};
            --lazy-text-secondary:{$text};
            --lazy-text-muted:{$text};
            --lazy-border-light:{$border_color};
            --lazy-border-medium:{$border_color};
            --lazy-border-strong:{$border_color};
        }";

        echo "<style id=\"ltlb-design-vars-admin\">{$css}</style>";
    }

    /**
     * Handle CSV export from dashboard
     */
    public function handle_csv_export(): void {
        if ( ! isset($_GET['ltlb_export']) || $_GET['ltlb_export'] !== 'csv' ) {
            return;
        }

        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('No access', 'ltl-bookings') );
        }

        if ( ! isset($_GET['_wpnonce']) || ! wp_verify_nonce( sanitize_text_field($_GET['_wpnonce']), 'ltlb_export' ) ) {
            wp_die( esc_html__('Security check failed', 'ltl-bookings') );
        }

        $start_date = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : date('Y-m-d');

        $analytics = LTLB_Analytics::instance();
        $csv_content = $analytics->export_csv($start_date, $end_date);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="lazy-bookings-export-' . date('Y-m-d') . '.csv"');
        echo $csv_content;
        exit;
    }

    /**
     * Handle ICS calendar feed requests
     */
    private function handle_ics_feed(): void {
        if ( ! isset($_GET['ltlb_ics_feed']) ) {
            return;
        }

        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $user_id = LTLB_ICS_Export::verify_feed_token( $token );

        if ( ! $user_id ) {
            wp_die( esc_html__('Invalid token', 'ltl-bookings'), 403 );
        }

        $filters = [ 'status' => 'confirmed' ];
        LTLB_ICS_Export::download_ics( $filters, 'lazybookings-' . $user_id . '.ics' );
    }

    /**
     * AJAX: Test AI connection
     */
    public function handle_test_ai_connection(): void {
        check_ajax_referer( 'ltlb_ai_nonce', 'nonce' );

        if ( ! current_user_can('manage_ai_secrets') ) {
            wp_send_json_error([
                'message' => __('No permission', 'ltl-bookings'),
            ]);
        }

        $provider = sanitize_text_field( $_POST['provider'] ?? 'gemini' );
        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
        $model = sanitize_text_field( $_POST['model'] ?? '' );

        $result = LTLB_AI_Factory::test_connection( $provider, $api_key, $model );

        wp_send_json( $result );
    }
}
