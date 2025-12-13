<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Shortcodes {
	public static function init(): void {
	add_shortcode( 'lazy_book', [ __CLASS__, 'render_lazy_book' ] );
	add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_assets' ] );
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

	// Template mode switch
	$settings = get_option('lazy_settings', []);
	$template_mode = is_array($settings) && isset($settings['template_mode']) ? $settings['template_mode'] : 'service';
	
	if ( $template_mode === 'hotel' ) {
		return self::render_hotel_booking();
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
						<select name="service_id" id="ltlb-service-select" required>
							<?php foreach ( $services as $s ): ?>
								<option value="<?php echo esc_attr( $s['id'] ); ?>" data-is-group="<?php echo esc_attr( ! empty( $s['is_group'] ) ? '1' : '0' ); ?>" data-max-seats="<?php echo esc_attr( intval( $s['max_seats_per_booking'] ?? 1 ) ); ?>"><?php echo esc_html( $s['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<script>
				(function() {
					const serviceSelect = document.getElementById('ltlb-service-select');
					const seatsField = document.getElementById('ltlb-seats-field');
					const seatsInput = document.getElementById('seats');
					function updateSeatsField() {
						const selected = serviceSelect.options[serviceSelect.selectedIndex];
						const isGroup = selected.getAttribute('data-is-group') === '1';
						const maxSeats = parseInt(selected.getAttribute('data-max-seats')) || 1;
						if (isGroup) {
							seatsField.style.display = 'block';
							seatsInput.max = maxSeats;
							seatsInput.required = true;
						} else {
							seatsField.style.display = 'none';
							seatsInput.required = false;
							seatsInput.value = '1';
						}
					}
					if (serviceSelect) {
						serviceSelect.addEventListener('change', updateSeatsField);
						updateSeatsField();
					}
				})();
				</script>
				<p>
					<label><?php echo esc_html__('Date', 'ltl-bookings'); ?> <input type="date" name="date" required></label>
				</p>
				<p>
					<label><?php echo esc_html__('Time', 'ltl-bookings'); ?>
						<select name="time_slot" required>
							<?php
							// generate slots for today using lazy_settings and LTLB_Time helper
							$tz = LTLB_Time::wp_timezone();
							$today = new DateTimeImmutable( 'now', $tz );
							$ls = get_option( 'lazy_settings', [] );
							if ( ! is_array( $ls ) ) $ls = [];
							$start_hour = (int) ( $ls['working_hours_start'] ?? 9 );
							$end_hour = (int) ( $ls['working_hours_end'] ?? 17 );
							$slot_minutes = (int) ( $ls['slot_size_minutes'] ?? 60 );
							$slots = LTLB_Time::generate_slots_for_day( $today, $start_hour, $end_hour, $slot_minutes );
							foreach ( $slots as $slot ) {
								$label = $slot->format('H:i');
								echo '<option value="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</option>';
							}
							?>
						</select>
					</label>
				</p>
				<p id="ltlb-seats-field" style="display:none;">
					<label><?php echo esc_html__('Number of Seats', 'ltl-bookings'); ?>
						<input type="number" name="seats" id="seats" min="1" max="10" value="1">
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
		if ( ! isset( $_POST['ltlb_book_nonce'] ) || ! wp_verify_nonce( $_POST['ltlb_book_nonce'], 'ltlb_book_action' ) ) {
			return '<div class="ltlb-error">' . esc_html__( 'Unable to process request.', 'ltl-bookings' ) . '</div>';
		}

		// Honeypot: if filled, silently fail
		if ( ! empty( $_POST['ltlb_hp'] ) ) {
			return '<div class="ltlb-error">' . esc_html__( 'Unable to process request.', 'ltl-bookings' ) . '</div>';
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
				return '<div class="ltlb-error">' . esc_html__( 'Too many requests. Please try again later.', 'ltl-bookings' ) . '</div>';
			}
			set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		}
	// Build payload and delegate to active engine
	$payload = [
		'service_id' => isset($_POST['service_id']) ? intval($_POST['service_id']) : 0,
		'date' => isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '',
		'time' => isset($_POST['time_slot']) ? sanitize_text_field($_POST['time_slot']) : '',
		'email' => LTLB_Sanitizer::email( $_POST['email'] ?? '' ),
		'first' => LTLB_Sanitizer::text( $_POST['first_name'] ?? '' ),
		'last' => LTLB_Sanitizer::text( $_POST['last_name'] ?? '' ),
		'phone' => LTLB_Sanitizer::text( $_POST['phone'] ?? '' ),
		'resource_id' => isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0,
		'seats' => isset($_POST['seats']) ? intval($_POST['seats']) : 1,
	];

	$engine = EngineFactory::get_engine();
	$valid = $engine->validate_payload($payload);
	if ( is_wp_error($valid) ) {
		return '<div class="ltlb-error">' . esc_html( $valid->get_error_message() ) . '</div>';
	}

	$result = $engine->create_booking($payload);
	if ( is_wp_error($result) ) {
		return '<div class="ltlb-error">' . esc_html( $result->get_error_message() ) . '</div>';
	}

	return '<div class="ltlb-success">' . esc_html__( 'Booking created (pending). We have sent confirmation emails where configured.', 'ltl-bookings' ) . '</div>';
	}

	private static function render_hotel_booking(): string {
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ltlb_book_submit'] ) ) {
			return self::handle_hotel_submission();
		}

		$service_repo = new LTLB_ServiceRepository();
		$services = $service_repo->get_all();
		ob_start();
		?>
		<style>
		.ltlb-booking{background:var(--lazy-bg,#fff);color:var(--lazy-text,#111);padding:16px;border-radius:6px}
		.ltlb-booking .button-primary{background:var(--lazy-primary,#2b7cff);color:var(--lazy-text,#fff);border:none}
		.ltlb-booking .ltlb-success{color:var(--lazy-primary,#2b7cff)}
		.ltlb-booking .ltlb-error{color:#c33}
		</style>
		<div class="ltlb-booking">
			<form method="post">
				<?php wp_nonce_field( 'ltlb_book_action', 'ltlb_book_nonce' ); ?>
				<div style="display:none;">
					<label>Leave this empty<input type="text" name="ltlb_hp" value=""></label>
				</div>
				<h3><?php echo esc_html__( 'Book a Room', 'ltl-bookings' ); ?></h3>

				<p>
					<label><?php echo esc_html__('Room Type', 'ltl-bookings'); ?>
						<select name="service_id" required>
							<?php foreach ( $services as $s ): ?>
								<?php if ( ! empty($s['is_active']) ): ?>
									<option value="<?php echo esc_attr( $s['id'] ); ?>"><?php echo esc_html( $s['name'] ); ?></option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<p>
					<label><?php echo esc_html__('Check-in Date', 'ltl-bookings'); ?> <input type="date" name="checkin" required></label>
				</p>
				<p>
					<label><?php echo esc_html__('Check-out Date', 'ltl-bookings'); ?> <input type="date" name="checkout" required></label>
				</p>
				<p>
					<label><?php echo esc_html__('Number of Guests', 'ltl-bookings'); ?> <input type="number" name="guests" min="1" max="10" value="1" required></label>
				</p>
				<h4><?php echo esc_html__('Your details', 'ltl-bookings'); ?></h4>
				<p><label><?php echo esc_html__('Email', 'ltl-bookings'); ?> <input type="email" name="email" required></label></p>
				<p><label><?php echo esc_html__('First name', 'ltl-bookings'); ?> <input type="text" name="first_name"></label></p>
				<p><label><?php echo esc_html__('Last name', 'ltl-bookings'); ?> <input type="text" name="last_name"></label></p>
				<p><label><?php echo esc_html__('Phone', 'ltl-bookings'); ?> <input type="text" name="phone"></label></p>

				<p><?php submit_button( esc_html__('Book Room', 'ltl-bookings'), 'primary', 'ltlb_book_submit', false ); ?></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function handle_hotel_submission(): string {
		if ( ! isset( $_POST['ltlb_book_nonce'] ) || ! wp_verify_nonce( $_POST['ltlb_book_nonce'], 'ltlb_book_action' ) ) {
			return '<div class="ltlb-error">' . esc_html__( 'Unable to process request.', 'ltl-bookings' ) . '</div>';
		}

		if ( ! empty( $_POST['ltlb_hp'] ) ) {
			return '<div class="ltlb-error">' . esc_html__( 'Unable to process request.', 'ltl-bookings' ) . '</div>';
		}

		// Rate limit
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
				return '<div class="ltlb-error">' . esc_html__( 'Too many requests. Please try again later.', 'ltl-bookings' ) . '</div>';
			}
			set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		}

		// Build hotel payload
		$payload = [
			'service_id' => isset($_POST['service_id']) ? intval($_POST['service_id']) : 0,
			'checkin' => isset($_POST['checkin']) ? sanitize_text_field($_POST['checkin']) : '',
			'checkout' => isset($_POST['checkout']) ? sanitize_text_field($_POST['checkout']) : '',
			'guests' => isset($_POST['guests']) ? intval($_POST['guests']) : 0,
			'email' => LTLB_Sanitizer::email( $_POST['email'] ?? '' ),
			'first' => LTLB_Sanitizer::text( $_POST['first_name'] ?? '' ),
			'last' => LTLB_Sanitizer::text( $_POST['last_name'] ?? '' ),
			'phone' => LTLB_Sanitizer::text( $_POST['phone'] ?? '' ),
		];

		$engine = EngineFactory::get_engine();
		if ( ! ( $engine instanceof HotelEngine ) ) {
			return '<div class="ltlb-error">' . esc_html__( 'Hotel booking not available.', 'ltl-bookings' ) . '</div>';
		}

		$result = $engine->create_hotel_booking( $payload );
		if ( is_wp_error($result) ) {
			return '<div class="ltlb-error">' . esc_html( $result->get_error_message() ) . '</div>';
		}

		return '<div class="ltlb-success">' . esc_html__( 'Booking created (pending). We have sent confirmation emails where configured.', 'ltl-bookings' ) . '</div>';
	}
}

// Initialize shortcodes
add_action( 'init', [ 'LTLB_Shortcodes', 'init' ] );

