<?php
if (!defined('ABSPATH')) exit;

class LTLB_PrivacyPage {

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'No access', 'ltl-bookings' ) );
        }

        // Handle manual cleanup trigger
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'run_cleanup' ) {
            $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'ltlb_run_cleanup' ) ) {
                wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
            }
            if ( class_exists( 'LTLB_Retention' ) ) {
                $result = LTLB_Retention::run( true );
                LTLB_Notices::add(
                    sprintf(
                        __( 'Cleanup completed. Deleted appointments: %d. Anonymized customers: %d.', 'ltl-bookings' ),
                        intval( $result['deleted_appointments'] ?? 0 ),
                        intval( $result['anonymized_customers'] ?? 0 )
                    ),
                    'success'
                );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=ltlb_privacy' ) );
            exit;
        }

        // Handle anonymize customer action
        if (isset($_POST['ltlb_anonymize_customer']) && !empty($_POST['customer_email'])) {
            if (!check_admin_referer('ltlb_anonymize_customer', 'ltlb_anonymize_nonce')) {
				wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
            }

            $email = sanitize_email($_POST['customer_email']);
            if (is_email($email)) {
                $result = $this->anonymize_customer_by_email($email);
                if ($result) {
                    LTLB_Notices::add(__('Customer data anonymized successfully.', 'ltl-bookings'), 'success');
                } else {
                    LTLB_Notices::add(__('Customer not found or anonymization failed.', 'ltl-bookings'), 'error');
                }
            } else {
                LTLB_Notices::add(__('Invalid email address.', 'ltl-bookings'), 'error');
            }
            wp_safe_redirect(admin_url('admin.php?page=ltlb_privacy'));
            exit;
        }

        // Handle retention settings save
        if (isset($_POST['ltlb_save_retention'])) {
            if (!check_admin_referer('ltlb_save_retention', 'ltlb_retention_nonce')) {
				wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
            }

            $settings = get_option('lazy_settings', []);
            if (!is_array($settings)) $settings = [];

            $settings['retention_delete_canceled_days'] = isset($_POST['delete_canceled_days']) ? intval($_POST['delete_canceled_days']) : 0;
            $settings['retention_anonymize_after_days'] = isset($_POST['anonymize_after_days']) ? intval($_POST['anonymize_after_days']) : 0;

            update_option('lazy_settings', $settings);

            LTLB_Notices::add(__('Retention settings saved.', 'ltl-bookings'), 'success');
            wp_safe_redirect(admin_url('admin.php?page=ltlb_privacy'));
            exit;
        }

        $settings = get_option('lazy_settings', []);
        $delete_canceled_days = $settings['retention_delete_canceled_days'] ?? 0;
        $anonymize_after_days = $settings['retention_anonymize_after_days'] ?? 0;

        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_privacy'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__('Privacy & GDPR', 'ltl-bookings'); ?></h1>
            <hr class="wp-header-end">

            <div class="ltlb-card">
                <h2><?php echo esc_html__('Data Retention Settings', 'ltl-bookings'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ltlb_save_retention', 'ltlb_retention_nonce'); ?>
                    <input type="hidden" name="ltlb_save_retention" value="1">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="delete_canceled_days"><?php echo esc_html__('Delete cancelled appointments after (days)', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <input name="delete_canceled_days" id="delete_canceled_days" type="number" value="<?php echo esc_attr($delete_canceled_days); ?>" class="small-text" min="0">
                                    <p class="description"><?php echo esc_html__('Set to 0 to disable automatic deletion. Appointments with status "cancelled" older than this will be permanently deleted.', 'ltl-bookings'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="anonymize_after_days"><?php echo esc_html__('Anonymize customer data after (days)', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <input name="anonymize_after_days" id="anonymize_after_days" type="number" value="<?php echo esc_attr($anonymize_after_days); ?>" class="small-text" min="0">
                                    <p class="description"><?php echo esc_html__('Set to 0 to disable automatic anonymization. Appointments older than this will have customer data anonymized (email, name, phone replaced).', 'ltl-bookings'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="submit">
                        <?php submit_button(__('Save Retention Settings', 'ltl-bookings'), 'primary', 'submit', false); ?>
                    </p>
                </form>
            </div>

            <div class="ltlb-card">
                <h2><?php echo esc_html__('Manual Anonymization', 'ltl-bookings'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ltlb_anonymize_customer', 'ltlb_anonymize_nonce'); ?>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="customer_email"><?php echo esc_html__('Customer Email', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <input name="customer_email" id="customer_email" type="email" class="regular-text" required>
                                    <p class="description"><?php echo esc_html__('Anonymizes customer data (email, first name, last name, phone) by replacing with anonymized values. This action cannot be undone.', 'ltl-bookings'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p><button type="submit" name="ltlb_anonymize_customer" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to anonymize this customer? This cannot be undone.', 'ltl-bookings')); ?>');"><?php echo esc_html__('Anonymize Customer', 'ltl-bookings'); ?></button></p>
                </form>
            </div>

            <div class="ltlb-card">
                <h2><?php echo esc_html__('Run Retention Cleanup', 'ltl-bookings'); ?></h2>
                <p><?php echo esc_html__('Retention policies are automatically applied via scheduled tasks. You can manually trigger cleanup here:', 'ltl-bookings'); ?></p>
                <p>
                    <a href="<?php echo esc_attr(add_query_arg(['page' => 'ltlb_privacy', 'action' => 'run_cleanup', 'nonce' => wp_create_nonce('ltlb_run_cleanup')], admin_url('admin.php'))); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Run retention cleanup now?', 'ltl-bookings')); ?>');"><?php echo esc_html__('Run Cleanup Now', 'ltl-bookings'); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    private function anonymize_customer_by_email(string $email): bool {
        global $wpdb;
        $customer_repo = new LTLB_CustomerRepository();
        $customers_table = $wpdb->prefix . 'lazy_customers';

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$customers_table} WHERE email = %s",
            $email
        ), ARRAY_A);

        if (!$customer) {
            return false;
        }

        $anonymized_email = 'anonymized_' . md5($email . time()) . '@deleted.local';
        $anonymized_name = 'Anonymized';
        $anonymized_phone = '';

        $result = $wpdb->update(
            $customers_table,
            [
                'email' => $anonymized_email,
                'first_name' => $anonymized_name,
                'last_name' => '',
                'phone' => $anonymized_phone,
                'notes' => '',
                'updated_at' => current_time('mysql')
            ],
            ['email' => $email],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%s']
        );

        return $result !== false;
    }
}
