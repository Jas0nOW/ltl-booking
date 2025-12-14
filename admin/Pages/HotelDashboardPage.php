<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_HotelDashboardPage {
	public function render(): void {
        if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

		$appt_repo = new LTLB_AppointmentRepository();
		$res_repo = new LTLB_ResourceRepository();

		$check_ins_today = $appt_repo->get_count_check_ins_today();
		$check_outs_today = $appt_repo->get_count_check_outs_today();
		$occupied_rooms = $appt_repo->get_count_occupied_rooms_today();

		$latest_bookings = $appt_repo->get_all(['limit' => 5]);
		?>
        <div class="wrap ltlb-admin ltlb-admin--dashboard">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_dashboard'); } ?>

            <div class="ltlb-dashboard-header">
                <h1 class="wp-heading-inline"><?php echo esc_html__('Hotel Dashboard', 'ltl-bookings'); ?></h1>
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
            </div>
            <hr class="wp-header-end">

            <div class="ltlb-kpi-grid">
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__( 'Check-ins Today', 'ltl-bookings' ), $check_ins_today, 'dashicons-arrow-right-alt'); ?>
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__( 'Check-outs Today', 'ltl-bookings' ), $check_outs_today, 'dashicons-arrow-left-alt'); ?>
                <?php LTLB_Admin_AppointmentsDashboardPage::render_kpi_card(__( 'Occupied Rooms', 'ltl-bookings' ), $occupied_rooms, 'dashicons-building'); ?>
            </div>

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
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $cust_name ); ?></td>
                                    <td><?php echo esc_html( $service ? $service['name'] : '—' ); ?></td>
                                    <td><?php echo esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), strtotime( $b['start_at'] ) ) ); ?></td>
                                    <td><?php echo esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), strtotime( $b['end_at'] ) ) ); ?></td>
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
}

