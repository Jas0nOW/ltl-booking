<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_AppointmentsDashboardPage {
	public function render(): void {
        $can_manage = current_user_can( 'manage_options' );
        $can_view_reports = $can_manage;
        if ( ! $can_view_reports && class_exists( 'LTLB_Role_Manager' ) ) {
            $can_view_reports = LTLB_Role_Manager::user_can( 'view_ai_reports' );
        }
        if ( ! $can_view_reports ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }

        // Handle AI insights generation
        if ( class_exists( 'LTLB_Automations' ) ) {
            LTLB_Automations::maybe_handle_generate_ai_insights_post( admin_url( 'admin.php?page=ltlb_dashboard' ) );
        }

		$appt_repo = new LTLB_AppointmentRepository();
		$cust_repo = new LTLB_CustomerRepository();

		$appointments_today = $appt_repo->get_count_for_today();
		$pending_appointments = $appt_repo->get_count_by_status( 'pending' );
		$confirmed_appointments = $appt_repo->get_count_by_status( 'confirmed' );
		$total_customers = $cust_repo->get_count();
		
		// Week-over-week comparisons
		$now = current_time('timestamp');
		$this_week_start = date('Y-m-d 00:00:00', strtotime('monday this week', $now));
		$this_week_end = date('Y-m-d 23:59:59', strtotime('sunday this week', $now));
		$last_week_start = date('Y-m-d 00:00:00', strtotime('monday last week', $now));
		$last_week_end = date('Y-m-d 23:59:59', strtotime('sunday last week', $now));
		
		$this_week_count = $appt_repo->get_count_by_date_range($this_week_start, $this_week_end);
		$last_week_count = $appt_repo->get_count_by_date_range($last_week_start, $last_week_end);
		
		$week_comparison = 0;
		if ($last_week_count > 0) {
			$week_comparison = round((($this_week_count - $last_week_count) / $last_week_count) * 100);
		}
		
		$latest_appointments = $appt_repo->get_all(['limit' => 8]);
        $can_view_appointment_detail = current_user_can( 'manage_options' );

		?>
        <div class="wrap ltlb-admin ltlb-admin--dashboard">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_dashboard'); } ?>
            
            <!-- Page Header -->
            <div class="ltlb-page-header">
                <div class="ltlb-page-header__content">
                    <h1 class="ltlb-page-header__title"><?php echo esc_html__('Dashboard', 'ltl-bookings'); ?></h1>
                    <p class="ltlb-page-header__subtitle"><?php echo esc_html__('Your business at a glance', 'ltl-bookings'); ?></p>
                </div>
				<?php if ( $can_manage ) : ?>
				<div class="ltlb-page-header__actions">
					<a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_appointments&action=add')); ?>" class="ltlb-btn ltlb-btn--primary">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<?php echo esc_html__('New Appointment', 'ltl-bookings'); ?>
					</a>
					<a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_calendar')); ?>" class="ltlb-btn ltlb-btn--secondary">
						<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
						<?php echo esc_html__('Calendar', 'ltl-bookings'); ?>
					</a>
				</div>
				<?php endif; ?>
            </div>

            <!-- KPI Cards Row -->
            <div class="ltlb-kpi-grid ltlb-kpi-grid--4col">
                <?php self::render_kpi_card(__('Today', 'ltl-bookings'), $appointments_today, 'dashicons-calendar-alt', null, 'primary'); ?>
                <?php self::render_kpi_card(__('This Week', 'ltl-bookings'), $this_week_count, 'dashicons-chart-bar', $week_comparison, 'info'); ?>
                <?php self::render_kpi_card(__('Pending', 'ltl-bookings'), $pending_appointments, 'dashicons-clock', null, 'warning'); ?>
                <?php self::render_kpi_card(__('Customers', 'ltl-bookings'), $total_customers, 'dashicons-groups', null, 'success'); ?>
            </div>

            <!-- Main Content Grid: 2 Column Layout -->
            <div class="ltlb-dashboard-layout">
                
                <!-- LEFT COLUMN: Main Content -->
                <div class="ltlb-dashboard-layout__main">
                    
                    <!-- Recent Appointments -->
                    <div class="ltlb-card">
                        <div class="ltlb-card__header">
                            <div class="ltlb-card__header-content">
                                <span class="dashicons dashicons-calendar"></span>
                                <h3 class="ltlb-card__title"><?php echo esc_html__('Recent Appointments', 'ltl-bookings'); ?></h3>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_appointments')); ?>" class="ltlb-card__link">
                                <?php echo esc_html__('View All', 'ltl-bookings'); ?> →
                            </a>
                        </div>
                        <div class="ltlb-card__body ltlb-card__body--flush">
                            <?php if ( empty($latest_appointments) ) : ?>
                                <div class="ltlb-empty-state">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <p><?php echo esc_html__('No appointments yet', 'ltl-bookings'); ?></p>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_appointments&action=add')); ?>" class="ltlb-btn ltlb-btn--primary ltlb-btn--small">
                                        <?php echo esc_html__('Create First Appointment', 'ltl-bookings'); ?>
                                    </a>
                                </div>
                            <?php else : ?>
                                <table class="ltlb-table ltlb-table--dashboard">
                                    <thead>
                                        <tr>
                                            <th><?php echo esc_html__('Customer', 'ltl-bookings'); ?></th>
                                            <th><?php echo esc_html__('Service', 'ltl-bookings'); ?></th>
                                            <th><?php echo esc_html__('Date & Time', 'ltl-bookings'); ?></th>
                                            <th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $s_repo = new LTLB_ServiceRepository();
                                        foreach ( $latest_appointments as $a ): 
                                            $cust = $cust_repo->get_by_id( intval($a['customer_id']) );
                                            $cust_name = $cust ? $cust['first_name'] . ' ' . $cust['last_name'] : '—';
                                            $service = $s_repo->get_by_id( intval($a['service_id']) );
                                            $view_url = admin_url('admin.php?page=ltlb_appointments&action=view&id=' . intval( $a['id'] ?? 0 ) );

                                            $appt_tz = (string) ( $a['timezone'] ?? '' );
                                            if ( $appt_tz === '' ) {
                                                $appt_tz = LTLB_Time::get_site_timezone_string();
                                            }
                                            $start_display = LTLB_DateTime::format_local_display_from_utc_mysql( (string) ( $a['start_at'] ?? '' ), get_option('date_format') . ' ' . get_option('time_format'), $appt_tz );
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php if ( $can_view_appointment_detail && ! empty( $a['id'] ) ) : ?>
                                                        <a href="<?php echo esc_url( $view_url ); ?>" class="ltlb-table__link"><?php echo esc_html( $cust_name ); ?></a>
                                                    <?php else : ?>
                                                        <?php echo esc_html( $cust_name ); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo esc_html( $service ? $service['name'] : '—' ); ?></td>
                                                <td class="ltlb-text-muted"><?php echo esc_html( $start_display ); ?></td>
                                                <td><?php echo self::render_status_badge( $a['status'] ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Analytics Section -->
                    <?php self::render_analytics_card(); ?>

                </div>

                <!-- RIGHT COLUMN: Sidebar -->
                <div class="ltlb-dashboard-layout__sidebar">
                    
                    <!-- Quick Actions -->
                    <?php if ( $can_manage ) : ?>
                    <div class="ltlb-card">
                        <div class="ltlb-card__header">
                            <div class="ltlb-card__header-content">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <h3 class="ltlb-card__title"><?php echo esc_html__('Quick Actions', 'ltl-bookings'); ?></h3>
                            </div>
                        </div>
                        <div class="ltlb-card__body">
                            <div class="ltlb-quick-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_appointments&action=add')); ?>" class="ltlb-quick-action">
                                    <span class="ltlb-quick-action__icon"><span class="dashicons dashicons-plus-alt2"></span></span>
                                    <span class="ltlb-quick-action__label"><?php echo esc_html__('New Appointment', 'ltl-bookings'); ?></span>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_customers&action=add')); ?>" class="ltlb-quick-action">
                                    <span class="ltlb-quick-action__icon"><span class="dashicons dashicons-admin-users"></span></span>
                                    <span class="ltlb-quick-action__label"><?php echo esc_html__('Add Customer', 'ltl-bookings'); ?></span>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_services&action=add')); ?>" class="ltlb-quick-action">
                                    <span class="ltlb-quick-action__icon"><span class="dashicons dashicons-clipboard"></span></span>
                                    <span class="ltlb-quick-action__label"><?php echo esc_html__('New Service', 'ltl-bookings'); ?></span>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_settings')); ?>" class="ltlb-quick-action">
                                    <span class="ltlb-quick-action__icon"><span class="dashicons dashicons-admin-generic"></span></span>
                                    <span class="ltlb-quick-action__label"><?php echo esc_html__('Settings', 'ltl-bookings'); ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- AI Insights -->
                    <?php self::render_ai_insights_card( $can_manage ); ?>

                    <!-- Recently Viewed -->
                    <?php self::render_recently_viewed_card(); ?>

                </div>
            </div>
		</div>
		<?php
	}

    /**
     * Render status badge
     */
    public static function render_status_badge(string $status): string {
        return LTLB_BookingStatus::render_badge( $status );
    }

    /**
     * Render KPI Card
     */
    public static function render_kpi_card(string $label, $value, string $icon, $comparison = null, string $variant = 'default'): void {
        $variant_class = $variant !== 'default' ? "ltlb-kpi-card--{$variant}" : '';
        ?>
        <div class="ltlb-kpi-card <?php echo esc_attr($variant_class); ?>">
            <div class="ltlb-kpi-card__icon">
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
            </div>
            <div class="ltlb-kpi-card__content">
                <div class="ltlb-kpi-card__value"><?php echo esc_html($value); ?></div>
                <div class="ltlb-kpi-card__label"><?php echo esc_html($label); ?></div>
                <?php if ($comparison !== null): ?>
                    <div class="ltlb-kpi-card__trend <?php echo $comparison >= 0 ? 'ltlb-kpi-card__trend--up' : 'ltlb-kpi-card__trend--down'; ?>">
                        <span class="dashicons dashicons-arrow-<?php echo $comparison >= 0 ? 'up' : 'down'; ?>-alt"></span>
                        <?php echo abs($comparison); ?>%
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Analytics Card
     */
    public static function render_analytics_card(): void {
        $analytics = LTLB_Analytics::instance();
        
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        $counts = $analytics->get_count_by_status($start_date, $end_date);
        $top_services = $analytics->get_top_services(5, $start_date, $end_date);
        $revenue = $analytics->get_revenue($start_date, $end_date);
        ?>
        <div class="ltlb-card">
            <div class="ltlb-card__header">
                <div class="ltlb-card__header-content">
                    <span class="dashicons dashicons-chart-area"></span>
                    <h3 class="ltlb-card__title"><?php echo esc_html__('Analytics', 'ltl-bookings'); ?></h3>
                    <span class="ltlb-badge ltlb-badge--info"><?php echo esc_html__('Last 30 Days', 'ltl-bookings'); ?></span>
                </div>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=ltlb_appointments&ltlb_export=csv&start=' . $start_date . '&end=' . $end_date), 'ltlb_export')); ?>" class="ltlb-btn ltlb-btn--ghost ltlb-btn--small" download>
                    <span class="dashicons dashicons-download"></span>
                    <?php echo esc_html__('Export', 'ltl-bookings'); ?>
                </a>
            </div>
            <div class="ltlb-card__body">
                <!-- Stats Row -->
                <div class="ltlb-stats-grid">
                    <div class="ltlb-stat-box ltlb-stat-box--success">
                        <div class="ltlb-stat-box__value"><?php echo intval($counts['confirmed']); ?></div>
                        <div class="ltlb-stat-box__label"><?php echo esc_html__('Confirmed', 'ltl-bookings'); ?></div>
                    </div>
                    <div class="ltlb-stat-box ltlb-stat-box--warning">
                        <div class="ltlb-stat-box__value"><?php echo intval($counts['pending']); ?></div>
                        <div class="ltlb-stat-box__label"><?php echo esc_html__('Pending', 'ltl-bookings'); ?></div>
                    </div>
                    <div class="ltlb-stat-box ltlb-stat-box--primary">
                        <div class="ltlb-stat-box__value">€<?php echo number_format(floatval($revenue['total']), 0); ?></div>
                        <div class="ltlb-stat-box__label"><?php echo esc_html__('Revenue', 'ltl-bookings'); ?></div>
                    </div>
                </div>
                
                <?php if (!empty($top_services)): ?>
                <div class="ltlb-top-services">
                    <h4 class="ltlb-top-services__title"><?php echo esc_html__('Top Services', 'ltl-bookings'); ?></h4>
                    <?php foreach ($top_services as $index => $svc): 
                        $max_count = $top_services[0]['count'] ?? 1;
                        $percentage = ($svc['count'] / max($max_count, 1)) * 100;
                    ?>
                        <div class="ltlb-service-row">
                            <span class="ltlb-service-row__rank"><?php echo $index + 1; ?></span>
                            <span class="ltlb-service-row__name"><?php echo esc_html($svc['name']); ?></span>
                            <div class="ltlb-service-row__bar">
                                <div class="ltlb-service-row__bar-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                            </div>
                            <span class="ltlb-service-row__count"><?php echo intval($svc['count']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render AI Insights Card
     */
    public static function render_ai_insights_card( bool $can_generate = false ): void {
        $last = get_option( 'lazy_ai_last_report', [] );
        if ( ! is_array( $last ) ) $last = [];
        $report = isset( $last['report'] ) ? (string) $last['report'] : '';
        $created_at = isset( $last['created_at'] ) ? (string) $last['created_at'] : '';

        $preview = '';
        if ( $report !== '' ) {
            $lines = preg_split( '/\r\n|\r|\n/', $report );
            $lines = is_array( $lines ) ? array_slice( $lines, 0, 5 ) : [];
            $preview = implode( "\n", $lines );
        }
        ?>
        <div class="ltlb-card">
            <div class="ltlb-card__header">
                <div class="ltlb-card__header-content">
                    <span class="dashicons dashicons-lightbulb"></span>
                    <h3 class="ltlb-card__title"><?php echo esc_html__('AI Insights', 'ltl-bookings'); ?></h3>
                </div>
            </div>
            <div class="ltlb-card__body">
                <?php if ( $preview === '' ) : ?>
                    <div class="ltlb-empty-state ltlb-empty-state--compact">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <p><?php echo esc_html__('No insights generated yet', 'ltl-bookings'); ?></p>
                    </div>
                <?php else : ?>
                    <?php if ( $created_at ) : ?>
                        <p class="ltlb-text-muted ltlb-text-xs"><?php echo esc_html__('Updated:', 'ltl-bookings') . ' ' . esc_html( $created_at ); ?></p>
                    <?php endif; ?>
                    <div class="ltlb-ai-preview"><?php echo esc_html( $preview ); ?></div>
                <?php endif; ?>

                <?php if ( $can_generate ) : ?>
                    <div class="ltlb-card__footer">
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field( 'ltlb_generate_ai_insights_action', 'ltlb_generate_ai_insights_nonce' ); ?>
                            <input type="hidden" name="ltlb_generate_ai_insights" value="1" />
                            <button type="submit" class="ltlb-btn ltlb-btn--primary ltlb-btn--small">
                                <span class="dashicons dashicons-update"></span>
                                <?php echo esc_html__('Generate', 'ltl-bookings'); ?>
                            </button>
                        </form>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_outbox&status=all')); ?>" class="ltlb-btn ltlb-btn--ghost ltlb-btn--small">
                            <?php echo esc_html__('Outbox', 'ltl-bookings'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Recently Viewed Card
     */
    public static function render_recently_viewed_card(): void {
        $recent_ids = get_user_meta(get_current_user_id(), 'ltlb_recently_viewed_appointments', true);
        if (!is_array($recent_ids)) $recent_ids = [];
        $recent_ids = array_slice($recent_ids, 0, 5);
        $can_view = current_user_can( 'manage_options' );
        
        ?>
        <div class="ltlb-card">
            <div class="ltlb-card__header">
                <div class="ltlb-card__header-content">
                    <span class="dashicons dashicons-visibility"></span>
                    <h3 class="ltlb-card__title"><?php echo esc_html__('Recently Viewed', 'ltl-bookings'); ?></h3>
                </div>
            </div>
            <div class="ltlb-card__body">
                <?php if (empty($recent_ids)): ?>
                    <p class="ltlb-text-muted ltlb-text-center"><?php echo esc_html__('No recently viewed', 'ltl-bookings'); ?></p>
                <?php else: ?>
                    <div class="ltlb-recent-list">
                        <?php 
                        $appt_repo = new LTLB_AppointmentRepository();
                        $cust_repo = new LTLB_CustomerRepository();
                        foreach ($recent_ids as $appt_id): 
                            $appt = $appt_repo->get_by_id($appt_id);
                            if (!$appt) continue;
                            $cust = $cust_repo->get_by_id(intval($appt['customer_id']));
                            $cust_name = $cust ? $cust['first_name'] . ' ' . $cust['last_name'] : '—';
                            $view_url = admin_url('admin.php?page=ltlb_appointments&action=view&id=' . $appt_id);

                            $appt_tz = (string) ( $appt['timezone'] ?? '' );
                            if ( $appt_tz === '' ) {
                                $appt_tz = LTLB_Time::get_site_timezone_string();
                            }
                            $time_display = LTLB_DateTime::format_local_display_from_utc_mysql( (string) ( $appt['start_at'] ?? '' ), get_option('time_format'), $appt_tz );
                        ?>
                            <a href="<?php echo esc_url($view_url); ?>" class="ltlb-recent-item">
                                <span class="ltlb-recent-item__name"><?php echo esc_html($cust_name); ?></span>
                                <span class="ltlb-recent-item__time"><?php echo esc_html($time_display); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // Legacy method aliases for backwards compatibility
    public static function render_recently_viewed(): void { self::render_recently_viewed_card(); }
    public static function render_analytics(): void { self::render_analytics_card(); }
    public static function render_ai_insights( bool $can_generate = false ): void { self::render_ai_insights_card( $can_generate ); }
}

