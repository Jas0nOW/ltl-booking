<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Setup Wizard Page
 * 
 * Guided onboarding for new installations.
 */
class LTLB_Admin_SetupWizardPage {

    public static function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }

        // Handle wizard restart
        if ( isset( $_GET['restart'] ) && $_GET['restart'] === '1' ) {
            delete_option( 'ltlb_wizard_completed' );
            wp_safe_redirect( admin_url( 'admin.php?page=ltlb_setup_wizard&step=1' ) );
            exit;
        }

        $step = isset( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;
        $total_steps = 6;

        // Handle form submissions
        if ( isset( $_POST['ltlb_wizard_submit'] ) && check_admin_referer('ltlb_wizard_step', 'ltlb_wizard_nonce') ) {
            self::process_step( $step, $_POST );
            $step++;
        }

        if ( isset( $_GET['skip_wizard'] ) ) {
            update_option( 'ltlb_wizard_completed', true );
            wp_safe_redirect( admin_url( 'admin.php?page=ltlb_dashboard' ) );
            exit;
        }

        if ( $step > $total_steps ) {
            update_option( 'ltlb_wizard_completed', true );
            wp_safe_redirect( admin_url( 'admin.php?page=ltlb_dashboard' ) );
            exit;
        }

        ?>
        <div class="wrap ltlb-wizard">
            <h1><?php echo esc_html__('LazyBookings Setup Wizard', 'ltl-bookings'); ?></h1>
            <p class="ltlb-wizard-progress"><?php echo sprintf( esc_html__('Step %d of %d', 'ltl-bookings'), $step, $total_steps ); ?></p>

            <div class="ltlb-wizard-container">
                <?php self::render_step( $step ); ?>
            </div>

            <p style="margin-top: 20px; text-align: center;">
                <a href="<?php echo esc_url( add_query_arg( 'skip_wizard', '1' ) ); ?>" class="ltlb-btn ltlb-btn--ghost"><?php echo esc_html__('Skip wizard and configure manually', 'ltl-bookings'); ?></a>
            </p>
        </div>

        <style>
        .ltlb-wizard { max-width: 800px; margin: 40px auto; }
        .ltlb-wizard-progress { font-size: 14px; color: #666; margin-bottom: 30px; }
        .ltlb-wizard-container { background: #fff; padding: 40px; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .ltlb-wizard h2 { margin-top: 0; margin-bottom: 20px; font-size: 24px; }
        .ltlb-wizard p { margin-bottom: 20px; line-height: 1.6; }
        .ltlb-wizard-option { 
            display: block; 
            padding: 20px; 
            margin: 15px 0; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: all 0.2s ease;
            background: #fafafa;
        }
        .ltlb-wizard-option:hover { 
            border-color: #2271b1; 
            background: #fff;
            box-shadow: 0 2px 8px rgba(34,113,177,0.1);
        }
        .ltlb-wizard-option input[type="radio"],
        .ltlb-wizard-option input[type="checkbox"] { 
            margin: 0 10px 0 0; 
            vertical-align: middle;
        }
        .ltlb-wizard-option strong { 
            display: inline-block;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .ltlb-wizard-option .description { 
            display: block;
            margin-top: 8px;
            margin-left: 24px;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        .ltlb-wizard .form-table { 
            margin-top: 20px;
            width: 100%;
        }
        .ltlb-wizard .form-table th { 
            padding: 15px 0;
            width: 200px;
            vertical-align: top;
            text-align: left;
        }
        .ltlb-wizard .form-table td { 
            padding: 15px 0;
        }
        .ltlb-wizard .form-table input[type="text"],
        .ltlb-wizard .form-table input[type="email"],
        .ltlb-wizard .form-table select { 
            width: 100%;
            max-width: 400px;
        }
        .ltlb-wizard .button-primary { 
            padding: 10px 30px;
            font-size: 16px;
            height: auto;
        }
        </style>
        <?php
    }

    private static function render_step( int $step ): void {
        switch ( $step ) {
            case 1:
                self::render_step_mode();
                break;
            case 2:
                self::render_step_location();
                break;
            case 3:
                self::render_step_payments();
                break;
            case 4:
                self::render_step_notifications();
                break;
            case 5:
                self::render_step_demo_data();
                break;
            case 6:
                self::render_step_checklist();
                break;
        }
    }

    private static function render_step_mode(): void {
        $settings = get_option('lazy_settings', []);
        $mode = $settings['template_mode'] ?? 'service';
        ?>
        <form method="post">
            <?php wp_nonce_field('ltlb_wizard_step', 'ltlb_wizard_nonce'); ?>
            <h2><?php echo esc_html__('What do you want to book?', 'ltl-bookings'); ?></h2>
            <p><?php echo esc_html__('This determines the booking flow and terminology.', 'ltl-bookings'); ?></p>

            <label class="ltlb-wizard-option">
                <input type="radio" name="template_mode" value="service" <?php checked($mode, 'service'); ?> required>
                <strong><?php echo esc_html__('Services & Appointments', 'ltl-bookings'); ?></strong><br>
                <span class="description"><?php echo esc_html__('For salons, studios, consultations, classes. Customers book time slots.', 'ltl-bookings'); ?></span>
            </label>

            <label class="ltlb-wizard-option">
                <input type="radio" name="template_mode" value="hotel" <?php checked($mode, 'hotel'); ?>>
                <strong><?php echo esc_html__('Hotel & Accommodation', 'ltl-bookings'); ?></strong><br>
                <span class="description"><?php echo esc_html__('For hotels, vacation rentals, B&Bs. Guests book date ranges (check-in/check-out).', 'ltl-bookings'); ?></span>
            </label>

            <p style="margin-top: 30px;">
                <button type="submit" name="ltlb_wizard_submit" class="ltlb-btn ltlb-btn--primary ltlb-btn--large"><?php echo esc_html__('Continue', 'ltl-bookings'); ?></button>
            </p>
        </form>
        <?php
    }

    private static function render_step_location(): void {
        ?>
        <form method="post">
            <?php wp_nonce_field('ltlb_wizard_step', 'ltlb_wizard_nonce'); ?>
            <h2><?php echo esc_html__('Business Information', 'ltl-bookings'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><label for="business_name"><?php echo esc_html__('Business Name', 'ltl-bookings'); ?></label></th>
                    <td><input type="text" id="business_name" name="business_name" class="regular-text" value="<?php echo esc_attr( get_bloginfo('name') ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="business_email"><?php echo esc_html__('Contact Email', 'ltl-bookings'); ?></label></th>
                    <td><input type="email" id="business_email" name="business_email" class="regular-text" value="<?php echo esc_attr( get_option('admin_email') ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="timezone"><?php echo esc_html__('Timezone', 'ltl-bookings'); ?></label></th>
                    <td>
                        <select id="timezone" name="timezone" class="regular-text">
                            <?php
                            $current_tz = wp_timezone_string();
                            $tzs = timezone_identifiers_list();
                            foreach ( $tzs as $tz ) {
                                echo '<option value="' . esc_attr($tz) . '" ' . selected($tz, $current_tz, false) . '>' . esc_html($tz) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>

            <p style="margin-top: 30px;">
                <button type="submit" name="ltlb_wizard_submit" class="ltlb-btn ltlb-btn--primary ltlb-btn--large"><?php echo esc_html__('Continue', 'ltl-bookings'); ?></button>
            </p>
        </form>
        <?php
    }

    private static function render_step_payments(): void {
        ?>
        <form method="post">
            <?php wp_nonce_field('ltlb_wizard_step', 'ltlb_wizard_nonce'); ?>
            <h2><?php echo esc_html__('Payment Methods', 'ltl-bookings'); ?></h2>
            <p><?php echo esc_html__('Choose how customers can pay. You can configure this later in Settings.', 'ltl-bookings'); ?></p>

            <label class="ltlb-wizard-option">
                <input type="checkbox" name="payment_methods[]" value="stripe">
                <strong>Stripe</strong>
                <span class="description"><?php echo esc_html__('Credit cards, Apple Pay, Google Pay', 'ltl-bookings'); ?></span>
            </label>

            <label class="ltlb-wizard-option">
                <input type="checkbox" name="payment_methods[]" value="paypal">
                <strong>PayPal</strong>
                <span class="description"><?php echo esc_html__('PayPal checkout', 'ltl-bookings'); ?></span>
            </label>

            <label class="ltlb-wizard-option">
                <input type="checkbox" name="payment_methods[]" value="onsite" checked>
                <strong><?php echo esc_html__('Payment on site', 'ltl-bookings'); ?></strong>
                <span class="description"><?php echo esc_html__('Pay in person', 'ltl-bookings'); ?></span>
            </label>

            <p style="margin-top: 30px;">
                <button type="submit" name="ltlb_wizard_submit" class="ltlb-btn ltlb-btn--primary ltlb-btn--large"><?php echo esc_html__('Continue', 'ltl-bookings'); ?></button>
            </p>
        </form>
        <?php
    }

    private static function render_step_notifications(): void {
        ?>
        <form method="post">
            <?php wp_nonce_field('ltlb_wizard_step', 'ltlb_wizard_nonce'); ?>
            <h2><?php echo esc_html__('Email Notifications', 'ltl-bookings'); ?></h2>

            <label class="ltlb-wizard-option">
                <input type="checkbox" name="notifications[]" value="customer_confirmation" checked>
                <strong><?php echo esc_html__('Send confirmation emails to customers', 'ltl-bookings'); ?></strong>
            </label>

            <label class="ltlb-wizard-option">
                <input type="checkbox" name="notifications[]" value="admin_notification" checked>
                <strong><?php echo esc_html__('Notify admin on new bookings', 'ltl-bookings'); ?></strong>
            </label>

            <label class="ltlb-wizard-option">
                <input type="checkbox" name="notifications[]" value="reminders">
                <strong><?php echo esc_html__('Send reminders 24h before appointment', 'ltl-bookings'); ?></strong>
            </label>

            <p style="margin-top: 30px;">
                <button type="submit" name="ltlb_wizard_submit" class="ltlb-btn ltlb-btn--primary ltlb-btn--large"><?php echo esc_html__('Continue', 'ltl-bookings'); ?></button>
            </p>
        </form>
        <?php
    }

    private static function render_step_demo_data(): void {
        ?>
        <form method="post">
            <?php wp_nonce_field('ltlb_wizard_step', 'ltlb_wizard_nonce'); ?>
            <h2><?php echo esc_html__('Demo Data', 'ltl-bookings'); ?></h2>
            <p><?php echo esc_html__('Would you like to import demo data to test the plugin?', 'ltl-bookings'); ?></p>

            <label class="ltlb-wizard-option">
                <input type="radio" name="demo_data" value="yes">
                <strong><?php echo esc_html__('Yes, import demo data', 'ltl-bookings'); ?></strong>
                <span class="description"><?php echo esc_html__('Sample services, staff, and bookings for testing', 'ltl-bookings'); ?></span>
            </label>

            <label class="ltlb-wizard-option">
                <input type="radio" name="demo_data" value="no" checked>
                <strong><?php echo esc_html__('No, I\'ll add my own services', 'ltl-bookings'); ?></strong>
                <span class="description"><?php echo esc_html__('Start with an empty setup', 'ltl-bookings'); ?></span>
            </label>

            <p style="margin-top: 30px;">
                <button type="submit" name="ltlb_wizard_submit" class="ltlb-btn ltlb-btn--primary ltlb-btn--large"><?php echo esc_html__('Continue', 'ltl-bookings'); ?></button>
            </p>
        </form>
        <?php
    }

    private static function render_step_checklist(): void {
        ?>
        <h2><?php echo esc_html__('Setup Complete!', 'ltl-bookings'); ?></h2>
        <p><?php echo esc_html__('Your booking system is ready. Here are the next steps:', 'ltl-bookings'); ?></p>

        <ul style="list-style: none; padding: 0;">
            <li style="padding: 10px; margin: 5px 0; background: #f0f0f0;">✅ <?php echo esc_html__('Add your first service or room type', 'ltl-bookings'); ?></li>
            <li style="padding: 10px; margin: 5px 0; background: #f0f0f0;">✅ <?php echo esc_html__('Configure payment methods (if enabled)', 'ltl-bookings'); ?></li>
            <li style="padding: 10px; margin: 5px 0; background: #f0f0f0;">✅ <?php echo esc_html__('Add booking form to your website', 'ltl-bookings'); ?></li>
            <li style="padding: 10px; margin: 5px 0; background: #f0f0f0;">✅ <?php echo esc_html__('Test a booking end-to-end', 'ltl-bookings'); ?></li>
        </ul>

        <p style="margin-top: 30px;">
            <a href="<?php echo esc_url( admin_url('admin.php?page=ltlb_dashboard') ); ?>" class="ltlb-btn ltlb-btn--primary ltlb-btn--large"><?php echo esc_html__('Go to Dashboard', 'ltl-bookings'); ?></a>
            <a href="<?php echo esc_url( admin_url('admin.php?page=ltlb_services') ); ?>" class="ltlb-btn ltlb-btn--secondary ltlb-btn--large"><?php echo esc_html__('Add Services', 'ltl-bookings'); ?></a>
        </p>
        <?php
    }

    private static function process_step( int $step, array $data ): void {
        $settings = get_option('lazy_settings', []);

        switch ( $step ) {
            case 1:
                $settings['template_mode'] = sanitize_key( $data['template_mode'] ?? 'service' );
                update_option('lazy_settings', $settings);
                break;

            case 2:
                update_option('bloginfo', sanitize_text_field( $data['business_name'] ?? '' ));
                update_option('admin_email', sanitize_email( $data['business_email'] ?? '' ));
                update_option('timezone_string', sanitize_text_field( $data['timezone'] ?? '' ));
                break;

            case 3:
                $methods = isset( $data['payment_methods'] ) && is_array( $data['payment_methods'] ) ? $data['payment_methods'] : [];
                $settings['payment_methods'] = array_map( 'sanitize_key', $methods );
                update_option('lazy_settings', $settings);
                break;

            case 4:
                $notifications = isset( $data['notifications'] ) && is_array( $data['notifications'] ) ? $data['notifications'] : [];
                $settings['notifications'] = array_map( 'sanitize_key', $notifications );
                update_option('lazy_settings', $settings);
                break;

            case 5:
                if ( isset( $data['demo_data'] ) && $data['demo_data'] === 'yes' ) {
                    if ( class_exists('LTLB_DemoSeeder') ) {
                        $result = LTLB_DemoSeeder::seed_demo_data();
                        if ( is_array($result) && ( $result['services'] > 0 || $result['customers'] > 0 ) ) {
                            // Success - demo data seeded
                            update_option('ltlb_demo_seeded', true);
                        } else {
                            // Log error if seeding failed
                            if ( class_exists('LTLB_Logger') ) {
                                LTLB_Logger::error('Demo data seeding failed or returned empty results');
                            }
                        }
                    }
                }
                break;
        }
    }
}
