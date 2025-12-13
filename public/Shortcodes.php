<?php
if ( ! defined('ABSPATH') ) exit;

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
	
	$settings = get_option( 'lazy_settings', [] );
	$template_mode = is_array($settings) && isset($settings['template_mode']) ? $settings['template_mode'] : 'service';
	$is_hotel_mode = ( $template_mode === 'hotel' );
	
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
		.ltlb-price-preview{background:#f9f9f9;border:1px solid #ddd;padding:1rem;border-radius:6px;margin:1rem 0;font-size:0.9375rem}
		.ltlb-price-preview strong{font-size:1.125rem;color:var(--lazy-primary,#2b7cff)}
		</style>
		<div class="ltlb-booking">
			<a href="#ltlb-form-start" class="ltlb-skip-link"><?php echo esc_html__('Skip to booking form', 'ltl-bookings'); ?></a>
			<form method="post" aria-label="<?php echo esc_attr__('Booking form', 'ltl-bookings'); ?>">
				<?php wp_nonce_field( 'ltlb_book_action', 'ltlb_book_nonce' ); ?>
				<!-- honeypot field: bots will fill this -->
				<div style="display:none;" aria-hidden="true">
					<label>Leave this empty<input type="text" name="ltlb_hp" value="" tabindex="-1"></label>
				</div>
				<span id="ltlb-form-start"></span>
				<h3><?php echo $is_hotel_mode ? esc_html__( 'Book a room', 'ltl-bookings' ) : esc_html__( 'Book a service', 'ltl-bookings' ); ?></h3>

				<?php if ( $is_hotel_mode ) : ?>
					<div id="ltlb-price-preview" class="ltlb-price-preview" style="display:none;">
						<p style="margin:0 0 0.5rem 0;"><?php echo esc_html__('Price estimate:', 'ltl-bookings'); ?></p>
						<p style="margin:0;"><strong id="ltlb-price-amount">—</strong> <span id="ltlb-price-breakdown" style="color:#666;font-size:0.875rem;"></span></p>
					</div>
				<?php endif; ?>

				<p>
					<label>
						<?php echo $is_hotel_mode ? esc_html__('Room Type', 'ltl-bookings') : esc_html__('Service', 'ltl-bookings'); ?><span class="ltlb-required" aria-label="required">*</span>
						<select name="service_id" required aria-required="true" data-price-cents="">
							<option value=""><?php echo $is_hotel_mode ? esc_html__('Select a room type', 'ltl-bookings') : esc_html__('Select a service', 'ltl-bookings'); ?></option>
							<?php foreach ( $services as $s ): ?>
								<option value="<?php echo esc_attr( $s['id'] ); ?>" data-price="<?php echo esc_attr( $s['price_cents'] ?? 0 ); ?>"><?php echo esc_html( $s['name'] ); ?><?php if ( isset($s['price_cents']) && $s['price_cents'] > 0 ) : ?> — <?php echo number_format($s['price_cents']/100, 2) . ' ' . ($s['currency'] ?? 'EUR'); ?><?php endif; ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( $is_hotel_mode ) : ?>
							<span class="ltlb-field-hint"><?php echo esc_html__('Price shown is per night', 'ltl-bookings'); ?></span>
						<?php endif; ?>
					</label>
				</p>
				<?php if ( $is_hotel_mode ) : ?>
					<p>
						<label>
							<?php echo esc_html__('Check-in', 'ltl-bookings'); ?><span class="ltlb-required" aria-label="required">*</span>
							<input type="date" name="checkin" required aria-required="true" id="ltlb-checkin">
							<span class="ltlb-field-hint"><?php echo esc_html__('Arrival date', 'ltl-bookings'); ?></span>
						</label>
					</p>
					<p>
						<label>
							<?php echo esc_html__('Check-out', 'ltl-bookings'); ?><span class="ltlb-required" aria-label="required">*</span>
							<input type="date" name="checkout" required aria-required="true" id="ltlb-checkout">
							<span class="ltlb-field-hint"><?php echo esc_html__('Departure date', 'ltl-bookings'); ?></span>
						</label>
					</p>
					<p>
						<label>
							<?php echo esc_html__('Guests', 'ltl-bookings'); ?><span class="ltlb-required" aria-label="required">*</span>
							<input type="number" name="guests" min="1" value="1" required aria-required="true">
							<span class="ltlb-field-hint"><?php echo esc_html__('Number of guests', 'ltl-bookings'); ?></span>
						</label>
					</p>
				<?php else : ?>
					<p>
						<label>
							<?php echo esc_html__('Date', 'ltl-bookings'); ?><span class="ltlb-required" aria-label="required">*</span>
							<input type="date" name="date" required aria-required="true">
							<span class="ltlb-field-hint"><?php echo esc_html__('Choose your preferred date', 'ltl-bookings'); ?></span>
						</label>
					</p>
				<?php endif; ?>
				<?php if ( ! $is_hotel_mode ) : ?>
					<p>
						<label>
							<?php echo esc_html__('Time', 'ltl-bookings'); ?><span class="ltlb-required" aria-label="required">*</span>
							<select name="time_slot" required aria-required="true">
								<option value=""><?php echo esc_html__('Select date first', 'ltl-bookings'); ?></option>
							</select>
							<span class="ltlb-field-hint"><?php echo esc_html__('Available time slots will load after selecting a date', 'ltl-bookings'); ?></span>
						</label>
					</p>
				<?php endif; ?>
				<p>
					<label>
						<?php echo $is_hotel_mode ? esc_html__('Room Preference', 'ltl-bookings') : esc_html__('Resource', 'ltl-bookings'); ?>
						<select name="resource_id" id="ltlb_resource_select" style="display:none;">
							<option value=""><?php echo esc_html__('Any available', 'ltl-bookings'); ?></option>
						</select>
						<span class="ltlb-field-hint" id="ltlb_resource_hint" style="display:none;">
							<?php echo $is_hotel_mode ? esc_html__('Multiple rooms available for your dates', 'ltl-bookings') : esc_html__('Multiple resources available for this time', 'ltl-bookings'); ?>
						</span>
					</label>
				</p>
				<h4><?php echo esc_html__('Your details', 'ltl-bookings'); ?></h4>
				<p>
					<label for="ltlb-email">
						<?php echo esc_html__('Email', 'ltl-bookings'); ?><span class="ltlb-required" aria-label="required">*</span>
						<input type="email" id="ltlb-email" name="email" required aria-required="true" placeholder="you@example.com" autocomplete="email">
					</label>
				</p>
				<p>
					<label for="ltlb-first-name">
						<?php echo esc_html__('First name', 'ltl-bookings'); ?>
						<input type="text" id="ltlb-first-name" name="first_name" autocomplete="given-name">
					</label>
				</p>
				<p>
					<label for="ltlb-last-name">
						<?php echo esc_html__('Last name', 'ltl-bookings'); ?>
						<input type="text" id="ltlb-last-name" name="last_name" autocomplete="family-name">
					</label>
				</p>
				<p>
					<label for="ltlb-phone">
						<?php echo esc_html__('Phone', 'ltl-bookings'); ?>
						<input type="tel" id="ltlb-phone" name="phone" placeholder="+1234567890" autocomplete="tel">
					</label>
				</p>

				<p><?php submit_button( esc_html__('Complete Booking', 'ltl-bookings'), 'primary', 'ltlb_book_submit', false, ['aria-label' => esc_attr__('Submit booking form', 'ltl-bookings')] ); ?></p>
			</form>
	</div>
	<?php
	return ob_get_clean();
	}
	private static function handle_submission(): string {
		if ( ! self::_validate_submission() ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Error:', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Unable to process your request. Please try again.', 'ltl-bookings' ) . '</div></div>';
		}

		$data = self::_get_sanitized_submission_data();

		if ( is_wp_error( $data ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Error:', 'ltl-bookings' ) . '</strong> ' . esc_html( $data->get_error_message() ) . '</div></div>';
		}

		$appointment_id = self::_create_appointment_from_submission( $data );

		if ( is_wp_error( $appointment_id ) ) {
			return '<div class="ltlb-booking"><div class="ltlb-error"><strong>' . esc_html__( 'Error:', 'ltl-bookings' ) . '</strong> ' . esc_html( $appointment_id->get_error_message() ) . '</div></div>';
		}

		return '<div class="ltlb-booking"><div class="ltlb-success"><strong>' . esc_html__( 'Success!', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Your booking has been received and is pending confirmation. Check your email for details.', 'ltl-bookings' ) . '</div></div>';
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
		$customer_repo = new LTLB_CustomerRepository();

		// Build lock key for this booking slot
		$lock_key = LTLB_LockManager::build_service_lock_key( $data['service_id'], $start_at_sql, $data['resource_id'] ?: null );

		// Execute booking within lock protection
		$result = LTLB_LockManager::with_lock( $lock_key, function() use ( $appointment_repo, $customer_repo, $data, $start_at_sql, $end_at_sql, $start_dt, $end_dt ) {
			// conflict check
			if ( $appointment_repo->has_conflict( $start_at_sql, $end_at_sql, $data['service_id'], null ) ) {
				return new WP_Error( 'conflict', __( 'Selected slot is already booked.', 'ltl-bookings' ) );
			}

			// upsert customer
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

			return $appt_id;
		});

		// Check if lock acquisition failed
		if ( $result === false ) {
			LTLB_Logger::warn( 'Booking lock timeout', [ 'service_id' => $data['service_id'], 'start' => $start_at_sql ] );
			return new WP_Error( 'lock_timeout', __( 'Another booking is in progress. Please try again.', 'ltl-bookings' ) );
		}

		// Check if appointment creation returned error
		if ( is_wp_error( $result ) ) {
			LTLB_Logger::error( 'Booking creation failed: ' . $result->get_error_message(), [ 'service_id' => $data['service_id'], 'email' => $data['email'] ] );
			return $result;
		}

		$appt_id = $result;
		LTLB_Logger::info( 'Booking created successfully', [ 'appointment_id' => $appt_id, 'service_id' => $data['service_id'], 'email' => $data['email'] ] );

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

