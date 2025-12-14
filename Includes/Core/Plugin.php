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
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);
        add_action('rest_api_init', [ $this, 'register_rest_routes' ]);
		add_action( 'ltlb_retention_cleanup', [ 'LTLB_Retention', 'run' ] );
        $this->ensure_retention_cron();

		// Per-user admin language setting
		add_action( 'admin_post_ltlb_set_admin_lang', [ $this, 'handle_set_admin_lang' ] );
        
        // Load required classes
        $this->load_classes();

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

    private function load_classes(): void {

        // Utilities
        require_once LTLB_PATH . 'Includes/Util/Sanitizer.php';
        require_once LTLB_PATH . 'Includes/Util/Time.php';
        require_once LTLB_PATH . 'Includes/Util/Notices.php';
        require_once LTLB_PATH . 'Includes/Util/LockManager.php';
        require_once LTLB_PATH . 'Includes/Util/Logger.php';
        require_once LTLB_PATH . 'Includes/Util/Mailer.php';
        require_once LTLB_PATH . 'Includes/Util/BookingService.php';
        require_once LTLB_PATH . 'Includes/Util/Availability.php';
		require_once LTLB_PATH . 'Includes/Util/I18n.php';
        require_once LTLB_PATH . 'Includes/Util/Retention.php';

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
        require_once LTLB_PATH . 'admin/Pages/StaffPage.php';
        require_once LTLB_PATH . 'admin/Pages/ResourcesPage.php';
        require_once LTLB_PATH . 'admin/Pages/DiagnosticsPage.php';
        require_once LTLB_PATH . 'admin/Pages/PrivacyPage.php';
        
        // Booking Engine (Hotel mode)
        require_once LTLB_PATH . 'Includes/Engine/BookingEngineInterface.php';
        require_once LTLB_PATH . 'Includes/Engine/HotelEngine.php';
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

			// Handle admin mode switch
			if ( isset( $_GET['ltlb_admin_mode'] ) ) {
				$new_mode = sanitize_text_field( $_GET['ltlb_admin_mode'] );
				if ( in_array( $new_mode, [ 'appointments', 'hotel' ] ) ) {
					$settings = get_option( 'lazy_settings', [] );
					if ( ! is_array( $settings ) ) {
						$settings = [];
					}
					$settings['admin_mode'] = $new_mode;
					update_option( 'lazy_settings', $settings );

					// Redirect to remove the query arg
					wp_safe_redirect( remove_query_arg( 'ltlb_admin_mode' ) );
					exit;
				}
			}
        }
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __( 'LazyBookings', 'ltl-bookings' ),
            __( 'LazyBookings', 'ltl-bookings' ),
            'manage_options',
            'ltlb_dashboard',
            [ $this, 'render_dashboard_page' ],
            'dashicons-calendar-alt',
            26
        );

		$settings = get_option( 'lazy_settings', [] );
		$admin_mode = is_array( $settings ) && isset( $settings['admin_mode'] ) ? $settings['admin_mode'] : 'appointments';
		$is_hotel_frontend = is_array($settings) && isset($settings['template_mode']) && $settings['template_mode'] === 'hotel';


        // Dashboard (explicit label + first submenu item)
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Dashboard', 'ltl-bookings' ),
            __( 'Dashboard', 'ltl-bookings' ),
            'manage_options',
            'ltlb_dashboard',
            [ $this, 'render_dashboard_page' ]
        );

        // Appointments / Bookings
		$appointments_label = $admin_mode === 'hotel' ? __( 'Bookings', 'ltl-bookings' ) : __( 'Appointments', 'ltl-bookings' );
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
        $customers_label = $admin_mode === 'hotel' ? __( 'Guests', 'ltl-bookings' ) : __( 'Customers', 'ltl-bookings' );
        add_submenu_page(
            'ltlb_dashboard',
            $customers_label,
            $customers_label,
            'manage_options',
            'ltlb_customers',
            [ $this, 'render_customers_page' ]
        );


        // Services (context-aware label)
        $services_label = $admin_mode === 'hotel' ? __( 'Room Types', 'ltl-bookings' ) : __( 'Services', 'ltl-bookings' );
        add_submenu_page(
            'ltlb_dashboard',
            $services_label,
            $services_label,
            'manage_options',
            'ltlb_services',
            [ $this, 'render_services_page' ]
        );

		if ($admin_mode === 'appointments') {
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
        $resources_label = $admin_mode === 'hotel' ? __( 'Rooms', 'ltl-bookings' ) : __( 'Resources', 'ltl-bookings' );
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
        $admin_mode = is_array( $settings ) && isset( $settings['admin_mode'] ) ? $settings['admin_mode'] : 'appointments';

        if ( $admin_mode === 'hotel' ) {
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
    }

    public function rest_admin_permission(): bool {
        return current_user_can('manage_options');
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

        wp_enqueue_style( 'ltlb-admin-css', LTLB_URL . 'assets/css/admin.css', [], LTLB_VERSION );

        // Wizard JS
        if ($page === 'ltlb_services' && isset($_GET['action'])) {
            wp_enqueue_script( 'ltlb-admin-wizard', LTLB_URL . 'assets/js/admin-wizard.js', [], LTLB_VERSION, true );
        }

        if ( $page === 'ltlb_calendar' ) {
            $ls = get_option( 'lazy_settings', [] );
            if ( ! is_array( $ls ) ) {
                $ls = [];
            }
            $wh_start = isset( $ls['working_hours_start'] ) ? max( 0, min( 23, intval( $ls['working_hours_start'] ) ) ) : null;
            $wh_end = isset( $ls['working_hours_end'] ) ? max( 0, min( 23, intval( $ls['working_hours_end'] ) ) ) : null;

                // Ensure admin locale matches the per-user language switch in the plugin header.
                // Fall back to WordPress user/site locale if our helper isn't available.
                $user_locale = ( class_exists( 'LTLB_I18n' ) && method_exists( 'LTLB_I18n', 'get_user_admin_locale' ) )
                    ? LTLB_I18n::get_user_admin_locale()
                    : ( function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale() );
                $fc_locale = is_string( $user_locale ) && $user_locale !== '' ? strtolower( substr( $user_locale, 0, 2 ) ) : 'en';

            wp_enqueue_style( 'ltlb-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css', [], '6.1.11' );
            wp_enqueue_script( 'ltlb-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js', [], '6.1.11', true );

            // Needed for non-English day/month names and date/time formatting.
            wp_enqueue_script( 'ltlb-fullcalendar-locales', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales-all.global.min.js', [ 'ltlb-fullcalendar' ], '6.1.11', true );

            wp_enqueue_script( 'wp-api-fetch' );
            wp_enqueue_script( 'ltlb-admin-calendar', LTLB_URL . 'assets/js/admin-calendar.js', [ 'ltlb-fullcalendar', 'ltlb-fullcalendar-locales', 'wp-api-fetch' ], LTLB_VERSION, true );
            wp_localize_script( 'ltlb-admin-calendar', 'ltlbAdminCalendar', [
                'restBase' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'workingHoursStart' => $wh_start,
                'workingHoursEnd' => $wh_end,
                'locale' => $fc_locale,
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
        if ( ! has_shortcode( $post->post_content, 'lazy_book' ) ) return;

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

        $css = ".ltlb-booking{
            --lazy-bg:{$bg_final};
            --lazy-primary:{$primary};
            --lazy-primary-hover:{$primary_hover};
            --lazy-secondary:{$secondary};
            --lazy-secondary-hover:{$secondary_hover};
            --lazy-secondary-text:{$secondary_text};
            --lazy-text:{$text};
            --lazy-accent:{$accent};
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
        $css = ".ltlb-admin{
            --lazy-bg:{$bg_final};
            --lazy-primary:{$primary};
            --lazy-primary-hover:{$primary_hover};
            --lazy-secondary:{$secondary};
            --lazy-secondary-hover:{$secondary_hover};
            --lazy-secondary-text:{$secondary_text};
            --lazy-text:{$text};
            --lazy-accent:{$accent};
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
        }";

        echo "<style id=\"ltlb-design-vars-admin\">{$css}</style>";
    }
}