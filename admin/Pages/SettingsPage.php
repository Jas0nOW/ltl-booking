<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_SettingsPage {

	public function render(): void {
        if ( ! current_user_can('manage_booking_settings') && ! current_user_can('manage_options') ) wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );

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

                $sent = ( class_exists( 'LTLB_Mailer' ) && method_exists( 'LTLB_Mailer', 'wp_mail' ) )
                    ? LTLB_Mailer::wp_mail( $test_email, $subject, $body, $headers )
                    : wp_mail( $test_email, $subject, $body, $headers );
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
                $current_tab_save = sanitize_key( (string) ( $_POST['ltlb_settings_tab'] ?? 'general' ) );
                if ( ! in_array( $current_tab_save, [ 'general', 'email', 'ai', 'security' ], true ) ) {
                    $current_tab_save = 'general';
                }

                // Collect and sanitize into a single lazy_settings option
				$settings = get_option( 'lazy_settings', [] );
				if ( ! is_array( $settings ) ) $settings = [];

                if ( $current_tab_save === 'general' ) {
                    $settings['working_hours_start'] = LTLB_Sanitizer::int( $_POST['working_hours_start'] ?? ( $settings['working_hours_start'] ?? 9 ) );
                    $settings['working_hours_end'] = LTLB_Sanitizer::int( $_POST['working_hours_end'] ?? ( $settings['working_hours_end'] ?? 17 ) );
                    $settings['slot_size_minutes'] = LTLB_Sanitizer::int( $_POST['slot_size_minutes'] ?? ( $settings['slot_size_minutes'] ?? 60 ) );
                    $settings['timezone'] = LTLB_Sanitizer::text( $_POST['ltlb_timezone'] ?? ( $settings['timezone'] ?? '' ) );
                    $settings['default_status'] = LTLB_Sanitizer::text( $_POST['default_status'] ?? ( $settings['default_status'] ?? 'pending' ) );
                    $settings['pending_blocks'] = isset( $_POST['pending_blocks'] ) ? 1 : 0;

                    // Logging settings
                    $settings['logging_enabled'] = isset( $_POST['logging_enabled'] ) ? 1 : 0;
                    $settings['log_level'] = LTLB_Sanitizer::text( $_POST['log_level'] ?? ( $settings['log_level'] ?? 'error' ) );

                    // Template Mode
                    $settings['template_mode'] = LTLB_Sanitizer::text( $_POST['template_mode'] ?? ( $settings['template_mode'] ?? 'service' ) );

                    // Profit model (simple margin)
                    $profit_margin = LTLB_Sanitizer::int( $_POST['profit_margin_percent'] ?? ( $settings['profit_margin_percent'] ?? 100 ) );
                    $settings['profit_margin_percent'] = max( 0, min( 100, (int) $profit_margin ) );

                    // Hotel fees (used for gross profit)
                    $hotel_fee_percent = LTLB_Sanitizer::int( $_POST['hotel_fee_percent'] ?? ( $settings['hotel_fee_percent'] ?? 0 ) );
                    $settings['hotel_fee_percent'] = max( 0, min( 100, (int) $hotel_fee_percent ) );
                    $settings['hotel_fee_fixed_cents'] = max( 0, LTLB_Sanitizer::money_cents( $_POST['hotel_fee_fixed'] ?? ( $settings['hotel_fee_fixed_cents'] ?? 0 ) ) );
                }

                if ( $current_tab_save === 'email' ) {
                    $settings['mail_admin_enabled'] = isset( $_POST['ltlb_email_send_admin'] ) ? 1 : 0;
                    $settings['mail_customer_enabled'] = isset( $_POST['ltlb_email_send_customer'] ) ? 1 : 0;
                    $settings['mail_from_name'] = LTLB_Sanitizer::text( $_POST['ltlb_email_from_name'] ?? ( $settings['mail_from_name'] ?? '' ) );
                    $settings['mail_from_email'] = LTLB_Sanitizer::email( $_POST['ltlb_email_from_address'] ?? ( $settings['mail_from_email'] ?? '' ) );
                    $settings['mail_reply_to'] = LTLB_Sanitizer::email( $_POST['ltlb_email_reply_to'] ?? ( $settings['mail_reply_to'] ?? '' ) );
                    $settings['mail_admin_template'] = wp_kses_post( $_POST['ltlb_email_admin_body'] ?? ( $settings['mail_admin_template'] ?? '' ) );
                    $settings['mail_customer_template'] = wp_kses_post( $_POST['ltlb_email_customer_body'] ?? ( $settings['mail_customer_template'] ?? '' ) );
                    $settings['mail_admin_subject'] = LTLB_Sanitizer::text( $_POST['ltlb_email_admin_subject'] ?? ( $settings['mail_admin_subject'] ?? '' ) );
                    $settings['mail_customer_subject'] = LTLB_Sanitizer::text( $_POST['ltlb_email_customer_subject'] ?? ( $settings['mail_customer_subject'] ?? '' ) );

                // SMTP (optional)
                $settings['smtp_enabled'] = isset( $_POST['ltlb_smtp_enabled'] ) ? 1 : 0;
                $settings['smtp_host'] = LTLB_Sanitizer::text( $_POST['ltlb_smtp_host'] ?? ( $settings['smtp_host'] ?? '' ) );
                $settings['smtp_port'] = max( 0, intval( $_POST['ltlb_smtp_port'] ?? ( $settings['smtp_port'] ?? 587 ) ) );
                $enc = sanitize_key( (string) ( $_POST['ltlb_smtp_encryption'] ?? ( $settings['smtp_encryption'] ?? 'tls' ) ) );
                $settings['smtp_encryption'] = in_array( $enc, [ 'none', 'tls', 'ssl' ], true ) ? $enc : 'tls';
                $settings['smtp_auth'] = isset( $_POST['ltlb_smtp_auth'] ) ? 1 : 0;
                $settings['smtp_username'] = LTLB_Sanitizer::text( $_POST['ltlb_smtp_username'] ?? ( $settings['smtp_username'] ?? '' ) );
                $settings['smtp_scope'] = isset( $_POST['ltlb_smtp_scope_plugin_only'] ) ? 'plugin' : 'global';

                // Store SMTP password separately (autoload=no). Blank input keeps existing.
                $mail_keys = get_option( 'lazy_mail_keys', [] );
                if ( ! is_array( $mail_keys ) ) {
                    $mail_keys = [];
                }
                $smtp_password = isset( $_POST['ltlb_smtp_password'] ) ? (string) $_POST['ltlb_smtp_password'] : '';
                $smtp_password = sanitize_text_field( $smtp_password );
                if ( $smtp_password !== '' ) {
                    $mail_keys['smtp_password'] = $smtp_password;
                    update_option( 'lazy_mail_keys', $mail_keys, false );
                }
                }

                if ( $current_tab_save === 'security' ) {
                    $settings['enable_payments'] = isset( $_POST['enable_payments'] ) ? 1 : 0;
                    $processor = sanitize_key( (string) ( $_POST['payment_processor'] ?? ( $settings['payment_processor'] ?? 'stripe' ) ) );
                    $settings['payment_processor'] = in_array( $processor, [ 'stripe', 'paypal' ], true ) ? $processor : 'stripe';

                    $store_country = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $_POST['store_country'] ?? ( $settings['store_country'] ?? '' ) ) ) );
                    $settings['store_country'] = $store_country !== '' ? substr( $store_country, 0, 2 ) : 'DE';
                    $currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $_POST['default_currency'] ?? ( $settings['default_currency'] ?? '' ) ) ) );
                    $settings['default_currency'] = $currency !== '' ? substr( $currency, 0, 3 ) : 'EUR';
                    $stripe_flow = sanitize_key( (string) ( $_POST['stripe_flow'] ?? ( $settings['stripe_flow'] ?? 'checkout' ) ) );
                    $settings['stripe_flow'] = in_array( $stripe_flow, [ 'checkout', 'elements' ], true ) ? $stripe_flow : 'checkout';

                    $allowed_methods = [ 'stripe_card', 'paypal', 'klarna', 'cash', 'pos_card', 'invoice' ];
                    $methods_in = isset( $_POST['payment_methods'] ) ? (array) $_POST['payment_methods'] : [];
                    $methods = [];
                    foreach ( $methods_in as $m ) {
                        $m = sanitize_key( (string) $m );
                        if ( in_array( $m, $allowed_methods, true ) ) {
                            $methods[] = $m;
                        }
                    }
                    $settings['payment_methods'] = array_values( array_unique( $methods ) );

                    // Store payment keys separately (autoload=no). Empty inputs keep existing keys.
                    $payment_keys = get_option( 'lazy_payment_keys', [] );
                    if ( ! is_array( $payment_keys ) ) {
                        $payment_keys = [];
                    }
                    $stripe_public = sanitize_text_field( (string) ( $_POST['stripe_public_key'] ?? '' ) );
                    $stripe_secret = sanitize_text_field( (string) ( $_POST['stripe_secret_key'] ?? '' ) );
                    $stripe_webhook_secret = sanitize_text_field( (string) ( $_POST['stripe_webhook_secret'] ?? '' ) );
                    $paypal_client_id = sanitize_text_field( (string) ( $_POST['paypal_client_id'] ?? '' ) );
                    $paypal_secret = sanitize_text_field( (string) ( $_POST['paypal_secret'] ?? '' ) );

                    if ( $stripe_public !== '' ) {
                        $payment_keys['stripe_public_key'] = $stripe_public;
                    }
                    if ( $stripe_secret !== '' ) {
                        $payment_keys['stripe_secret_key'] = $stripe_secret;
                    }
					if ( $stripe_webhook_secret !== '' ) {
						$payment_keys['stripe_webhook_secret'] = $stripe_webhook_secret;
					}
                    if ( $paypal_client_id !== '' ) {
                        $payment_keys['paypal_client_id'] = $paypal_client_id;
                    }
                    if ( $paypal_secret !== '' ) {
                        $payment_keys['paypal_secret'] = $paypal_secret;
                    }

                    update_option( 'lazy_payment_keys', $payment_keys, false );
                }

                update_option( 'lazy_settings', $settings );

			$redirect = admin_url( 'admin.php?page=ltlb_settings&settings_updated=1' );
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
            $smtp_enabled = isset( $settings['smtp_enabled'] ) ? (int) $settings['smtp_enabled'] : 0;
            $smtp_host = $settings['smtp_host'] ?? '';
            $smtp_port = isset( $settings['smtp_port'] ) ? intval( $settings['smtp_port'] ) : 587;
            $smtp_encryption = $settings['smtp_encryption'] ?? 'tls';
            $smtp_auth = isset( $settings['smtp_auth'] ) ? (int) $settings['smtp_auth'] : 1;
            $smtp_username = $settings['smtp_username'] ?? '';
            $smtp_scope = $settings['smtp_scope'] ?? 'global';
            $smtp_scope = in_array( (string) $smtp_scope, [ 'global', 'plugin' ], true ) ? (string) $smtp_scope : 'global';
            $mail_keys = get_option( 'lazy_mail_keys', [] );
            if ( ! is_array( $mail_keys ) ) {
                $mail_keys = [];
            }
            $has_smtp_password = ! empty( $mail_keys['smtp_password'] ?? '' );
			
			$logging_enabled = isset( $settings['logging_enabled'] ) ? (int)$settings['logging_enabled'] : 0;
			$log_level = $settings['log_level'] ?? 'error';
			$template_mode = $settings['template_mode'] ?? 'service';
            $profit_margin_percent = isset( $settings['profit_margin_percent'] ) ? max( 0, min( 100, (int) $settings['profit_margin_percent'] ) ) : 100;
            $hotel_fee_percent = isset( $settings['hotel_fee_percent'] ) ? max( 0, min( 100, (int) $settings['hotel_fee_percent'] ) ) : 0;
            $hotel_fee_fixed_cents = isset( $settings['hotel_fee_fixed_cents'] ) ? max( 0, (int) $settings['hotel_fee_fixed_cents'] ) : 0;
            $hotel_fee_fixed = number_format( $hotel_fee_fixed_cents / 100, 2, '.', '' );

		$timezones = timezone_identifiers_list();

		// Get AI config and business context
		$ai_config = get_option( 'lazy_ai_config', [] );
		if ( ! is_array( $ai_config ) ) $ai_config = [];
		$ai_enabled = $ai_config['enabled'] ?? 0;
		$ai_provider = $ai_config['provider'] ?? 'gemini';
		$ai_model = $ai_config['model'] ?? 'gemini-2.5-flash';
		$ai_mode = $ai_config['operating_mode'] ?? 'human-in-the-loop';

        $api_keys = get_option( 'lazy_api_keys', [] );
        if ( ! is_array( $api_keys ) ) $api_keys = [];
        $gemini_key = $api_keys['gemini'] ?? '';

        $payment_keys = get_option( 'lazy_payment_keys', [] );
        if ( ! is_array( $payment_keys ) ) {
            $payment_keys = [];
        }
        $has_stripe_public = ! empty( $payment_keys['stripe_public_key'] ?? ( $settings['stripe_public_key'] ?? '' ) );
        $has_stripe_secret = ! empty( $payment_keys['stripe_secret_key'] ?? ( $settings['stripe_secret_key'] ?? '' ) );
        $has_stripe_webhook = ! empty( $payment_keys['stripe_webhook_secret'] ?? '' );
        $has_paypal_client = ! empty( $payment_keys['paypal_client_id'] ?? ( $settings['paypal_client_id'] ?? '' ) );
        $has_paypal_secret = ! empty( $payment_keys['paypal_secret'] ?? ( $settings['paypal_secret'] ?? '' ) );

        $store_country = $settings['store_country'] ?? 'DE';
        $default_currency = $settings['default_currency'] ?? 'EUR';
        $stripe_flow = $settings['stripe_flow'] ?? 'checkout';
        $selected_methods = isset( $settings['payment_methods'] ) && is_array( $settings['payment_methods'] ) ? $settings['payment_methods'] : [];

		$business_context = get_option( 'lazy_business_context', [] );
		if ( ! is_array( $business_context ) ) $business_context = [];

		// Determine current tab
		$current_tab = sanitize_text_field( $_GET['tab'] ?? 'general' );
		if ( ! in_array( $current_tab, [ 'general', 'email', 'ai', 'security' ], true ) ) {
			$current_tab = 'general';
		}
		?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_settings'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__( 'Settings', 'ltl-bookings' ); ?></h1>
            <hr class="wp-header-end">
            
            <?php if ( isset( $_GET['settings_updated'] ) && $_GET['settings_updated'] === '1' ): ?>
                <div class="notice notice-success is-dismissible">
                    <p><span class="dashicons dashicons-yes-alt ltlb-icon-success" aria-hidden="true"></span> <?php echo esc_html__( 'Settings saved successfully.', 'ltl-bookings' ); ?></p>
                </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="ltlb-design-subnav" style="margin: 10px 0 15px 0;">
                <?php
                $tabs = [
                    'general' => __('General', 'ltl-bookings'),
                    'email' => __('Email', 'ltl-bookings'),
                ];
                if ( LTLB_Role_Manager::user_can('manage_ai_settings') ) {
                    $tabs['ai'] = __('AI', 'ltl-bookings');
                }
                if ( LTLB_Role_Manager::user_can('manage_options') ) {
                    $tabs['security'] = __('Security', 'ltl-bookings');
                }
                foreach ( $tabs as $tab_key => $tab_label ) : ?>
                    <a class="ltlb-admin-tab <?php echo $current_tab === $tab_key ? 'is-active' : ''; ?>" 
                       href="<?php echo esc_url( admin_url( 'admin.php?page=ltlb_settings&tab=' . $tab_key ) ); ?>">
                        <?php echo esc_html( $tab_label ); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="post">
				<?php wp_nonce_field( 'ltlb_settings_save_action', 'ltlb_settings_nonce' ); ?>
				<input type="hidden" name="ltlb_settings_save" value="1" />
				<input type="hidden" name="ltlb_settings_tab" value="<?php echo esc_attr( $current_tab ); ?>" />

				<!-- Save Button at Top -->
				<p class="submit" style="margin-top:10px; padding-top:0;">
                    <?php submit_button( esc_html__( 'Save Settings', 'ltl-bookings' ), 'primary', 'ltlb_settings_save_top', false ); ?>
				</p>

                <?php if ( $current_tab === 'general' ) : ?>
                <!-- GENERAL SETTINGS -->
                <?php LTLB_Admin_Component::card_start(__( 'General Settings', 'ltl-bookings' )); ?>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="working_hours_start"><?php echo esc_html__( 'Business hours', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <label><?php echo esc_html__( 'Start:', 'ltl-bookings' ); ?> <input name="working_hours_start" id="working_hours_start" type="number" value="<?php echo esc_attr( $start ); ?>" class="small-text" min="0" max="23"></label>
                                    &nbsp;&nbsp;
                                    <label><?php echo esc_html__( 'End:', 'ltl-bookings' ); ?> <input name="working_hours_end" id="working_hours_end" type="number" value="<?php echo esc_attr( $end ); ?>" class="small-text" min="0" max="23"></label>
                                    <p class="description"><?php echo esc_html__( 'Global business hours (0-23). Individual staff hours can override this.', 'ltl-bookings' ); ?></p>
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
                            <tr>
                                <th><label for="profit_margin_percent"><?php echo esc_html__( 'Profit Margin (%)', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input name="profit_margin_percent" id="profit_margin_percent" type="number" class="small-text" min="0" max="100" value="<?php echo esc_attr( (string) $profit_margin_percent ); ?>" />
                                    <p class="description"><?php echo esc_html__( 'Used for estimated profit calculations in dashboards and AI reports.', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                        <tr>
                            <th><label for="hotel_fee_percent"><?php echo esc_html__( 'Hotel Fees (%)', 'ltl-bookings' ); ?></label></th>
                            <td>
                                <input name="hotel_fee_percent" id="hotel_fee_percent" type="number" class="small-text" min="0" max="100" value="<?php echo esc_attr( (string) $hotel_fee_percent ); ?>" />
                                <p class="description"><?php echo esc_html__( 'Used for hotel gross profit: fee percent applied to booking amount.', 'ltl-bookings' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="hotel_fee_fixed"><?php echo esc_html__( 'Hotel Fee (Fixed)', 'ltl-bookings' ); ?></label></th>
                            <td>
                                <input name="hotel_fee_fixed" id="hotel_fee_fixed" type="text" class="regular-text" value="<?php echo esc_attr( (string) $hotel_fee_fixed ); ?>" />
                                <p class="description"><?php echo esc_html__( 'Fixed fee per booking (e.g., payment processor fixed fee). Stored in cents.', 'ltl-bookings' ); ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                <?php LTLB_Admin_Component::card_end(); ?>

                <!-- SETUP WIZARD -->
                <?php LTLB_Admin_Component::card_start(__( 'Setup Wizard', 'ltl-bookings' )); ?>
                    <p><?php echo esc_html__( 'Run the setup wizard again to reconfigure your basic settings.', 'ltl-bookings' ); ?></p>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ltlb_setup_wizard&restart=1' ) ); ?>" class="ltlb-btn ltlb-btn--secondary">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                            <?php echo esc_html__( 'Restart Setup Wizard', 'ltl-bookings' ); ?>
                        </a>
                    </p>
                <?php LTLB_Admin_Component::card_end(); ?>

                <!-- ADVANCED SETTINGS (Collapsible) -->
                <div class="ltlb-advanced-settings-toggle" style="margin: 20px 0 10px 0;">
                    <button type="button" class="ltlb-btn ltlb-btn--secondary ltlb-btn--small ltlb-toggle-advanced" data-target="ltlb-advanced-general">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php echo esc_html__( 'Advanced Settings', 'ltl-bookings' ); ?>
                    </button>
                </div>

                <div id="ltlb-advanced-general" class="ltlb-advanced-section" style="display: none;">
                    <!-- LOGGING SETTINGS -->
                    <?php LTLB_Admin_Component::card_start(__( 'Logging', 'ltl-bookings' )); ?>
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
                    <?php LTLB_Admin_Component::card_end(); ?>
                </div>
                <?php endif; ?>

                <?php if ( $current_tab === 'email' ) : ?>
                <!-- EMAIL SETTINGS -->
                <?php LTLB_Admin_Component::card_start(__( 'Email Settings', 'ltl-bookings' )); ?>
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
                <?php LTLB_Admin_Component::card_end(); ?>

                <!-- ADVANCED SETTINGS (Collapsible) -->
                <div class="ltlb-advanced-settings-toggle" style="margin: 20px 0 10px 0;">
                    <button type="button" class="ltlb-btn ltlb-btn--secondary ltlb-btn--small ltlb-toggle-advanced" data-target="ltlb-advanced-email">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php echo esc_html__( 'Advanced Email Settings (SMTP)', 'ltl-bookings' ); ?>
                    </button>
                </div>

                <div id="ltlb-advanced-email" class="ltlb-advanced-section" style="display: none;">
                <?php LTLB_Admin_Component::card_start(__( 'SMTP Configuration', 'ltl-bookings' )); ?>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="ltlb_smtp_enabled"><?php echo esc_html__( 'Enable SMTP', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <label><input type="checkbox" name="ltlb_smtp_enabled" id="ltlb_smtp_enabled" value="1" <?php checked( $smtp_enabled ); ?>> <?php echo esc_html__( 'Send emails via SMTP instead of the server mail function', 'ltl-bookings' ); ?></label>
                                    <p class="description"><?php echo esc_html__( 'By default this configures WordPress wp_mail() globally while enabled.', 'ltl-bookings' ); ?></p>
                                    <p style="margin-top:6px;"><label><input type="checkbox" name="ltlb_smtp_scope_plugin_only" value="1" <?php checked( $smtp_scope === 'plugin' ); ?>> <?php echo esc_html__( 'Only apply SMTP to LazyBookings emails', 'ltl-bookings' ); ?></label></p>
                                    <p class="description"><?php echo esc_html__( 'Enable this to avoid affecting other plugins that also send emails.', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ltlb_smtp_host"><?php echo esc_html__( 'SMTP Host', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" name="ltlb_smtp_host" id="ltlb_smtp_host" value="<?php echo esc_attr( (string) $smtp_host ); ?>" placeholder="smtp.example.com">
                                    <p class="description"><?php echo esc_html__( 'Examples: smtp.gmail.com, smtp.hostinger.com, smtp.strato.de', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ltlb_smtp_port"><?php echo esc_html__( 'SMTP Port', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="number" class="small-text" name="ltlb_smtp_port" id="ltlb_smtp_port" value="<?php echo esc_attr( (string) $smtp_port ); ?>" min="1" max="65535">
                                    <p class="description"><?php echo esc_html__( 'Common ports: 587 (TLS), 465 (SSL).', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ltlb_smtp_encryption"><?php echo esc_html__( 'Encryption', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <select name="ltlb_smtp_encryption" id="ltlb_smtp_encryption">
                                        <option value="none" <?php selected( (string) $smtp_encryption, 'none' ); ?>><?php echo esc_html__( 'None', 'ltl-bookings' ); ?></option>
                                        <option value="tls" <?php selected( (string) $smtp_encryption, 'tls' ); ?>>TLS</option>
                                        <option value="ssl" <?php selected( (string) $smtp_encryption, 'ssl' ); ?>>SSL</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ltlb_smtp_auth"><?php echo esc_html__( 'Authentication', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <label><input type="checkbox" name="ltlb_smtp_auth" id="ltlb_smtp_auth" value="1" <?php checked( $smtp_auth ); ?>> <?php echo esc_html__( 'Use SMTP authentication', 'ltl-bookings' ); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ltlb_smtp_username"><?php echo esc_html__( 'SMTP Username', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" name="ltlb_smtp_username" id="ltlb_smtp_username" value="<?php echo esc_attr( (string) $smtp_username ); ?>" autocomplete="off">
                                    <p class="description"><?php echo esc_html__( 'Usually your full email address for Gmail/Hostinger/Strato.', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ltlb_smtp_password"><?php echo esc_html__( 'SMTP Password', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="password" class="regular-text" name="ltlb_smtp_password" id="ltlb_smtp_password" value="" autocomplete="new-password">
                                    <p class="description">
                                        <?php if ( $has_smtp_password ) : ?><?php echo esc_html__( 'A password is stored. Leave blank to keep the existing password.', 'ltl-bookings' ); ?><br><?php endif; ?>
                                        <?php echo esc_html__( 'For Gmail you must use an App Password (not your normal login password).', 'ltl-bookings' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php LTLB_Admin_Component::card_end(); ?>
                </div><!-- /.ltlb-advanced-section for email -->
                <?php endif; ?>

                <?php if ( $current_tab === 'ai' ) : ?>
                <?php LTLB_Admin_Component::card_start( __( 'AI Settings', 'ltl-bookings' ) ); ?>
                    <p class="description"><?php echo esc_html__( 'AI settings (provider, model, business context, and operating mode) are managed under “AI & Automations”.', 'ltl-bookings' ); ?></p>
                    <p>
                        <a class="ltlb-btn ltlb-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ltlb_ai' ) ); ?>"><?php echo esc_html__( 'Open AI Settings', 'ltl-bookings' ); ?></a>
                    </p>
                <?php LTLB_Admin_Component::card_end(); ?>
                <?php endif; ?>

                <?php if ( $current_tab === 'security' ) : ?>
                <?php LTLB_Admin_Component::card_start(__( 'Payment Methods', 'ltl-bookings' )); ?>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="enable_payments"><?php echo esc_html__( 'Enable Payments', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="checkbox" name="enable_payments" id="enable_payments" value="1" <?php checked( ! empty( $settings['enable_payments'] ) ); ?>>
                                    <p class="description"><?php echo esc_html__( 'Allow customers to pay online via Stripe or PayPal.', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="payment_processor"><?php echo esc_html__( 'Payment Processor', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <select name="payment_processor" id="payment_processor">
                                        <option value="stripe" <?php selected( ( $settings['payment_processor'] ?? 'stripe' ), 'stripe' ); ?>>Stripe</option>
                                        <option value="paypal" <?php selected( ( $settings['payment_processor'] ?? 'stripe' ), 'paypal' ); ?>>PayPal</option>
                                    </select>
                                </td>
                            </tr>
                        <tr>
                            <th scope="row"><label for="store_country"><?php echo esc_html__( 'Store Country', 'ltl-bookings' ); ?></label></th>
                            <td>
                                <input type="text" name="store_country" id="store_country" class="small-text" value="<?php echo esc_attr( strtoupper( (string) $store_country ) ); ?>" maxlength="2" autocomplete="off">
                                <p class="description"><?php echo esc_html__( 'Two-letter country code (e.g. DE, ES). Used to decide which payment methods make sense.', 'ltl-bookings' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="default_currency"><?php echo esc_html__( 'Default Currency', 'ltl-bookings' ); ?></label></th>
                            <td>
                                <input type="text" name="default_currency" id="default_currency" class="small-text" value="<?php echo esc_attr( strtoupper( (string) $default_currency ) ); ?>" maxlength="3" autocomplete="off">
                                <p class="description"><?php echo esc_html__( 'Three-letter currency code (e.g. EUR).', 'ltl-bookings' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="stripe_flow"><?php echo esc_html__( 'Stripe Flow', 'ltl-bookings' ); ?></label></th>
                            <td>
                                <select name="stripe_flow" id="stripe_flow">
                                    <option value="checkout" <?php selected( (string) $stripe_flow, 'checkout' ); ?>><?php echo esc_html__( 'Checkout (redirect)', 'ltl-bookings' ); ?></option>
                                    <option value="elements" <?php selected( (string) $stripe_flow, 'elements' ); ?>><?php echo esc_html__( 'Elements (inline)', 'ltl-bookings' ); ?></option>
                                </select>
                                <p class="description"><?php echo esc_html__( 'Checkout is recommended for the MVP (SCA/3DS handled by Stripe).', 'ltl-bookings' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__( 'Enabled Methods', 'ltl-bookings' ); ?></th>
                            <td>
                                <fieldset>
                                    <label><input type="checkbox" name="payment_methods[]" value="stripe_card" <?php checked( in_array( 'stripe_card', $selected_methods, true ) ); ?>> <?php echo esc_html__( 'Card (Stripe)', 'ltl-bookings' ); ?></label><br>
                                    <label><input type="checkbox" name="payment_methods[]" value="paypal" <?php checked( in_array( 'paypal', $selected_methods, true ) ); ?>> PayPal</label><br>
                                    <label><input type="checkbox" name="payment_methods[]" value="klarna" <?php checked( in_array( 'klarna', $selected_methods, true ) ); ?>> Klarna</label><br>
                                    <label><input type="checkbox" name="payment_methods[]" value="cash" <?php checked( in_array( 'cash', $selected_methods, true ) ); ?>> <?php echo esc_html__( 'Cash (on site)', 'ltl-bookings' ); ?></label><br>
                                    <label><input type="checkbox" name="payment_methods[]" value="pos_card" <?php checked( in_array( 'pos_card', $selected_methods, true ) ); ?>> <?php echo esc_html__( 'Card (POS / on site)', 'ltl-bookings' ); ?></label><br>
                                    <label><input type="checkbox" name="payment_methods[]" value="invoice" <?php checked( in_array( 'invoice', $selected_methods, true ) ); ?>> <?php echo esc_html__( 'Invoice (company)', 'ltl-bookings' ); ?></label>
                                </fieldset>
                                <p class="description"><?php echo esc_html__( 'These are used to control which methods can be offered on the booking form (implementation follows in the next steps).', 'ltl-bookings' ); ?></p>
                            </td>
                        </tr>
                            <tr>
                                <th scope="row"><label for="stripe_public_key"><?php echo esc_html__( 'Stripe Public Key', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="password" name="stripe_public_key" id="stripe_public_key" class="regular-text" value="" autocomplete="new-password">
                                    <p class="description">
                                        <?php if ( $has_stripe_public ) : ?><?php echo esc_html__( 'A key is stored. Leave blank to keep the existing key.', 'ltl-bookings' ); ?><br><?php endif; ?>
                                        <?php echo esc_html__( 'Your Stripe publishable key (pk_live_... or pk_test_...).', 'ltl-bookings' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stripe_secret_key"><?php echo esc_html__( 'Stripe Secret Key', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="password" name="stripe_secret_key" id="stripe_secret_key" class="regular-text" value="" autocomplete="new-password">
                                    <p class="description">
                                        <?php if ( $has_stripe_secret ) : ?><?php echo esc_html__( 'A key is stored. Leave blank to keep the existing key.', 'ltl-bookings' ); ?><br><?php endif; ?>
                                        <?php echo esc_html__( 'Your Stripe secret key (sk_live_... or sk_test_...).', 'ltl-bookings' ); ?>
                                    </p>
                                </td>
                            </tr>
						<tr>
							<th scope="row"><label for="stripe_webhook_secret"><?php echo esc_html__( 'Stripe Webhook Secret', 'ltl-bookings' ); ?></label></th>
							<td>
								<input type="password" name="stripe_webhook_secret" id="stripe_webhook_secret" class="regular-text" value="" autocomplete="new-password">
								<p class="description">
									<?php if ( $has_stripe_webhook ) : ?><?php echo esc_html__( 'A webhook secret is stored. Leave blank to keep the existing secret.', 'ltl-bookings' ); ?><br><?php endif; ?>
									<?php echo esc_html__( 'Your Stripe webhook signing secret (whsec_...). Used to verify payment events.', 'ltl-bookings' ); ?>
								</p>
							</td>
						</tr>
                            <tr>
                                <th scope="row"><label for="paypal_client_id"><?php echo esc_html__( 'PayPal Client ID', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="password" name="paypal_client_id" id="paypal_client_id" class="regular-text" value="" autocomplete="new-password">
                                    <p class="description">
                                        <?php if ( $has_paypal_client ) : ?><?php echo esc_html__( 'A key is stored. Leave blank to keep the existing key.', 'ltl-bookings' ); ?><br><?php endif; ?>
                                        <?php echo esc_html__( 'Your PayPal Client ID.', 'ltl-bookings' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="paypal_secret"><?php echo esc_html__( 'PayPal Secret', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="password" name="paypal_secret" id="paypal_secret" class="regular-text" value="" autocomplete="new-password">
                                    <p class="description">
                                        <?php if ( $has_paypal_secret ) : ?><?php echo esc_html__( 'A key is stored. Leave blank to keep the existing key.', 'ltl-bookings' ); ?><br><?php endif; ?>
                                        <?php echo esc_html__( 'Your PayPal Secret.', 'ltl-bookings' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php LTLB_Admin_Component::card_end(); ?>
                <?php endif; ?>

                <p class="submit" style="margin-top:20px;">
                    <?php submit_button( esc_html__( 'Save Settings', 'ltl-bookings' ), 'primary', 'ltlb_settings_save', false ); ?>
                </p>
            </form>

            <script>
            (function($) {
                'use strict';
                $(document).ready(function() {
                    // Load saved state from localStorage
                    $('.ltlb-toggle-advanced').each(function() {
                        const targetId = $(this).data('target');
                        const savedState = localStorage.getItem('ltlb_' + targetId);
                        if (savedState === 'open') {
                            $('#' + targetId).show();
                            $(this).find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                        }
                    });

                    // Toggle advanced sections
                    $('.ltlb-toggle-advanced').on('click', function() {
                        const targetId = $(this).data('target');
                        const $target = $('#' + targetId);
                        const $icon = $(this).find('.dashicons');
                        
                        $target.slideToggle(200);
                        
                        if ($target.is(':visible')) {
                            $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                            localStorage.setItem('ltlb_' + targetId, 'open');
                        } else {
                            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                            localStorage.setItem('ltlb_' + targetId, 'closed');
                        }
                    });
                });
            })(jQuery);
            </script>

            <!-- TEST EMAIL -->
            <?php if ( $current_tab === 'email' ) : ?>
            <?php LTLB_Admin_Component::card_start(__( 'Test Email Configuration', 'ltl-bookings' )); ?>
                <form method="post" style="display:flex; gap:10px; align-items:flex-end;">
                    <?php wp_nonce_field( 'ltlb_test_email_action', 'ltlb_test_email_nonce' ); ?>
                    <input type="hidden" name="ltlb_send_test_email" value="1">
                    <div>
                        <label for="test_email_address" style="display:block;margin-bottom:5px;"><?php echo esc_html__( 'Send test email to:', 'ltl-bookings' ); ?></label>
                        <input type="email" name="test_email_address" id="test_email_address" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required>
                    </div>
                    <?php submit_button( esc_html__( 'Send Test Email', 'ltl-bookings' ), 'secondary', 'submit', false ); ?>
                </form>
            <?php LTLB_Admin_Component::card_end(); ?>
			<?php endif; ?>
		</div>

		<?php
	}
}

