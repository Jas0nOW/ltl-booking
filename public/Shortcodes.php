<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Shortcodes {
	private static function enqueue_public_assets(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		// Determine if we should use minified files
		$debug_assets = defined( 'LTLB_DEBUG_ASSETS' ) && LTLB_DEBUG_ASSETS;
		$min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$css_dir = $debug_assets ? 'assets/css/' : 'assets/css/dist/';

		// Design System CSS (consolidated)
		$public_ver = self::asset_version( $css_dir . "public{$min}.css" );
		$js_ver = self::asset_version( 'assets/js/public.js' );
		
		wp_enqueue_style( 'ltlb-public', plugins_url( "../{$css_dir}public{$min}.css", __FILE__ ), [], $public_ver );
		
		wp_enqueue_script( 'ltlb-public', plugins_url( '../assets/js/public.js', __FILE__ ), [ 'jquery' ], $js_ver, true );

		wp_localize_script( 'ltlb-public', 'LTLB_PUBLIC', [
			'restRoot' => esc_url_raw( rest_url( 'ltlb/v1' ) ),
		] );

		wp_localize_script( 'ltlb-public', 'ltlbI18n', [
			'step_of' => __( 'Step %s of %s', 'ltl-bookings' ),
			'night' => __( 'night', 'ltl-bookings' ),
			'nights' => __( 'nights', 'ltl-bookings' ),
			'any' => __( 'Any', 'ltl-bookings' ),
			'room_number' => __( 'Room #', 'ltl-bookings' ),
			'resource_number' => __( 'Resource #', 'ltl-bookings' ),
			'select_room_optional' => __( 'Optional: select a room.', 'ltl-bookings' ),
			'select_resource_optional' => __( 'Optional: select a resource.', 'ltl-bookings' ),
			'loading_availability' => __( 'Loading availability…', 'ltl-bookings' ),
			'loading_resources' => __( 'Loading resources…', 'ltl-bookings' ),
			'loading_times' => __( 'Loading available times…', 'ltl-bookings' ),
			'times_still_loading' => __( 'Available times are still loading…', 'ltl-bookings' ),
			'select_date_first' => __( 'Select a date first', 'ltl-bookings' ),
			'times_load_failed' => __( 'Times could not be loaded', 'ltl-bookings' ),
			'times_load_failed_reload' => __( 'Times could not be loaded. Please reload the page.', 'ltl-bookings' ),
			'times_load_failed_retry' => __( 'Times could not be loaded. Please try again.', 'ltl-bookings' ),
			'no_times_available' => __( 'No times available', 'ltl-bookings' ),
			'no_times_available_for_date' => __( 'No times are available for this date.', 'ltl-bookings' ),
			'select_time' => __( 'Select a time', 'ltl-bookings' ),
			'validation_select_service' => __( 'Please select a service.', 'ltl-bookings' ),
			'validation_select_date' => __( 'Please select a date.', 'ltl-bookings' ),
			'validation_select_time' => __( 'Please select a time.', 'ltl-bookings' ),
			'validation_select_checkin' => __( 'Please select a check-in date.', 'ltl-bookings' ),
			'validation_select_checkout' => __( 'Please select a check-out date.', 'ltl-bookings' ),
			'validation_date_past' => __( 'The date cannot be in the past.', 'ltl-bookings' ),
			'validation_checkout_after_checkin' => __( 'Check-out must be after check-in.', 'ltl-bookings' ),
			'validation_enter_email' => __( 'Please enter your email address.', 'ltl-bookings' ),
			'validation_invalid_email' => __( 'Please enter a valid email address.', 'ltl-bookings' ),
			'availability_error' => __( 'Availability could not be loaded. Please try again.', 'ltl-bookings' ),
			'resources_error' => __( 'Resources could not be loaded. Please try again.', 'ltl-bookings' ),
		] );
	}

	private static function asset_version( string $relative_path ): string {
		$debug_assets = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
		if ( $debug_assets ) {
			$mtime = @filemtime( LTLB_PATH . ltrim( $relative_path, '/' ) );
			if ( $mtime ) {
				return (string) $mtime;
			}
		}
		return LTLB_VERSION;
	}

	private static function maybe_rate_limit( string $key_prefix ): ?WP_REST_Response {
		$ls = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls ) ) $ls = [];
		if ( empty( $ls['rate_limit_enabled'] ) ) {
			return null;
		}

		$per_min = isset( $ls['rate_limit_per_minute'] ) ? max( 1, intval( $ls['rate_limit_per_minute'] ) ) : 60;
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		if ( ! $ip ) {
			return null;
		}

		$key = 'ltlb_rate_' . $key_prefix . '_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $per_min ) {
			return new WP_REST_Response( [ 'error' => 'rate_limited' ], 429 );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return null;
	}

	public static function init(): void {
		add_shortcode( 'lazy_book', [ __CLASS__, 'render_lazy_book' ] );
		add_shortcode( 'lazy_book_calendar', [ __CLASS__, 'render_lazy_book_calendar' ] );
		add_shortcode( 'lazy_book_bar', [ __CLASS__, 'render_booking_bar' ] );
		add_shortcode( 'lazy_hotel_bar', [ __CLASS__, 'render_hotel_booking_bar' ] );
		add_shortcode( 'lazy_book_widget', [ __CLASS__, 'render_booking_widget' ] );
		add_shortcode( 'lazy_hotel_widget', [ __CLASS__, 'render_hotel_booking_widget' ] );
		add_shortcode( 'lazy_services', [ __CLASS__, 'render_services_grid' ] );
		add_shortcode( 'lazy_room_types', [ __CLASS__, 'render_room_types_grid' ] );
		// Kept for backward compatibility, but no longer uses comments/testimonials.
		add_shortcode( 'lazy_testimonials', [ __CLASS__, 'render_trust_section' ] );
		add_shortcode( 'lazy_trust', [ __CLASS__, 'render_trust_section' ] );
		// Language Switcher Shortcode
		add_shortcode( 'lazy_lang_switcher', [ __CLASS__, 'render_language_switcher' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_assets' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
	}

	public static function render_lazy_book_calendar( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}
		$atts['mode'] = 'calendar';
		return self::render_lazy_book( $atts );
	}

	public static function register_rest_routes(): void {
		register_rest_route( 'ltlb/v1', '/time-slots', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'get_time_slots' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'ltlb/v1', '/slot-resources', [
			'methods' => 'GET',
			'callback' => [ __CLASS__, 'get_slot_resources' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'ltlb/v1', '/hotel/availability', [
			'methods' => 'GET',
			'callback' => [ __CLASS__, 'get_hotel_availability' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'ltlb/v1', '/process-payment', [
			'methods' => 'POST',
			'callback' => [ __CLASS__, 'process_payment' ],
			'permission_callback' => '__return_true',
		] );
	}

	public static function process_payment( WP_REST_Request $request ): WP_REST_Response {
		// Legacy endpoint: disabled for security.
		// Payments are confirmed via provider webhooks (e.g., Stripe Checkout webhook).
		return new WP_REST_Response( [
			'success' => false,
			'error' => 'payment_endpoint_disabled',
		], 410 );
	}

	public static function get_time_slots( WP_REST_Request $request ): WP_REST_Response {
		$limited = self::maybe_rate_limit( 'time_slots' );
		if ( $limited ) return $limited;

		$service_id = intval( $request->get_param( 'service_id' ) );
		$date = sanitize_text_field( (string) $request->get_param( 'date' ) );

		if ( $service_id <= 0 || empty( $date ) ) {
			return new WP_REST_Response( [ 'error' => 'Required parameters are missing.' ], 400 );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_REST_Response( [ 'error' => 'Invalid date.' ], 400 );
		}
		if ( class_exists( 'LTLB_Time' ) && ! LTLB_Time::create_datetime_immutable( $date ) ) {
			return new WP_REST_Response( [ 'error' => 'Invalid date.' ], 400 );
		}

		// Block booking in the past.
		if ( class_exists( 'LTLB_Time' ) ) {
			$tz = LTLB_Time::wp_timezone();
			$day_start = new DateTimeImmutable( $date . ' 00:00:00', $tz );
			$now = new DateTimeImmutable( 'now', $tz );
			if ( $day_start < $now->setTime( 0, 0, 0 ) ) {
				return new WP_REST_Response( [], 200 );
			}
		}

		$availability = new Availability();
		$slots = $availability->compute_time_slots( $service_id, $date );

		return new WP_REST_Response( $slots, 200 );
	}

	public static function get_slot_resources( WP_REST_Request $request ): WP_REST_Response {
		$limited = self::maybe_rate_limit( 'slot_resources' );
		if ( $limited ) return $limited;

		$service_id = intval( $request->get_param( 'service_id' ) );
		$start = sanitize_text_field( (string) $request->get_param( 'start' ) ); // ISO or YYYY-MM-DD HH:MM:SS
		if ( ! $service_id || ! $start ) {
			return new WP_REST_Response( [ 'error' => 'Required parameters are missing.' ], 400 );
		}

		$service_repo = new LTLB_ServiceRepository();
		$service = $service_repo->get_by_id( $service_id );
		if ( ! $service ) return new WP_REST_Response( [ 'error' => 'Invalid service.' ], 400 );

		$duration = intval( $service['duration_min'] ?? 60 );
		$start_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::create_datetime_immutable( $start ) : null;
		if ( ! $start_dt ) return new WP_REST_Response( [ 'error' => 'Invalid start time.' ], 400 );
		if ( class_exists( 'LTLB_Time' ) ) {
			$now = new DateTimeImmutable( 'now', LTLB_Time::wp_timezone() );
			if ( $start_dt < $now ) {
				return new WP_REST_Response( [ 'error' => 'Start time is in the past.' ], 400 );
			}
		}
		$end_dt = $start_dt->modify('+' . $duration . ' minutes');

		$service_resources_repo = new LTLB_ServiceResourcesRepository();
		$resource_repo = new LTLB_ResourceRepository();
		$appt_res_repo = new LTLB_AppointmentResourcesRepository();

		$allowed = $service_resources_repo->get_resources_for_service( $service_id );
		if ( empty( $allowed ) ) {
			$all = $resource_repo->get_all();
			$allowed = array_map(function($r){ return intval($r['id']); }, $all );
		}

		$ls = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls ) ) $ls = [];
		$include_pending = ! empty( $ls['pending_blocks'] );
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

	public static function get_hotel_availability( WP_REST_Request $request ): WP_REST_Response {
		$limited = self::maybe_rate_limit( 'hotel_availability' );
		if ( $limited ) return $limited;

		$service_id = intval( $request->get_param( 'service_id' ) );
		$checkin = sanitize_text_field( (string) $request->get_param( 'checkin' ) );
		$checkout = sanitize_text_field( (string) $request->get_param( 'checkout' ) );
		$guests = absint( $request->get_param( 'guests' ) );
		if ( $guests < 1 ) $guests = 1;

		if ( $service_id <= 0 || empty( $checkin ) || empty( $checkout ) ) {
			return new WP_REST_Response( [ 'error' => 'Required parameters are missing.' ], 400 );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checkin ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checkout ) ) {
			return new WP_REST_Response( [ 'error' => 'Invalid dates.' ], 400 );
		}
		if ( class_exists( 'LTLB_Time' ) ) {
			if ( ! LTLB_Time::create_datetime_immutable( $checkin ) || ! LTLB_Time::create_datetime_immutable( $checkout ) ) {
				return new WP_REST_Response( [ 'error' => 'Invalid dates.' ], 400 );
			}
		}

		$service_repo = new LTLB_ServiceRepository();
		$service = $service_repo->get_by_id( $service_id );
		if ( ! $service ) {
			return new WP_REST_Response( [ 'error' => 'Invalid service.' ], 400 );
		}

		$nights = class_exists( 'LTLB_Time' ) ? LTLB_Time::nights_between( $checkin, $checkout ) : 0;
		if ( $nights < 1 ) {
			return new WP_REST_Response( [ 'error' => 'Invalid dates: check-out must be after check-in.' ], 400 );
		}

		$ls = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls ) ) $ls = [];
		$checkin_time = $ls['hotel_checkin_time'] ?? '15:00';
		$checkout_time = $ls['hotel_checkout_time'] ?? '11:00';

		$checkin_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::combine_date_time( $checkin, $checkin_time ) : null;
		$checkout_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::combine_date_time( $checkout, $checkout_time ) : null;
		if ( ! $checkin_dt || ! $checkout_dt ) {
			return new WP_REST_Response( [ 'error' => 'Invalid date/time format.' ], 400 );
		}
		if ( class_exists( 'LTLB_Time' ) ) {
			$now = new DateTimeImmutable( 'now', LTLB_Time::wp_timezone() );
			if ( $checkin_dt < $now ) {
				return new WP_REST_Response( [ 'error' => 'Check-in is in the past.' ], 400 );
			}
		}

		$start_at_sql = LTLB_Time::format_utc_mysql( $checkin_dt );
		$end_at_sql = LTLB_Time::format_utc_mysql( $checkout_dt );

		$svc_res_repo = new LTLB_ServiceResourcesRepository();
		$res_repo = new LTLB_ResourceRepository();
		$appt_res_repo = new LTLB_AppointmentResourcesRepository();

		$allowed_resources = $svc_res_repo->get_resources_for_service( $service_id );
		if ( empty( $allowed_resources ) ) {
			$all = $res_repo->get_all();
			$allowed_resources = array_map(function($r){ return intval($r['id']); }, $all );
		}

		$include_pending = ! empty( $ls['pending_blocks'] );
		$blocked = $appt_res_repo->get_blocked_resources( $start_at_sql, $end_at_sql, $include_pending );

		$resources = [];
		$free_count = 0;
		foreach ( $allowed_resources as $rid ) {
			$r = $res_repo->get_by_id( intval( $rid ) );
			if ( ! $r ) continue;
			$capacity = intval( $r['capacity'] ?? 1 );
			$used = isset( $blocked[ $rid ] ) ? intval( $blocked[ $rid ] ) : 0;
			$available = max( 0, $capacity - $used );
			$fits = ( $available >= $guests );
			if ( $fits ) $free_count += 1;
			$resources[] = [
				'id' => intval( $r['id'] ),
				'name' => $r['name'],
				'capacity' => $capacity,
				'used' => $used,
				'available' => $available,
				'fits' => $fits,
			];
		}

		$price_cents = intval( $service['price_cents'] ?? 0 );
		$total_price_cents = $price_cents * $nights;

		return new WP_REST_Response( [
			'nights' => $nights,
			'free_resources_count' => $free_count,
			'resources' => $resources,
			'total_price_cents' => $total_price_cents,
			'currency' => $service['currency'] ?? 'EUR',
		], 200 );
	}

	public static function maybe_enqueue_assets(): void {
		global $post;
		if ( empty( $post ) ) return;
		if (
			has_shortcode( $post->post_content, 'lazy_book' ) ||
			has_shortcode( $post->post_content, 'lazy_book_calendar' ) ||
			has_shortcode( $post->post_content, 'lazy_book_bar' ) ||
			has_shortcode( $post->post_content, 'lazy_hotel_bar' ) ||
			has_shortcode( $post->post_content, 'lazy_book_widget' ) ||
			has_shortcode( $post->post_content, 'lazy_hotel_widget' ) ||
			has_shortcode( $post->post_content, 'lazy_services' ) ||
			has_shortcode( $post->post_content, 'lazy_room_types' ) ||
			has_shortcode( $post->post_content, 'lazy_testimonials' ) ||
			has_shortcode( $post->post_content, 'lazy_trust' )
		) {
			self::enqueue_public_assets();
		}
	}

	private static function format_money_from_cents( int $cents, string $currency ): string {
		$amount = $cents / 100;
		$formatted = number_format_i18n( $amount, 2 );
		$currency = $currency ? strtoupper( preg_replace( '/[^A-Za-z]/', '', $currency ) ) : 'EUR';
		return $formatted . ' ' . $currency;
	}
	public static function render_lazy_book( $atts ): string {
		$atts = shortcode_atts(
			[
				'service' => '',
				'mode'    => 'wizard',
			],
			is_array( $atts ) ? $atts : [],
			'lazy_book'
		);

		$prefill_service_id = intval( $atts['service'] ?? 0 );
		if ( $prefill_service_id <= 0 ) {
			$prefill_service_id = isset( $_GET['service'] ) ? intval( $_GET['service'] ) : 0;
		}
		if ( $prefill_service_id <= 0 ) {
			$prefill_service_id = isset( $_GET['service_id'] ) ? intval( $_GET['service_id'] ) : 0;
		}
		$prefill_date = isset( $_GET['date'] ) ? sanitize_text_field( (string) $_GET['date'] ) : '';
		$prefill_time = isset( $_GET['time'] ) ? sanitize_text_field( (string) $_GET['time'] ) : '';
		$prefill_checkin = isset( $_GET['checkin'] ) ? sanitize_text_field( (string) $_GET['checkin'] ) : '';
		$prefill_checkout = isset( $_GET['checkout'] ) ? sanitize_text_field( (string) $_GET['checkout'] ) : '';
		$prefill_guests = isset( $_GET['guests'] ) ? max( 1, intval( $_GET['guests'] ) ) : 1;
		$start_mode = sanitize_key( strval( $atts['mode'] ?? 'wizard' ) );
		if ( $start_mode !== 'calendar' ) {
			$start_mode = 'wizard';
		}

		// Always enqueue assets when the shortcode is actually rendered.
		self::enqueue_public_assets();

		// Add design variables as inline CSS, scoped to the booking widget.
		$design = get_option( 'lazy_design', [] );
		if ( ! is_array( $design ) ) {
			$design = [];
		}

		$bg = $design['background'] ?? '#ffffff';
		$primary = $design['primary'] ?? '#2b7cff';
		$primary_hover = $design['primary_hover'] ?? ( $design['accent'] ?? '#ffcc00' );
		$secondary = $design['secondary'] ?? $primary;
		$secondary_hover = $design['secondary_hover'] ?? $secondary;
		$text = $design['text'] ?? '#222222';
		$accent = $design['accent'] ?? '#ffcc00';
		$border_color = $design['border_color'] ?? '#cccccc';
		$panel_bg = $design['panel_background'] ?? 'transparent';
		$border_width = isset($design['border_width']) ? max(0, intval($design['border_width'])) : 1;
		$border_radius = isset($design['border_radius']) ? max(0, intval($design['border_radius'])) : 4;
		$box_shadow_blur = isset($design['box_shadow_blur']) ? max(0, intval($design['box_shadow_blur'])) : 4;
		$box_shadow_spread = isset($design['box_shadow_spread']) ? max(0, intval($design['box_shadow_spread'])) : 0;
		$transition_duration = isset($design['transition_duration']) ? max(0, intval($design['transition_duration'])) : 200;
		$enable_animations = isset($design['enable_animations']) ? (int) $design['enable_animations'] : 1;
		$use_gradient = isset($design['use_gradient']) ? (int) $design['use_gradient'] : 0;
		$use_auto_button_text = isset($design['auto_button_text']) ? (int) $design['auto_button_text'] : 1;
		$button_text = $design['button_text'] ?? '#ffffff';

		$shadow_container = isset($design['shadow_container']) ? (int) $design['shadow_container'] : 1;
		$shadow_button = isset($design['shadow_button']) ? (int) $design['shadow_button'] : 1;
		$shadow_input = isset($design['shadow_input']) ? (int) $design['shadow_input'] : 0;
		$shadow_card = isset($design['shadow_card']) ? (int) $design['shadow_card'] : 1;

		$bg_final = $use_gradient ? "linear-gradient(135deg, {$primary}, {$accent})" : $bg;

		// Simple contrast helper (black/white)
		$contrast = function( $hex ) {
			$hex = is_string($hex) ? trim($hex) : '';
			if ( ! preg_match('/^#?[0-9A-Fa-f]{6}$/', $hex ) ) return '#ffffff';
			$hex = ltrim($hex, '#');
			$r8 = hexdec(substr($hex, 0, 2));
			$g8 = hexdec(substr($hex, 2, 2));
			$b8 = hexdec(substr($hex, 4, 2));
			$toLinear = function($c){
				$x = $c / 255.0;
				return $x <= 0.03928 ? $x / 12.92 : pow(($x + 0.055) / 1.055, 2.4);
			};
			$r = $toLinear($r8);
			$g = $toLinear($g8);
			$b = $toLinear($b8);
			$L = 0.2126*$r + 0.7152*$g + 0.0722*$b;
			return $L > 0.5 ? '#000000' : '#ffffff';
		};

		if ( $use_auto_button_text ) {
			$button_text = $contrast( $primary );
		}
		$secondary_text = $contrast( $secondary_hover );

		$shadow_container_val = $shadow_container ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';
		$shadow_button_val = $shadow_button ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';
		$shadow_input_val = $shadow_input ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.12)" : 'none';
		$shadow_card_val = $shadow_card ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';
		$transition_val = ($enable_animations ? $transition_duration : 0) . 'ms';

		$vars_css = ".ltlb-booking{"
			. "--lazy-bg:{$bg_final};"
			. "--lazy-primary:{$primary};"
			. "--lazy-primary-hover:{$primary_hover};"
			. "--lazy-secondary:{$secondary};"
			. "--lazy-secondary-hover:{$secondary_hover};"
			. "--lazy-secondary-text:{$secondary_text};"
			. "--lazy-text:{$text};"
			. "--lazy-accent:{$accent};"
			. "--lazy-border-color:{$border_color};"
			. "--lazy-panel-bg:{$panel_bg};"
			. "--lazy-button-text:{$button_text};"
			. "--lazy-border-width:{$border_width}px;"
			. "--lazy-border-radius:{$border_radius}px;"
			. "--lazy-shadow-container:{$shadow_container_val};"
			. "--lazy-shadow-button:{$shadow_button_val};"
			. "--lazy-shadow-input:{$shadow_input_val};"
			. "--lazy-shadow-card:{$shadow_card_val};"
			. "--lazy-transition-duration:{$transition_val};"
			. "}";

		wp_add_inline_style( 'ltlb-public', $vars_css );

		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ltlb_book_submit'] ) ) {
			return self::handle_submission();
		}
	
	// Force fresh settings read (bypass any object cache)
	wp_cache_delete('lazy_settings', 'options');
	$settings = get_option( 'lazy_settings', [] );
	$template_mode = is_array($settings) && isset($settings['template_mode']) ? $settings['template_mode'] : 'service';
	$is_hotel_mode = ( $template_mode === 'hotel' );
	
	$service_repo = new LTLB_ServiceRepository();
	$services = $service_repo->get_all();
	
	// Ensure services is always an array
	if ( ! is_array($services) ) {
		$services = [];
	}

		// Payments (for UI): show only enabled methods; show online only if configured.
		$ls_pay = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls_pay ) ) $ls_pay = [];
		$enable_payments_ui = ! empty( $ls_pay['enable_payments'] );
		$methods = [];
		if ( $enable_payments_ui && ! empty( $ls_pay['payment_methods'] ) && is_array( $ls_pay['payment_methods'] ) ) {
			foreach ( $ls_pay['payment_methods'] as $m ) {
				$m = sanitize_key( (string) $m );
				if ( $m !== '' ) $methods[] = $m;
			}
		}
		$methods = array_values( array_unique( $methods ) );
		// Gate online methods behind actual provider configuration.
		$online_available = class_exists( 'LTLB_PaymentEngine' ) && LTLB_PaymentEngine::instance()->is_enabled();
		if ( ! $online_available ) {
			$methods = array_values( array_filter( $methods, function( $m ) {
				return $m !== 'stripe_card' && $m !== 'paypal' && $m !== 'klarna';
			} ) );
		}
		$ltlb_payment_methods = $methods;
		$ltlb_default_payment_method = '';
		if ( in_array( 'stripe_card', $ltlb_payment_methods, true ) ) {
			$ltlb_default_payment_method = 'stripe_card';
		} elseif ( ! empty( $ltlb_payment_methods ) ) {
			$ltlb_default_payment_method = (string) $ltlb_payment_methods[0];
		}
	
	ob_start();
	$template_path = __DIR__ . '/Templates/wizard.php';
	if ( $start_mode === 'calendar' ) {
		$calendar_path = __DIR__ . '/Templates/calendar.php';
		if ( file_exists( $calendar_path ) ) {
			include $calendar_path;
			return ob_get_clean();
		}
	}

	if ( file_exists( $template_path ) ) {
		include $template_path;
	} else {
		echo '<div class="ltlb-booking"><div class="ltlb-error">' . esc_html__( 'Template file not found:', 'ltl-bookings' ) . ' ' . esc_html( $template_path ) . '</div></div>';
	}
	return ob_get_clean();
	}
	private static function handle_submission(): string {
		$valid = self::_validate_submission();
		if ( is_wp_error( $valid ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Error:', 'ltl-bookings' ) . '</strong> ' . esc_html( $valid->get_error_message() ) . '</div></div>';
		}
		if ( $valid !== true ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Error:', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Your request could not be processed. Please try again.', 'ltl-bookings' ) . '</div></div>';
		}

		$is_hotel_mode = self::_is_hotel_mode();
		$data = $is_hotel_mode ? self::_get_sanitized_hotel_submission_data() : self::_get_sanitized_submission_data();

		if ( is_wp_error( $data ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Error:', 'ltl-bookings' ) . '</strong> ' . esc_html( $data->get_error_message() ) . '</div></div>';
		}

		$appointment_id = $is_hotel_mode ? self::_create_hotel_booking_from_submission( $data ) : self::_create_appointment_from_submission( $data );

		if ( is_wp_error( $appointment_id ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Error:', 'ltl-bookings' ) . '</strong> ' . esc_html( $appointment_id->get_error_message() ) . '</div></div>';
		}

		$appt_repo = new LTLB_AppointmentRepository();
		$appointment = $appt_repo->get_by_id( intval( $appointment_id ) );
		$price = is_array( $appointment ) ? floatval( $appointment['price'] ?? 0 ) : 0;
		if ( $price <= 0 && is_array( $appointment ) && isset( $appointment['amount_cents'] ) ) {
			$price = floatval( intval( $appointment['amount_cents'] ) ) / 100;
		}

		// Determine selected payment method (only if a price exists).
		$ls_pay = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls_pay ) ) $ls_pay = [];
		$enable_payments = ! empty( $ls_pay['enable_payments'] );
		$enabled_methods = [];
		if ( $enable_payments && ! empty( $ls_pay['payment_methods'] ) && is_array( $ls_pay['payment_methods'] ) ) {
			foreach ( $ls_pay['payment_methods'] as $m ) {
				$m = sanitize_key( (string) $m );
				if ( $m !== '' ) $enabled_methods[] = $m;
			}
		}
		$enabled_methods = array_values( array_unique( $enabled_methods ) );

		// Safety: if the booking has a price and payments are enabled, do not allow completing
		// the booking with no configured payment methods (would look like a free booking).
		if ( $price > 0 && $enable_payments && empty( $enabled_methods ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>'
				. esc_html__( 'Payment required:', 'ltl-bookings' )
				. '</strong> '
				. esc_html__( 'Online payments are enabled, but no payment methods are configured. Please contact us or try again later.', 'ltl-bookings' )
				. '</div></div>';
		}
		$payment_method = isset( $data['payment_method'] ) ? sanitize_key( (string) $data['payment_method'] ) : '';
		if ( $payment_method === '' ) {
			$payment_method = in_array( 'stripe_card', $enabled_methods, true ) ? 'stripe_card' : ( ! empty( $enabled_methods ) ? (string) $enabled_methods[0] : '' );
		}
		if ( $payment_method !== '' && ! in_array( $payment_method, $enabled_methods, true ) ) {
			$payment_method = in_array( 'stripe_card', $enabled_methods, true ) ? 'stripe_card' : ( ! empty( $enabled_methods ) ? (string) $enabled_methods[0] : '' );
		}

		// Paid flow: Stripe Checkout redirect.
		$payment_engine = class_exists( 'LTLB_PaymentEngine' ) ? LTLB_PaymentEngine::instance() : null;
		$online_methods = [ 'stripe_card', 'klarna', 'paypal' ];
		if ( $price > 0 && $enable_payments && in_array( $payment_method, $online_methods, true ) && $payment_engine ) {
			$return_base = function_exists( 'get_permalink' ) ? get_permalink() : home_url( '/' );
			$success_url = add_query_arg(
				[
					'ltlb_payment_return' => '1',
					'provider' => $payment_method === 'paypal' ? 'paypal' : 'stripe',
					'status' => 'success',
					'appointment_id' => intval( $appointment_id ),
				],
				$return_base
			);
			$cancel_url = add_query_arg(
				[
					'ltlb_payment_return' => '1',
					'provider' => $payment_method === 'paypal' ? 'paypal' : 'stripe',
					'status' => 'cancel',
					'appointment_id' => intval( $appointment_id ),
				],
				$return_base
			);

			global $wpdb;
			$table = $wpdb->prefix . 'lazy_appointments';

			if ( $payment_method === 'paypal' && method_exists( $payment_engine, 'create_paypal_redirect' ) && method_exists( $payment_engine, 'is_paypal_enabled' ) && $payment_engine->is_paypal_enabled() ) {
				$paypal_res = $payment_engine->create_paypal_redirect( is_array( $appointment ) ? $appointment : [], $success_url, $cancel_url );
				if ( is_array( $paypal_res ) && ! empty( $paypal_res['approve_url'] ) && ! empty( $paypal_res['order_id'] ) ) {
					$wpdb->update(
						$table,
						[ 'payment_method' => 'paypal', 'payment_ref' => (string) $paypal_res['order_id'], 'updated_at' => current_time( 'mysql' ) ],
						[ 'id' => intval( $appointment_id ) ],
						[ '%s', '%s', '%s' ],
						[ '%d' ]
					);
					wp_safe_redirect( esc_url_raw( (string) $paypal_res['approve_url'] ) );
					exit;
				}
			}

			if ( $payment_method !== 'paypal' && method_exists( $payment_engine, 'create_checkout_redirect' ) && method_exists( $payment_engine, 'is_stripe_enabled' ) && $payment_engine->is_stripe_enabled() ) {
				$appointment_for_payment = is_array( $appointment ) ? $appointment : [];
				$appointment_for_payment['payment_method'] = $payment_method;
				$redirect = $payment_engine->create_checkout_redirect( $appointment_for_payment, $success_url, $cancel_url );
				if ( is_array( $redirect ) && ! empty( $redirect['checkout_url'] ) ) {
					$wpdb->update(
						$table,
						[ 'payment_method' => $payment_method, 'updated_at' => current_time( 'mysql' ) ],
						[ 'id' => intval( $appointment_id ) ],
						[ '%s', '%s' ],
						[ '%d' ]
					);
					wp_safe_redirect( esc_url_raw( (string) $redirect['checkout_url'] ) );
					exit;
				}
			}

			// If an online method was chosen but we couldn't start checkout, do not fall back to offline flows.
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Payment error:', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'We could not start the online payment. Please try again or choose a different payment method.', 'ltl-bookings' ) . '</div></div>';
		}

		// Offline/invoice (or free): send email notifications immediately.
		if ( class_exists( 'LTLB_EmailNotifications' ) ) {
			LTLB_EmailNotifications::send_customer_booking_confirmation( intval( $appointment_id ) );
			LTLB_EmailNotifications::send_admin_booking_notification( intval( $appointment_id ) );
		}

		if ( $price > 0 && $enable_payments && $payment_method === 'invoice' ) {
			return '<div class="ltlb-booking"><div class="ltlb-success"><strong>' . esc_html__( 'Booking received', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'You selected company invoice. We will contact you with the invoice details. Your booking is awaiting confirmation.', 'ltl-bookings' ) . '</div></div>';
		}
		if ( $price > 0 && $enable_payments && ( $payment_method === 'cash' || $payment_method === 'pos_card' ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-success"><strong>' . esc_html__( 'Booking received', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'You selected payment on site. Your booking is awaiting confirmation.', 'ltl-bookings' ) . '</div></div>';
		}

		return '<div class="ltlb-booking"><div class="ltlb-success"><strong>' . esc_html__( 'Success!', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Your booking has been received and is awaiting confirmation. Please check your email for details.', 'ltl-bookings' ) . '</div></div>';
	}

	private static function _is_hotel_mode(): bool {
		wp_cache_delete('lazy_settings', 'options');
		$settings = get_option( 'lazy_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		return ( ( $settings['template_mode'] ?? 'service' ) === 'hotel' );
	}

	private static function _validate_submission(): bool|WP_Error {
		$nonce = isset( $_POST['ltlb_book_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ltlb_book_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ltlb_book_action' ) ) {
			return new WP_Error( 'ltlb_nonce_failed', __( 'Security check failed. Please reload the page and try again.', 'ltl-bookings' ) );
		}

		// Honeypot: if filled, silently fail
		if ( ! empty( $_POST['ltlb_hp'] ) ) {
			return false;
		}

		// Simple rate limit per IP: 10 submits per 10 minutes
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		if ( $ip ) {
			$key = 'ltlb_rate_' . md5( $ip );
			$count = (int) get_transient( $key );
			if ( $count >= 10 ) {
				return new WP_Error( 'ltlb_rate_limited', __( 'Too many requests. Please try again in a few minutes.', 'ltl-bookings' ) );
			}
			set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		}

		return true;
	}

	private static function _get_sanitized_submission_data(): array|WP_Error {
		$service_id = isset( $_POST['service_id'] ) ? intval( $_POST['service_id'] ) : 0;
		$date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
		$time = isset( $_POST['time_slot'] ) ? sanitize_text_field( $_POST['time_slot'] ) : '';

		$email = LTLB_Sanitizer::email( $_POST['email'] ?? '' );
		$first = LTLB_Sanitizer::text( $_POST['first_name'] ?? '' );
		$last = LTLB_Sanitizer::text( $_POST['last_name'] ?? '' );
		$phone = LTLB_Sanitizer::text( $_POST['phone'] ?? '' );
		$payment_method = isset( $_POST['payment_method'] ) ? sanitize_key( (string) wp_unslash( $_POST['payment_method'] ) ) : '';
		$company_name = isset( $_POST['company_name'] ) ? LTLB_Sanitizer::text( (string) wp_unslash( $_POST['company_name'] ) ) : '';
		$company_vat = isset( $_POST['company_vat'] ) ? LTLB_Sanitizer::text( (string) wp_unslash( $_POST['company_vat'] ) ) : '';

		if ( empty( $service_id ) || empty( $date ) || empty( $time ) || empty( $email ) ) {
			return new WP_Error( 'missing_fields', __( 'Please fill in the required fields.', 'ltl-bookings' ) );
		}

		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;

		if ( $payment_method === 'invoice' && empty( $company_name ) ) {
			return new WP_Error( 'missing_company', __( 'Please enter your company name for invoices.', 'ltl-bookings' ) );
		}

		return compact( 'service_id', 'date', 'time', 'email', 'first', 'last', 'phone', 'resource_id', 'payment_method', 'company_name', 'company_vat' );
	}

	private static function _get_sanitized_hotel_submission_data(): array|WP_Error {
		$service_id = isset( $_POST['service_id'] ) ? intval( $_POST['service_id'] ) : 0;
		$checkin = isset( $_POST['checkin'] ) ? sanitize_text_field( (string) $_POST['checkin'] ) : '';
		$checkout = isset( $_POST['checkout'] ) ? sanitize_text_field( (string) $_POST['checkout'] ) : '';
		$guests = isset( $_POST['guests'] ) ? max( 1, intval( $_POST['guests'] ) ) : 1;

		$email = LTLB_Sanitizer::email( $_POST['email'] ?? '' );
		$first = LTLB_Sanitizer::text( $_POST['first_name'] ?? '' );
		$last = LTLB_Sanitizer::text( $_POST['last_name'] ?? '' );
		$phone = LTLB_Sanitizer::text( $_POST['phone'] ?? '' );
		$payment_method = isset( $_POST['payment_method'] ) ? sanitize_key( (string) wp_unslash( $_POST['payment_method'] ) ) : '';
		$company_name = isset( $_POST['company_name'] ) ? LTLB_Sanitizer::text( (string) wp_unslash( $_POST['company_name'] ) ) : '';
		$company_vat = isset( $_POST['company_vat'] ) ? LTLB_Sanitizer::text( (string) wp_unslash( $_POST['company_vat'] ) ) : '';

		if ( empty( $service_id ) || empty( $checkin ) || empty( $checkout ) || empty( $email ) || empty( $guests ) ) {
			return new WP_Error( 'missing_fields', __( 'Please fill in the required fields.', 'ltl-bookings' ) );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checkin ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checkout ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid dates.', 'ltl-bookings' ) );
		}
		if ( class_exists( 'LTLB_Time' ) ) {
			if ( ! LTLB_Time::create_datetime_immutable( $checkin ) || ! LTLB_Time::create_datetime_immutable( $checkout ) ) {
				return new WP_Error( 'invalid_date', __( 'Invalid dates.', 'ltl-bookings' ) );
			}
		}

		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;

		if ( $payment_method === 'invoice' && empty( $company_name ) ) {
			return new WP_Error( 'missing_company', __( 'Please enter your company name for invoices.', 'ltl-bookings' ) );
		}

		return compact( 'service_id', 'checkin', 'checkout', 'guests', 'email', 'first', 'last', 'phone', 'resource_id', 'payment_method', 'company_name', 'company_vat' );
	}

	private static function _create_hotel_booking_from_submission( array $data ): int|WP_Error {
		return LTLB_BookingService::create_hotel_booking_from_submission( $data );
	}

	private static function _create_appointment_from_submission( array $data ): int|WP_Error {
		return LTLB_BookingService::create_service_booking_from_submission( $data );
	}

	/**
	 * Quick Booking Bar - Sticky header with quick booking (like Booking.com)
	 * [lazy_book_bar position="top" sticky="true" background="dark"]
	 */
	public static function render_booking_bar( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}

		$position = isset( $atts['position'] ) ? sanitize_text_field( $atts['position'] ) : 'top';
		$sticky = isset( $atts['sticky'] ) && $atts['sticky'] !== 'false' ? 'true' : 'false';
		$bg_style = isset( $atts['background'] ) ? sanitize_text_field( $atts['background'] ) : 'primary';
		$target = isset( $atts['target'] ) ? esc_url_raw( (string) $atts['target'] ) : '';
		$mode = isset( $atts['mode'] ) ? sanitize_key( (string) $atts['mode'] ) : 'wizard';
		if ( $mode !== 'calendar' ) {
			$mode = 'wizard';
		}

		$service_repo = new LTLB_ServiceRepository();
		$services = $service_repo->get_all();

		if ( empty( $services ) ) {
			return '';
		}

		ob_start();
		$action_url = $target ? $target : ( function_exists( 'get_permalink' ) ? get_permalink() : '' );
		$action_url = $action_url ? $action_url : home_url( '/' );
		?>
		<div class="ltlb-booking-bar" data-position="<?php echo esc_attr( $position ); ?>" data-sticky="<?php echo esc_attr( $sticky ); ?>" data-bg="<?php echo esc_attr( $bg_style ); ?>">
			<div class="ltlb-booking-bar__container">
				<form method="get" class="ltlb-booking-bar__form" action="<?php echo esc_url( $action_url ); ?>">
					<input type="hidden" name="mode" value="<?php echo esc_attr( $mode ); ?>">

					<div class="ltlb-booking-bar__group">
						<label for="ltlb-qb-service">
							<span class="ltlb-booking-bar__label"><?php esc_html_e( 'Service', 'ltl-bookings' ); ?></span>
							<select id="ltlb-qb-service" name="service_id" class="ltlb-booking-bar__select" required>
								<option value=""><?php esc_html_e( 'Select service', 'ltl-bookings' ); ?></option>
								<?php foreach ( $services as $service ): ?>
									<option value="<?php echo esc_attr( $service['id'] ); ?>">
										<?php echo esc_html( $service['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>

					<div class="ltlb-booking-bar__group">
						<label for="ltlb-qb-date">
							<span class="ltlb-booking-bar__label"><?php esc_html_e( 'Date', 'ltl-bookings' ); ?></span>
							<input type="date" id="ltlb-qb-date" name="date" class="ltlb-booking-bar__input" required>
						</label>
					</div>

					<div class="ltlb-booking-bar__group">
						<label for="ltlb-qb-time">
							<span class="ltlb-booking-bar__label"><?php esc_html_e( 'Time', 'ltl-bookings' ); ?></span>
							<input type="time" id="ltlb-qb-time" name="time" class="ltlb-booking-bar__input" required>
						</label>
					</div>

					<button type="submit" class="ltlb-booking-bar__btn">
						<?php esc_html_e( 'Book Now', 'ltl-bookings' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Quick Hotel Booking Bar
	 * [lazy_hotel_bar position="top" sticky="true" background="primary" target="/booking" ]
	 */
	public static function render_hotel_booking_bar( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}

		$position = isset( $atts['position'] ) ? sanitize_text_field( $atts['position'] ) : 'top';
		$sticky = isset( $atts['sticky'] ) && $atts['sticky'] !== 'false' ? 'true' : 'false';
		$bg_style = isset( $atts['background'] ) ? sanitize_text_field( $atts['background'] ) : 'primary';
		$target = isset( $atts['target'] ) ? esc_url_raw( (string) $atts['target'] ) : '';
		$action_url = $target ? $target : ( function_exists( 'get_permalink' ) ? get_permalink() : '' );
		$action_url = $action_url ? $action_url : home_url( '/' );

		$service_repo = new LTLB_ServiceRepository();
		$services = $service_repo->get_all();
		if ( empty( $services ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="ltlb-booking-bar ltlb-booking-bar--hotel" data-position="<?php echo esc_attr( $position ); ?>" data-sticky="<?php echo esc_attr( $sticky ); ?>" data-bg="<?php echo esc_attr( $bg_style ); ?>">
			<div class="ltlb-booking-bar__container">
				<form method="get" class="ltlb-booking-bar__form" action="<?php echo esc_url( $action_url ); ?>">
					<input type="hidden" name="mode" value="wizard">
					<div class="ltlb-booking-bar__group">
						<label for="ltlb-qh-service">
							<span class="ltlb-booking-bar__label"><?php esc_html_e( 'Room type', 'ltl-bookings' ); ?></span>
							<select id="ltlb-qh-service" name="service_id" class="ltlb-booking-bar__select" required>
								<option value=""><?php esc_html_e( 'Select room type', 'ltl-bookings' ); ?></option>
								<?php foreach ( $services as $service ): ?>
									<option value="<?php echo esc_attr( $service['id'] ); ?>"><?php echo esc_html( $service['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>

					<div class="ltlb-booking-bar__group">
						<label for="ltlb-qh-checkin">
							<span class="ltlb-booking-bar__label"><?php esc_html_e( 'Check-in', 'ltl-bookings' ); ?></span>
							<input type="date" id="ltlb-qh-checkin" name="checkin" class="ltlb-booking-bar__input" required>
						</label>
					</div>

					<div class="ltlb-booking-bar__group">
						<label for="ltlb-qh-checkout">
							<span class="ltlb-booking-bar__label"><?php esc_html_e( 'Check-out', 'ltl-bookings' ); ?></span>
							<input type="date" id="ltlb-qh-checkout" name="checkout" class="ltlb-booking-bar__input" required>
						</label>
					</div>

					<div class="ltlb-booking-bar__group">
						<label for="ltlb-qh-guests">
							<span class="ltlb-booking-bar__label"><?php esc_html_e( 'Guests', 'ltl-bookings' ); ?></span>
							<input type="number" id="ltlb-qh-guests" name="guests" class="ltlb-booking-bar__input" min="1" value="1" required>
						</label>
					</div>

					<button type="submit" class="ltlb-booking-bar__btn">
						<?php esc_html_e( 'Check availability', 'ltl-bookings' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Booking Widget (appointment mode)
	 * A non-sticky, card-like booking form for landing pages.
	 * [lazy_book_widget target="/booking" mode="wizard" title="Book in seconds" subtitle="Pick a service and time"]
	 */
	public static function render_booking_widget( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}
		$title = isset( $atts['title'] ) ? sanitize_text_field( (string) $atts['title'] ) : '';
		$subtitle = isset( $atts['subtitle'] ) ? sanitize_text_field( (string) $atts['subtitle'] ) : '';
		$target = isset( $atts['target'] ) ? esc_url_raw( (string) $atts['target'] ) : '';
		$style = isset( $atts['style'] ) ? sanitize_key( (string) $atts['style'] ) : 'default';
		if ( $style !== 'default' && $style !== 'compact' && $style !== 'flat' ) {
			$style = 'default';
		}
		$mode = isset( $atts['mode'] ) ? sanitize_key( (string) $atts['mode'] ) : 'wizard';
		if ( $mode !== 'calendar' ) {
			$mode = 'wizard';
		}

		$service_repo = new LTLB_ServiceRepository();
		$services = $service_repo->get_all();
		if ( empty( $services ) ) {
			return '';
		}

		$action_url = $target ? $target : ( function_exists( 'get_permalink' ) ? get_permalink() : '' );
		$action_url = $action_url ? $action_url : home_url( '/' );

		ob_start();
		?>
		<div class="ltlb-booking-widget" data-variant="service" data-style="<?php echo esc_attr( $style ); ?>">
			<?php if ( $title || $subtitle ): ?>
				<div class="ltlb-booking-widget__head">
					<?php if ( $title ): ?><h3 class="ltlb-booking-widget__title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>
					<?php if ( $subtitle ): ?><p class="ltlb-booking-widget__subtitle"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>

			<form method="get" class="ltlb-booking-widget__form" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="mode" value="<?php echo esc_attr( $mode ); ?>">
				<div class="ltlb-booking-widget__grid">
					<label class="ltlb-booking-widget__field">
						<span class="ltlb-booking-widget__label"><?php esc_html_e( 'Service', 'ltl-bookings' ); ?></span>
						<select name="service_id" class="ltlb-booking-widget__control" required>
							<option value=""><?php esc_html_e( 'Select service', 'ltl-bookings' ); ?></option>
							<?php foreach ( $services as $service ): ?>
								<option value="<?php echo esc_attr( $service['id'] ); ?>"><?php echo esc_html( $service['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<label class="ltlb-booking-widget__field">
						<span class="ltlb-booking-widget__label"><?php esc_html_e( 'Date', 'ltl-bookings' ); ?></span>
						<input type="date" name="date" class="ltlb-booking-widget__control" required>
					</label>

					<label class="ltlb-booking-widget__field">
						<span class="ltlb-booking-widget__label"><?php esc_html_e( 'Time', 'ltl-bookings' ); ?></span>
						<input type="time" name="time" class="ltlb-booking-widget__control" required>
					</label>
				</div>

				<div class="ltlb-booking-widget__actions">
					<button type="submit" class="ltlb-booking-widget__btn"><?php esc_html_e( 'Book now', 'ltl-bookings' ); ?></button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Booking Widget (hotel mode)
	 * [lazy_hotel_widget target="/booking" title="Find your stay" subtitle="Choose dates and guests"]
	 */
	public static function render_hotel_booking_widget( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}
		$title = isset( $atts['title'] ) ? sanitize_text_field( (string) $atts['title'] ) : '';
		$subtitle = isset( $atts['subtitle'] ) ? sanitize_text_field( (string) $atts['subtitle'] ) : '';
		$target = isset( $atts['target'] ) ? esc_url_raw( (string) $atts['target'] ) : '';
		$style = isset( $atts['style'] ) ? sanitize_key( (string) $atts['style'] ) : 'default';
		if ( $style !== 'default' && $style !== 'compact' && $style !== 'flat' ) {
			$style = 'default';
		}
		$action_url = $target ? $target : ( function_exists( 'get_permalink' ) ? get_permalink() : '' );
		$action_url = $action_url ? $action_url : home_url( '/' );

		$service_repo = new LTLB_ServiceRepository();
		$services = $service_repo->get_all();
		if ( empty( $services ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="ltlb-booking-widget" data-variant="hotel" data-style="<?php echo esc_attr( $style ); ?>">
			<?php if ( $title || $subtitle ): ?>
				<div class="ltlb-booking-widget__head">
					<?php if ( $title ): ?><h3 class="ltlb-booking-widget__title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>
					<?php if ( $subtitle ): ?><p class="ltlb-booking-widget__subtitle"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>

			<form method="get" class="ltlb-booking-widget__form" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="mode" value="wizard">
				<div class="ltlb-booking-widget__grid">
					<label class="ltlb-booking-widget__field">
						<span class="ltlb-booking-widget__label"><?php esc_html_e( 'Room type', 'ltl-bookings' ); ?></span>
						<select name="service_id" class="ltlb-booking-widget__control" required>
							<option value=""><?php esc_html_e( 'Select room type', 'ltl-bookings' ); ?></option>
							<?php foreach ( $services as $service ): ?>
								<option value="<?php echo esc_attr( $service['id'] ); ?>"><?php echo esc_html( $service['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<label class="ltlb-booking-widget__field">
						<span class="ltlb-booking-widget__label"><?php esc_html_e( 'Check-in', 'ltl-bookings' ); ?></span>
						<input type="date" name="checkin" class="ltlb-booking-widget__control" required>
					</label>

					<label class="ltlb-booking-widget__field">
						<span class="ltlb-booking-widget__label"><?php esc_html_e( 'Check-out', 'ltl-bookings' ); ?></span>
						<input type="date" name="checkout" class="ltlb-booking-widget__control" required>
					</label>

					<label class="ltlb-booking-widget__field">
						<span class="ltlb-booking-widget__label"><?php esc_html_e( 'Guests', 'ltl-bookings' ); ?></span>
						<input type="number" name="guests" class="ltlb-booking-widget__control" min="1" value="1" required>
					</label>
				</div>

				<div class="ltlb-booking-widget__actions">
					<button type="submit" class="ltlb-booking-widget__btn"><?php esc_html_e( 'Check availability', 'ltl-bookings' ); ?></button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Services Grid - Display all services in a card grid
	 * [lazy_services columns="3" show_price="true" show_description="true"]
	 */
	public static function render_services_grid( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}

		$columns = isset( $atts['columns'] ) ? max( 1, intval( $atts['columns'] ) ) : 3;
		$show_price = isset( $atts['show_price'] ) && $atts['show_price'] !== 'false';
		$show_desc = isset( $atts['show_description'] ) && $atts['show_description'] !== 'false';
		$target = isset( $atts['target'] ) ? esc_url_raw( (string) $atts['target'] ) : '';
		$mode = isset( $atts['mode'] ) ? sanitize_key( (string) $atts['mode'] ) : '';
		if ( $mode !== 'wizard' && $mode !== 'calendar' ) {
			$mode = '';
		}
		$target_url = $target ? $target : ( function_exists( 'get_permalink' ) ? get_permalink() : '' );
		$target_url = $target_url ? $target_url : home_url( '/' );

		$service_repo = new LTLB_ServiceRepository();
		$services = $service_repo->get_all();

		if ( empty( $services ) ) {
			return '<p class="ltlb-empty-message">' . esc_html__( 'No services available.', 'ltl-bookings' ) . '</p>';
		}

		ob_start();
		?>
		<div class="ltlb-services-grid" data-columns="<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $services as $service ): ?>
				<div class="ltlb-service-card">
					<div class="ltlb-service-card__header">
						<h3 class="ltlb-service-card__title"><?php echo esc_html( $service['name'] ); ?></h3>
						<?php
							$price_cents = intval( $service['price_cents'] ?? 0 );
							$currency = $service['currency'] ?? 'EUR';
						?>
						<?php if ( $show_price && $price_cents > 0 ): ?>
							<span class="ltlb-service-card__price">
								<?php echo esc_html( self::format_money_from_cents( $price_cents, (string) $currency ) ); ?>
							</span>
						<?php endif; ?>
					</div>

					<?php if ( $show_desc && ! empty( $service['description'] ) ): ?>
						<div class="ltlb-service-card__description">
							<?php echo wp_kses_post( wp_trim_words( $service['description'], 20 ) ); ?>
						</div>
					<?php endif; ?>

					<div class="ltlb-service-card__footer">
						<?php
							$link_args = [ 'service_id' => $service['id'] ];
							if ( $mode ) {
								$link_args['mode'] = $mode;
							}
							$book_url = add_query_arg( $link_args, $target_url );
						?>
						<a href="<?php echo esc_url( $book_url ); ?>" class="ltlb-service-card__link button button-primary">
							<?php esc_html_e( 'Book Now', 'ltl-bookings' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Room Types Grid (Hotel)
	 * [lazy_room_types columns="3" show_price="true" show_amenities="true"]
	 */
	public static function render_room_types_grid( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}

		$columns = isset( $atts['columns'] ) ? max( 1, intval( $atts['columns'] ) ) : 3;
		$show_price = isset( $atts['show_price'] ) && $atts['show_price'] !== 'false';
		$show_amenities = isset( $atts['show_amenities'] ) && $atts['show_amenities'] !== 'false';
		$target = isset( $atts['target'] ) ? esc_url_raw( (string) $atts['target'] ) : '';
		$target_url = $target ? $target : ( function_exists( 'get_permalink' ) ? get_permalink() : '' );
		$target_url = $target_url ? $target_url : home_url( '/' );

		$service_repo = new LTLB_ServiceRepository();
		$services = $service_repo->get_all();
		if ( empty( $services ) ) {
			return '<p class="ltlb-empty-message">' . esc_html__( 'No room types available.', 'ltl-bookings' ) . '</p>';
		}

		ob_start();
		?>
		<div class="ltlb-services-grid ltlb-services-grid--hotel" data-columns="<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $services as $service ): ?>
				<div class="ltlb-service-card ltlb-room-card">
					<div class="ltlb-service-card__header">
						<h3 class="ltlb-service-card__title"><?php echo esc_html( $service['name'] ); ?></h3>
						<?php
							$price_cents = intval( $service['price_cents'] ?? 0 );
							$currency = $service['currency'] ?? 'EUR';
						?>
						<?php if ( $show_price && $price_cents > 0 ): ?>
							<span class="ltlb-service-card__price"><?php echo esc_html( self::format_money_from_cents( $price_cents, (string) $currency ) ); ?></span>
						<?php endif; ?>
					</div>

					<?php
						$meta_bits = [];
						$beds = trim( (string) ( $service['beds_type'] ?? '' ) );
						$adults = intval( $service['max_adults'] ?? 0 );
						$children = intval( $service['max_children'] ?? 0 );
						if ( $beds ) $meta_bits[] = $beds;
						if ( $adults > 0 ) $meta_bits[] = sprintf( __( 'Max adults: %d', 'ltl-bookings' ), $adults );
						if ( $children > 0 ) $meta_bits[] = sprintf( __( 'Max children: %d', 'ltl-bookings' ), $children );
					?>
					<?php if ( ! empty( $meta_bits ) ): ?>
						<div class="ltlb-service-card__description">
							<?php echo esc_html( implode( ' • ', $meta_bits ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $show_amenities && ! empty( $service['amenities'] ) ): ?>
						<div class="ltlb-service-card__description">
							<?php echo wp_kses_post( wp_trim_words( (string) $service['amenities'], 24 ) ); ?>
						</div>
					<?php endif; ?>

					<div class="ltlb-service-card__footer">
						<a href="<?php echo esc_url( add_query_arg( 'service_id', $service['id'], $target_url ) ); ?>" class="ltlb-service-card__link button button-primary">
							<?php esc_html_e( 'Check availability', 'ltl-bookings' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Trust / USP section
	 * (Replaces the old testimonials block, which required collecting reviews.)
	 * [lazy_trust title="Why book with us" subtitle="Fast, clear, and reliable" button_url="/booking" button_text="Start booking"]
	 */
	public static function render_trust_section( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}

		$title = isset( $atts['title'] ) ? sanitize_text_field( (string) $atts['title'] ) : '';
		$subtitle = isset( $atts['subtitle'] ) ? sanitize_text_field( (string) $atts['subtitle'] ) : '';
		$style = isset( $atts['style'] ) ? sanitize_key( (string) $atts['style'] ) : 'default';
		if ( $style !== 'default' && $style !== 'compact' && $style !== 'flat' ) {
			$style = 'default';
		}
		$button_url = isset( $atts['button_url'] ) ? esc_url_raw( (string) $atts['button_url'] ) : '';
		$button_text = isset( $atts['button_text'] ) ? sanitize_text_field( (string) $atts['button_text'] ) : '';
		if ( ! $button_text ) {
			$button_text = __( 'Start booking', 'ltl-bookings' );
		}
		if ( ! $button_url ) {
			$button_url = function_exists( 'get_permalink' ) ? (string) get_permalink() : '';
		}
		$button_url = $button_url ? $button_url : home_url( '/' );

		$items = [
			[ 'title' => __( 'Clear availability', 'ltl-bookings' ), 'text' => __( 'See what is available and pick your preferred time in seconds.', 'ltl-bookings' ) ],
			[ 'title' => __( 'Instant confirmation flow', 'ltl-bookings' ), 'text' => __( 'A guided booking process that prevents mistakes and keeps everything consistent.', 'ltl-bookings' ) ],
			[ 'title' => __( 'Mobile-first design', 'ltl-bookings' ), 'text' => __( 'Optimized for phone, tablet, and desktop — without breaking your theme.', 'ltl-bookings' ) ],
		];

		ob_start();
		?>
		<section class="ltlb-trust" data-style="<?php echo esc_attr( $style ); ?>" aria-label="<?php echo esc_attr__( 'Trust & highlights', 'ltl-bookings' ); ?>">
			<div class="ltlb-trust__inner">
				<?php if ( $title || $subtitle ): ?>
					<header class="ltlb-trust__head">
						<?php if ( $title ): ?><h2 class="ltlb-trust__title"><?php echo esc_html( $title ); ?></h2><?php endif; ?>
						<?php if ( $subtitle ): ?><p class="ltlb-trust__subtitle"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
					</header>
				<?php endif; ?>

				<div class="ltlb-trust__grid">
					<?php foreach ( $items as $item ): ?>
						<div class="ltlb-trust__card">
							<h3 class="ltlb-trust__card-title"><?php echo esc_html( $item['title'] ); ?></h3>
							<p class="ltlb-trust__card-text"><?php echo esc_html( $item['text'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="ltlb-trust__cta">
					<a class="ltlb-btn ltlb-btn--primary" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_text ); ?></a>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Language Switcher Shortcode
	 * Usage: [lazy_lang_switcher style="dropdown"] or [lazy_lang_switcher style="buttons"]
	 */
	public static function render_language_switcher( $atts ): string {
		self::enqueue_public_assets();
		
		$atts = shortcode_atts( [
			'style'   => 'dropdown', // 'dropdown' or 'buttons'
			'show_flags' => 'yes',   // 'yes' or 'no'
		], $atts, 'lazy_lang_switcher' );

		$current_locale = LTLB_I18n::get_frontend_locale();
		
		$languages = [
			'de_DE' => [
				'name'  => 'Deutsch',
				'short' => 'DE',
				'flag'  => '🇩🇪',
			],
			'en_US' => [
				'name'  => 'English',
				'short' => 'EN',
				'flag'  => '🇬🇧',
			],
			'es_ES' => [
				'name'  => 'Español',
				'short' => 'ES',
				'flag'  => '🇪🇸',
			],
		];

		ob_start();
		?>
		<div class="ltlb-lang-switcher ltlb-lang-switcher--<?php echo esc_attr( $atts['style'] ); ?>" data-current="<?php echo esc_attr( $current_locale ); ?>">
			<?php if ( $atts['style'] === 'dropdown' ) : ?>
				<button type="button" class="ltlb-lang-switcher__toggle ltlb-btn ltlb-btn--secondary" aria-expanded="false">
					<?php if ( $atts['show_flags'] === 'yes' ) : ?>
						<span class="ltlb-lang-switcher__flag"><?php echo esc_html( $languages[ $current_locale ]['flag'] ?? '🌐' ); ?></span>
					<?php endif; ?>
					<span class="ltlb-lang-switcher__current"><?php echo esc_html( $languages[ $current_locale ]['short'] ?? 'EN' ); ?></span>
					<svg class="ltlb-lang-switcher__arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<ul class="ltlb-lang-switcher__dropdown" role="menu" hidden>
					<?php foreach ( $languages as $locale => $lang ) : ?>
						<li>
							<button type="button" class="ltlb-lang-switcher__option<?php echo $locale === $current_locale ? ' ltlb-lang-switcher__option--active' : ''; ?>" data-locale="<?php echo esc_attr( $locale ); ?>" role="menuitem">
								<?php if ( $atts['show_flags'] === 'yes' ) : ?>
									<span class="ltlb-lang-switcher__flag"><?php echo esc_html( $lang['flag'] ); ?></span>
								<?php endif; ?>
								<span class="ltlb-lang-switcher__name"><?php echo esc_html( $lang['name'] ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : /* buttons style */ ?>
				<div class="ltlb-lang-switcher__buttons" role="group" aria-label="<?php esc_attr_e( 'Select language', 'ltl-bookings' ); ?>">
					<?php foreach ( $languages as $locale => $lang ) : ?>
						<button type="button" class="ltlb-lang-switcher__btn ltlb-btn<?php echo $locale === $current_locale ? ' ltlb-btn--primary' : ' ltlb-btn--secondary'; ?>" data-locale="<?php echo esc_attr( $locale ); ?>">
							<?php if ( $atts['show_flags'] === 'yes' ) : ?>
								<span class="ltlb-lang-switcher__flag"><?php echo esc_html( $lang['flag'] ); ?></span>
							<?php endif; ?>
							<span class="ltlb-lang-switcher__short"><?php echo esc_html( $lang['short'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

