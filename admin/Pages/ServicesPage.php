<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_ServicesPage {

    private $service_repository;
    private $resource_repository;
    private $service_resources_repository;

    public function __construct() {
        $this->service_repository = new LTLB_ServiceRepository();
        $this->resource_repository = new LTLB_ResourceRepository();
        $this->service_resources_repository = new LTLB_ServiceResourcesRepository();
    }

    public function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('You do not have permission to view this page.', 'ltl-bookings') );
        }
        
        // Context-aware labels
        $settings = get_option('lazy_settings', []);
        $is_hotel = isset($settings['template_mode']) && $settings['template_mode'] === 'hotel';
		$label_singular = $is_hotel ? __('Room Type', 'ltl-bookings') : __('Service', 'ltl-bookings');
		$label_plural = $is_hotel ? __('Room Types', 'ltl-bookings') : __('Services', 'ltl-bookings');
        // Handle form submissions
        if ( isset( $_POST['ltlb_service_save'] ) ) {
            if ( ! check_admin_referer( 'ltlb_service_save_action', 'ltlb_service_nonce' ) ) {
                wp_die( esc_html__('Security check failed', 'ltl-bookings') );
            }

            $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
            $data = [];
            $data['name'] = LTLB_Sanitizer::text( $_POST['name'] ?? '' );
            $data['description'] = isset( $_POST['description'] ) ? wp_kses_post( $_POST['description'] ) : null;
            $data['duration_min'] = LTLB_Sanitizer::int( $_POST['duration_min'] ?? 60 );
            $data['buffer_before_min'] = LTLB_Sanitizer::int( $_POST['buffer_before_min'] ?? 0 );
            $data['buffer_after_min'] = LTLB_Sanitizer::int( $_POST['buffer_after_min'] ?? 0 );
            $data['price_cents'] = LTLB_Sanitizer::money_cents( $_POST['price_eur'] ?? '' );
            $data['currency'] = LTLB_Sanitizer::text( $_POST['currency'] ?? 'EUR' );
            $data['is_active'] = isset( $_POST['is_active'] ) ? 1 : 0;
            $data['is_group'] = isset( $_POST['is_group'] ) ? 1 : 0;
            $data['max_seats_per_booking'] = LTLB_Sanitizer::int( $_POST['max_seats_per_booking'] ?? 1 );

            // Optional availability limits (per service)
            $availability_mode = isset($_POST['availability_mode']) ? sanitize_key( wp_unslash($_POST['availability_mode']) ) : 'window';
            if ( ! in_array( $availability_mode, [ 'window', 'fixed' ], true ) ) {
                $availability_mode = 'window';
            }
            $data['availability_mode'] = $availability_mode;

            $days = isset( $_POST['available_weekdays'] ) ? (array) $_POST['available_weekdays'] : [];
            $days = array_values(array_unique(array_map('intval', $days)));
            $days = array_filter($days, function($d){ return $d >= 0 && $d <= 6; });
            sort($days);
            $data['available_weekdays'] = ! empty($days) ? implode(',', $days) : '';

            $start_time = isset($_POST['available_start_time']) ? sanitize_text_field( wp_unslash($_POST['available_start_time']) ) : '';
            $end_time = isset($_POST['available_end_time']) ? sanitize_text_field( wp_unslash($_POST['available_end_time']) ) : '';
            $time_ok = function($t){ return is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t); };
            $data['available_start_time'] = $time_ok($start_time) ? ($start_time . ':00') : '';
            $data['available_end_time'] = $time_ok($end_time) ? ($end_time . ':00') : '';

            // Fixed weekly slots (optional)
            $fixed_weekdays = isset($_POST['fixed_slot_weekday']) ? (array) $_POST['fixed_slot_weekday'] : [];
            $fixed_times = isset($_POST['fixed_slot_time']) ? (array) $_POST['fixed_slot_time'] : [];
            $fixed_slots = [];
            $count = min(count($fixed_weekdays), count($fixed_times));
            for ( $i = 0; $i < $count; $i++ ) {
                $w = intval($fixed_weekdays[$i]);
                $t = sanitize_text_field( wp_unslash( $fixed_times[$i] ) );
                if ( $w < 0 || $w > 6 ) continue;
                if ( ! $time_ok($t) ) continue;
                $fixed_slots[] = [ 'weekday' => $w, 'time' => $t ];
            }
            $data['fixed_weekly_slots'] = ! empty($fixed_slots) ? wp_json_encode($fixed_slots) : '';

            if ( $id > 0 ) {
                $ok = $this->service_repository->update( $id, $data );
                $saved_id = $id;
            } else {
                $created = $this->service_repository->create( $data );
                $ok = $created !== false;
                $saved_id = $created ?: 0;
            }

            // save service -> resource mappings
            $resource_ids = isset( $_POST['resource_ids'] ) ? array_map( 'intval', (array) $_POST['resource_ids'] ) : [];
            if ( $saved_id > 0 ) {
                $this->service_resources_repository->set_resources_for_service( $saved_id, $resource_ids );
            }

            $redirect = admin_url( 'admin.php?page=ltlb_services' );
            if ( $ok ) {
                LTLB_Notices::add( __( 'Saved.', 'ltl-bookings' ), 'success' );
            } else {
                LTLB_Notices::add( __( 'An error occurred.', 'ltl-bookings' ), 'error' );
            }
            wp_safe_redirect( $redirect );
            exit;
        }

        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $editing = false;
        $service = null;
        if ( $action === 'edit' && ! empty( $_GET['id'] ) ) {
            $service = $this->service_repository->get_by_id( intval( $_GET['id'] ) );
            if ( $service ) $editing = true;
        }

        $services = $this->service_repository->get_all();
        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_services'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html($label_plural); ?></h1>
            <?php if ( $action !== 'add' && ! $editing ) : ?>
                <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_services&action=add') ); ?>" class="page-title-action"><?php echo esc_html__('Add New', 'ltl-bookings'); ?></a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php // Notices are rendered via LTLB_Notices::render() hooked to admin_notices ?>

            <?php if ( $action === 'add' || $editing ) :
                $form_id = $editing ? intval( $service['id'] ) : 0;
                $name = $editing ? $service['name'] : '';
                $description = $editing ? $service['description'] : '';
                $duration = $editing ? $service['duration_min'] : 60;
                $buffer_before = $editing ? $service['buffer_before_min'] : 0;
                $buffer_after = $editing ? $service['buffer_after_min'] : 0;
                $price = $editing && isset( $service['price_cents'] ) ? number_format( $service['price_cents'] / 100, 2, '.', '' ) : '';
                $currency = $editing ? ( $service['currency'] ?? 'EUR' ) : 'EUR';
                $is_active = $editing ? ( ! empty( $service['is_active'] ) ) : true;
                $is_group = $editing ? ( ! empty( $service['is_group'] ) ) : false;
                $max_seats = $editing ? intval($service['max_seats_per_booking'] ?? 1) : 1;

                $available_weekdays = $editing ? (string)($service['available_weekdays'] ?? '') : '';
                $available_weekdays_arr = array_map('intval', array_filter(preg_split('/\s*,\s*/', $available_weekdays), 'strlen'));
                $available_start_time = $editing ? (string)($service['available_start_time'] ?? '') : '';
                $available_end_time = $editing ? (string)($service['available_end_time'] ?? '') : '';

                $availability_mode = $editing ? (string)($service['availability_mode'] ?? 'window') : 'window';
                if ( ! in_array($availability_mode, ['window','fixed'], true) ) $availability_mode = 'window';
                $fixed_weekly_slots_raw = $editing ? (string)($service['fixed_weekly_slots'] ?? '') : '';
                $fixed_weekly_slots = [];
                if ( $fixed_weekly_slots_raw !== '' ) {
                    $decoded = json_decode( $fixed_weekly_slots_raw, true );
                    if ( is_array($decoded) ) {
                        foreach ( $decoded as $row ) {
                            if ( ! is_array($row) ) continue;
                            if ( ! isset($row['weekday'], $row['time']) ) continue;
                            $w = intval($row['weekday']);
                            $t = (string)$row['time'];
                            if ( $w < 0 || $w > 6 ) continue;
                            if ( ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ) continue;
                            $fixed_weekly_slots[] = [ 'weekday' => $w, 'time' => $t ];
                        }
                    }
                }

                // Normalize TIME to HH:MM for inputs
                if ( preg_match('/^(\d{2}:\d{2})/', $available_start_time, $m1) ) $available_start_time = $m1[1];
                if ( preg_match('/^(\d{2}:\d{2})/', $available_end_time, $m2) ) $available_end_time = $m2[1];

                $weekdays = [
                    1 => __('Mon', 'ltl-bookings'),
                    2 => __('Tue', 'ltl-bookings'),
                    3 => __('Wed', 'ltl-bookings'),
                    4 => __('Thu', 'ltl-bookings'),
                    5 => __('Fri', 'ltl-bookings'),
                    6 => __('Sat', 'ltl-bookings'),
                    0 => __('Sun', 'ltl-bookings'),
                ];
                ?>
                
                <div class="ltlb-card" style="max-width: 800px; margin-top: 20px;">
                    <h2>
                        <?php echo $editing ? sprintf( esc_html__( 'Edit %s', 'ltl-bookings' ), $label_singular ) : sprintf( esc_html__( 'Create %s', 'ltl-bookings' ), $label_singular ); ?>
                    </h2>
                    
                    <form method="post">
                        <?php wp_nonce_field( 'ltlb_service_save_action', 'ltlb_service_nonce' ); ?>
                        <input type="hidden" name="ltlb_service_save" value="1" />
                        <input type="hidden" name="id" value="<?php echo esc_attr( $form_id ); ?>" />

                        <table class="form-table">
                            <tr>
                                <th><label for="name"><?php echo esc_html__('Name', 'ltl-bookings'); ?></label></th>
                                <td><input name="name" type="text" id="name" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required aria-required="true"></td>
                            </tr>
                            <tr>
                                <th><label for="description"><?php echo esc_html__('Description', 'ltl-bookings'); ?></label></th>
                                <td><textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="duration_min"><?php echo esc_html__('Duration (minutes)', 'ltl-bookings'); ?></label></th>
                                <td><input name="duration_min" type="number" id="duration_min" value="<?php echo esc_attr( $duration ); ?>" class="small-text" min="1" required aria-required="true" aria-describedby="duration-desc">
                                <p class="description" id="duration-desc"><?php echo esc_html__('Service duration in minutes', 'ltl-bookings'); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="price_eur"><?php echo esc_html__('Price', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <input name="price_eur" type="number" id="price_eur" value="<?php echo esc_attr( $price ); ?>" class="small-text" step="0.01" min="0">
                                    <select name="currency" style="vertical-align: top;">
                                        <option value="EUR" <?php selected( $currency, 'EUR' ); ?>>EUR</option>
                                        <option value="USD" <?php selected( $currency, 'USD' ); ?>>USD</option>
                                        <option value="GBP" <?php selected( $currency, 'GBP' ); ?>>GBP</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php echo esc_html__('Buffer Time', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <label><input name="buffer_before_min" type="number" value="<?php echo esc_attr( $buffer_before ); ?>" class="small-text" min="0"> <?php echo esc_html__('Before (min)', 'ltl-bookings'); ?></label>
                                    &nbsp;&nbsp;
                                    <label><input name="buffer_after_min" type="number" value="<?php echo esc_attr( $buffer_after ); ?>" class="small-text" min="0"> <?php echo esc_html__('After (min)', 'ltl-bookings'); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php echo esc_html__('Resources', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <?php
                                    $all_resources = $this->resource_repository->get_all();
                                    $assigned_ids = [];
                                    if ( $editing ) {
                                        $assigned_ids = $this->service_resources_repository->get_resources_for_service( $form_id );
                                    }
                                    if ( empty($all_resources) ) {
                                        echo '<p class="description">' . esc_html__('No resources found. Please add resources first.', 'ltl-bookings') . '</p>';
                                    } else {
                                        echo '<div style="max-height:150px; overflow-y:auto; border:1px solid #ddd; padding:10px; border-radius:4px;">';
                                        foreach ( $all_resources as $res ) {
                                            $checked = in_array( intval($res['id']), $assigned_ids ) ? 'checked' : '';
                                            echo '<label style="display:block;margin-bottom:5px;"><input type="checkbox" name="resource_ids[]" value="' . esc_attr($res['id']) . '" ' . $checked . '> ' . esc_html($res['name']) . '</label>';
                                        }
                                        echo '</div>';
                                        echo '<p class="description">' . esc_html__('Select resources that can perform this service.', 'ltl-bookings') . '</p>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="is_active"><?php echo esc_html__('Active', 'ltl-bookings'); ?></label></th>
                                <td><input name="is_active" type="checkbox" id="is_active" value="1" <?php checked( $is_active ); ?>></td>
                            </tr>

                            <?php if ( ! $is_hotel ) : ?>
                            <tr>
                                <th><label><?php echo esc_html__('Availability (optional)', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <p class="description" id="ltlb-availability-mode-desc" style="margin-top:0;">
                                        <?php echo esc_html__('Limit this class to specific days/times. Choose a window (any time inside) or fixed weekly start times (e.g. Fri 18:00). If left empty, global working hours apply.', 'ltl-bookings'); ?>
                                    </p>

                                    <div class="ltlb-choice-tiles-container" style="margin: 10px 0 14px;">
                                        <?php
                                        LTLB_Admin_Component::choice_tile(
                                            'availability_mode',
                                            'window',
                                            __( 'Window', 'ltl-bookings' ),
                                            __( 'Available any time within the allowed hours.', 'ltl-bookings' ),
                                            $availability_mode === 'window'
                                        );
                                        LTLB_Admin_Component::choice_tile(
                                            'availability_mode',
                                            'fixed',
                                            __( 'Fixed', 'ltl-bookings' ),
                                            __( 'Available only at specific weekly time slots.', 'ltl-bookings' ),
                                            $availability_mode === 'fixed'
                                        );
                                        ?>
                                    </div>

                                    <div id="ltlb-fixed-slots" style="border:1px solid #e5e5e5; border-radius:6px; padding:12px; margin-bottom:14px;">
                                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                                            <strong><?php echo esc_html__('Fixed weekly times', 'ltl-bookings'); ?></strong>
                                            <button type="button" class="button" id="ltlb-add-fixed-slot"><?php echo esc_html__('Add time', 'ltl-bookings'); ?></button>
                                        </div>
                                        <p class="description" style="margin:8px 0 10px;">
                                            <?php echo esc_html__('Add one or more weekly start times. Example: Fri 18:00. The customer will only see these times (still respecting staff/global hours and existing bookings).', 'ltl-bookings'); ?>
                                        </p>
                                        <table class="widefat striped" style="max-width:520px;">
                                            <thead>
                                                <tr>
                                                    <th><?php echo esc_html__('Weekday', 'ltl-bookings'); ?></th>
                                                    <th><?php echo esc_html__('Start time', 'ltl-bookings'); ?></th>
                                                    <th><?php echo esc_html__('Remove', 'ltl-bookings'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id="ltlb-fixed-slots-body">
                                                <?php
                                                $render_row = function($w, $t) use ($weekdays) {
                                                    echo '<tr>';
                                                    echo '<td><select name="fixed_slot_weekday[]">';
                                                    foreach ( $weekdays as $idx => $label ) {
                                                        echo '<option value="' . esc_attr($idx) . '" ' . selected(intval($w), intval($idx), false) . '>' . esc_html($label) . '</option>';
                                                    }
                                                    echo '</select></td>';
                                                    echo '<td><input type="time" name="fixed_slot_time[]" value="' . esc_attr($t) . '"></td>';
                                                    echo '<td><button type="button" class="button ltlb-remove-fixed-slot">' . esc_html__('Remove', 'ltl-bookings') . '</button></td>';
                                                    echo '</tr>';
                                                };

                                                if ( empty($fixed_weekly_slots) ) {
                                                    $render_row( 5, '18:00' );
                                                } else {
                                                    foreach ( $fixed_weekly_slots as $row ) {
                                                        $render_row( intval($row['weekday']), (string)$row['time'] );
                                                    }
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div style="display:flex;flex-wrap:wrap;gap:10px; margin-bottom:10px;">
                                        <?php
                                        foreach ( $weekdays as $idx => $label ) {
                                            $checked_day = in_array( intval($idx), $available_weekdays_arr, true ) ? 'checked' : '';
                                            echo '<label style="display:inline-flex;align-items:center;gap:6px;">'
                                                . '<input type="checkbox" name="available_weekdays[]" value="' . esc_attr($idx) . '" ' . $checked_day . '>'
                                                . esc_html($label)
                                                . '</label>';
                                        }
                                        ?>
                                    </div>
                                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                        <label>
                                            <?php echo esc_html__('From', 'ltl-bookings'); ?>
                                            <input type="time" name="available_start_time" value="<?php echo esc_attr($available_start_time); ?>" style="margin-left:6px;">
                                        </label>
                                        <label>
                                            <?php echo esc_html__('To', 'ltl-bookings'); ?>
                                            <input type="time" name="available_end_time" value="<?php echo esc_attr($available_end_time); ?>" style="margin-left:6px;">
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>

                        <?php if ( ! $is_hotel ) : ?>
                        <script>
                        (function(){
                            function qs(sel, root){ return (root || document).querySelector(sel); }
                            function qsa(sel, root){ return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

                            var fixedWrap = qs('#ltlb-fixed-slots');
                            var addBtn = qs('#ltlb-add-fixed-slot');
                            var body = qs('#ltlb-fixed-slots-body');
                            if (!fixedWrap || !addBtn || !body) return;

                            function getMode(){
                                var checked = qs('input[name="availability_mode"]:checked');
                                return checked ? checked.value : 'window';
                            }

                            function updateVisibility(){
                                fixedWrap.style.display = (getMode() === 'fixed') ? 'block' : 'none';
                            }

                            qsa('input[name="availability_mode"]').forEach(function(r){
                                r.addEventListener('change', updateVisibility);
                            });

                            addBtn.addEventListener('click', function(){
                                var tr = document.createElement('tr');
                                tr.innerHTML = body.querySelector('tr') ? body.querySelector('tr').innerHTML : '';
                                if (!tr.innerHTML) return;
                                // reset time value
                                var timeInput = tr.querySelector('input[type="time"]');
                                if (timeInput) timeInput.value = '18:00';
                                body.appendChild(tr);
                            });

                            body.addEventListener('click', function(e){
                                var btn = e.target && e.target.classList && e.target.classList.contains('ltlb-remove-fixed-slot') ? e.target : null;
                                if (!btn) return;
                                var tr = btn.closest('tr');
                                if (!tr) return;
                                if (body.querySelectorAll('tr').length <= 1) {
                                    // keep at least one row for UX; just clear
                                    var time = tr.querySelector('input[type="time"]');
                                    if (time) time.value = '';
                                    return;
                                }
                                tr.remove();
                            });

                            updateVisibility();
                        })();
                        </script>
                        <?php endif; ?>

                        <p class="submit">
                            <?php submit_button( esc_html__('Save', 'ltl-bookings'), 'primary', 'ltlb_service_save', false ); ?>
                            <a href="<?php echo admin_url('admin.php?page=ltlb_services'); ?>" class="button"><?php echo esc_html__('Cancel', 'ltl-bookings'); ?></a>
                        </p>
                    </form>
                </div>

            <?php else : ?>

                <div class="ltlb-card" style="margin-top:20px;">
                    <?php if ( empty($services) ) : ?>
                        <div style="text-align:center; padding:40px;">
                            <p style="font-size:1.2em; color:#666;"><?php echo esc_html__('No services defined yet.', 'ltl-bookings'); ?></p>
                            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_services&action=add') ); ?>" class="button button-primary button-hero"><?php echo esc_html__('Create Your First Service', 'ltl-bookings'); ?></a>
                        </div>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Name', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Duration', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Price', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $services as $s ): ?>
                                    <tr>
                                        <td>
                                            <strong><a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_services&action=edit&id='.$s['id']) ); ?>"><?php echo esc_html( $s['name'] ); ?></a></strong>
                                            <?php if ( ! empty($s['description']) ) : ?>
                                                <p class="description" style="margin:5px 0 0;"><?php echo esc_html( wp_trim_words($s['description'], 10) ); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo intval( $s['duration_min'] ); ?> min</td>
                                        <td>
                                            <?php 
                                            if ( isset($s['price_cents']) ) {
                                                echo number_format( $s['price_cents'] / 100, 2 ) . ' ' . esc_html( $s['currency'] ?? 'EUR' );
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ( ! empty($s['is_active']) ) : ?>
                                                <span class="ltlb-status-badge status-active"><?php echo esc_html__('Active', 'ltl-bookings'); ?></span>
                                            <?php else : ?>
                                                <span class="ltlb-status-badge status-inactive"><?php echo esc_html__('Inactive', 'ltl-bookings'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_services&action=edit&id='.$s['id']) ); ?>" class="button button-small"><?php echo esc_html__('Edit', 'ltl-bookings'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>


        <?php
    }

    private function render_list_table(): void {
        $repo = new LTLB_ServiceRepository();
        
        $per_page = 20;
		$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		$offset = ($current_page - 1) * $per_page;

        $total_services = $repo->get_count();
        $services = $repo->get_all_with_staff_and_resources($per_page, $offset);

        $settings = get_option( 'lazy_settings', [] );
        $admin_mode = is_array( $settings ) && isset( $settings['admin_mode'] ) ? $settings['admin_mode'] : 'appointments';
        $is_hotel = $admin_mode === 'hotel';
        $service_label_plural = $is_hotel ? __( 'Room Types', 'ltl-bookings' ) : __( 'Services', 'ltl-bookings' );
        $service_label_singular = $is_hotel ? __( 'Room Type', 'ltl-bookings' ) : __( 'Service', 'ltl-bookings' );

        $columns = [];
        if($is_hotel) {
            $columns = [
                'name' => $service_label_singular,
                'price' => __( 'Price', 'ltl-bookings' ),
                'resources' => __( 'Rooms', 'ltl-bookings' ),
            ];
        } else {
            $columns = [
                'name' => $service_label_singular,
                'duration' => __( 'Duration', 'ltl-bookings' ),
                'price' => __( 'Price', 'ltl-bookings' ),
                'staff' => __( 'Staff', 'ltl-bookings' ),
                'resources' => __( 'Resources', 'ltl-bookings' ),
            ];
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($service_label_plural); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_services&action=add')); ?>" class="page-title-action"><?php echo esc_html__('Add New', 'ltl-bookings'); ?></a>
            <hr class="wp-header-end">

            <?php LTLB_Admin_Component::card_start('', ['style' => 'margin-top:20px;']); ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <?php foreach($columns as $key => $label): ?>
                                <th scope="col" class="manage-column"><?php echo esc_html($label); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="<?php echo count($columns); ?>">
                                    <?php
                                    LTLB_Admin_Component::empty_state(
                                        sprintf(__('No %s Found', 'ltl-bookings'), $service_label_plural),
                                        sprintf(__('You have not created any %s yet. Click the button to get started.', 'ltl-bookings'), strtolower($service_label_plural)),
                                        sprintf(__('Add New %s', 'ltl-bookings'), $service_label_singular),
                                        admin_url('admin.php?page=ltlb_services&action=add'),
                                        'dashicons-admin-settings-alt'
                                    );
                                    ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td>
                                        <strong><a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_services&action=edit&id=' . $service['id'])); ?>"><?php echo esc_html($service['name']); ?></a></strong>
                                        <div class="row-actions">
                                            <span class="edit"><a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_services&action=edit&id=' . $service['id'])); ?>"><?php echo esc_html__( 'Edit', 'ltl-bookings' ); ?></a> | </span>
                                            <span class="trash"><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=ltlb_services&action=delete&id=' . $service['id']), 'ltlb_delete_service_' . $service['id'], 'ltlb_service_nonce')); ?>" class="text-danger" onclick="return confirm('<?php echo esc_js(__( 'Are you sure you want to delete this item?', 'ltl-bookings' )); ?>');"><?php echo esc_html__( 'Delete', 'ltl-bookings' ); ?></a></span>
                                        </div>
                                    </td>
                                    <?php if(!$is_hotel): ?>
                                        <td><?php echo esc_html($service['duration_min']); ?> min</td>
                                    <?php endif; ?>
                                    <td><?php echo esc_html(number_format($service['price_cents'] / 100, 2) . ' ' . $service['currency']); ?></td>
                                    <?php if(!$is_hotel): ?>
                                        <td><?php echo esc_html($service['staff_name'] ?? __( 'Any', 'ltl-bookings' )); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo esc_html($service['resources'] ? implode(', ', $service['resources']) : ($is_hotel ? __('No rooms assigned', 'ltl-bookings') : __( 'All', 'ltl-bookings' ))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php LTLB_Admin_Component::pagination($total_services, $per_page); ?>
            <?php LTLB_Admin_Component::card_end(); ?>
        </div>
        <script>
        (function(){
            function qs(sel, root){ return (root || document).querySelector(sel); }
            function qsa(sel, root){ return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

            var modeSelect = qs('select[name="admin_mode"]');
            var serviceRows = qsa('.wp-list-table tbody tr');
            var currentMode = modeSelect ? modeSelect.value : 'appointments';

            function toggleColumns(mode) {
                var durationCols = qsa('.column-duration');
                var staffCols = qsa('.column-staff');
                var resourceCols = qsa('.column-resources');

                if (mode === 'appointments') {
                    durationCols.forEach(function(td){ td.style.display = ''; });
                    staffCols.forEach(function(td){ td.style.display = ''; });
                    resourceCols.forEach(function(td){ td.style.display = ''; });
                } else {
                    durationCols.forEach(function(td){ td.style.display = 'none'; });
                    staffCols.forEach(function(td){ td.style.display = 'none'; });
                    resourceCols.forEach(function(td){ td.style.display = 'none'; });
                }
            }

            toggleColumns(currentMode);

            if (modeSelect) {
                modeSelect.addEventListener('change', function() {
                    currentMode = this.value;
                    toggleColumns(currentMode);
                });
            }
        })();
        </script>
        <?php
    }

    private function render_add_edit_form(array $service = null, array $all_staff = [], array $all_resources = []): void {
        $is_edit = !is_null($service);
        $nonce_action = $is_edit ? 'ltlb_edit_service_' . $service['id'] : 'ltlb_add_service';
        $nonce_name = 'ltlb_service_nonce';

        $name = $service['name'] ?? '';
        $description = $service['description'] ?? '';
        $duration_min = $service['duration_min'] ?? 60;
        $price_cents = $service['price_cents'] ?? 0;
        $currency = $service['currency'] ?? 'EUR';
        $is_active = !empty($service['is_active']);
        $is_group = !empty($service['is_group']);
        $max_seats_per_booking = $service['max_seats_per_booking'] ?? 1;

        $available_weekdays = $service['available_weekdays'] ?? '';
        $available_start_time = $service['available_start_time'] ?? '';
        $available_end_time = $service['available_end_time'] ?? '';
        $fixed_weekly_slots_json = $service['fixed_weekly_slots'] ?? '[]';
        $availability_mode = $service['availability_mode'] ?? 'window';

        $available_weekdays_arr = !empty($available_weekdays) ? explode(',', $available_weekdays) : [];
        $fixed_weekly_slots = json_decode($fixed_weekly_slots_json, true);

        $repo = new LTLB_ServiceRepository();
        $selected_resources = $is_edit ? $repo->get_assigned_resource_ids($service['id']) : [];
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? esc_html__('Edit ', 'ltl-bookings') . esc_html($service_label_singular) : esc_html__('Add New ', 'ltl-bookings') . esc_html($service_label_singular); ?></h1>

            <?php LTLB_Admin_Component::card_start('', ['class' => 'ltlb-wizard-form']); ?>
                <form method="post">
                    <?php 
                    $steps = [__('General', 'ltl-bookings'), __('Availability', 'ltl-bookings'), __('Resources', 'ltl-bookings')];
                    LTLB_Admin_Component::wizard_steps($steps, 1); 
                    ?>

                    <?php wp_nonce_field($nonce_action, $nonce_name); ?>
                    <input type="hidden" name="service_id" value="<?php echo esc_attr($service['id'] ?? 0); ?>">

                    <?php LTLB_Admin_Component::wizard_step_start(1); ?>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="name"><?php echo esc_html($service_label_singular . ' Name'); ?></label></th>
                                    <td><input name="name" type="text" id="name" value="<?php echo esc_attr($name); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="description"><?php echo esc_html__( 'Description', 'ltl-bookings' ); ?></label></th>
                                    <td><textarea name="description" id="description" rows="4" class="large-text"><?php echo esc_textarea($description); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="duration_min"><?php echo esc_html__( 'Duration (minutes)', 'ltl-bookings' ); ?></label></th>
                                    <td><input name="duration_min" type="number" id="duration_min" value="<?php echo esc_attr($duration_min); ?>" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="price_cents"><?php echo esc_html__( 'Price', 'ltl-bookings' ); ?></label></th>
                                    <td><input name="price_cents" type="number" step="0.01" id="price_cents" value="<?php echo esc_attr($price_cents / 100); ?>" class="small-text"> <?php echo esc_html($currency); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php LTLB_Admin_Component::wizard_step_end(true, false); ?>

                    <?php LTLB_Admin_Component::wizard_step_start(2); ?>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label><?php echo esc_html__( 'Availability Mode', 'ltl-bookings' ); ?></label>
                                    </th>
                                    <td>
                                        <div class="ltlb-choice-tiles-container">
                                            <?php
                                            LTLB_Admin_Component::choice_tile(
                                                'availability_mode',
                                                'window',
                                                __( 'Window', 'ltl-bookings' ),
                                                __( 'Available any time within the allowed hours.', 'ltl-bookings' ),
                                                $availability_mode === 'window'
                                            );
                                            LTLB_Admin_Component::choice_tile(
                                                'availability_mode',
                                                'fixed',
                                                __( 'Fixed', 'ltl-bookings' ),
                                                __( 'Available only at specific weekly time slots.', 'ltl-bookings' ),
                                                $availability_mode === 'fixed'
                                            );
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="ltlb-availability-setting" data-mode="window">
                                    <th scope="row"><label for="available_weekdays"><?php echo esc_html__( 'Available Days & Times', 'ltl-bookings' ); ?></label></th>
                                    <td>
                                        <div style="margin-bottom: 10px;">
                                            <?php 
                                            $weekdays = ['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '0' => 'Sun'];
                                            foreach($weekdays as $day_val => $day_label) {
                                                echo '<label style="margin-right: 15px;"><input type="checkbox" name="available_weekdays[]" value="'.esc_attr($day_val).'" '.checked(in_array($day_val, $available_weekdays_arr), true, false).'> '.esc_html($day_label).'</label>';
                                            }
                                            ?>
                                        </div>
                                        <input type="time" name="available_start_time" value="<?php echo esc_attr($available_start_time); ?>"> to
                                        <input type="time" name="available_end_time" value="<?php echo esc_attr($available_end_time); ?>">
                                    </td>
                                </tr>
                                <tr class="ltlb-availability-setting" data-mode="fixed">
                                    <th scope="row"><label for="fixed_weekly_slots"><?php echo esc_html__( 'Fixed Weekly Slots', 'ltl-bookings' ); ?></label></th>
                                    <td>
                                        <div id="fixed-slots-container">
                                        </div>
                                        <button type="button" class="button" id="add-fixed-slot"><?php echo esc_html__('Add Slot', 'ltl-bookings'); ?></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php LTLB_Admin_Component::wizard_step_end(false, false); ?>

                    <?php LTLB_Admin_Component::wizard_step_start(3); ?>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><label><?php echo esc_html__( 'Assigned Resources', 'ltl-bookings' ); ?></label></th>
                                    <td>
                                        <p class="description"><?php echo esc_html__('Select the resources that can be used for this service. If none are selected, all available resources will be considered.', 'ltl-bookings'); ?></p>
                                        <div class="resources-checkbox-group" style="margin-top: 10px;">
                                            <?php
                                            foreach($all_resources as $resource) {
                                                echo '<label style="margin-right: 15px;"><input type="checkbox" name="resources[]" value="'.esc_attr($resource['id']).'" '.checked(in_array($resource['id'], $selected_resources), true, false).'> '.esc_html($resource['name']).'</label>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php LTLB_Admin_Component::wizard_step_end(false, true, $is_edit ? __( 'Save Changes', 'ltl-bookings' ) : __( 'Create ', 'ltl-bookings') . $service_label_singular); ?>

                </form>
            <?php LTLB_Admin_Component::card_end(); ?>
        </div>
        <script>
        (function($){
            // Step navigation
            var $form = $('.ltlb-wizard-form');
            var $steps = $form.find('.wizard-step');
            var currentStep = 0;

            function updateStepVisibility() {
                $steps.each(function(index) {
                    var $step = $(this);
                    if (index === currentStep) {
                        $step.show();
                    } else {
                        $step.hide();
                    }
                });
            }

            function validateCurrentStep() {
                var isValid = true;
                var $currentInputs = $steps.eq(currentStep).find(':input[required]');
                $currentInputs.each(function() {
                    if (!this.value) {
                        isValid = false;
                        $(this).addClass('ltlb-input-error');
                    } else {
                        $(this).removeClass('ltlb-input-error');
                    }
                });
                return isValid;
            }

            $form.find('.wizard-step-next').on('click', function(e) {
                e.preventDefault();
                if (!validateCurrentStep()) return;
                currentStep++;
                updateStepVisibility();
            });

            $form.find('.wizard-step-prev').on('click', function(e) {
                e.preventDefault();
                currentStep--;
                updateStepVisibility();
            });

            // Initial setup
            $steps.hide();
            $steps.eq(currentStep).show();
        })(jQuery);
        </script>
        <?php
    }
}
