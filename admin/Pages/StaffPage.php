<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_StaffPage {
    private $hours_repo;
    private $exceptions_repo;

    public function __construct() {
        $this->hours_repo = new StaffHoursRepository();
        $this->exceptions_repo = new StaffExceptionsRepository();
    }

    public function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('You do not have permission to view this page.', 'ltl-bookings') );
        }

        // Handle saving weekly hours
        if ( isset( $_POST['ltlb_staff_save'] ) ) {
            if ( ! check_admin_referer( 'ltlb_staff_save_action', 'ltlb_staff_nonce' ) ) {
                wp_die( esc_html__('Security check failed', 'ltl-bookings') );
            }

            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $hours = $_POST['hours'] ?? [];

            // Normalize and sanitize input
            $rows = [];
            foreach ( $hours as $weekday => $h ) {
                $rows[] = [
                    'weekday' => intval($weekday),
                    'start_time' => LTLB_Sanitizer::text( $h['start_time'] ?? '' ),
                    'end_time' => LTLB_Sanitizer::text( $h['end_time'] ?? '' ),
                    'is_active' => isset($h['is_active']) ? 1 : 0
                ];
            }

            $ok = $this->hours_repo->save_weekly( $user_id, $rows );
            if ( $ok ) {
                LTLB_Notices::add( __( 'Working hours saved.', 'ltl-bookings' ), 'success' );
            } else {
                LTLB_Notices::add( __( 'Failed to save working hours. Please check your input and try again.', 'ltl-bookings' ), 'error' );
            }

            wp_safe_redirect( admin_url('admin.php?page=ltlb_staff&user_id=' . intval($user_id)) );
            exit;
        }

        // Handle creating exception
        if ( isset($_POST['ltlb_exception_create']) ) {
            if ( ! check_admin_referer( 'ltlb_exception_create_action', 'ltlb_exception_nonce' ) ) {
                wp_die( esc_html__('Security check failed', 'ltl-bookings') );
            }

            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $date = isset($_POST['date']) ? sanitize_text_field( $_POST['date'] ) : '';
            $is_off = isset($_POST['is_off_day']) ? 1 : 0;
            $start = isset($_POST['start_time']) ? LTLB_Sanitizer::text( $_POST['start_time'] ) : null;
            $end = isset($_POST['end_time']) ? LTLB_Sanitizer::text( $_POST['end_time'] ) : null;
            $note = isset($_POST['note']) ? LTLB_Sanitizer::text( $_POST['note'] ) : null;

            $res = $this->exceptions_repo->create( $user_id, $date, $is_off, $start, $end, $note );
            if ( $res ) {
                LTLB_Notices::add( __( 'Exception created.', 'ltl-bookings' ), 'success' );
            } else {
                LTLB_Notices::add( __( 'Could not create exception.', 'ltl-bookings' ), 'error' );
            }

            wp_safe_redirect( admin_url('admin.php?page=ltlb_staff&user_id=' . intval($user_id)) );
            exit;
        }

        // Handle deleting exception
        if ( isset($_POST['ltlb_exception_delete']) ) {
            if ( ! check_admin_referer( 'ltlb_exception_delete_action', 'ltlb_exception_delete_nonce' ) ) {
                wp_die( esc_html__('Security check failed', 'ltl-bookings') );
            }
            $id = isset($_POST['exception_id']) ? intval($_POST['exception_id']) : 0;
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $ok = $this->exceptions_repo->delete( $id );
            if ( $ok ) {
                LTLB_Notices::add( __( 'Exception deleted.', 'ltl-bookings' ), 'success' );
            } else {
                LTLB_Notices::add( __( 'Could not delete exception.', 'ltl-bookings' ), 'error' );
            }
            wp_safe_redirect( admin_url('admin.php?page=ltlb_staff&user_id=' . intval($user_id)) );
            exit;
        }

        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        // List staff users
        $staff_users = get_users( ['role' => 'ltlb_staff'] );

        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_staff'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__('Staff', 'ltl-bookings'); ?></h1>
            <hr class="wp-header-end">

            <div class="ltlb-card" style="margin-top:20px;">
                <h2><?php echo esc_html__('Team', 'ltl-bookings'); ?></h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr><th scope="col"><?php echo esc_html__('Name', 'ltl-bookings'); ?></th><th scope="col"><?php echo esc_html__('Email', 'ltl-bookings'); ?></th><th scope="col"><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th></tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $staff_users ) ) : ?>
                            <tr>
                                <td colspan="3">
                                    <p style="margin: 0 0 8px;">
                                        <?php echo esc_html__( 'No staff members found.', 'ltl-bookings' ); ?>
                                    </p>
                                    <a class="button button-primary" href="<?php echo esc_attr( admin_url( 'user-new.php?role=ltlb_staff' ) ); ?>">
                                        <?php echo esc_html__( 'Add staff member', 'ltl-bookings' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $staff_users as $u ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $u->display_name ); ?></td>
                                    <td><?php echo esc_html( $u->user_email ); ?></td>
                                    <td><a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_staff&user_id=' . intval($u->ID)) ); ?>" class="button button-secondary"><?php echo esc_html__('Edit working hours', 'ltl-bookings'); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $user_id > 0 ) :
                $weekly = $this->hours_repo->get_weekly( $user_id );
                $by_weekday = [];
                foreach ( $weekly as $w ) {
                    $by_weekday[ intval($w['weekday']) ] = $w;
                }
                ?>
                
                <div class="ltlb-card" style="margin-top:20px;">
                    <h2><?php echo esc_html__('Edit working hours', 'ltl-bookings'); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field( 'ltlb_staff_save_action', 'ltlb_staff_nonce' ); ?>
                        <input type="hidden" name="ltlb_staff_save" value="1" />
                        <input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>" />

                        <table class="form-table">
                            <tbody>
                                <?php for ( $d = 0; $d < 7; $d++ ) :
                                    $w = $by_weekday[$d] ?? null;
                                    $start = $w['start_time'] ?? '09:00:00';
                                    $end = $w['end_time'] ?? '17:00:00';
                                    $active = ! empty( $w['is_active'] );
                                    $label = date_i18n( 'l', strtotime("Sunday +{$d} days") );
                                    ?>
                                    <tr>
                                        <th><?php echo esc_html( $label ); ?></th>
                                        <td>
                                            <label style="display:inline-block; width: 80px;"><?php echo esc_html__('Active', 'ltl-bookings'); ?> <input type="checkbox" name="hours[<?php echo $d; ?>][is_active]" value="1" <?php checked( $active ); ?>></label>
                                            
                                            <label style="margin-left:1rem"><?php echo esc_html__('Start', 'ltl-bookings'); ?> <input type="time" name="hours[<?php echo $d; ?>][start_time]" value="<?php echo esc_attr( substr($start,0,5) ); ?>"></label>
                                            <label style="margin-left:1rem"><?php echo esc_html__('End', 'ltl-bookings'); ?> <input type="time" name="hours[<?php echo $d; ?>][end_time]" value="<?php echo esc_attr( substr($end,0,5) ); ?>"></label>
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>

                        <p class="submit">
                            <?php submit_button( esc_html__('Save working hours', 'ltl-bookings'), 'primary', 'submit', false ); ?>
                        </p>
                    </form>
                </div>
                
                <div class="ltlb-card" style="margin-top:20px;">
                    <h2><?php echo esc_html__('Exceptions', 'ltl-bookings'); ?></h2>

                    <?php
                    $from = date('Y-m-d', strtotime('-365 days'));
                    $to = date('Y-m-d', strtotime('+365 days'));
                    $exceptions = $this->exceptions_repo->get_range( $user_id, $from, $to );
                    ?>

                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr><th scope="col"><?php echo esc_html__('Date', 'ltl-bookings'); ?></th><th scope="col"><?php echo esc_html__('Off day', 'ltl-bookings'); ?></th><th scope="col"><?php echo esc_html__('From', 'ltl-bookings'); ?></th><th scope="col"><?php echo esc_html__('To', 'ltl-bookings'); ?></th><th scope="col"><?php echo esc_html__('Note', 'ltl-bookings'); ?></th><th scope="col"><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th></tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $exceptions ) ) : ?>
                                <tr><td colspan="6"><?php echo esc_html__('No exceptions found.', 'ltl-bookings'); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $exceptions as $e ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $e['date'] ); ?></td>
                                        <td><?php echo esc_html( ! empty($e['is_off_day']) ? __( 'Yes', 'ltl-bookings' ) : __( 'No', 'ltl-bookings' ) ); ?></td>
                                        <td><?php echo esc_html( $e['start_time'] ?? '' ); ?></td>
                                        <td><?php echo esc_html( $e['end_time'] ?? '' ); ?></td>
                                        <td><?php echo esc_html( $e['note'] ?? '' ); ?></td>
                                        <td>
                                            <form method="post" style="display:inline">
                                                <?php wp_nonce_field( 'ltlb_exception_delete_action', 'ltlb_exception_delete_nonce' ); ?>
                                                <input type="hidden" name="ltlb_exception_delete" value="1">
                                                <input type="hidden" name="exception_id" value="<?php echo esc_attr( intval($e['id']) ); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
                                                <button class="button button-secondary" type="submit" onclick="return confirm('<?php echo esc_js(__('Are you sure?', 'ltl-bookings')); ?>');"><?php echo esc_html__('Delete', 'ltl-bookings'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="ltlb-card" style="margin-top:20px;">
                    <h3><?php echo esc_html__('Create exception', 'ltl-bookings'); ?></h3>
                    <form method="post">
                        <?php wp_nonce_field( 'ltlb_exception_create_action', 'ltlb_exception_nonce' ); ?>
                        <input type="hidden" name="ltlb_exception_create" value="1">
                        <input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th><?php echo esc_html__('Date', 'ltl-bookings'); ?></th>
                                    <td><input type="date" name="date" required></td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Off day', 'ltl-bookings'); ?></th>
                                    <td><label><input type="checkbox" name="is_off_day" value="1"> <?php echo esc_html__('Yes, off', 'ltl-bookings'); ?></label></td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Start time', 'ltl-bookings'); ?></th>
                                    <td><input type="time" name="start_time"> <span class="description"><?php echo esc_html__('Leave empty if off', 'ltl-bookings'); ?></span></td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('End time', 'ltl-bookings'); ?></th>
                                    <td><input type="time" name="end_time"></td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Note', 'ltl-bookings'); ?></th>
                                    <td><input type="text" name="note" class="regular-text"></td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="submit">
                            <?php submit_button( esc_html__('Create exception', 'ltl-bookings'), 'secondary', 'submit', false ); ?>
                        </p>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
