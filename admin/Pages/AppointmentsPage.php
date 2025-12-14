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
            if ( ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'ltlb_export_csv') ) {
                wp_die( esc_html__('Security check failed', 'ltl-bookings') );
            }
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

        // Build query args (repository expects: from/to/status/service_id)
        $args = [];
        if ( $filter_date_from ) $args['from'] = $filter_date_from . ' 00:00:00';
        if ( $filter_date_to ) $args['to'] = $filter_date_to . ' 23:59:59';
        if ( $filter_status ) $args['status'] = $filter_status;
        if ( $filter_service ) $args['service_id'] = $filter_service;

        $appointments = $this->appointment_repository->get_all( $args );
        $services = $this->service_repository->get_all();

        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_appointments'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__('Appointments', 'ltl-bookings'); ?></h1>
            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_calendar') ); ?>" class="page-title-action"><?php echo esc_html__('View Calendar', 'ltl-bookings'); ?></a>
            <a href="<?php echo esc_attr( wp_nonce_url( admin_url('admin.php?page=ltlb_appointments&action=export_csv'), 'ltlb_export_csv' ) ); ?>" class="page-title-action"><?php echo esc_html__('Export CSV', 'ltl-bookings'); ?></a>
            <hr class="wp-header-end">

            <?php // Notices rendered via hook ?>

            <div class="ltlb-card" style="margin-top:20px; padding:15px;">
                <form method="get">
                    <input type="hidden" name="page" value="ltlb_appointments" />
                    <div class="tablenav top" style="height:auto;">
                        <div class="alignleft actions">
                            <input type="date" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>" placeholder="<?php echo esc_attr__('From Date', 'ltl-bookings'); ?>" aria-label="<?php echo esc_attr__('Filter from date', 'ltl-bookings'); ?>">
                            <input type="date" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>" placeholder="<?php echo esc_attr__('To Date', 'ltl-bookings'); ?>" aria-label="<?php echo esc_attr__('Filter to date', 'ltl-bookings'); ?>">

                            <select name="status" aria-label="<?php echo esc_attr__('Filter by status', 'ltl-bookings'); ?>">
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
                    <p>
                        <?php
                        if ( ! empty( $args ) ) {
                            echo esc_html__( 'No appointments found for the current filters.', 'ltl-bookings' );
                        } else {
                            echo esc_html__( 'No appointments yet. Once someone books via the booking form, they will appear here.', 'ltl-bookings' );
                        }
                        ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_calendar') ); ?>" class="button button-primary"><?php echo esc_html__( 'View Calendar', 'ltl-bookings' ); ?></a>
                        <?php if ( ! empty( $args ) ) : ?>
                            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_appointments') ); ?>" class="button"><?php echo esc_html__( 'Reset', 'ltl-bookings' ); ?></a>
                        <?php endif; ?>
                    </p>
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
                                $cust = isset($appt['customer_id']) ? $this->customer_repository->get_by_id( (int) $appt['customer_id'] ) : null;
                                $svc = isset($appt['service_id']) ? $this->service_repository->get_by_id( (int) $appt['service_id'] ) : null;
                                $res = null;
                                if ( isset($appt['resource_id']) && $appt['resource_id'] !== null && $appt['resource_id'] !== '' ) {
                                    $res = $this->resource_repository->get_by_id( (int) $appt['resource_id'] );
                                }

                                $start_raw = $appt['start_at'] ?? '';
                                $end_raw = $appt['end_at'] ?? '';
                                $start_ts = $start_raw ? strtotime( $start_raw ) : 0;
                                $end_ts = $end_raw ? strtotime( $end_raw ) : 0;

                                $cust_name = '—';
                                $cust_email = '';
                                $cust_phone = '';
                                if ( is_array($cust) ) {
                                    $first = trim( (string)($cust['first_name'] ?? '') );
                                    $last = trim( (string)($cust['last_name'] ?? '') );
                                    $full = trim( $first . ' ' . $last );
                                    $cust_name = $full !== '' ? $full : (string)($cust['name'] ?? '—');
                                    $cust_email = (string)($cust['email'] ?? '');
                                    $cust_phone = (string)($cust['phone'] ?? '');
                                }
                                ?>
                                <tr>
                                    <td>#<?php echo intval($appt['id']); ?></td>
                                    <td>
                                        <?php if ( $start_ts ) : ?>
                                            <?php echo date_i18n( get_option('date_format'), $start_ts ); ?> <br>
                                            <small>
                                                <?php echo date_i18n( get_option('time_format'), $start_ts ); ?>
                                                <?php if ( $end_ts ) : ?> - <?php echo date_i18n( get_option('time_format'), $end_ts ); ?><?php endif; ?>
                                            </small>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        echo '<strong>' . esc_html($cust_name) . '</strong><br>';
                                        if ( $cust_email !== '' ) {
                                            echo '<a href="mailto:' . esc_attr($cust_email) . '">' . esc_html($cust_email) . '</a><br>';
                                        }
                                        if ( $cust_phone !== '' ) {
                                            echo esc_html($cust_phone);
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

                                        $status_label = $appt['status'];
                                        if ( $appt['status'] === 'confirmed' ) $status_label = __( 'Confirmed', 'ltl-bookings' );
                                        if ( $appt['status'] === 'pending' ) $status_label = __( 'Pending', 'ltl-bookings' );
                                        if ( $appt['status'] === 'cancelled' ) $status_label = __( 'Cancelled', 'ltl-bookings' );
                                        ?>
                                        <span class="ltlb-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html( $status_label ); ?></span>
                                    </td>
                                    <td>
                                        <?php if ( $appt['status'] !== 'cancelled' ) : ?>
                                            <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=ltlb_appointments&action=status&new_status=cancelled&id='.$appt['id']), 'ltlb_status_appointment' ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Cancel this appointment?', 'ltl-bookings' ) ); ?>');"><?php echo esc_html__('Cancel', 'ltl-bookings'); ?></a>
                                        <?php endif; ?>
                                        <?php if ( $appt['status'] === 'pending' ) : ?>
                                            <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=ltlb_appointments&action=status&new_status=confirmed&id='.$appt['id']), 'ltlb_status_appointment' ); ?>" class="button button-small button-primary"><?php echo esc_html__('Confirm', 'ltl-bookings'); ?></a>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=ltlb_appointments&action=delete&id='.$appt['id']), 'ltlb_delete_appointment' ); ?>" class="button button-small" style="color:#a00;" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete?', 'ltl-bookings' ) ); ?>');"><?php echo esc_html__('Delete', 'ltl-bookings'); ?></a>
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
        fputcsv($output, [
            __( 'ID', 'ltl-bookings' ),
            __( 'Start', 'ltl-bookings' ),
            __( 'End', 'ltl-bookings' ),
            __( 'Customer Name', 'ltl-bookings' ),
            __( 'Customer Email', 'ltl-bookings' ),
            __( 'Customer Phone', 'ltl-bookings' ),
            __( 'Service', 'ltl-bookings' ),
            __( 'Resource', 'ltl-bookings' ),
            __( 'Status', 'ltl-bookings' ),
        ]);

        $appointments = $this->appointment_repository->get_all();
        foreach ($appointments as $appt) {
            $cust = isset($appt['customer_id']) ? $this->customer_repository->get_by_id( (int) $appt['customer_id'] ) : null;
            $svc = isset($appt['service_id']) ? $this->service_repository->get_by_id( (int) $appt['service_id'] ) : null;
            $res = null;
            if ( isset($appt['resource_id']) && $appt['resource_id'] !== null && $appt['resource_id'] !== '' ) {
                $res = $this->resource_repository->get_by_id( (int) $appt['resource_id'] );
            }

            $cust_name = '';
            $cust_email = '';
            $cust_phone = '';
            if ( is_array($cust) ) {
                $first = trim( (string)($cust['first_name'] ?? '') );
                $last = trim( (string)($cust['last_name'] ?? '') );
                $full = trim( $first . ' ' . $last );
                $cust_name = $full !== '' ? $full : (string)($cust['name'] ?? '');
                $cust_email = (string)($cust['email'] ?? '');
                $cust_phone = (string)($cust['phone'] ?? '');
            }

            fputcsv($output, [
                $appt['id'],
                $appt['start_at'] ?? '',
                $appt['end_at'] ?? '',
                $cust_name,
                $cust_email,
                $cust_phone,
                $svc ? $svc['name'] : '',
                $res ? $res['name'] : '',
                $appt['status']
            ]);
        }
        fclose($output);
        exit;
    }
}

