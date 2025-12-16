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

        // Handle AI insights generation.
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

		$latest_bookings = $appt_repo->get_all(['limit' => 5]);
		?>
        <div class="wrap ltlb-admin ltlb-admin--dashboard">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_dashboard'); } ?>

            <div class="ltlb-dashboard-header">
                <h1 class="wp-heading-inline"><?php echo esc_html__('Hotel Dashboard', 'ltl-bookings'); ?></h1>
				<?php if ( $can_manage ) : ?>
				<div class="ltlb-quick-actions">
					<a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_services&action=add')); ?>" class="button button-primary" aria-label="<?php echo esc_attr__('Create new room type', 'ltl-bookings'); ?>">
						<span class="dashicons dashicons-plus" aria-hidden="true"></span>
						<?php echo esc_html__('New Room Type', 'ltl-bookings'); ?>
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
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__( 'Check-ins Today', 'ltl-bookings' ), $check_ins_today, 'dashicons-arrow-right-alt'); ?>
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__( 'Check-outs Today', 'ltl-bookings' ), $check_outs_today, 'dashicons-arrow-left-alt'); ?>
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__( 'Occupied Rooms', 'ltl-bookings' ), $occupied_rooms, 'dashicons-building'); ?>
            </div>

            <?php $this->render_ai_insights_card( $can_manage ); ?>

            <?php $this->render_occupancy_card( $appt_repo, $occupied_rooms, $total_rooms ); ?>

			<?php $this->render_finance_card(); ?>

            <?php LTLB_Admin_Component::card_start(__( 'Latest Bookings', 'ltl-bookings' )); ?>
                <?php if ( empty($latest_bookings) ) : ?>
                    <p><?php echo esc_html__('No bookings found.', 'ltl-bookings'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Customer', 'ltl-bookings'); ?></th>
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
                                $start_display = LTLB_DateTime::format_local_display_from_utc_mysql( (string) ( $b['start_at'] ?? '' ), get_option('date_format') . ' ' . get_option('time_format'), $appt_tz );
                                $end_display = LTLB_DateTime::format_local_display_from_utc_mysql( (string) ( $b['end_at'] ?? '' ), get_option('date_format') . ' ' . get_option('time_format'), $appt_tz );
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $cust_name ); ?></td>
                                    <td><?php echo esc_html( $service ? $service['name'] : '—' ); ?></td>
									<td><?php echo esc_html( $start_display ); ?></td>
									<td><?php echo esc_html( $end_display ); ?></td>
                                    <td>
                                        <?php echo LTLB_Admin_AppointmentsDashboardPage::render_status_badge( $b['status'] ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php LTLB_Admin_Component::card_end(); ?>
		</div>
		<?php
	}

    private function render_occupancy_card( LTLB_AppointmentRepository $appt_repo, int $occupied_today, int $total_rooms ): void {
        $rate = $total_rooms > 0 ? round( ( $occupied_today / $total_rooms ) * 100 ) : 0;
        $rate = max( 0, min( 100, (int) $rate ) );
        ?>
        <?php LTLB_Admin_Component::card_start( __( 'Occupancy', 'ltl-bookings' ) ); ?>
            <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                <div class="ltlb-donut" style="--ltlb-donut-pct: <?php echo esc_attr( (string) $rate ); ?>;" aria-label="<?php echo esc_attr__( 'Today occupancy rate', 'ltl-bookings' ); ?>">
                    <div class="ltlb-donut__inner">
                        <div class="ltlb-donut__value"><?php echo esc_html( (string) $rate ); ?>%</div>
                        <div class="ltlb-donut__label"><?php echo esc_html__( 'Today', 'ltl-bookings' ); ?></div>
                    </div>
                </div>
                <div>
                    <div style="font-size:14px;">
                        <strong><?php echo esc_html( (string) $occupied_today ); ?></strong>
                        <?php echo esc_html__( 'occupied', 'ltl-bookings' ); ?>
                        <?php echo esc_html__( 'of', 'ltl-bookings' ); ?>
                        <strong><?php echo esc_html( (string) $total_rooms ); ?></strong>
                        <?php echo esc_html__( 'rooms', 'ltl-bookings' ); ?>
                    </div>
                    <p class="description" style="margin:6px 0 0;">
                        <?php echo esc_html__( 'Based on confirmed bookings with assigned rooms.', 'ltl-bookings' ); ?>
                    </p>
                </div>
            </div>

            <h4 style="margin-top:16px;"><?php echo esc_html__( 'Next 7 Days', 'ltl-bookings' ); ?></h4>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Date', 'ltl-bookings' ); ?></th>
                        <th><?php echo esc_html__( 'Occupied Rooms', 'ltl-bookings' ); ?></th>
                        <th><?php echo esc_html__( 'Occupancy', 'ltl-bookings' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ( $i = 0; $i < 7; $i++ ) :
                        $date = date( 'Y-m-d', strtotime( '+' . $i . ' day' ) );
                        $occupied = $appt_repo->get_count_occupied_rooms_on_date( $date );
                        $day_rate = $total_rooms > 0 ? round( ( $occupied / $total_rooms ) * 100 ) : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) ); ?></td>
                        <td><?php echo esc_html( (string) $occupied ); ?> / <?php echo esc_html( (string) $total_rooms ); ?></td>
                        <td><?php echo esc_html( (string) max( 0, min( 100, (int) $day_rate ) ) ); ?>%</td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        <?php LTLB_Admin_Component::card_end(); ?>
        <?php
    }

    private function render_ai_insights_card( bool $can_generate = false ): void {
        $last = get_option( 'lazy_ai_last_report', [] );
        if ( ! is_array( $last ) ) $last = [];
        $report = isset( $last['report'] ) ? (string) $last['report'] : '';
        $created_at = isset( $last['created_at'] ) ? (string) $last['created_at'] : '';
        $preview = '';
        if ( $report !== '' ) {
            $lines = preg_split( '/\r\n|\r|\n/', $report );
            $lines = is_array( $lines ) ? array_slice( $lines, 0, 8 ) : [];
            $preview = implode( "\n", $lines );
        }
        $outbox_url = admin_url( 'admin.php?page=ltlb_outbox&status=all' );
        ?>
        <?php LTLB_Admin_Component::card_start( __( 'AI Insights', 'ltl-bookings' ) ); ?>
            <?php if ( $preview === '' ) : ?>
                <p class="ltlb-muted" style="margin-top:0;"><?php echo esc_html__( 'No saved report yet. Generate one to see daily + overall insights.', 'ltl-bookings' ); ?></p>
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
        <?php LTLB_Admin_Component::card_start( __( 'Finance (Last 30 Days)', 'ltl-bookings' ) ); ?>
            <div class="ltlb-analytics-grid">
                <div class="ltlb-analytics-stat">
                    <div class="ltlb-analytics-stat__value"><?php echo esc_html( LTLB_Finance::format_money_from_cents( $revenue ) ); ?></div>
                    <div class="ltlb-analytics-stat__label"><?php echo esc_html__( 'Revenue (gross)', 'ltl-bookings' ); ?></div>
                </div>
                <div class="ltlb-analytics-stat">
                    <div class="ltlb-analytics-stat__value"><?php echo esc_html( LTLB_Finance::format_money_from_cents( $fees ) ); ?></div>
                    <div class="ltlb-analytics-stat__label"><?php echo esc_html__( 'Fees', 'ltl-bookings' ); ?></div>
                </div>
                <div class="ltlb-analytics-stat">
                    <div class="ltlb-analytics-stat__value"><?php echo esc_html( LTLB_Finance::format_money_from_cents( $room_costs ) ); ?></div>
                    <div class="ltlb-analytics-stat__label"><?php echo esc_html__( 'Room costs', 'ltl-bookings' ); ?></div>
                </div>
                <div class="ltlb-analytics-stat">
                    <div class="ltlb-analytics-stat__value"><?php echo esc_html( LTLB_Finance::format_money_from_cents( $gross_profit ) ); ?></div>
                    <div class="ltlb-analytics-stat__label"><?php echo esc_html__( 'Gross profit', 'ltl-bookings' ); ?></div>
                </div>
            </div>
            <p class="description" style="margin:10px 0 0;">
                <?php echo esc_html__( 'Deterministic: gross profit = revenue − fees − room costs (from assigned rooms × nights).', 'ltl-bookings' ); ?>
            </p>
        <?php LTLB_Admin_Component::card_end(); ?>
        <?php
    }
}

