<?php
if ( ! defined('ABSPATH') ) exit;

use WP_REST_Request;
use WP_REST_Response;

class LTLB_Shortcodes {
	public static function init(): void {
		add_shortcode( 'lazy_book', [ __CLASS__, 'render_lazy_book' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_assets' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
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
	}

	public static function get_time_slots( WP_REST_Request $request ): WP_REST_Response {
		$service_id = $request->get_param( 'service_id' );
		$date = $request->get_param( 'date' );

		if ( ! $service_id || ! $date ) {
			return new WP_REST_Response( [ 'error' => 'Missing required parameters.' ], 400 );
		}

		$availability = new Availability();
		$slots = $availability->compute_time_slots( $service_id, $date );

		return new WP_REST_Response( $slots, 200 );
	}

	public static function get_slot_resources( WP_REST_Request $request ): WP_REST_Response {
		$service_id = intval( $request->get_param( 'service_id' ) );
		$start = sanitize_text_field( $request->get_param( 'start' ) ); // YYYY-MM-DD HH:MM:SS
		if ( ! $service_id || ! $start ) {
			return new WP_REST_Response( [ 'error' => 'Missing required parameters.' ], 400 );
		}

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

	public static function maybe_enqueue_assets(): void {
	global $post;
	if ( empty( $post ) ) return;
	if ( has_shortcode( $post->post_content, 'lazy_book' ) ) {
			wp_enqueue_style( 'ltlb-public', plugins_url( '../assets/css/public.css', __FILE__ ) );
			wp_enqueue_script( 'ltlb-public', plugins_url( '../assets/js/public.js', __FILE__ ), ['jquery'], false, true );
	}
	}
	public static function render_lazy_book( $atts ): string {
	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ltlb_book_submit'] ) ) {
			return self::handle_submission();
	}
	$service_repo = new LTLB_ServiceRepository();
	$services = $service_repo->get_all();
	ob_start();
	?>
		<style>
		/* LazyBookings inline styles using design CSS variables */
		.ltlb-booking{background:var(--lazy-bg,#fff);color:var(--lazy-text,#111);padding:16px;border-radius:6px}
		.ltlb-booking .button-primary{background:var(--lazy-primary,#2b7cff);color:var(--lazy-text,#fff);border:none}
		.ltlb-booking .ltlb-success{color:var(--lazy-primary,#2b7cff)}
		.ltlb-booking .ltlb-error{color:#c33}
		</style>
		<div class="ltlb-booking">
			<form method="post">
				<?php wp_nonce_field( 'ltlb_book_action', 'ltlb_book_nonce' ); ?>
				<!-- honeypot field: bots will fill this -->
				<div style="display:none;">
					<label>Leave this empty<input type="text" name="ltlb_hp" value=""></label>
				</div>
				<h3><?php echo esc_html__( 'Book a service', 'ltl-bookings' ); ?></h3>

				<p>
					<label><?php echo esc_html__('Service', 'ltl-bookings'); ?>
						<select name="service_id" required>
							<?php foreach ( $services as $s ): ?>
								<option value="<?php echo esc_attr( $s['id'] ); ?>"><?php echo esc_html( $s['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<p>
					<label><?php echo esc_html__('Date', 'ltl-bookings'); ?> <input type="date" name="date" required></label>
				</p>
				<p>
					<label><?php echo esc_html__('Time', 'ltl-bookings'); ?>
						<select name="time_slot" required>
							<!-- Time slots will be loaded dynamically via JavaScript -->
						</select>
					</label>
				</p>
				<p>
					<label><?php echo esc_html__('Resource (optional)', 'ltl-bookings'); ?>
						<select name="resource_id" id="ltlb_resource_select" style="display:none; min-width:200px;">
							<!-- filled via JS when multiple resources are available -->
						</select>
					</label>
				</p>
				<h4><?php echo esc_html__('Your details', 'ltl-bookings'); ?></h4>
				<p><label><?php echo esc_html__('Email', 'ltl-bookings'); ?> <input type="email" name="email" required></label></p>
				<p><label><?php echo esc_html__('First name', 'ltl-bookings'); ?> <input type="text" name="first_name"></label></p>
				<p><label><?php echo esc_html__('Last name', 'ltl-bookings'); ?> <input type="text" name="last_name"></label></p>
				<p><label><?php echo esc_html__('Phone', 'ltl-bookings'); ?> <input type="text" name="phone"></label></p>

				<p><?php submit_button( esc_html__('Book', 'ltl-bookings'), 'primary', 'ltlb_book_submit', false ); ?></p>
			</form>
	</div>
	<?php
	return ob_get_clean();
	}
	private static function handle_submission(): string {
		if ( ! self::_validate_submission() ) {
			return '<div class="ltlb-error">' . esc_html__( 'Unable to process request.', 'ltl-bookings' ) . '</div>';
		}

		$data = self::_get_sanitized_submission_data();

		if ( is_wp_error( $data ) ) {
			return '<div class="ltlb-error">' . esc_html( $data->get_error_message() ) . '</div>';
		}

		$appointment_id = self::_create_appointment_from_submission( $data );

		if ( is_wp_error( $appointment_id ) ) {
			return '<div class="ltlb-error">' . esc_html( $appointment_id->get_error_message() ) . '</div>';
		}

		return '<div class="ltlb-success">' . esc_html__( 'Booking created (pending). We have sent confirmation emails where configured.', 'ltl-bookings' ) . '</div>';
	}

	private static function _validate_submission(): bool {
		if ( ! isset( $_POST['ltlb_book_nonce'] ) || ! wp_verify_nonce( $_POST['ltlb_book_nonce'], 'ltlb_book_action' ) ) {
			return false;
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
				return false;
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
			return new WP_Error( 'missing_fields', __( 'Please fill the required fields.', 'ltl-bookings' ) );
		}

		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;

		return compact( 'service_id', 'date', 'time', 'email', 'first', 'last', 'phone', 'resource_id' );
	}

	private static function _create_appointment_from_submission( array $data ): int|WP_Error {
		// compute start_at and end_at based on service duration
		$service_repo = new LTLB_ServiceRepository();
		$service = $service_repo->get_by_id( $data['service_id'] );
		$duration = $service && isset( $service['duration_min'] ) ? intval( $service['duration_min'] ) : 60;

		$start_dt = LTLB_Time::parse_date_and_time( $data['date'], $data['time'] );
		if ( ! $start_dt ) {
			return new WP_Error( 'invalid_date', __( 'Invalid date/time.', 'ltl-bookings' ) );
		}

		$end_dt = $start_dt->modify( '+' . intval( $duration ) . ' minutes' );

		$start_at_sql = LTLB_Time::format_wp_datetime( $start_dt );
		$end_at_sql = LTLB_Time::format_wp_datetime( $end_dt );

		$appointment_repo = new LTLB_AppointmentRepository();

		// conflict check
		if ( $appointment_repo->has_conflict( $start_at_sql, $end_at_sql, $data['service_id'], null ) ) {
			return new WP_Error( 'conflict', __( 'Selected slot is already booked.', 'ltl-bookings' ) );
		}

		// upsert customer
		$customer_repo = new LTLB_CustomerRepository();
		$customer_id = $customer_repo->upsert_by_email( [
			'email'      => $data['email'],
			'first_name' => $data['first'],
			'last_name'  => $data['last'],
			'phone'      => $data['phone'],
		] );

		if ( ! $customer_id ) {
			return new WP_Error( 'customer_error', __( 'Unable to save customer.', 'ltl-bookings' ) );
		}

		$ls = get_option( 'lazy_settings', [] );
		if ( ! is_array( $ls ) ) {
			$ls = [];
		}
		$default_status = $ls['default_status'] ?? 'pending';
		$appt_id = $appointment_repo->create( [
			'service_id'  => $data['service_id'],
			'customer_id' => $customer_id,
			'start_at'    => $start_dt,
			'end_at'      => $end_dt,
			'status'      => $default_status,
			'timezone'    => LTLB_Time::get_site_timezone_string(),
		] );

		if ( is_wp_error( $appt_id ) ) {
			return $appt_id;
		}

		// If user selected a resource, try to persist it (validate capacity and mapping), otherwise select automatically
		$service_resources_repo = new LTLB_ServiceResourcesRepository();
		$resource_repo = new LTLB_ResourceRepository();
		$appt_resource_repo = new LTLB_AppointmentResourcesRepository();

		$allowed_resources = $service_resources_repo->get_resources_for_service( intval( $data['service_id'] ) );
		if ( empty( $allowed_resources ) ) {
			$all = $resource_repo->get_all();
			$allowed_resources = array_map(function($r){ return intval($r['id']); }, $all );
		}

		$include_pending = get_option('ltlb_pending_blocks', 0) ? true : false;
		$blocked_counts = $appt_resource_repo->get_blocked_resources( $start_at_sql, $end_at_sql, $include_pending );

		$chosen = isset($data['resource_id']) ? intval($data['resource_id']) : 0;
		if ( $chosen > 0 && in_array($chosen, $allowed_resources, true) ) {
			// validate capacity
			$res = $resource_repo->get_by_id( $chosen );
			if ( $res ) {
				$cap = intval($res['capacity'] ?? 1);
				$used = isset($blocked_counts[$chosen]) ? intval($blocked_counts[$chosen]) : 0;
				if ( $used < $cap ) {
					$appt_resource_repo->set_resource_for_appointment( intval($appt_id), $chosen );
				}
			}
		} else {
			foreach ( $allowed_resources as $rid ) {
				$res = $resource_repo->get_by_id( intval($rid) );
				if ( ! $res ) continue;
				$capacity = intval( $res['capacity'] ?? 1 );
				$used = isset( $blocked_counts[ $rid ] ) ? intval( $blocked_counts[ $rid ] ) : 0;
				if ( $used < $capacity ) {
					$appt_resource_repo->set_resource_for_appointment( intval($appt_id), intval($rid) );
					break;
				}
			}
		}

		// fetch fresh service and customer data and send notifications
		$service = $service_repo->get_by_id( $data['service_id'] );
		$customer = $customer_repo->get_by_id( $customer_id );

		if ( class_exists( 'LTLB_Mailer' ) ) {
			LTLB_Mailer::send_booking_notifications( $appt_id, $service ?: [], $customer ?: [], $start_at_sql, $end_at_sql, $default_status );
		}

		return $appt_id;
	}
}

// Initialize shortcodes
add_action( 'init', [ 'LTLB_Shortcodes', 'init' ] );

