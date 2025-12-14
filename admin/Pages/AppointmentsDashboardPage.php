<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_AppointmentsDashboardPage {
	public function render(): void {
        if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

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

		?>
        <div class="wrap ltlb-admin ltlb-admin--dashboard">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_dashboard'); } ?>
            
            <div class="ltlb-dashboard-header">
                <h1 class="wp-heading-inline"><?php echo esc_html__('Appointments Dashboard', 'ltl-bookings'); ?></h1>
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
            </div>
            <hr class="wp-header-end">

            <div class="ltlb-kpi-grid">
                <?php self::render_kpi_card(__( 'Appointments This Week', 'ltl-bookings' ), $this_week_count, 'dashicons-calendar-alt', $week_comparison); ?>
                <?php self::render_kpi_card(__( 'Pending Appointments', 'ltl-bookings' ), $pending_appointments, 'dashicons-clock'); ?>
                <?php self::render_kpi_card(__( 'Total Customers', 'ltl-bookings' ), $total_customers, 'dashicons-admin-users'); ?>
            </div>

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
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $cust_name ); ?></td>
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
		<?php
	}

    /**
     * Render a translatable status badge
     */
    public static function render_status_badge(string $status): string {
        $labels = [
            'pending' => __( 'Pending', 'ltl-bookings' ),
            'confirmed' => __( 'Confirmed', 'ltl-bookings' ),
            'cancelled' => __( 'Cancelled', 'ltl-bookings' ),
            'completed' => __( 'Completed', 'ltl-bookings' ),
        ];
        $label = $labels[ $status ] ?? ucfirst( $status );
        return '<span class="ltlb-status-badge status-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
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
}

