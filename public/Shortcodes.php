<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Shortcodes {
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
	}

	public static function get_time_slots( WP_REST_Request $request ): WP_REST_Response {
		$limited = self::maybe_rate_limit( 'time_slots' );
		if ( $limited ) return $limited;

		$service_id = intval( $request->get_param( 'service_id' ) );
		$date = sanitize_text_field( (string) $request->get_param( 'date' ) );

		if ( $service_id <= 0 || empty( $date ) ) {
			return new WP_REST_Response( [ 'error' => 'Erforderliche Parameter fehlen.' ], 400 );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_REST_Response( [ 'error' => 'Ungültiges Datum' ], 400 );
		}
		if ( class_exists( 'LTLB_Time' ) && ! LTLB_Time::create_datetime_immutable( $date ) ) {
			return new WP_REST_Response( [ 'error' => 'Ungültiges Datum' ], 400 );
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
			return new WP_REST_Response( [ 'error' => 'Erforderliche Parameter fehlen.' ], 400 );
		}

		$service_repo = new LTLB_ServiceRepository();
		$service = $service_repo->get_by_id( $service_id );
		if ( ! $service ) return new WP_REST_Response( [ 'error' => 'Ungültige Leistung' ], 400 );

		$duration = intval( $service['duration_min'] ?? 60 );
		$start_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::create_datetime_immutable( $start ) : null;
		if ( ! $start_dt ) return new WP_REST_Response( [ 'error' => 'Ungültige Startzeit' ], 400 );
		if ( class_exists( 'LTLB_Time' ) ) {
			$now = new DateTimeImmutable( 'now', LTLB_Time::wp_timezone() );
			if ( $start_dt < $now ) {
				return new WP_REST_Response( [ 'error' => 'Startzeit liegt in der Vergangenheit' ], 400 );
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
			return new WP_REST_Response( [ 'error' => 'Erforderliche Parameter fehlen.' ], 400 );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checkin ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checkout ) ) {
			return new WP_REST_Response( [ 'error' => 'Ungültige Daten' ], 400 );
		}
		if ( class_exists( 'LTLB_Time' ) ) {
			if ( ! LTLB_Time::create_datetime_immutable( $checkin ) || ! LTLB_Time::create_datetime_immutable( $checkout ) ) {
				return new WP_REST_Response( [ 'error' => 'Ungültige Daten' ], 400 );
			}
		}

		$service_repo = new LTLB_ServiceRepository();
		$service = $service_repo->get_by_id( $service_id );
		if ( ! $service ) {
			return new WP_REST_Response( [ 'error' => 'Ungültige Leistung' ], 400 );
		}

		$nights = class_exists( 'LTLB_Time' ) ? LTLB_Time::nights_between( $checkin, $checkout ) : 0;
		if ( $nights < 1 ) {
			return new WP_REST_Response( [ 'error' => 'Ungültige Daten: Abreise muss nach Anreise sein' ], 400 );
		}

		$ls = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls ) ) $ls = [];
		$checkin_time = $ls['hotel_checkin_time'] ?? '15:00';
		$checkout_time = $ls['hotel_checkout_time'] ?? '11:00';

		$checkin_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::combine_date_time( $checkin, $checkin_time ) : null;
		$checkout_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::combine_date_time( $checkout, $checkout_time ) : null;
		if ( ! $checkin_dt || ! $checkout_dt ) {
			return new WP_REST_Response( [ 'error' => 'Ungültiges Datums-/Zeitformat' ], 400 );
		}
		if ( class_exists( 'LTLB_Time' ) ) {
			$now = new DateTimeImmutable( 'now', LTLB_Time::wp_timezone() );
			if ( $checkin_dt < $now ) {
				return new WP_REST_Response( [ 'error' => 'Anreise liegt in der Vergangenheit' ], 400 );
			}
		}

		$start_at_sql = LTLB_Time::format_wp_datetime( $checkin_dt );
		$end_at_sql = LTLB_Time::format_wp_datetime( $checkout_dt );

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
	if ( has_shortcode( $post->post_content, 'lazy_book' ) || has_shortcode( $post->post_content, 'lazy_book_calendar' ) ) {
			wp_enqueue_style( 'ltlb-public', plugins_url( '../assets/css/public.css', __FILE__ ), [], LTLB_VERSION );
			wp_enqueue_script( 'ltlb-public', plugins_url( '../assets/js/public.js', __FILE__ ), ['jquery'], LTLB_VERSION, true );
	}
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
		$start_mode = sanitize_key( strval( $atts['mode'] ?? 'wizard' ) );
		if ( $start_mode !== 'calendar' ) {
			$start_mode = 'wizard';
		}

		// Always enqueue assets when the shortcode is actually rendered.
		wp_enqueue_style( 'ltlb-public', plugins_url( '../assets/css/public.css', __FILE__ ), [], LTLB_VERSION );
		wp_enqueue_script( 'ltlb-public', plugins_url( '../assets/js/public.js', __FILE__ ), ['jquery'], LTLB_VERSION, true );
		wp_localize_script( 'ltlb-public', 'LTLB_PUBLIC', [
			'restRoot' => esc_url_raw( rest_url( 'ltlb/v1' ) ),
		] );

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
		echo '<div class="ltlb-booking"><div class="ltlb-error">' . esc_html__( 'Template-Datei nicht gefunden:', 'ltl-bookings' ) . ' ' . esc_html( $template_path ) . '</div></div>';
	}
	return ob_get_clean();
	}
	private static function handle_submission(): string {
		$valid = self::_validate_submission();
		if ( is_wp_error( $valid ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Fehler:', 'ltl-bookings' ) . '</strong> ' . esc_html( $valid->get_error_message() ) . '</div></div>';
		}
		if ( $valid !== true ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Fehler:', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Deine Anfrage konnte nicht verarbeitet werden. Bitte versuche es erneut.', 'ltl-bookings' ) . '</div></div>';
		}

		$is_hotel_mode = self::_is_hotel_mode();
		$data = $is_hotel_mode ? self::_get_sanitized_hotel_submission_data() : self::_get_sanitized_submission_data();

		if ( is_wp_error( $data ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Fehler:', 'ltl-bookings' ) . '</strong> ' . esc_html( $data->get_error_message() ) . '</div></div>';
		}

		$appointment_id = $is_hotel_mode ? self::_create_hotel_booking_from_submission( $data ) : self::_create_appointment_from_submission( $data );

		if ( is_wp_error( $appointment_id ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Fehler:', 'ltl-bookings' ) . '</strong> ' . esc_html( $appointment_id->get_error_message() ) . '</div></div>';
		}

		return '<div class="ltlb-booking"><div class="ltlb-success"><strong>' . esc_html__( 'Erfolgreich!', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Deine Buchung ist eingegangen und wartet auf Bestätigung. Bitte prüfe deine E-Mails für Details.', 'ltl-bookings' ) . '</div></div>';
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
			return new WP_Error( 'ltlb_nonce_failed', __( 'Sicherheitsprüfung fehlgeschlagen. Bitte lade die Seite neu und versuche es erneut.', 'ltl-bookings' ) );
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
				return new WP_Error( 'ltlb_rate_limited', __( 'Zu viele Anfragen. Bitte versuche es in ein paar Minuten erneut.', 'ltl-bookings' ) );
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

		if ( empty( $service_id ) || empty( $date ) || empty( $time ) || empty( $email ) ) {
			return new WP_Error( 'missing_fields', __( 'Bitte fülle die Pflichtfelder aus.', 'ltl-bookings' ) );
		}

		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;

		return compact( 'service_id', 'date', 'time', 'email', 'first', 'last', 'phone', 'resource_id' );
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

		if ( empty( $service_id ) || empty( $checkin ) || empty( $checkout ) || empty( $email ) || empty( $guests ) ) {
			return new WP_Error( 'missing_fields', __( 'Bitte fülle die Pflichtfelder aus.', 'ltl-bookings' ) );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checkin ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checkout ) ) {
			return new WP_Error( 'invalid_date', __( 'Ungültige Daten.', 'ltl-bookings' ) );
		}
		if ( class_exists( 'LTLB_Time' ) ) {
			if ( ! LTLB_Time::create_datetime_immutable( $checkin ) || ! LTLB_Time::create_datetime_immutable( $checkout ) ) {
				return new WP_Error( 'invalid_date', __( 'Ungültige Daten.', 'ltl-bookings' ) );
			}
		}

		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;

		return compact( 'service_id', 'checkin', 'checkout', 'guests', 'email', 'first', 'last', 'phone', 'resource_id' );
	}

	private static function _create_hotel_booking_from_submission( array $data ): int|WP_Error {
		return LTLB_BookingService::create_hotel_booking_from_submission( $data );
	}

	private static function _create_appointment_from_submission( array $data ): int|WP_Error {
		return LTLB_BookingService::create_service_booking_from_submission( $data );
	}
}

