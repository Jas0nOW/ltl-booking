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

        // Handle AI insights generation.
        if ( class_exists( 'LTLB_Automations' ) ) {
            LTLB_Automations::maybe_handle_generate_ai_insights_post( admin_url( 'admin.php?page=ltlb_dashboard' ) );
        }

		$appt_repo = new LTLB_AppointmentRepository();
		$cust_repo = new LTLB_CustomerRepository();

		$appointments_today = $appt_repo->get_count_for_today();
		$pending_appointments = $appt_repo->get_count_by_status( 'pending' );
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
		
		$latest_appointments = $appt_repo->get_all(['limit' => 5]);
        $can_view_appointment_detail = current_user_can( 'manage_options' );

		?>
        <div class="wrap ltlb-admin ltlb-admin--dashboard">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_dashboard'); } ?>
            
            <div class="ltlb-dashboard-header">
                <h1 class="wp-heading-inline"><?php echo esc_html__('Appointments Dashboard', 'ltl-bookings'); ?></h1>
				<?php if ( $can_manage ) : ?>
				<div class="ltlb-quick-actions">
					<a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_appointments&action=add')); ?>" class="button button-primary" aria-label="<?php echo esc_attr__('Create new appointment', 'ltl-bookings'); ?>">
						<span class="dashicons dashicons-plus" aria-hidden="true"></span>
						<?php echo esc_html__('New Appointment', 'ltl-bookings'); ?>
					</a>
					<a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_calendar')); ?>" class="button" aria-label="<?php echo esc_attr__('Open calendar view', 'ltl-bookings'); ?>">
						<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
						<?php echo esc_html__('View Calendar', 'ltl-bookings'); ?>
					</a>
				</div>
				<?php endif; ?>
            </div>
            <hr class="wp-header-end">

            <div class="ltlb-kpi-grid">
                <?php self::render_kpi_card(__( 'Appointments This Week', 'ltl-bookings' ), $this_week_count, 'dashicons-calendar-alt', $week_comparison); ?>
                <?php self::render_kpi_card(__( 'Pending Appointments', 'ltl-bookings' ), $pending_appointments, 'dashicons-clock'); ?>
                <?php self::render_kpi_card(__( 'Total Customers', 'ltl-bookings' ), $total_customers, 'dashicons-admin-users'); ?>
            </div>

            <div class="ltlb-dashboard-grid">
                <div class="ltlb-dashboard-grid__main">
                    <?php LTLB_Admin_Component::card_start(__( 'Latest Appointments', 'ltl-bookings' )); ?>
                        <?php if ( empty($latest_appointments) ) : ?>
                            <p><?php echo esc_html__('No appointments found.', 'ltl-bookings'); ?></p>
                        <?php else : ?>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Customer', 'ltl-bookings'); ?></th>
                                        <th><?php echo esc_html__('Service', 'ltl-bookings'); ?></th>
                                        <th><?php echo esc_html__('Start', 'ltl-bookings'); ?></th>
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
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ( $can_view_appointment_detail && ! empty( $a['id'] ) ) : ?>
                                                    <a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $cust_name ); ?></a>
                                                <?php else : ?>
                                                    <?php echo esc_html( $cust_name ); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html( $service ? $service['name'] : '—' ); ?></td>
                                            <td><?php echo esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), strtotime( $a['start_at'] ) ) ); ?></td>
                                            <td>
                                                <?php echo self::render_status_badge( $a['status'] ); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php LTLB_Admin_Component::card_end(); ?>
                </div>
                
                <div class="ltlb-dashboard-grid__sidebar">
                    <?php self::render_ai_insights( $can_manage ); ?>
                    <?php self::render_analytics(); ?>
                    <?php self::render_recently_viewed(); ?>
                </div>
            </div>
		</div>
		<?php
	}

    /**
     * Render a translatable status badge
     */
    public static function render_status_badge(string $status): string {
        return LTLB_BookingStatus::render_badge( $status );
    }

    public static function render_kpi_card(string $label, $value, string $icon, $comparison = null): void {
        ?>
        <div class="ltlb-kpi-card">
            <div class="ltlb-kpi-card__icon">
                <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            </div>
            <div class="ltlb-kpi-card__content">
                <div class="ltlb-kpi-card__label"><?php echo esc_html($label); ?></div>
                <div class="ltlb-kpi-card__value"><?php echo esc_html($value); ?></div>
                <?php if ($comparison !== null): ?>
                    <div class="ltlb-kpi-card__comparison <?php echo $comparison >= 0 ? 'positive' : 'negative'; ?>">
                        <span class="dashicons dashicons-arrow-<?php echo $comparison >= 0 ? 'up' : 'down'; ?>-alt" aria-hidden="true"></span>
                        <?php echo abs($comparison); ?>% <?php echo esc_html__('vs last week', 'ltl-bookings'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render recently viewed appointments sidebar widget
     */
    public static function render_recently_viewed(): void {
        $recent_ids = get_user_meta(get_current_user_id(), 'ltlb_recently_viewed_appointments', true);
        if (!is_array($recent_ids)) $recent_ids = [];
        $recent_ids = array_slice($recent_ids, 0, 5);
        $can_view_appointment_detail = current_user_can( 'manage_options' );
        
        ?>
        <?php LTLB_Admin_Component::card_start(__( 'Recently Viewed', 'ltl-bookings' ), ['class' => 'ltlb-card--sidebar']); ?>
            <?php if (empty($recent_ids)): ?>
                <p class="ltlb-muted"><?php echo esc_html__('No recently viewed appointments yet.', 'ltl-bookings'); ?></p>
            <?php else: ?>
                <ul class="ltlb-recent-items">
                    <?php 
                    $appt_repo = new LTLB_AppointmentRepository();
                    $cust_repo = new LTLB_CustomerRepository();
                    foreach ($recent_ids as $appt_id): 
                        $appt = $appt_repo->get_by_id($appt_id);
                        if (!$appt) continue;
                        $cust = $cust_repo->get_by_id(intval($appt['customer_id']));
                        $cust_name = $cust ? $cust['first_name'] . ' ' . $cust['last_name'] : '—';
                        $view_url = admin_url('admin.php?page=ltlb_appointments&action=view&id=' . $appt_id);
                    ?>
                        <li class="ltlb-recent-item">
                            <?php if ( $can_view_appointment_detail ) : ?>
                                <a href="<?php echo esc_url($view_url); ?>" class="ltlb-recent-item__link">
                            <?php else : ?>
                                <div class="ltlb-recent-item__link">
                            <?php endif; ?>
                                <div class="ltlb-recent-item__title">
                                    <?php echo esc_html($cust_name); ?>
                                </div>
                                <div class="ltlb-recent-item__meta">
                                    <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                    <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($appt['start_at']))); ?>
                                </div>
                            <?php if ( $can_view_appointment_detail ) : ?>
                                </a>
                            <?php else : ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php LTLB_Admin_Component::card_end(); ?>
        <?php
    }

    public static function render_analytics(): void {
        $analytics = LTLB_Analytics::instance();
        
        // Last 30 days
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        $counts = $analytics->get_count_by_status($start_date, $end_date);
        $top_services = $analytics->get_top_services(5, $start_date, $end_date);
        $revenue = $analytics->get_revenue($start_date, $end_date);
        
        ?>
        <?php LTLB_Admin_Component::card_start(__( 'Analytics (Last 30 Days)', 'ltl-bookings' )); ?>
            <div class="ltlb-analytics-grid">
                <div class="ltlb-analytics-stat">
                    <div class="ltlb-analytics-stat__value"><?php echo intval($counts['confirmed']); ?></div>
                    <div class="ltlb-analytics-stat__label"><?php echo esc_html__('Confirmed', 'ltl-bookings'); ?></div>
                </div>
                <div class="ltlb-analytics-stat">
                    <div class="ltlb-analytics-stat__value"><?php echo intval($counts['pending']); ?></div>
                    <div class="ltlb-analytics-stat__label"><?php echo esc_html__('Pending', 'ltl-bookings'); ?></div>
                </div>
                <div class="ltlb-analytics-stat">
                    <div class="ltlb-analytics-stat__value">€<?php echo number_format(floatval($revenue['total']), 2); ?></div>
                    <div class="ltlb-analytics-stat__label"><?php echo esc_html__('Revenue', 'ltl-bookings'); ?></div>
                </div>
            </div>
            
            <?php if (!empty($top_services)): ?>
                <h4><?php echo esc_html__('Top Services', 'ltl-bookings'); ?></h4>
                <ul class="ltlb-analytics-list">
                    <?php foreach ($top_services as $svc): ?>
                        <li>
                            <span><?php echo esc_html($svc['name']); ?></span>
                            <span class="ltlb-analytics-badge"><?php echo intval($svc['count']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <div class="ltlb-analytics-actions" style="margin-top:12px; padding-top:12px; border-top:1px solid rgba(0,0,0,0.08);">
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=ltlb_appointments&ltlb_export=csv&start=' . $start_date . '&end=' . $end_date), 'ltlb_export')); ?>" class="button button-secondary" download>
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <?php echo esc_html__('Export CSV', 'ltl-bookings'); ?>
                </a>
            </div>
        <?php LTLB_Admin_Component::card_end(); ?>
        <?php
    }

    public static function render_ai_insights( bool $can_generate = false ): void {
        $last = get_option( 'lazy_ai_last_report', [] );
        if ( ! is_array( $last ) ) $last = [];
        $report = isset( $last['report'] ) ? (string) $last['report'] : '';
        $created_at = isset( $last['created_at'] ) ? (string) $last['created_at'] : '';

        // Keep preview small.
        $preview = '';
        if ( $report !== '' ) {
            $lines = preg_split( '/\r\n|\r|\n/', $report );
            $lines = is_array( $lines ) ? array_slice( $lines, 0, 8 ) : [];
            $preview = implode( "\n", $lines );
        }
        $outbox_url = admin_url( 'admin.php?page=ltlb_outbox&status=all' );
        ?>
        <?php LTLB_Admin_Component::card_start( __( 'AI Insights', 'ltl-bookings' ), [ 'class' => 'ltlb-card--sidebar' ] ); ?>
            <?php if ( $preview === '' ) : ?>
                <p class="ltlb-muted"><?php echo esc_html__( 'No saved report yet. Generate one to see daily + overall insights.', 'ltl-bookings' ); ?></p>
            <?php else : ?>
                <?php if ( $created_at ) : ?>
                    <p class="ltlb-muted" style="margin-top:0;"><?php echo esc_html__( 'Last saved:', 'ltl-bookings' ) . ' ' . esc_html( $created_at ); ?></p>
                <?php endif; ?>
                <textarea class="large-text code" rows="8" readonly><?php echo esc_textarea( $preview ); ?></textarea>
            <?php endif; ?>

            <?php if ( $can_generate ) : ?>
                <form method="post" style="margin-top:10px; display:flex; gap:8px; align-items:center;">
                    <?php wp_nonce_field( 'ltlb_generate_ai_insights_action', 'ltlb_generate_ai_insights_nonce' ); ?>
                    <input type="hidden" name="ltlb_generate_ai_insights" value="1" />
                    <button type="submit" class="button button-primary"><?php echo esc_html__( 'Generate Report', 'ltl-bookings' ); ?></button>
                    <a class="button" href="<?php echo esc_url( $outbox_url ); ?>"><?php echo esc_html__( 'Open Outbox', 'ltl-bookings' ); ?></a>
                </form>
            <?php else : ?>
                <p class="description" style="margin-top:10px;">
                    <?php echo esc_html__( 'You have read-only access. Ask an administrator to generate a new report.', 'ltl-bookings' ); ?>
                </p>
            <?php endif; ?>
        <?php LTLB_Admin_Component::card_end(); ?>
        <?php
    }
}

