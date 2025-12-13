<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_StaffProfile {
    private $hours_repo;
    private $exceptions_repo;

    public function __construct() {
        $this->hours_repo = new StaffHoursRepository();
        $this->exceptions_repo = new StaffExceptionsRepository();

        add_action('show_user_profile', [ $this, 'render_profile_fields' ]);
        add_action('edit_user_profile', [ $this, 'render_profile_fields' ]);
        add_action('personal_options_update', [ $this, 'save_profile_fields' ]);
        add_action('edit_user_profile_update', [ $this, 'save_profile_fields' ]);
    }

    public function render_profile_fields( $user ) {
        if ( ! current_user_can('edit_user', $user->ID) ) return;

        // Only show for users with ltlb_staff role
        if ( ! in_array('ltlb_staff', (array) $user->roles, true) ) return;

        $weekly = $this->hours_repo->get_weekly( $user->ID );
        $by_weekday = [];
        foreach ( $weekly as $w ) {
            $by_weekday[ intval($w['weekday']) ] = $w;
        }

        ?>
        <h2><?php echo esc_html__('LazyBookings Working Hours', 'ltl-bookings'); ?></h2>
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
                        <label><?php echo esc_html__('Active', 'ltl-bookings'); ?> <input type="checkbox" name="ltlb_hours[<?php echo $d; ?>][is_active]" value="1" <?php checked( $active ); ?>></label>
                        <br>
                        <label><?php echo esc_html__('Start', 'ltl-bookings'); ?> <input type="time" name="ltlb_hours[<?php echo $d; ?>][start_time]" value="<?php echo esc_attr( substr($start,0,5) ); ?>"></label>
                        <label style="margin-left:1rem"><?php echo esc_html__('End', 'ltl-bookings'); ?> <input type="time" name="ltlb_hours[<?php echo $d; ?>][end_time]" value="<?php echo esc_attr( substr($end,0,5) ); ?>"></label>
                    </td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>

        <h3><?php echo esc_html__('LazyBookings Exceptions (next 30 days)', 'ltl-bookings'); ?></h3>
        <?php
        $from = date('Y-m-d');
        $to = date('Y-m-d', strtotime('+30 days'));
        $exceptions = $this->exceptions_repo->get_range( $user->ID, $from, $to );
        if ( empty( $exceptions ) ) {
            echo '<p>' . esc_html__('No exceptions in the next 30 days.', 'ltl-bookings') . '</p>';
        } else {
            echo '<ul>';
            foreach ( $exceptions as $e ) {
                printf('<li>%s — %s (%s)</li>', esc_html($e['date']), esc_html( ! empty($e['is_off_day']) ? 'Off' : ($e['start_time'] . '–' . $e['end_time']) ), esc_html($e['note'] ?? '') );
            }
            echo '</ul>';
        }
    }

    public function save_profile_fields( $user_id ) {
        if ( ! current_user_can('edit_user', $user_id) ) return;

        if ( empty( $_POST['ltlb_hours'] ) || ! is_array( $_POST['ltlb_hours'] ) ) return;

        $hours = $_POST['ltlb_hours'];
        $rows = [];
        foreach ( $hours as $weekday => $h ) {
            $rows[] = [
                'weekday' => intval($weekday),
                'start_time' => LTLB_Sanitizer::text( $h['start_time'] ?? '' ),
                'end_time' => LTLB_Sanitizer::text( $h['end_time'] ?? '' ),
                'is_active' => isset($h['is_active']) ? 1 : 0
            ];
        }

        $this->hours_repo->save_weekly( $user_id, $rows );
    }
}

// instantiate when plugin loads (Plugin.php will require file)
