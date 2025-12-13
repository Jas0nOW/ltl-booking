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
	$service_repo = new LTLB_ServiceRepository();
	$services = $service_repo->get_all();
	ob_start();
	?>
	<div class="ltlb-booking">
			<form method="post">
				<?php wp_nonce_field( 'ltlb_book_action', 'ltlb_book_nonce' ); ?>
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
							<?php
							// basic slots 09:00 - 17:00 hourly
							for ( $h = 9; $h <= 16; $h++ ) {
								$label = sprintf('%02d:00', $h);
								echo '<option value="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</option>';
							}
							?>
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
	if ( ! isset( $_POST['ltlb_book_nonce'] ) || ! wp_verify_nonce( $_POST['ltlb_book_nonce'], 'ltlb_book_action' ) ) {
			return '<div class="ltlb-error">' . esc_html__( 'Security check failed.', 'ltl-bookings' ) . '</div>';
	}
	$service_id = isset( $_POST['service_id'] ) ? intval( $_POST['service_id'] ) : 0;
	$date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
	$time = isset( $_POST['time_slot'] ) ? sanitize_text_field( $_POST['time_slot'] ) : '';

	$email = LTLB_Sanitizer::email( $_POST['email'] ?? '' );
	$first = LTLB_Sanitizer::text( $_POST['first_name'] ?? '' );
	$last = LTLB_Sanitizer::text( $_POST['last_name'] ?? '' );
	$phone = LTLB_Sanitizer::text( $_POST['phone'] ?? '' );

	if ( empty( $service_id ) || empty( $date ) || empty( $time ) || empty( $email ) ) {
			return '<div class="ltlb-error">' . esc_html__( 'Please fill the required fields.', 'ltl-bookings' ) . '</div>';
	}

	// compute start_at and end_at based on service duration
	$service_repo = new LTLB_ServiceRepository();
	$service = $service_repo->get_by_id( $service_id );
	$duration = $service && isset( $service['duration_min'] ) ? intval( $service['duration_min'] ) : 60;

	$start_at = $date . ' ' . $time . ':00';
	$start_dt = DateTime::createFromFormat('Y-m-d H:i:s', $start_at);
	if ( ! $start_dt ) {
			// try Y-m-d H:i
			$start_dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
	}
	if ( ! $start_dt ) return '<div class="ltlb-error">' . esc_html__( 'Invalid date/time.', 'ltl-bookings' ) . '</div>';

	$end_dt = clone $start_dt;
	$end_dt->modify( '+' . $duration . ' minutes' );

	$start_at_sql = $start_dt->format('Y-m-d H:i:s');
	$end_at_sql = $end_dt->format('Y-m-d H:i:s');

	$appointment_repo = new LTLB_AppointmentRepository();

	// conflict check
	if ( $appointment_repo->has_conflict( $start_at_sql, $end_at_sql, $service_id ) ) {
			return '<div class="ltlb-error">' . esc_html__( 'Selected slot is already booked.', 'ltl-bookings' ) . '</div>';
	}

	// upsert customer
	$customer_repo = new LTLB_CustomerRepository();
	$customer_id = $customer_repo->upsert_by_email( [
			'email' => $email,
			'first_name' => $first,
			'last_name' => $last,
			'phone' => $phone,
	] );

	if ( ! $customer_id ) {
			return '<div class="ltlb-error">' . esc_html__( 'Unable to save customer.', 'ltl-bookings' ) . '</div>';
	}

	$appt_id = $appointment_repo->create( [
			'service_id' => $service_id,
			'customer_id' => $customer_id,
			'start_at' => $start_at_sql,
			'end_at' => $end_at_sql,
			'status' => 'pending',
			'timezone' => date_default_timezone_get() ?: 'UTC',
	] );

	if ( ! $appt_id ) {
			return '<div class="ltlb-error">' . esc_html__( 'Unable to create appointment.', 'ltl-bookings' ) . '</div>';
	}

	return '<div class="ltlb-success">' . esc_html__( 'Booking created (pending). We will contact you.', 'ltl-bookings' ) . '</div>';
	}
}

// Initialize shortcodes
add_action( 'init', [ 'LTLB_Shortcodes', 'init' ] );

