<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_AppointmentsPage {

    private $appointment_repository;
    private $service_repository;
    private $resource_repository;
    private $customer_repository;

    public function __construct() {
        $this->appointment_repository = new LTLB_AppointmentRepository();
        $this->service_repository = new LTLB_ServiceRepository();
        $this->resource_repository = new LTLB_ResourceRepository();
        $this->customer_repository = new LTLB_CustomerRepository();
    }

    public function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('You do not have permission to view this page.', 'ltl-bookings') );
        }

        // Handle CSV Export
        if ( isset($_GET['action']) && $_GET['action'] === 'export_csv' ) {
            $this->export_csv();
            return; // Stop rendering
        }

        // Handle Delete
        if ( isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) ) {
            if ( check_admin_referer('ltlb_delete_appointment') ) {
                $this->appointment_repository->delete( intval($_GET['id']) );
                LTLB_Notices::add( __('Appointment deleted.', 'ltl-bookings'), 'success' );
                wp_safe_redirect( admin_url('admin.php?page=ltlb_appointments') );
                exit;
            }
        }

        // Handle Status Change
        if ( isset($_GET['action']) && $_GET['action'] === 'status' && isset($_GET['id']) && isset($_GET['new_status']) ) {
            if ( check_admin_referer('ltlb_status_appointment') ) {
                $this->appointment_repository->update_status( intval($_GET['id']), sanitize_text_field($_GET['new_status']) );
                LTLB_Notices::add( __('Status updated.', 'ltl-bookings'), 'success' );
                wp_safe_redirect( admin_url('admin.php?page=ltlb_appointments') );
                exit;
            }
        }

        // Filter params
        $filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $filter_date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_service = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

        // Build query args
        $args = [];
        if ( $filter_date_from ) $args['date_from'] = $filter_date_from;
        if ( $filter_date_to ) $args['date_to'] = $filter_date_to;
        if ( $filter_status ) $args['status'] = $filter_status;
        if ( $filter_service ) $args['service_id'] = $filter_service;

        $appointments = $this->appointment_repository->get_all( $args );
        $services = $this->service_repository->get_all();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Appointments', 'ltl-bookings'); ?></h1>
            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_calendar') ); ?>" class="page-title-action"><?php echo esc_html__('View Calendar', 'ltl-bookings'); ?></a>
            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_appointments&action=export_csv') ); ?>" class="page-title-action"><?php echo esc_html__('Export CSV', 'ltl-bookings'); ?></a>
            <hr class="wp-header-end">

            <?php // Notices rendered via hook ?>

            <div class="ltlb-card" style="margin-top:20px; padding:15px;">
                <form method="get">
                    <input type="hidden" name="page" value="ltlb_appointments" />
                    <div class="tablenav top" style="height:auto;">
                        <div class="alignleft actions">
                            <input type="date" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>" placeholder="<?php echo esc_attr__('From Date', 'ltl-bookings'); ?>">
                            <input type="date" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>" placeholder="<?php echo esc_attr__('To Date', 'ltl-bookings'); ?>">
                            
                            <select name="status">
                                <option value=""><?php echo esc_html__('All Statuses', 'ltl-bookings'); ?></option>
                                <option value="confirmed" <?php selected($filter_status, 'confirmed'); ?>><?php echo esc_html__('Confirmed', 'ltl-bookings'); ?></option>
                                <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php echo esc_html__('Pending', 'ltl-bookings'); ?></option>
                                <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>><?php echo esc_html__('Cancelled', 'ltl-bookings'); ?></option>
                            </select>

                            <select name="service_id">
                                <option value="0"><?php echo esc_html__('All Services', 'ltl-bookings'); ?></option>
                                <?php foreach ($services as $s): ?>
                                    <option value="<?php echo esc_attr($s['id']); ?>" <?php selected($filter_service, $s['id']); ?>><?php echo esc_html($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <input type="submit" class="button" value="<?php echo esc_attr__('Filter', 'ltl-bookings'); ?>">
                            <?php if ( ! empty($args) ) : ?>
                                <a href="<?php echo admin_url('admin.php?page=ltlb_appointments'); ?>" class="button"><?php echo esc_html__('Reset', 'ltl-bookings'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <?php if ( empty($appointments) ) : ?>
                    <p><?php echo esc_html__('No appointments found.', 'ltl-bookings'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('ID', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Date & Time', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Customer', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Service', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Resource', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $appointments as $appt ): 
                                $cust = $this->customer_repository->get_by_id( $appt['customer_id'] );
                                $svc = $this->service_repository->get_by_id( $appt['service_id'] );
                                $res = $this->resource_repository->get_by_id( $appt['resource_id'] );
                                $start_ts = strtotime( $appt['start_time'] );
                                $end_ts = strtotime( $appt['end_time'] );
                                ?>
                                <tr>
                                    <td>#<?php echo intval($appt['id']); ?></td>
                                    <td>
                                        <?php echo date_i18n( get_option('date_format'), $start_ts ); ?> <br>
                                        <small><?php echo date_i18n( get_option('time_format'), $start_ts ); ?> - <?php echo date_i18n( get_option('time_format'), $end_ts ); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($cust) {
                                            echo '<strong>' . esc_html($cust['name']) . '</strong><br>';
                                            echo '<a href="mailto:' . esc_attr($cust['email']) . '">' . esc_html($cust['email']) . '</a><br>';
                                            echo esc_html($cust['phone']);
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $svc ? esc_html($svc['name']) : '—'; ?></td>
                                    <td><?php echo $res ? esc_html($res['name']) : '—'; ?></td>
                                    <td>
                                        <?php
                                        $status_class = 'status-pending';
                                        if ( $appt['status'] === 'confirmed' ) $status_class = 'status-active';
                                        if ( $appt['status'] === 'cancelled' ) $status_class = 'status-inactive';
                                        ?>
                                        <span class="ltlb-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html( ucfirst($appt['status']) ); ?></span>
                                    </td>
                                    <td>
                                        <?php if ( $appt['status'] !== 'cancelled' ) : ?>
                                            <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=ltlb_appointments&action=status&new_status=cancelled&id='.$appt['id']), 'ltlb_status_appointment' ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js__('Cancel this appointment?', 'ltl-bookings'); ?>');"><?php echo esc_html__('Cancel', 'ltl-bookings'); ?></a>
                                        <?php endif; ?>
                                        <?php if ( $appt['status'] === 'pending' ) : ?>
                                            <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=ltlb_appointments&action=status&new_status=confirmed&id='.$appt['id']), 'ltlb_status_appointment' ); ?>" class="button button-small button-primary"><?php echo esc_html__('Confirm', 'ltl-bookings'); ?></a>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=ltlb_appointments&action=delete&id='.$appt['id']), 'ltlb_delete_appointment' ); ?>" class="button button-small" style="color:#a00;" onclick="return confirm('<?php echo esc_js__('Permanently delete?', 'ltl-bookings'); ?>');"><?php echo esc_html__('Delete', 'ltl-bookings'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function export_csv(): void {
        $filename = 'appointments-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Start', 'End', 'Customer Name', 'Customer Email', 'Customer Phone', 'Service', 'Resource', 'Status']);

        $appointments = $this->appointment_repository->get_all();
        foreach ($appointments as $appt) {
            $cust = $this->customer_repository->get_by_id( $appt['customer_id'] );
            $svc = $this->service_repository->get_by_id( $appt['service_id'] );
            $res = $this->resource_repository->get_by_id( $appt['resource_id'] );

            fputcsv($output, [
                $appt['id'],
                $appt['start_time'],
                $appt['end_time'],
                $cust ? $cust['name'] : '',
                $cust ? $cust['email'] : '',
                $cust ? $cust['phone'] : '',
                $svc ? $svc['name'] : '',
                $res ? $res['name'] : '',
                $appt['status']
            ]);
        }
        fclose($output);
        exit;
    }
}

