<?php
if (!defined('ABSPATH')) exit;

class LTLB_DiagnosticsPage {

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'No access', 'ltl-bookings' ) );
        }

        // Handle migration action
        if (isset($_POST['ltlb_run_migrations']) && check_admin_referer('ltlb_run_migrations', 'ltlb_migrations_nonce')) {
            LTLB_DB_Migrator::migrate();
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Migrations ran successfully.', 'ltl-bookings' ) . '</p></div>';
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
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_diagnostics'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__('Diagnostics', 'ltl-bookings'); ?></h1>
            <hr class="wp-header-end">

            <div class="ltlb-card">
                <h2><?php echo esc_html__('System Information', 'ltl-bookings'); ?></h2>
                <table class="widefat striped" style="border:none; box-shadow:none;">
                    <tbody>
                        <tr>
                            <td><strong><?php echo esc_html__( 'WordPress Version', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'PHP Version', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html(PHP_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'Database Prefix', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html($wpdb->prefix); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'Booking Mode', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html($template_mode); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'DB Version', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html($db_version); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'Plugin Version', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html( defined('LTLB_VERSION') ? LTLB_VERSION : '' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="ltlb-card">
                <h2><?php echo esc_html__('Database Statistics', 'ltl-bookings'); ?></h2>
                <table class="widefat striped" style="border:none; box-shadow:none;">
                    <tbody>
                        <tr>
                            <td><strong><?php echo esc_html__( 'Services', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html($services_count); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'Customers', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html($customers_count); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'Appointments', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html($appointments_count); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'Resources', 'ltl-bookings' ); ?></strong></td>
                            <td><?php echo esc_html($resources_count); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="ltlb-card">
                <h2><?php echo esc_html__('Database Maintenance', 'ltl-bookings'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ltlb_run_migrations', 'ltlb_migrations_nonce'); ?>
                    <p>
                        <button type="submit" name="ltlb_run_migrations" class="button button-secondary">
                            <?php echo esc_html__( 'Run migrations', 'ltl-bookings' ); ?>
                        </button>
                        <span class="description"><?php echo esc_html__( 'Runs database migrations again. Can be run multiple times (uses dbDelta).', 'ltl-bookings' ); ?></span>
                    </p>
                </form>
                
                <form method="post" style="margin-top: 10px;">
                    <?php wp_nonce_field('ltlb_run_doctor', 'ltlb_doctor_nonce'); ?>
                    <p>
                        <button type="submit" name="ltlb_run_doctor" class="button button-secondary">
                            <?php echo esc_html__( 'Run system check', 'ltl-bookings' ); ?>
                        </button>
                        <span class="description"><?php echo esc_html__( 'Run system diagnostics (read-only).', 'ltl-bookings' ); ?></span>
                    </p>
                </form>
            </div>
            
            <?php
            // Handle doctor action
            if (isset($_POST['ltlb_run_doctor']) && check_admin_referer('ltlb_run_doctor', 'ltlb_doctor_nonce')) {
                $this->render_doctor_output();
            }
            ?>

            <div class="ltlb-card">
                <h2><?php echo esc_html__('Table Status', 'ltl-bookings'); ?></h2>
                <?php
                $tables = [
                    'lazy_services',
                    'lazy_customers',
                    'lazy_appointments',
                    'lazy_resources',
                    'lazy_service_resources',
                    'lazy_appointment_resources'
                ];

                echo '<table class="widefat striped" style="border:none; box-shadow:none;">';
                echo '<thead><tr><th>' . esc_html__( 'Table name', 'ltl-bookings' ) . '</th><th>' . esc_html__( 'Status', 'ltl-bookings' ) . '</th><th>' . esc_html__( 'Rows', 'ltl-bookings' ) . '</th></tr></thead>';
                echo '<tbody>';

                foreach ($tables as $table) {
                    $full_table = $wpdb->prefix . $table;
                    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table));
                    $status = $exists ? __( '✓ Present', 'ltl-bookings' ) : __( '✗ Missing', 'ltl-bookings' );
                    $row_count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM `{$full_table}`") : __( 'N/A', 'ltl-bookings' );

                    echo '<tr>';
                    echo '<td>' . esc_html($full_table) . '</td>';
                    echo '<td>' . esc_html($status) . '</td>';
                    echo '<td>' . esc_html($row_count) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_doctor_output(): void {
        global $wpdb;
        $settings = get_option('lazy_settings', []);
        
        echo '<div class="notice notice-info" style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #72aee6;">';
        echo '<h3 style="margin-top: 0;">' . esc_html__( 'System Check Results', 'ltl-bookings' ) . '</h3>';
        
        // Version info
        $plugin_version = defined('LTLB_VERSION') ? LTLB_VERSION : __( 'unknown', 'ltl-bookings' );
        $db_version = get_option('ltlb_db_version', 'not set');
        echo '<p><strong>' . esc_html__( 'Plugin Version:', 'ltl-bookings' ) . '</strong> ' . esc_html($plugin_version) . '</p>';
        echo '<p><strong>' . esc_html__( 'DB Version:', 'ltl-bookings' ) . '</strong> ' . esc_html($db_version) . '</p>';
        
        if (version_compare($plugin_version, $db_version, '>')) {
            echo '<p style="color: #d63638;"><strong>' . esc_html__( '⚠ DB version is behind plugin version.', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Please run migrations.', 'ltl-bookings' ) . '</p>';
        } elseif (version_compare($plugin_version, $db_version, '=')) {
            echo '<p style="color: #00a32a;"><strong>' . esc_html__( '✓ DB version matches plugin version.', 'ltl-bookings' ) . '</strong></p>';
        }
        
        // Template mode
        $template_mode = $settings['template_mode'] ?? 'service';
        echo '<p><strong>' . esc_html__( 'Booking Mode:', 'ltl-bookings' ) . '</strong> ' . esc_html($template_mode) . '</p>';
        
        // Lock support
        $lock_test = $wpdb->get_var("SELECT GET_LOCK('ltlb_test_lock', 0)");
        $lock_supported = ($lock_test === '1');
        if ($lock_supported) {
            $wpdb->query("SELECT RELEASE_LOCK('ltlb_test_lock')");
            echo '<p style="color: #00a32a;"><strong>' . esc_html__( 'MySQL Named Locks:', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Supported ✓', 'ltl-bookings' ) . '</p>';
        } else {
            echo '<p style="color: #d63638;"><strong>' . esc_html__( 'MySQL Named Locks:', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Not supported (race condition protection disabled)', 'ltl-bookings' ) . '</p>';
        }
        
        // Mail configuration
        $from_email = $settings['mail_from_email'] ?? get_option('admin_email');
        $from_name = $settings['mail_from_name'] ?? get_bloginfo('name');
        $reply_to = $settings['mail_reply_to'] ?? '';
        echo '<p><strong>' . esc_html__( 'Email from:', 'ltl-bookings' ) . '</strong> ' . esc_html($from_name) . ' &lt;' . esc_html($from_email) . '&gt;</p>';
        if (!empty($reply_to)) {
            echo '<p><strong>' . esc_html__( 'Reply-To:', 'ltl-bookings' ) . '</strong> ' . esc_html($reply_to) . '</p>';
        }
        
        // Logging
        $logging_enabled = !empty($settings['logging_enabled']);
        $log_level = $settings['log_level'] ?? 'error';
        $log_status = $logging_enabled ? sprintf( __( 'Enabled (%s)', 'ltl-bookings' ), $log_level ) : __( 'Disabled', 'ltl-bookings' );
        echo '<p><strong>' . esc_html__( 'Logging:', 'ltl-bookings' ) . '</strong> ' . esc_html($log_status) . '</p>';
        
        // Dev tools
        $dev_tools_enabled = (defined('WP_DEBUG') && WP_DEBUG) || !empty($settings['enable_dev_tools']);
        $dev_status = $dev_tools_enabled ? __( 'Enabled', 'ltl-bookings' ) : __( 'Disabled', 'ltl-bookings' );
        echo '<p><strong>' . esc_html__( 'Dev Tools:', 'ltl-bookings' ) . '</strong> ' . esc_html($dev_status) . '</p>';
        
        // Last migration
        $last_migration = get_option('ltlb_last_migration_time', 'never');
        echo '<p><strong>' . esc_html__( 'Last Migration:', 'ltl-bookings' ) . '</strong> ' . esc_html($last_migration) . '</p>';
        
        echo '</div>';
    }
}
