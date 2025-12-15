<?php
if (!defined('ABSPATH')) exit;

class LTLB_DiagnosticsPage {

    private function get_log_dir(): string {
        $upload_dir = wp_upload_dir();
        $basedir = is_array( $upload_dir ) && ! empty( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
        return rtrim( $basedir, '/\\' ) . '/ltlb-logs';
    }

    private function list_log_files(): array {
        $dir = $this->get_log_dir();
        if ( ! $dir || ! is_dir( $dir ) ) {
            return [];
        }
        $files = glob( $dir . '/ltlb-*.log' );
        if ( ! is_array( $files ) ) {
            return [];
        }
        rsort( $files );
        return $files;
    }

    private function safe_pick_log_file( string $requested, array $files ): string {
        if ( $requested === '' ) {
            return $files[0] ?? '';
        }
        $requested = basename( $requested );
        foreach ( $files as $path ) {
            if ( basename( (string) $path ) === $requested ) {
                return (string) $path;
            }
        }
        return $files[0] ?? '';
    }

    private function tail_file_lines( string $path, int $max_lines = 200 ): array {
        $max_lines = max( 1, min( 2000, $max_lines ) );
        if ( ! $path || ! is_readable( $path ) ) {
            return [];
        }

        $lines = [];
        try {
            $fh = new SplFileObject( $path, 'r' );
            while ( ! $fh->eof() ) {
                $line = (string) $fh->fgets();
                if ( $line === '' ) {
                    continue;
                }
                $lines[] = rtrim( $line, "\r\n" );
                if ( count( $lines ) > $max_lines ) {
                    array_shift( $lines );
                }
            }
        } catch ( Exception $e ) {
            return [];
        }

        return $lines;
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }

        // Handle log download (read-only).
        if ( isset( $_GET['ltlb_download_log'] ) && $_GET['ltlb_download_log'] === '1' ) {
            $files = $this->list_log_files();
            $requested = isset( $_GET['ltlb_log'] ) ? sanitize_text_field( (string) $_GET['ltlb_log'] ) : '';
            $picked = $this->safe_pick_log_file( $requested, $files );

            if ( ! $picked ) {
                wp_die( esc_html__( 'Log file not found.', 'ltl-bookings' ) );
            }
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( (string) $_GET['_wpnonce'], 'ltlb_download_log' ) ) {
                wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
            }

            nocache_headers();
            header( 'Content-Type: text/plain; charset=UTF-8' );
            header( 'Content-Disposition: attachment; filename=' . basename( $picked ) );
            @readfile( $picked );
            exit;
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

            <div class="ltlb-card">
                <h2><?php echo esc_html__( 'Logs', 'ltl-bookings' ); ?></h2>
                <?php
                $settings = get_option( 'lazy_settings', [] );
                if ( ! is_array( $settings ) ) {
                    $settings = [];
                }
                $logging_enabled = ! empty( $settings['logging_enabled'] );

                $files = $this->list_log_files();
                $requested = isset( $_GET['ltlb_log'] ) ? sanitize_text_field( (string) $_GET['ltlb_log'] ) : '';
                $picked = $this->safe_pick_log_file( $requested, $files );
                $picked_name = $picked ? basename( $picked ) : '';

                $base_url = admin_url( 'admin.php?page=ltlb_diagnostics' );
                ?>

                <p class="description" style="margin-top:0;">
                    <?php
                    echo esc_html__( 'View recent log entries written by the plugin (uploads/ltlb-logs).', 'ltl-bookings' );
                    if ( ! $logging_enabled ) {
                        echo ' ' . esc_html__( 'Logging is currently disabled in Settings.', 'ltl-bookings' );
                    }
                    ?>
                </p>

                <?php if ( empty( $files ) ) : ?>
                    <p class="ltlb-muted" style="margin:0;">
                        <?php echo esc_html__( 'No log files found yet.', 'ltl-bookings' ); ?>
                    </p>
                <?php else : ?>
                    <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="page" value="ltlb_diagnostics" />
                        <label for="ltlb_log" class="screen-reader-text"><?php echo esc_html__( 'Select log file', 'ltl-bookings' ); ?></label>
                        <select name="ltlb_log" id="ltlb_log">
                            <?php foreach ( $files as $f ) :
                                $bn = basename( (string) $f );
                            ?>
                                <option value="<?php echo esc_attr( $bn ); ?>" <?php selected( $bn, $picked_name ); ?>><?php echo esc_html( $bn ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button"><?php echo esc_html__( 'View', 'ltl-bookings' ); ?></button>
                        <?php
                        $dl_url = add_query_arg(
                            [
                                'page' => 'ltlb_diagnostics',
                                'ltlb_log' => $picked_name,
                                'ltlb_download_log' => '1',
                                '_wpnonce' => wp_create_nonce( 'ltlb_download_log' ),
                            ],
                            $base_url
                        );
                        ?>
                        <a class="button button-secondary" href="<?php echo esc_url( $dl_url ); ?>"><?php echo esc_html__( 'Download', 'ltl-bookings' ); ?></a>
                    </form>

                    <?php
                    $lines = $picked ? $this->tail_file_lines( $picked, 200 ) : [];
                    $content = ! empty( $lines ) ? implode( "\n", $lines ) : '';
                    ?>
                    <p style="margin-top:12px;">
                        <label class="screen-reader-text" for="ltlb-log-preview"><?php echo esc_html__( 'Log preview', 'ltl-bookings' ); ?></label>
                        <textarea id="ltlb-log-preview" class="large-text code" rows="14" readonly><?php echo esc_textarea( $content !== '' ? $content : __( 'Log file is empty.', 'ltl-bookings' ) ); ?></textarea>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private function render_doctor_output(): void {
        global $wpdb;
        $settings = get_option('lazy_settings', []);
        
        echo '<div class="notice notice-info ltlb-diagnostics-notice">';
        echo '<h3 class="ltlb-diagnostics-notice__title">' . esc_html__( 'System Check Results', 'ltl-bookings' ) . '</h3>';
        
        // Version info
        $plugin_version = defined('LTLB_VERSION') ? LTLB_VERSION : __( 'unknown', 'ltl-bookings' );
        $db_version = get_option('ltlb_db_version', 'not set');
        echo '<p><strong>' . esc_html__( 'Plugin Version:', 'ltl-bookings' ) . '</strong> ' . esc_html($plugin_version) . '</p>';
        echo '<p><strong>' . esc_html__( 'DB Version:', 'ltl-bookings' ) . '</strong> ' . esc_html($db_version) . '</p>';
        
        if (version_compare($plugin_version, $db_version, '>')) {
            echo '<p class="ltlb-diagnostics-status ltlb-diagnostics-status--warn"><strong>' . esc_html__( '⚠ DB version is behind plugin version.', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Please run migrations.', 'ltl-bookings' ) . '</p>';
        } elseif (version_compare($plugin_version, $db_version, '=')) {
            echo '<p class="ltlb-diagnostics-status ltlb-diagnostics-status--ok"><strong>' . esc_html__( '✓ DB version matches plugin version.', 'ltl-bookings' ) . '</strong></p>';
        }
        
        // Template mode
        $template_mode = $settings['template_mode'] ?? 'service';
        echo '<p><strong>' . esc_html__( 'Booking Mode:', 'ltl-bookings' ) . '</strong> ' . esc_html($template_mode) . '</p>';
        
        // Lock support
        $lock_test = $wpdb->get_var("SELECT GET_LOCK('ltlb_test_lock', 0)");
        $lock_supported = ($lock_test === '1');
        if ($lock_supported) {
            $wpdb->query("SELECT RELEASE_LOCK('ltlb_test_lock')");
            echo '<p class="ltlb-diagnostics-status ltlb-diagnostics-status--ok"><strong>' . esc_html__( 'MySQL Named Locks:', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Supported ✓', 'ltl-bookings' ) . '</p>';
        } else {
            echo '<p class="ltlb-diagnostics-status ltlb-diagnostics-status--warn"><strong>' . esc_html__( 'MySQL Named Locks:', 'ltl-bookings' ) . '</strong> ' . esc_html__( 'Not supported (race condition protection disabled)', 'ltl-bookings' ) . '</p>';
        }
        
        // Mail configuration
        $from_email = $settings['mail_from_email'] ?? get_option('admin_email');
        $from_name = $settings['mail_from_name'] ?? get_bloginfo('name');
        $reply_to = $settings['mail_reply_to'] ?? '';
        echo '<p><strong>' . esc_html__( 'Email from:', 'ltl-bookings' ) . '</strong> ' . esc_html($from_name) . ' &lt;' . esc_html($from_email) . '&gt;</p>';
        if (!empty($reply_to)) {
            echo '<p><strong>' . esc_html__( 'Reply-To:', 'ltl-bookings' ) . '</strong> ' . esc_html($reply_to) . '</p>';
        }

		$smtp_enabled = ! empty( $settings['smtp_enabled'] );
		$smtp_host = isset( $settings['smtp_host'] ) ? (string) $settings['smtp_host'] : '';
		$smtp_port = isset( $settings['smtp_port'] ) ? intval( $settings['smtp_port'] ) : 0;
		echo '<p><strong>' . esc_html__( 'SMTP:', 'ltl-bookings' ) . '</strong> ' . esc_html( $smtp_enabled ? 'Enabled' : 'Disabled' ) . '</p>';
		if ( $smtp_enabled && $smtp_host !== '' && $smtp_port > 0 ) {
			echo '<p><strong>' . esc_html__( 'SMTP server:', 'ltl-bookings' ) . '</strong> ' . esc_html( $smtp_host ) . ':' . esc_html( (string) $smtp_port ) . '</p>';
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
