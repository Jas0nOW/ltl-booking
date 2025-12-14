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
		
		$latest_appointments = $appt_repo->get_all(['limit' => 5]);

		?>
        <div class="wrap ltlb-admin ltlb-admin--dashboard">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_dashboard'); } ?>
            
            <div class="ltlb-dashboard-header">
                <h1 class="wp-heading-inline"><?php echo esc_html__('Appointments Dashboard', 'ltl-bookings'); ?></h1>
                <div class="ltlb-quick-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_appointments&action=add')); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span>
                        <?php echo esc_html__('New Appointment', 'ltl-bookings'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_calendar')); ?>" class="button">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo esc_html__('View Calendar', 'ltl-bookings'); ?>
                    </a>
                </div>
            </div>
            <hr class="wp-header-end">

            <div class="ltlb-kpi-grid">
                <?php self::render_kpi_card(__( 'Appointments Today', 'ltl-bookings' ), $appointments_today, 'dashicons-calendar-alt'); ?>
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
                                    <td><?php echo esc_html( $a['start_at'] ); ?></td>
                                    <td>
                                        <span class="ltlb-status-badge status-<?php echo esc_attr($a['status']); ?>"><?php echo esc_html( ucfirst($a['status']) ); ?></span>
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

    public static function render_kpi_card(string $label, $value, string $icon): void {
        ?>
        <div class="ltlb-kpi-card">
            <div class="ltlb-kpi-card__icon">
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
            </div>
            <div class="ltlb-kpi-card__content">
                <div class="ltlb-kpi-card__label"><?php echo esc_html($label); ?></div>
                <div class="ltlb-kpi-card__value"><?php echo esc_html($value); ?></div>
            </div>
        </div>
        <?php
    }
}

