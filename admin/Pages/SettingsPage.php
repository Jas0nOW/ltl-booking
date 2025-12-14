<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_SettingsPage {

	public function render(): void {
        if ( ! current_user_can('manage_options') ) wp_die( esc_html__( 'No access', 'ltl-bookings' ) );

		// Handle test email
		if ( isset( $_POST['ltlb_send_test_email'] ) ) {
			if ( ! check_admin_referer( 'ltlb_test_email_action', 'ltlb_test_email_nonce' ) ) {
                wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
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

                $subject = __( 'LazyBookings Test Email', 'ltl-bookings' );
                $body = '<p>' . esc_html__( 'This is a test email from LazyBookings plugin.', 'ltl-bookings' ) . '</p>';
                $body .= '<p>' . esc_html__( 'From:', 'ltl-bookings' ) . ' ' . esc_html($from_name) . ' &lt;' . esc_html($from_email) . '&gt;</p>';
				if ( ! empty( $reply_to ) ) {
                    $body .= '<p>' . esc_html__( 'Reply-To:', 'ltl-bookings' ) . ' ' . esc_html($reply_to) . '</p>';
				}
                $body .= '<p>' . esc_html__( 'Sent at:', 'ltl-bookings' ) . ' ' . esc_html( current_time('Y-m-d H:i:s') ) . '</p>';

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
                wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
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
				
				// Template Mode
				$settings['template_mode'] = LTLB_Sanitizer::text( $_POST['template_mode'] ?? 'service' );

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
			$template_mode = $settings['template_mode'] ?? 'service';

		$timezones = timezone_identifiers_list();
		?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_settings'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__( 'Settings', 'ltl-bookings' ); ?></h1>
            <hr class="wp-header-end">

			<form method="post">
				<?php wp_nonce_field( 'ltlb_settings_save_action', 'ltlb_settings_nonce' ); ?>
				<input type="hidden" name="ltlb_settings_save" value="1" />

				<!-- Save Button at Top -->
				<p class="submit" style="margin-top:10px; padding-top:0;">
                    <?php submit_button( esc_html__( 'Save Settings', 'ltl-bookings' ), 'primary', 'ltlb_settings_save_top', false ); ?>
				</p>

                <!-- GENERAL SETTINGS -->
                <div class="ltlb-card" style="margin-top:20px;">
                    <h2><?php echo esc_html__( 'General Settings', 'ltl-bookings' ); ?></h2>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="working_hours_start"><?php echo esc_html__( 'Working Hours', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <label><?php echo esc_html__( 'Start:', 'ltl-bookings' ); ?> <input name="working_hours_start" id="working_hours_start" type="number" value="<?php echo esc_attr( $start ); ?>" class="small-text" min="0" max="23"></label>
                                    &nbsp;&nbsp;
                                    <label><?php echo esc_html__( 'End:', 'ltl-bookings' ); ?> <input name="working_hours_end" id="working_hours_end" type="number" value="<?php echo esc_attr( $end ); ?>" class="small-text" min="0" max="23"></label>
                                    <p class="description"><?php echo esc_html__( 'Global working hours (0-23). Individual staff hours can override this.', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="slot_size_minutes"><?php echo esc_html__( 'Slot Size (minutes)', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input name="slot_size_minutes" id="slot_size_minutes" type="number" value="<?php echo esc_attr( $slot ); ?>" class="small-text" min="5" step="5" aria-describedby="ltlb-slot-size-desc" title="<?php echo esc_attr__( 'Controls the time grid used to compute available times.', 'ltl-bookings' ); ?>">
                                    <p class="description" id="ltlb-slot-size-desc"><?php echo esc_html__( 'Base time slot interval for calendar generation.', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ltlb_timezone"><?php echo esc_html__( 'Timezone', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <select name="ltlb_timezone" id="ltlb_timezone">
                                        <option value=""><?php echo esc_html__( 'WordPress Default', 'ltl-bookings' ); ?></option>
                                        <?php foreach ( $timezones as $tzid ): ?>
                                            <option value="<?php echo esc_attr( $tzid ); ?>" <?php selected( $tz, $tzid ); ?>><?php echo esc_html( $tzid ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="default_status"><?php echo esc_html__( 'Default Booking Status', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <select name="default_status" id="default_status">
                                        <option value="pending" <?php selected( $default_status, 'pending' ); ?>><?php echo esc_html__( 'Pending', 'ltl-bookings' ); ?></option>
                                        <option value="confirmed" <?php selected( $default_status, 'confirmed' ); ?>><?php echo esc_html__( 'Confirmed', 'ltl-bookings' ); ?></option>
                                    </select>
                                    <p class="description"><?php echo esc_html__( 'Status assigned to new bookings.', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="pending_blocks"><?php echo esc_html__( 'Pending Blocks Availability', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <label><input name="pending_blocks" id="pending_blocks" type="checkbox" value="1" <?php checked( $pending_blocks ); ?> aria-describedby="ltlb-pending-blocks-desc" title="<?php echo esc_attr__( 'If enabled, pending bookings are treated as occupied.', 'ltl-bookings' ); ?>"> <?php echo esc_html__( 'Yes, pending bookings block the time slot', 'ltl-bookings' ); ?></label>
                                    <p class="description" id="ltlb-pending-blocks-desc"><?php echo esc_html__( 'Useful to avoid double bookings before you confirm.', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="template_mode"><?php echo esc_html__( 'Booking Template Mode', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <select name="template_mode" id="template_mode" aria-describedby="ltlb-template-mode-desc" title="<?php echo esc_attr__( 'Controls whether services (appointments) or hotel date ranges are bookable.', 'ltl-bookings' ); ?>">
                                        <option value="service" <?php selected( $template_mode, 'service' ); ?>><?php echo esc_html__( 'Service Booking (Appointments)', 'ltl-bookings' ); ?></option>
                                        <option value="hotel" <?php selected( $template_mode, 'hotel' ); ?>><?php echo esc_html__( 'Hotel Booking (Check-in/Check-out)', 'ltl-bookings' ); ?></option>
                                    </select>
                                    <p class="description" id="ltlb-template-mode-desc"><?php echo esc_html__( 'Switch between appointment-based booking (services) and date-range booking (hotel/rooms).', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- EMAIL SETTINGS -->
                <div class="ltlb-card" style="margin-top:20px;">
                    <h2><?php echo esc_html__( 'Email Settings', 'ltl-bookings' ); ?></h2>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label><?php echo esc_html__( 'Sender Info', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <p><label><?php echo esc_html__( 'From Name:', 'ltl-bookings' ); ?> <input name="ltlb_email_from_name" type="text" value="<?php echo esc_attr( $mail_from_name ); ?>" class="regular-text"></label></p>
                                    <p><label><?php echo esc_html__( 'From Email:', 'ltl-bookings' ); ?> <input name="ltlb_email_from_address" type="email" value="<?php echo esc_attr( $mail_from_email ); ?>" class="regular-text"></label></p>
                                    <p><label><?php echo esc_html__( 'Reply-To:', 'ltl-bookings' ); ?> <input name="ltlb_email_reply_to" type="email" value="<?php echo esc_attr( $mail_reply_to ); ?>" class="regular-text"></label></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php echo esc_html__( 'Admin Notifications', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <label><input name="ltlb_email_send_admin" type="checkbox" value="1" <?php checked( $mail_admin_enabled ); ?>> <?php echo esc_html__( 'Send email to admin on new booking', 'ltl-bookings' ); ?></label>
                                    <br><br>
                                    <label><?php echo esc_html__( 'Subject:', 'ltl-bookings' ); ?> <input name="ltlb_email_admin_subject" type="text" value="<?php echo esc_attr( $mail_admin_subject ); ?>" class="large-text"></label>
                                    <br>
                                    <label><?php echo esc_html__( 'Body:', 'ltl-bookings' ); ?><br>
                                    <textarea name="ltlb_email_admin_body" class="large-text" rows="5" aria-describedby="ltlb-email-admin-placeholders" title="<?php echo esc_attr__( 'You can use tags that will be replaced automatically.', 'ltl-bookings' ); ?>"><?php echo esc_textarea( $mail_admin_template ); ?></textarea></label>
                                    <p class="description" id="ltlb-email-admin-placeholders"><?php echo esc_html__( 'Available tags: {customer_name}, {service_name}, {start_time}, {end_time}, {status}', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php echo esc_html__( 'Customer Notifications', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <label><input name="ltlb_email_send_customer" type="checkbox" value="1" <?php checked( $mail_customer_enabled ); ?>> <?php echo esc_html__( 'Send confirmation email to customer', 'ltl-bookings' ); ?></label>
                                    <br><br>
                                    <label><?php echo esc_html__( 'Subject:', 'ltl-bookings' ); ?> <input name="ltlb_email_customer_subject" type="text" value="<?php echo esc_attr( $mail_customer_subject ); ?>" class="large-text"></label>
                                    <br>
                                    <label><?php echo esc_html__( 'Body:', 'ltl-bookings' ); ?><br>
                                    <textarea name="ltlb_email_customer_body" class="large-text" rows="5" aria-describedby="ltlb-email-customer-placeholders" title="<?php echo esc_attr__( 'You can use tags that will be replaced automatically.', 'ltl-bookings' ); ?>"><?php echo esc_textarea( $mail_customer_template ); ?></textarea></label>
                                    <p class="description" id="ltlb-email-customer-placeholders"><?php echo esc_html__( 'Available tags: {customer_name}, {service_name}, {start_time}, {end_time}, {status}', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- LOGGING SETTINGS -->
                <div class="ltlb-card" style="margin-top:20px;">
                    <h2><?php echo esc_html__( 'Logging', 'ltl-bookings' ); ?></h2>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="logging_enabled"><?php echo esc_html__( 'Enable Logging', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <label><input name="logging_enabled" id="logging_enabled" type="checkbox" value="1" <?php checked( $logging_enabled ); ?> aria-describedby="ltlb-logging-enabled-desc" title="<?php echo esc_attr__( 'Writes events/errors to a log file for diagnostics.', 'ltl-bookings' ); ?>"> <?php echo esc_html__( 'Log errors and events to file', 'ltl-bookings' ); ?></label>
                                    <p class="description" id="ltlb-logging-enabled-desc"><?php echo esc_html__( 'Enable this only when needed (e.g., for debugging).', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="log_level"><?php echo esc_html__( 'Log Level', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <select name="log_level" id="log_level" aria-describedby="ltlb-log-level-desc" title="<?php echo esc_attr__( 'Controls how verbose logging is.', 'ltl-bookings' ); ?>">
                                        <option value="error" <?php selected( $log_level, 'error' ); ?>><?php echo esc_html__( 'Error', 'ltl-bookings' ); ?></option>
                                        <option value="warn" <?php selected( $log_level, 'warn' ); ?>><?php echo esc_html__( 'Warning', 'ltl-bookings' ); ?></option>
                                        <option value="info" <?php selected( $log_level, 'info' ); ?>><?php echo esc_html__('Info', 'ltl-bookings'); ?></option>
                                        <option value="debug" <?php selected( $log_level, 'debug' ); ?>><?php echo esc_html__('Debug', 'ltl-bookings'); ?></option>
                                    </select>
                                    <p class="description" id="ltlb-log-level-desc"><?php echo esc_html__( 'For normal use, "Error" is usually enough. Use "Debug" only temporarily.', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

				<p class="submit" style="margin-top:20px;">
                    <?php submit_button( esc_html__( 'Save Settings', 'ltl-bookings' ), 'primary', 'ltlb_settings_save', false ); ?>
                </p>
			</form>

            <!-- TEST EMAIL -->
            <div class="ltlb-card" style="margin-top:20px;">
                <h3><?php echo esc_html__( 'Test Email Configuration', 'ltl-bookings' ); ?></h3>
                <form method="post" style="display:flex; gap:10px; align-items:flex-end;">
                    <?php wp_nonce_field( 'ltlb_test_email_action', 'ltlb_test_email_nonce' ); ?>
                    <input type="hidden" name="ltlb_send_test_email" value="1">
                    <div>
                        <label for="test_email_address" style="display:block;margin-bottom:5px;"><?php echo esc_html__( 'Send test email to:', 'ltl-bookings' ); ?></label>
                        <input type="email" name="test_email_address" id="test_email_address" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required>
                    </div>
                    <?php submit_button( esc_html__( 'Send Test Email', 'ltl-bookings' ), 'secondary', 'submit', false ); ?>
                </form>
            </div>
		</div>

		<?php
	}
}

