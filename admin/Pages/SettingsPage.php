<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_SettingsPage {

	public function render(): void {
		if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

		// Handle save
			if ( isset( $_POST['ltlb_settings_save'] ) ) {
			if ( ! check_admin_referer( 'ltlb_settings_save_action', 'ltlb_settings_nonce' ) ) {
				wp_die( esc_html__('Nonce verification failed', 'ltl-bookings') );
			}
				// Collect and sanitize into a single lazy_settings option
				$settings = get_option( 'lazy_settings', [] );
				if ( ! is_array( $settings ) ) $settings = [];
				$settings['working_hours_start'] = LTLB_Sanitizer::int( $_POST['working_hours_start'] ?? 9 );
				$settings['working_hours_end'] = LTLB_Sanitizer::int( $_POST['working_hours_end'] ?? 17 );
				$settings['slot_size_minutes'] = LTLB_Sanitizer::int( $_POST['slot_size_minutes'] ?? 60 );
				$settings['timezone'] = LTLB_Sanitizer::text( $_POST['ltlb_timezone'] ?? '' );
				$settings['default_status'] = LTLB_Sanitizer::text( $_POST['default_status'] ?? 'pending' );
				$settings['pending_blocks'] = isset( $_POST['pending_blocks'] ) ? 1 : 0;
				$settings['mail_admin_enabled'] = isset( $_POST['ltlb_email_send_admin'] ) ? 1 : 0;
				$settings['mail_customer_enabled'] = isset( $_POST['ltlb_email_send_customer'] ) ? 1 : 0;
				$settings['mail_from_name'] = LTLB_Sanitizer::text( $_POST['ltlb_email_from_name'] ?? '' );
				$settings['mail_from_email'] = LTLB_Sanitizer::email( $_POST['ltlb_email_from_address'] ?? '' );
				$settings['mail_admin_template'] = wp_kses_post( $_POST['ltlb_email_admin_body'] ?? '' );
				$settings['mail_customer_template'] = wp_kses_post( $_POST['ltlb_email_customer_body'] ?? '' );

				update_option( 'lazy_settings', $settings );

			$redirect = admin_url( 'admin.php?page=ltlb_settings' );
			LTLB_Notices::add( __( 'Settings saved.', 'ltl-bookings' ), 'success' );
			wp_safe_redirect( $redirect );
			exit;
		}

			$settings = get_option( 'lazy_settings', [] );
			if ( ! is_array( $settings ) ) $settings = [];
			$start = (int) ( $settings['working_hours_start'] ?? 9 );
			$end = (int) ( $settings['working_hours_end'] ?? 17 );
			$slot = (int) ( $settings['slot_size_minutes'] ?? 60 );
			$tz = $settings['timezone'] ?? '';
			$default_status = $settings['default_status'] ?? 'pending';
			$pending_blocks = $settings['pending_blocks'] ?? 0;

		$timezones = timezone_identifiers_list();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('LazyBookings Settings', 'ltl-bookings'); ?></h1>

			<?php // Notices are rendered via LTLB_Notices::render() hooked to admin_notices ?>

			<form method="post">
				<?php wp_nonce_field( 'ltlb_settings_save_action', 'ltlb_settings_nonce' ); ?>
				<input type="hidden" name="ltlb_settings_save" value="1">

				<table class="form-table">
					<tbody>
						<tr>
							<th><label for="working_hours_start"><?php echo esc_html__('Working hours start (hour)', 'ltl-bookings'); ?></label></th>
							<td><input name="working_hours_start" id="working_hours_start" type="number" value="<?php echo esc_attr( $start ); ?>" class="small-text"> (0-23)</td>
						</tr>
						<tr>
							<th><label for="working_hours_end"><?php echo esc_html__('Working hours end (hour)', 'ltl-bookings'); ?></label></th>
							<td><input name="working_hours_end" id="working_hours_end" type="number" value="<?php echo esc_attr( $end ); ?>" class="small-text"> (exclusive, 1-24)</td>
						</tr>
						<tr>
							<th><label for="slot_size_minutes"><?php echo esc_html__('Slot size (minutes)', 'ltl-bookings'); ?></label></th>
							<td><input name="slot_size_minutes" id="slot_size_minutes" type="number" value="<?php echo esc_attr( $slot ); ?>" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_timezone"><?php echo esc_html__('Timezone (optional)', 'ltl-bookings'); ?></label></th>
							<td>
								<select name="ltlb_timezone" id="ltlb_timezone">
									<option value=""><?php echo esc_html__('Use site timezone', 'ltl-bookings'); ?></option>
									<?php foreach ( $timezones as $tzid ): ?>
										<option value="<?php echo esc_attr( $tzid ); ?>" <?php selected( $tz, $tzid ); ?>><?php echo esc_html( $tzid ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="default_status"><?php echo esc_html__('Default appointment status', 'ltl-bookings'); ?></label></th>
							<td>
								<select name="default_status" id="default_status">
									<option value="pending" <?php selected( $default_status, 'pending' ); ?>><?php echo esc_html__('Pending', 'ltl-bookings'); ?></option>
									<option value="confirmed" <?php selected( $default_status, 'confirmed' ); ?>><?php echo esc_html__('Confirmed', 'ltl-bookings'); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php echo esc_html__('Pending blocks slots', 'ltl-bookings'); ?></th>
							<td><label><input name="pending_blocks" type="checkbox" value="1" <?php checked( $pending_blocks ); ?>> <?php echo esc_html__('Treat pending appointments as blocking slots', 'ltl-bookings'); ?></label></td>
						</tr>
						<tr>
							<th colspan="2"><h2><?php echo esc_html__('Email Settings', 'ltl-bookings'); ?></h2></th>
						</tr>
						<tr>
							<th><label for="ltlb_email_from_name"><?php echo esc_html__('From name', 'ltl-bookings'); ?></label></th>
							<td><input name="ltlb_email_from_name" id="ltlb_email_from_name" type="text" value="<?php echo esc_attr( get_option('ltlb_email_from_name', '') ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_email_from_address"><?php echo esc_html__('From email', 'ltl-bookings'); ?></label></th>
							<td><input name="ltlb_email_from_address" id="ltlb_email_from_address" type="email" value="<?php echo esc_attr( get_option('ltlb_email_from_address', get_option('admin_email')) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_email_admin_subject"><?php echo esc_html__('Admin email subject', 'ltl-bookings'); ?></label></th>
							<td><input name="ltlb_email_admin_subject" id="ltlb_email_admin_subject" type="text" value="<?php echo esc_attr( get_option('ltlb_email_admin_subject', '') ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_email_admin_body"><?php echo esc_html__('Admin email body', 'ltl-bookings'); ?></label></th>
							<td><textarea name="ltlb_email_admin_body" id="ltlb_email_admin_body" class="large-text" rows="6"><?php echo esc_textarea( get_option('ltlb_email_admin_body', '') ); ?></textarea>
								<p class="description"><?php echo esc_html__('Placeholders: {service}, {start}, {end}, {name}, {email}, {phone}, {status}, {appointment_id}', 'ltl-bookings'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="ltlb_email_customer_subject"><?php echo esc_html__('Customer email subject', 'ltl-bookings'); ?></label></th>
							<td><input name="ltlb_email_customer_subject" id="ltlb_email_customer_subject" type="text" value="<?php echo esc_attr( get_option('ltlb_email_customer_subject', '') ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_email_customer_body"><?php echo esc_html__('Customer email body', 'ltl-bookings'); ?></label></th>
							<td><textarea name="ltlb_email_customer_body" id="ltlb_email_customer_body" class="large-text" rows="6"><?php echo esc_textarea( get_option('ltlb_email_customer_body', '') ); ?></textarea>
								<p class="description"><?php echo esc_html__('Placeholders: {service}, {start}, {end}, {name}, {email}, {phone}, {status}, {appointment_id}', 'ltl-bookings'); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php echo esc_html__('Send customer email', 'ltl-bookings'); ?></th>
							<td><label><input name="ltlb_email_send_customer" type="checkbox" value="1" <?php checked( get_option('ltlb_email_send_customer', 1) ); ?>> <?php echo esc_html__('Send confirmation email to customer after booking', 'ltl-bookings'); ?></label></td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( esc_html__('Save Settings', 'ltl-bookings') ); ?>
			</form>
		</div>
		<?php
	}
}

