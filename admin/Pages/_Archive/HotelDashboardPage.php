<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_HotelDashboardPage {
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
		$res_repo = new LTLB_ResourceRepository();

		$check_ins_today = $appt_repo->get_count_check_ins_today();
		$check_outs_today = $appt_repo->get_count_check_outs_today();
		$occupied_rooms = $appt_repo->get_count_occupied_rooms_today();
        $resources_all = $res_repo->get_all();
        $total_rooms = 0;
        foreach ( $resources_all as $r ) {
            if ( ! empty( $r['is_active'] ) ) {
                $total_rooms++;
            }
        }
        
        $occupancy_rate = $total_rooms > 0 ? round( ( $occupied_rooms / $total_rooms ) * 100 ) : 0;
		$latest_bookings = $appt_repo->get_all(['limit' => 8]);

		?>
        <div class="wrap ltlb-admin ltlb-admin--dashboard">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_dashboard'); } ?>
            
            <!-- Page Header -->
            <div class="ltlb-page-header">
                <div class="ltlb-page-header__content">
                    <h1 class="ltlb-page-header__title"><?php echo esc_html__('Hotel Dashboard', 'ltl-bookings'); ?></h1>
                    <p class="ltlb-page-header__subtitle"><?php echo esc_html__('Room bookings and occupancy overview', 'ltl-bookings'); ?></p>
                </div>
				<?php if ( $can_manage ) : ?>
				<div class="ltlb-page-header__actions">
					<a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_services&action=add')); ?>" class="ltlb-btn ltlb-btn--primary">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<?php echo esc_html__('New Room Type', 'ltl-bookings'); ?>
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
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__('Check-ins Today', 'ltl-bookings'), $check_ins_today, 'dashicons-arrow-right-alt', null, 'success'); ?>
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__('Check-outs Today', 'ltl-bookings'), $check_outs_today, 'dashicons-arrow-left-alt', null, 'warning'); ?>
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__('Occupied Rooms', 'ltl-bookings'), $occupied_rooms . '/' . $total_rooms, 'dashicons-building', null, 'primary'); ?>
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__('Occupancy Rate', 'ltl-bookings'), $occupancy_rate . '%', 'dashicons-chart-pie', null, 'info'); ?>
            </div>

            <!-- Main Content Grid: 2 Column Layout -->
            <div class="ltlb-dashboard-layout">
                
                <!-- LEFT COLUMN: Main Content -->
                <div class="ltlb-dashboard-layout__main">
                    
                    <!-- Occupancy Forecast -->
                    <?php $this->render_occupancy_card( $appt_repo, $occupied_rooms, $total_rooms ); ?>

                    <!-- Latest Bookings -->
                    <div class="ltlb-card">
                        <div class="ltlb-card__header">
                            <div class="ltlb-card__header-content">
                                <span class="dashicons dashicons-calendar"></span>
                                <h3 class="ltlb-card__title"><?php echo esc_html__('Latest Bookings', 'ltl-bookings'); ?></h3>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_appointments')); ?>" class="ltlb-card__link">
                                <?php echo esc_html__('View All', 'ltl-bookings'); ?> →
                            </a>
                        </div>
                        <div class="ltlb-card__body ltlb-card__body--flush">
                            <?php if ( empty($latest_bookings) ) : ?>
                                <div class="ltlb-empty-state">
                                    <span class="dashicons dashicons-building"></span>
                                    <p><?php echo esc_html__('No bookings yet', 'ltl-bookings'); ?></p>
                                </div>
                            <?php else : ?>
                                <table class="ltlb-table ltlb-table--dashboard">
                                    <thead>
                                        <tr>
                                            <th><?php echo esc_html__('Guest', 'ltl-bookings'); ?></th>
                                            <th><?php echo esc_html__('Room Type', 'ltl-bookings'); ?></th>
                                            <th><?php echo esc_html__('Check-in', 'ltl-bookings'); ?></th>
                                            <th><?php echo esc_html__('Check-out', 'ltl-bookings'); ?></th>
                                            <th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $s_repo = new LTLB_ServiceRepository();
                                        $c_repo = new LTLB_CustomerRepository();
                                        foreach ( $latest_bookings as $b ): 
                                            $cust = $c_repo->get_by_id( intval($b['customer_id']) );
                                            $cust_name = $cust ? $cust['first_name'] . ' ' . $cust['last_name'] : '—';
                                            $service = $s_repo->get_by_id( intval($b['service_id']) );

                                            $appt_tz = (string) ( $b['timezone'] ?? '' );
                                            if ( $appt_tz === '' ) {
                                                $appt_tz = LTLB_Time::get_site_timezone_string();
                                            }
                                            $start_display = LTLB_Time::format_local_display_from_utc_mysql( (string) ( $b['start_at'] ?? '' ), get_option('date_format'), $appt_tz );
                                            $end_display = LTLB_Time::format_local_display_from_utc_mysql( (string) ( $b['end_at'] ?? '' ), get_option('date_format'), $appt_tz );
                                        ?>
                                            <tr>
                                                <td><?php echo esc_html( $cust_name ); ?></td>
                                                <td><?php echo esc_html( $service ? $service['name'] : '—' ); ?></td>
                                                <td class="ltlb-text-muted"><?php echo esc_html( $start_display ); ?></td>
                                                <td class="ltlb-text-muted"><?php echo esc_html( $end_display ); ?></td>
                                                <td><?php echo LTLB_Admin_AppointmentsDashboardPage::render_status_badge( $b['status'] ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

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
                                    <span class="ltlb-quick-action__label"><?php echo esc_html__('New Booking', 'ltl-bookings'); ?></span>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_resources')); ?>" class="ltlb-quick-action">
                                    <span class="ltlb-quick-action__icon"><span class="dashicons dashicons-building"></span></span>
                                    <span class="ltlb-quick-action__label"><?php echo esc_html__('Manage Rooms', 'ltl-bookings'); ?></span>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_customers')); ?>" class="ltlb-quick-action">
                                    <span class="ltlb-quick-action__icon"><span class="dashicons dashicons-admin-users"></span></span>
                                    <span class="ltlb-quick-action__label"><?php echo esc_html__('Guest List', 'ltl-bookings'); ?></span>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_settings')); ?>" class="ltlb-quick-action">
                                    <span class="ltlb-quick-action__icon"><span class="dashicons dashicons-admin-generic"></span></span>
                                    <span class="ltlb-quick-action__label"><?php echo esc_html__('Settings', 'ltl-bookings'); ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Finance Card -->
                    <?php $this->render_finance_card(); ?>

                    <!-- AI Insights -->
                    <?php LTLB_Admin_AppointmentsDashboardPage::render_ai_insights_card( $can_manage ); ?>

                </div>
            </div>
		</div>
		<?php
	}

    /**
     * Render Occupancy Card with forecast
     */
    private function render_occupancy_card( LTLB_AppointmentRepository $appt_repo, int $occupied_today, int $total_rooms ): void {
        $rate = $total_rooms > 0 ? round( ( $occupied_today / $total_rooms ) * 100 ) : 0;
        $rate = max( 0, min( 100, (int) $rate ) );
        ?>
        <div class="ltlb-card">
            <div class="ltlb-card__header">
                <div class="ltlb-card__header-content">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <h3 class="ltlb-card__title"><?php echo esc_html__('Occupancy Forecast', 'ltl-bookings'); ?></h3>
                    <span class="ltlb-badge ltlb-badge--info"><?php echo esc_html__('Next 7 Days', 'ltl-bookings'); ?></span>
                </div>
            </div>
            <div class="ltlb-card__body">
                <!-- Today's Occupancy Highlight -->
                <div class="ltlb-occupancy-today">
                    <div class="ltlb-occupancy-donut" style="--occupancy-pct: <?php echo esc_attr( (string) $rate ); ?>;">
                        <div class="ltlb-occupancy-donut__inner">
                            <span class="ltlb-occupancy-donut__value"><?php echo esc_html( (string) $rate ); ?>%</span>
                            <span class="ltlb-occupancy-donut__label"><?php echo esc_html__('Today', 'ltl-bookings'); ?></span>
                        </div>
                    </div>
                    <div class="ltlb-occupancy-info">
                        <div class="ltlb-occupancy-stat">
                            <strong><?php echo esc_html( (string) $occupied_today ); ?></strong>
                            <span><?php echo esc_html__('occupied', 'ltl-bookings'); ?></span>
                        </div>
                        <div class="ltlb-occupancy-stat">
                            <strong><?php echo esc_html( (string) max(0, $total_rooms - $occupied_today) ); ?></strong>
                            <span><?php echo esc_html__('available', 'ltl-bookings'); ?></span>
                        </div>
                        <div class="ltlb-occupancy-stat">
                            <strong><?php echo esc_html( (string) $total_rooms ); ?></strong>
                            <span><?php echo esc_html__('total rooms', 'ltl-bookings'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- 7 Day Forecast Table -->
                <table class="ltlb-table ltlb-table--compact">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Date', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Rooms', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Occupancy', 'ltl-bookings'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ( $i = 0; $i < 7; $i++ ) :
                            $date = date( 'Y-m-d', strtotime( '+' . $i . ' day' ) );
                            $occupied = $appt_repo->get_count_occupied_rooms_on_date( $date );
                            $day_rate = $total_rooms > 0 ? round( ( $occupied / $total_rooms ) * 100 ) : 0;
                            $day_rate = max( 0, min( 100, (int) $day_rate ) );
                            
                            // Color class based on occupancy
                            $color_class = $day_rate >= 80 ? 'ltlb-text-success' : ($day_rate >= 50 ? 'ltlb-text-warning' : 'ltlb-text-muted');
                        ?>
                        <tr>
                            <td>
                                <?php if ($i === 0): ?>
                                    <strong><?php echo esc_html__('Today', 'ltl-bookings'); ?></strong>
                                <?php elseif ($i === 1): ?>
                                    <?php echo esc_html__('Tomorrow', 'ltl-bookings'); ?>
                                <?php else: ?>
                                    <?php echo esc_html( date_i18n( 'D, M j', strtotime( $date ) ) ); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( (string) $occupied ); ?> / <?php echo esc_html( (string) $total_rooms ); ?></td>
                            <td>
                                <div class="ltlb-occupancy-bar">
                                    <div class="ltlb-occupancy-bar__fill" style="width: <?php echo esc_attr($day_rate); ?>%"></div>
                                    <span class="ltlb-occupancy-bar__label <?php echo esc_attr($color_class); ?>"><?php echo esc_html( (string) $day_rate ); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render Finance Card
     */
    private function render_finance_card(): void {
        if ( ! class_exists( 'LTLB_Finance' ) ) {
            return;
        }

        $end_date = date( 'Y-m-d' );
        $start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
        $fin = LTLB_Finance::hotel_financials_cents( $start_date, $end_date );

        $revenue = intval( $fin['revenue_cents'] ?? 0 );
        $fees = intval( $fin['fees_cents'] ?? 0 );
        $room_costs = intval( $fin['room_costs_cents'] ?? 0 );
        $gross_profit = intval( $fin['gross_profit_cents'] ?? 0 );
        ?>
        <div class="ltlb-card">
            <div class="ltlb-card__header">
                <div class="ltlb-card__header-content">
                    <span class="dashicons dashicons-chart-area"></span>
                    <h3 class="ltlb-card__title"><?php echo esc_html__('Finance', 'ltl-bookings'); ?></h3>
                    <span class="ltlb-badge ltlb-badge--info"><?php echo esc_html__('30 Days', 'ltl-bookings'); ?></span>
                </div>
            </div>
            <div class="ltlb-card__body">
                <div class="ltlb-finance-grid">
                    <div class="ltlb-finance-item">
                        <span class="ltlb-finance-item__label"><?php echo esc_html__('Revenue', 'ltl-bookings'); ?></span>
                        <span class="ltlb-finance-item__value ltlb-text-success"><?php echo esc_html( LTLB_Finance::format_money_from_cents( $revenue ) ); ?></span>
                    </div>
                    <div class="ltlb-finance-item">
                        <span class="ltlb-finance-item__label"><?php echo esc_html__('Fees', 'ltl-bookings'); ?></span>
                        <span class="ltlb-finance-item__value ltlb-text-muted"><?php echo esc_html( LTLB_Finance::format_money_from_cents( $fees ) ); ?></span>
                    </div>
                    <div class="ltlb-finance-item">
                        <span class="ltlb-finance-item__label"><?php echo esc_html__('Room Costs', 'ltl-bookings'); ?></span>
                        <span class="ltlb-finance-item__value ltlb-text-muted"><?php echo esc_html( LTLB_Finance::format_money_from_cents( $room_costs ) ); ?></span>
                    </div>
                    <div class="ltlb-finance-item ltlb-finance-item--highlight">
                        <span class="ltlb-finance-item__label"><?php echo esc_html__('Gross Profit', 'ltl-bookings'); ?></span>
                        <span class="ltlb-finance-item__value"><?php echo esc_html( LTLB_Finance::format_money_from_cents( $gross_profit ) ); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

