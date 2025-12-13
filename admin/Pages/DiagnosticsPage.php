<?php
if (!defined('ABSPATH')) exit;

class LTLB_DiagnosticsPage {

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'lazy-bookings'));
        }

        // Handle migration action
        if (isset($_POST['ltlb_run_migrations']) && check_admin_referer('ltlb_run_migrations', 'ltlb_migrations_nonce')) {
            LTLB_DB_Migrator::migrate();
            echo '<div class="notice notice-success"><p>Migrations executed successfully.</p></div>';
        }

        global $wpdb;
        $settings = get_option('lazy_settings', []);
        $template_mode = $settings['template_mode'] ?? 'service';
        $db_version = get_option('ltlb_db_version', 'not set');

        // Counts
        $services_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lazy_services");
        $customers_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lazy_customers");
        $appointments_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lazy_appointments");
        $resources_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lazy_resources");

        ?>
        <div class="wrap">
            <h1>LazyBookings Diagnostics</h1>

            <h2>System Information</h2>
            <table class="widefat striped" style="max-width: 800px;">
                <tbody>
                    <tr>
                        <td><strong>WordPress Version</strong></td>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <td><strong>PHP Version</strong></td>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Database Prefix</strong></td>
                        <td><?php echo esc_html($wpdb->prefix); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Template Mode</strong></td>
                        <td><?php echo esc_html($template_mode); ?></td>
                    </tr>
                    <tr>
                        <td><strong>DB Version</strong></td>
                        <td><?php echo esc_html($db_version); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Plugin Version</strong></td>
                        <td>0.4.0</td>
                    </tr>
                </tbody>
            </table>

            <h2>Database Statistics</h2>
            <table class="widefat striped" style="max-width: 800px;">
                <tbody>
                    <tr>
                        <td><strong>Services</strong></td>
                        <td><?php echo esc_html($services_count); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Customers</strong></td>
                        <td><?php echo esc_html($customers_count); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Appointments</strong></td>
                        <td><?php echo esc_html($appointments_count); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Resources</strong></td>
                        <td><?php echo esc_html($resources_count); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2>Database Maintenance</h2>
            <form method="post">
                <?php wp_nonce_field('ltlb_run_migrations', 'ltlb_migrations_nonce'); ?>
                <p>
                    <button type="submit" name="ltlb_run_migrations" class="button button-secondary">
                        Run Migrations
                    </button>
                    <span class="description">Re-runs database migrations. Safe to execute multiple times (uses dbDelta).</span>
                </p>
            </form>

            <h2>Table Status</h2>
            <?php
            $tables = [
                'lazy_services',
                'lazy_customers',
                'lazy_appointments',
                'lazy_resources',
                'lazy_service_resources',
                'lazy_appointment_resources'
            ];

            echo '<table class="widefat striped" style="max-width: 800px;">';
            echo '<thead><tr><th>Table Name</th><th>Status</th><th>Rows</th></tr></thead>';
            echo '<tbody>';

            foreach ($tables as $table) {
                $full_table = $wpdb->prefix . $table;
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table));
                $status = $exists ? '✓ Exists' : '✗ Missing';
                $row_count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM `{$full_table}`") : 'N/A';

                echo '<tr>';
                echo '<td>' . esc_html($full_table) . '</td>';
                echo '<td>' . esc_html($status) . '</td>';
                echo '<td>' . esc_html($row_count) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            ?>
        </div>
        <?php
    }
}
