<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_SettingsPage {

	public function render(): void {
		if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

		// Handle test email
		if ( isset( $_POST['ltlb_send_test_email'] ) ) {
			if ( ! check_admin_referer( 'ltlb_test_email_action', 'ltlb_test_email_nonce' ) ) {
				wp_die( esc_html__('Nonce verification failed', 'ltl-bookings') );
			}
			$test_email = sanitize_email( $_POST['test_email_address'] ?? '' );
			if ( ! empty( $test_email ) && is_email( $test_email ) ) {
				$settings = get_option( 'lazy_settings', [] );
				$from_name = $settings['mail_from_name'] ?? get_bloginfo('name');
				$from_email = $settings['mail_from_email'] ?? get_option('admin_email');
				$reply_to = $settings['mail_reply_to'] ?? '';
				
				$headers = [];
				$headers[] = 'Content-Type: text/html; charset=UTF-8';
				$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
				if ( ! empty( $reply_to ) && is_email( $reply_to ) ) {
					$headers[] = 'Reply-To: ' . $reply_to;
				}

				$subject = 'LazyBookings Test Email';
				$body = '<p>This is a test email from LazyBookings plugin.</p>';
				$body .= '<p>From: ' . esc_html($from_name) . ' &lt;' . esc_html($from_email) . '&gt;</p>';
				if ( ! empty( $reply_to ) ) {
					$body .= '<p>Reply-To: ' . esc_html($reply_to) . '</p>';
				}
				$body .= '<p>Sent at: ' . current_time('Y-m-d H:i:s') . '</p>';

				$sent = wp_mail( $test_email, $subject, $body, $headers );
				if ( $sent ) {
					LTLB_Notices::add( __( 'Test email sent successfully to ', 'ltl-bookings' ) . $test_email, 'success' );
				} else {
					LTLB_Notices::add( __( 'Failed to send test email.', 'ltl-bookings' ), 'error' );
				}
			} else {
				LTLB_Notices::add( __( 'Invalid email address.', 'ltl-bookings' ), 'error' );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_settings' ) );
			exit;
		}

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
				$settings['mail_reply_to'] = LTLB_Sanitizer::email( $_POST['ltlb_email_reply_to'] ?? '' );
				$settings['mail_admin_template'] = wp_kses_post( $_POST['ltlb_email_admin_body'] ?? '' );
				$settings['mail_customer_template'] = wp_kses_post( $_POST['ltlb_email_customer_body'] ?? '' );
				$settings['mail_admin_subject'] = LTLB_Sanitizer::text( $_POST['ltlb_email_admin_subject'] ?? '' );
				$settings['mail_customer_subject'] = LTLB_Sanitizer::text( $_POST['ltlb_email_customer_subject'] ?? '' );
				
				// Logging settings
				$settings['logging_enabled'] = isset( $_POST['logging_enabled'] ) ? 1 : 0;
				$settings['log_level'] = LTLB_Sanitizer::text( $_POST['log_level'] ?? 'error' );

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
			$mail_from_name = $settings['mail_from_name'] ?? '';
			$mail_from_email = $settings['mail_from_email'] ?? get_option('admin_email');
			$mail_reply_to = $settings['mail_reply_to'] ?? '';
			$mail_admin_template = $settings['mail_admin_template'] ?? '';
			$mail_customer_template = $settings['mail_customer_template'] ?? '';
			$mail_customer_enabled = isset( $settings['mail_customer_enabled'] ) ? (int)$settings['mail_customer_enabled'] : 1;
			$mail_admin_enabled = isset( $settings['mail_admin_enabled'] ) ? (int)$settings['mail_admin_enabled'] : 0;
			$mail_admin_subject = $settings['mail_admin_subject'] ?? '';
			$mail_customer_subject = $settings['mail_customer_subject'] ?? '';
			
			$logging_enabled = isset( $settings['logging_enabled'] ) ? (int)$settings['logging_enabled'] : 0;
			$log_level = $settings['log_level'] ?? 'error';

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
							<th colspan="2"><h2><?php echo esc_html__('Logging Settings', 'ltl-bookings'); ?></h2></th>
						</tr>
						<tr>
							<th><?php echo esc_html__('Enable Logging', 'ltl-bookings'); ?></th>
							<td>
								<label><input name="logging_enabled" type="checkbox" value="1" <?php checked( $logging_enabled ); ?>> <?php echo esc_html__('Enable internal logging to wp-content/debug.log', 'ltl-bookings'); ?></label>
								<p class="description"><?php echo esc_html__('Requires WP_DEBUG_LOG to be enabled in wp-config.php. PII is automatically hashed/truncated.', 'ltl-bookings'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="log_level"><?php echo esc_html__('Log Level', 'ltl-bookings'); ?></label></th>
							<td>
								<select name="log_level" id="log_level">
									<option value="error" <?php selected( $log_level, 'error' ); ?>><?php echo esc_html__('Error (critical issues only)', 'ltl-bookings'); ?></option>
									<option value="warn" <?php selected( $log_level, 'warn' ); ?>><?php echo esc_html__('Warning (errors + warnings)', 'ltl-bookings'); ?></option>
									<option value="info" <?php selected( $log_level, 'info' ); ?>><?php echo esc_html__('Info (errors + warnings + info)', 'ltl-bookings'); ?></option>
									<option value="debug" <?php selected( $log_level, 'debug' ); ?>><?php echo esc_html__('Debug (all messages)', 'ltl-bookings'); ?></option>
								</select>
								<p class="description"><?php echo esc_html__('Lower levels include higher levels (e.g., Info includes Error and Warning).', 'ltl-bookings'); ?></p>
							</td>
						</tr>
						<tr>
							<th colspan="2"><h2><?php echo esc_html__('Email Settings', 'ltl-bookings'); ?></h2></th>
						</tr>
						<tr>
							<th><label for="ltlb_email_from_name"><?php echo esc_html__('From name', 'ltl-bookings'); ?></label></th>
							<td><input name="ltlb_email_from_name" id="ltlb_email_from_name" type="text" value="<?php echo esc_attr( $mail_from_name ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_email_from_address"><?php echo esc_html__('From email', 'ltl-bookings'); ?></label></th>
							<td><input name="ltlb_email_from_address" id="ltlb_email_from_address" type="email" value="<?php echo esc_attr( $mail_from_email ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_email_reply_to"><?php echo esc_html__('Reply-To email (optional)', 'ltl-bookings'); ?></label></th>
							<td><input name="ltlb_email_reply_to" id="ltlb_email_reply_to" type="email" value="<?php echo esc_attr( $mail_reply_to ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__('If set, replies will go to this address instead of From email.', 'ltl-bookings'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="ltlb_email_admin_subject"><?php echo esc_html__('Admin email subject', 'ltl-bookings'); ?></label></th>
							<td><input name="ltlb_email_admin_subject" id="ltlb_email_admin_subject" type="text" value="<?php echo esc_attr( $mail_admin_subject ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_email_admin_body"><?php echo esc_html__('Admin email body', 'ltl-bookings'); ?></label></th>
							<td><textarea name="ltlb_email_admin_body" id="ltlb_email_admin_body" class="large-text" rows="6"><?php echo esc_textarea( $mail_admin_template ); ?></textarea>
								<p class="description"><?php echo esc_html__('Placeholders: {service}, {start}, {end}, {name}, {email}, {phone}, {status}, {appointment_id}', 'ltl-bookings'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="ltlb_email_customer_subject"><?php echo esc_html__('Customer email subject', 'ltl-bookings'); ?></label></th>
							<td><input name="ltlb_email_customer_subject" id="ltlb_email_customer_subject" type="text" value="<?php echo esc_attr( $mail_customer_subject ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_email_customer_body"><?php echo esc_html__('Customer email body', 'ltl-bookings'); ?></label></th>
							<td><textarea name="ltlb_email_customer_body" id="ltlb_email_customer_body" class="large-text" rows="6"><?php echo esc_textarea( $mail_customer_template ); ?></textarea>
								<p class="description"><?php echo esc_html__('Placeholders: {service}, {start}, {end}, {name}, {email}, {phone}, {status}, {appointment_id}', 'ltl-bookings'); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php echo esc_html__('Send customer email', 'ltl-bookings'); ?></th>
							<td>
								<label><input name="ltlb_email_send_admin" type="checkbox" value="1" <?php checked( $mail_admin_enabled ); ?>> <?php echo esc_html__('Send notification email to admin on booking', 'ltl-bookings'); ?></label>
								<br>
								<label><input name="ltlb_email_send_customer" type="checkbox" value="1" <?php checked( $mail_customer_enabled ); ?>> <?php echo esc_html__('Send confirmation email to customer after booking', 'ltl-bookings'); ?></label>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( esc_html__('Save Settings', 'ltl-bookings') ); ?>
			</form>

			<hr>
			<h2><?php echo esc_html__('Email Deliverability Test', 'ltl-bookings'); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'ltlb_test_email_action', 'ltlb_test_email_nonce' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th><label for="test_email_address"><?php echo esc_html__('Send test email to', 'ltl-bookings'); ?></label></th>
							<td>
								<input name="test_email_address" id="test_email_address" type="email" value="<?php echo esc_attr( get_option('admin_email') ); ?>" class="regular-text">
								<p class="description"><?php echo esc_html__('Sends a test email using current From/Reply-To settings.', 'ltl-bookings'); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<p><button type="submit" name="ltlb_send_test_email" class="button button-secondary"><?php echo esc_html__('Send Test Email', 'ltl-bookings'); ?></button></p>
			</form>
		</div>
		<?php
	}
}

