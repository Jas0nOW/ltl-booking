<?php
if (!defined('ABSPATH')) exit;

class LTLB_PrivacyPage {

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'lazy-bookings'));
        }

        // Handle anonymize customer action
        if (isset($_POST['ltlb_anonymize_customer']) && !empty($_POST['customer_email'])) {
            if (!check_admin_referer('ltlb_anonymize_customer', 'ltlb_anonymize_nonce')) {
                wp_die(__('Nonce verification failed', 'lazy-bookings'));
            }

            $email = sanitize_email($_POST['customer_email']);
            if (is_email($email)) {
                $result = $this->anonymize_customer_by_email($email);
                if ($result) {
                    LTLB_Notices::add(__('Customer data anonymized successfully.', 'lazy-bookings'), 'success');
                } else {
                    LTLB_Notices::add(__('Customer not found or anonymization failed.', 'lazy-bookings'), 'error');
                }
            } else {
                LTLB_Notices::add(__('Invalid email address.', 'lazy-bookings'), 'error');
            }
            wp_safe_redirect(admin_url('admin.php?page=ltlb_privacy'));
            exit;
        }

        // Handle retention settings save
        if (isset($_POST['ltlb_save_retention'])) {
            if (!check_admin_referer('ltlb_save_retention', 'ltlb_retention_nonce')) {
                wp_die(__('Nonce verification failed', 'lazy-bookings'));
            }

            $settings = get_option('lazy_settings', []);
            if (!is_array($settings)) $settings = [];

            $settings['retention_delete_canceled_days'] = isset($_POST['delete_canceled_days']) ? intval($_POST['delete_canceled_days']) : 0;
            $settings['retention_anonymize_after_days'] = isset($_POST['anonymize_after_days']) ? intval($_POST['anonymize_after_days']) : 0;

            update_option('lazy_settings', $settings);

            LTLB_Notices::add(__('Retention settings saved.', 'lazy-bookings'), 'success');
            wp_safe_redirect(admin_url('admin.php?page=ltlb_privacy'));
            exit;
        }

        $settings = get_option('lazy_settings', []);
        $delete_canceled_days = $settings['retention_delete_canceled_days'] ?? 0;
        $anonymize_after_days = $settings['retention_anonymize_after_days'] ?? 0;

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Privacy & GDPR', 'lazy-bookings'); ?></h1>

            <h2><?php echo esc_html__('Data Retention Settings', 'lazy-bookings'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('ltlb_save_retention', 'ltlb_retention_nonce'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="delete_canceled_days"><?php echo esc_html__('Delete canceled appointments after (days)', 'lazy-bookings'); ?></label></th>
                            <td>
                                <input name="delete_canceled_days" id="delete_canceled_days" type="number" value="<?php echo esc_attr($delete_canceled_days); ?>" class="small-text" min="0">
                                <p class="description"><?php echo esc_html__('Set to 0 to disable automatic deletion. Appointments with status "canceled" older than this will be permanently deleted.', 'lazy-bookings'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="anonymize_after_days"><?php echo esc_html__('Anonymize customer data after (days)', 'lazy-bookings'); ?></label></th>
                            <td>
                                <input name="anonymize_after_days" id="anonymize_after_days" type="number" value="<?php echo esc_attr($anonymize_after_days); ?>" class="small-text" min="0">
                                <p class="description"><?php echo esc_html__('Set to 0 to disable automatic anonymization. Appointments older than this will have customer data anonymized (email, name, phone replaced).', 'lazy-bookings'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save Retention Settings', 'lazy-bookings')); ?>
            </form>

            <hr>

            <h2><?php echo esc_html__('Manual Anonymization', 'lazy-bookings'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('ltlb_anonymize_customer', 'ltlb_anonymize_nonce'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="customer_email"><?php echo esc_html__('Customer Email', 'lazy-bookings'); ?></label></th>
                            <td>
                                <input name="customer_email" id="customer_email" type="email" class="regular-text" required>
                                <p class="description"><?php echo esc_html__('Anonymizes customer data (email, first name, last name, phone) by replacing with anonymized values. This action cannot be undone.', 'lazy-bookings'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p><button type="submit" name="ltlb_anonymize_customer" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to anonymize this customer? This cannot be undone.', 'lazy-bookings')); ?>');"><?php echo esc_html__('Anonymize Customer', 'lazy-bookings'); ?></button></p>
            </form>

            <hr>

            <h2><?php echo esc_html__('Run Retention Cleanup', 'lazy-bookings'); ?></h2>
            <p><?php echo esc_html__('Retention policies are automatically applied via scheduled tasks. You can manually trigger cleanup here:', 'lazy-bookings'); ?></p>
            <p>
                <a href="<?php echo esc_attr(add_query_arg(['page' => 'ltlb_privacy', 'action' => 'run_cleanup', 'nonce' => wp_create_nonce('ltlb_run_cleanup')], admin_url('admin.php'))); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Run retention cleanup now?', 'lazy-bookings')); ?>');"><?php echo esc_html__('Run Cleanup Now', 'lazy-bookings'); ?></a>
            </p>
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
        $anonymized_name = 'Anonymized User';
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
