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
        // Handle bulk delete
        if ( isset( $_POST['ltlb_service_bulk_action'] ) && $_POST['action'] === 'bulk_delete' ) {
            if ( ! check_admin_referer( 'ltlb_services_bulk', 'ltlb_services_bulk_nonce' ) ) {
                wp_die( esc_html__('Security check failed', 'ltl-bookings') );
            }
            
            $service_ids = isset($_POST['service_ids']) ? array_map('intval', (array)$_POST['service_ids']) : [];
            if (!empty($service_ids)) {
                $deleted = $this->service_repository->bulk_soft_delete($service_ids);
                LTLB_Notices::add( sprintf( __( '%d services deleted successfully.', 'ltl-bookings' ), $deleted ), 'success' );
            }
            
            wp_safe_redirect( admin_url('admin.php?page=ltlb_services') );
            exit;
        }
        
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

            // Hotel-specific fields
            if ( $is_hotel ) {
                $data['beds_type'] = isset( $_POST['beds_type'] ) ? sanitize_text_field( $_POST['beds_type'] ) : '';
                $data['amenities'] = isset( $_POST['amenities'] ) ? sanitize_textarea_field( $_POST['amenities'] ) : '';
                $data['max_adults'] = isset( $_POST['max_adults'] ) ? intval( $_POST['max_adults'] ) : 2;
                $data['max_children'] = isset( $_POST['max_children'] ) ? intval( $_POST['max_children'] ) : 0;
            }

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
                LTLB_Notices::add( __( 'Service saved successfully.', 'ltl-bookings' ), 'success' );
            } else {
                LTLB_Notices::add( __( 'Could not save service. Please try again.', 'ltl-bookings' ), 'error' );
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

                // Hotel-specific fields
                $beds_type = $editing ? (string)($service['beds_type'] ?? '') : '';
                $amenities = $editing ? (string)($service['amenities'] ?? '') : '';
                $max_adults = $editing ? intval($service['max_adults'] ?? 2) : 2;
                $max_children = $editing ? intval($service['max_children'] ?? 0) : 0;

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
                
                <div class="ltlb-card ltlb-card--narrow">
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
                                    <select name="currency" class="ltlb-currency-selector">
                                        <option value="EUR" <?php selected( $currency, 'EUR' ); ?>>EUR</option>
                                        <option value="USD" <?php selected( $currency, 'USD' ); ?>>USD</option>
                                        <option value="GBP" <?php selected( $currency, 'GBP' ); ?>>GBP</option>
                                    </select>
                                    <?php if ( $is_hotel ) : ?>
                                        <p class="description"><?php echo esc_html__('Price per night', 'ltl-bookings'); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ( $is_hotel ) : ?>
                            <tr>
                                <th><label for="beds_type"><?php echo esc_html__('Bed Type', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <select name="beds_type" id="beds_type" class="regular-text">
                                        <option value=""><?php echo esc_html__('— Select —', 'ltl-bookings'); ?></option>
                                        <option value="single" <?php selected( $beds_type, 'single' ); ?>><?php echo esc_html__('Single Bed', 'ltl-bookings'); ?></option>
                                        <option value="double" <?php selected( $beds_type, 'double' ); ?>><?php echo esc_html__('Double Bed', 'ltl-bookings'); ?></option>
                                        <option value="twin" <?php selected( $beds_type, 'twin' ); ?>><?php echo esc_html__('Twin Beds', 'ltl-bookings'); ?></option>
                                        <option value="queen" <?php selected( $beds_type, 'queen' ); ?>><?php echo esc_html__('Queen Bed', 'ltl-bookings'); ?></option>
                                        <option value="king" <?php selected( $beds_type, 'king' ); ?>><?php echo esc_html__('King Bed', 'ltl-bookings'); ?></option>
                                        <option value="bunk" <?php selected( $beds_type, 'bunk' ); ?>><?php echo esc_html__('Bunk Beds', 'ltl-bookings'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="max_adults"><?php echo esc_html__('Max Occupancy', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <label><input name="max_adults" type="number" id="max_adults" value="<?php echo esc_attr( $max_adults ); ?>" class="small-text" min="1" max="20"> <?php echo esc_html__('Adults', 'ltl-bookings'); ?></label>
                                    &nbsp;&nbsp;
                                    <label><input name="max_children" type="number" value="<?php echo esc_attr( $max_children ); ?>" class="small-text" min="0" max="10"> <?php echo esc_html__('Children', 'ltl-bookings'); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="amenities"><?php echo esc_html__('Amenities', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <textarea name="amenities" id="amenities" rows="3" class="large-text" placeholder="<?php echo esc_attr__('e.g. WiFi, TV, Air conditioning, Mini bar, Balcony', 'ltl-bookings'); ?>"><?php echo esc_textarea( $amenities ); ?></textarea>
                                    <p class="description"><?php echo esc_html__('List amenities, one per line or comma-separated', 'ltl-bookings'); ?></p>
                                </td>
                            </tr>
                            <?php endif; ?>
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
                                        echo '<div class="ltlb-checkbox-scroll">';
                                        foreach ( $all_resources as $res ) {
                                            $checked = in_array( intval($res['id']), $assigned_ids ) ? 'checked' : '';
                                            echo '<label class="ltlb-checkbox-label"><input type="checkbox" name="resource_ids[]" value="' . esc_attr($res['id']) . '" ' . $checked . '> ' . esc_html($res['name']) . '</label>';
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
                                    <p class="description" id="ltlb-availability-mode-desc">
                                        <?php echo esc_html__('Limit this class to specific days/times. Choose a window (any time inside) or fixed weekly start times (e.g. Fri 18:00). If left empty, global working hours apply.', 'ltl-bookings'); ?>
                                    </p>

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

                                    <div id="ltlb-fixed-slots" class="ltlb-settings-panel">
                                        <div class="ltlb-flex-header">
                                            <strong><?php echo esc_html__('Fixed weekly times', 'ltl-bookings'); ?></strong>
                                            <button type="button" class="button" id="ltlb-add-fixed-slot"><?php echo esc_html__('Add time', 'ltl-bookings'); ?></button>
                                        </div>
                                        <p class="description">
                                            <?php echo esc_html__('Add one or more weekly start times. Example: Fri 18:00. The customer will only see these times (still respecting staff/global hours and existing bookings).', 'ltl-bookings'); ?>
                                        </p>
                                        <table class="widefat striped ltlb-narrow-table">
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

                                    <div class="ltlb-weekdays-flex">
                                        <?php
                                        foreach ( $weekdays as $idx => $label ) {
                                            $checked_day = in_array( intval($idx), $available_weekdays_arr, true ) ? 'checked' : '';
                                            echo '<label class="ltlb-weekday-label">'
                                                . '<input type="checkbox" name="available_weekdays[]" value="' . esc_attr($idx) . '" ' . $checked_day . '>'
                                                . esc_html($label)
                                                . '</label>';
                                        }
                                        ?>
                                    </div>
                                    <div class="ltlb-time-range">
                                        <label>
                                            <?php echo esc_html__('From', 'ltl-bookings'); ?>
                                            <input type="time" name="available_start_time" value="<?php echo esc_attr($available_start_time); ?>" class="ltlb-time-input">
                                        </label>
                                        <label>
                                            <?php echo esc_html__('To', 'ltl-bookings'); ?>
                                            <input type="time" name="available_end_time" value="<?php echo esc_attr($available_end_time); ?>" class="ltlb-time-input">
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

                <div class="ltlb-card">
                    <?php if ( empty($services) ) : ?>
                        <?php
                        LTLB_Admin_Component::empty_state(
                            $is_hotel ? __( 'No Room Types Yet', 'ltl-bookings' ) : __( 'No Services Yet', 'ltl-bookings' ),
                            $is_hotel 
                                ? __( 'Room types define the different accommodation options you offer. Create your first room type to start accepting bookings.', 'ltl-bookings' )
                                : __( 'Services define what you offer to your customers. Create your first service to start accepting appointments.', 'ltl-bookings' ),
                            $is_hotel ? __( 'Create First Room Type', 'ltl-bookings' ) : __( 'Create First Service', 'ltl-bookings' ),
                            admin_url('admin.php?page=ltlb_services&action=add'),
                            $is_hotel ? 'dashicons-building' : 'dashicons-admin-tools'
                        );
                        ?>
                    <?php else : ?>
                        <form method="post" id="ltlb-services-bulk-form">
                            <?php wp_nonce_field( 'ltlb_services_bulk', 'ltlb_services_bulk_nonce' ); ?>
                            <input type="hidden" name="ltlb_service_bulk_action" value="1">
                            
                            <div class="tablenav top">
                                <div class="alignleft actions bulkactions">
                                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php echo esc_html__( 'Select bulk action', 'ltl-bookings' ); ?></label>
                                    <select name="action" id="bulk-action-selector-top">
                                        <option value="-1"><?php echo esc_html__( 'Bulk Actions', 'ltl-bookings' ); ?></option>
                                        <option value="bulk_delete"><?php echo esc_html__( 'Delete', 'ltl-bookings' ); ?></option>
                                    </select>
                                    <button type="submit" class="button action" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete the selected services?', 'ltl-bookings' ) ); ?>');"><?php echo esc_html__( 'Apply', 'ltl-bookings' ); ?></button>
                                </div>
                            </div>
                            
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <td class="check-column"><input type="checkbox" id="cb-select-all-1" /></td>
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
                                        <th scope="row" class="check-column"><input type="checkbox" name="service_ids[]" value="<?php echo esc_attr( $s['id'] ); ?>" class="ltlb-service-checkbox" /></th>
                                        <td>
                                            <strong><a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_services&action=edit&id='.$s['id']) ); ?>"><?php echo esc_html( $s['name'] ); ?></a></strong>
                                            <?php if ( ! empty($s['description']) ) : ?>
                                                <?php $full_desc = wp_strip_all_tags( $s['description'] ); ?>
                                                <?php $short_desc = wp_trim_words( $full_desc, 10 ); ?>
                                                <p class="description ltlb-service-desc" title="<?php echo esc_attr( $full_desc ); ?>"><?php echo esc_html( $short_desc ); ?></p>
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
                                            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_services&action=edit&id='.$s['id']) ); ?>" class="button button-secondary"><?php echo esc_html__('Edit', 'ltl-bookings'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </form>
                        <script>
                        (function() {
                            var selectAll = document.getElementById('cb-select-all-1');
                            var checkboxes = document.querySelectorAll('.ltlb-service-checkbox');
                            if (selectAll) {
                                selectAll.addEventListener('change', function() {
                                    checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                                });
                            }
                        })();
                        </script>
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
        $template_mode = is_array( $settings ) && isset( $settings['template_mode'] ) ? $settings['template_mode'] : 'service';
        $is_hotel = $template_mode === 'hotel';
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
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_services'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html($service_label_plural); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_services&action=add')); ?>" class="page-title-action"><?php echo esc_html__('Add New', 'ltl-bookings'); ?></a>
            <hr class="wp-header-end">

            <div class="ltlb-table-actions" style="margin-top: 20px;">
                <button type="button" class="button ltlb-column-toggle-btn" id="ltlb-services-column-toggle">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <?php echo esc_html__('Columns', 'ltl-bookings'); ?>
                </button>
                <div class="ltlb-column-toggle-menu" id="ltlb-services-column-menu" hidden>
                    <?php foreach($columns as $key => $label): ?>
                        <label class="ltlb-column-toggle-item">
                            <input type="checkbox" class="ltlb-column-checkbox" data-column="<?php echo esc_attr($key); ?>" checked>
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php LTLB_Admin_Component::card_start('', ['style' => 'margin-top:12px;']); ?>
                <table class="wp-list-table widefat fixed striped ltlb-table-with-toggles" id="ltlb-services-table">
                    <thead>
                        <tr>
                            <?php foreach($columns as $key => $label): ?>
                                <th scope="col" class="manage-column ltlb-col-<?php echo esc_attr($key); ?>" data-column="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></th>
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
                                    <td class="ltlb-col-name" data-column="name">
                                        <strong><a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_services&action=edit&id=' . $service['id'])); ?>"><?php echo esc_html($service['name']); ?></a></strong>
                                        <div class="row-actions">
                                            <span class="edit"><a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_services&action=edit&id=' . $service['id'])); ?>"><?php echo esc_html__( 'Edit', 'ltl-bookings' ); ?></a> | </span>
                                            <span class="trash"><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=ltlb_services&action=delete&id=' . $service['id']), 'ltlb_delete_service_' . $service['id'], 'ltlb_service_nonce')); ?>" class="text-danger" onclick="return confirm('<?php echo esc_js(__( 'Are you sure you want to delete this item?', 'ltl-bookings' )); ?>');"><?php echo esc_html__( 'Delete', 'ltl-bookings' ); ?></a></span>
                                        </div>
                                    </td>
                                    <?php if(!$is_hotel): ?>
                                        <td class="ltlb-col-duration" data-column="duration"><?php echo esc_html($service['duration_min']); ?> min</td>
                                    <?php endif; ?>
                                    <td class="ltlb-col-price" data-column="price"><?php echo esc_html(number_format($service['price_cents'] / 100, 2) . ' ' . $service['currency']); ?></td>
                                    <?php if(!$is_hotel): ?>
                                        <td class="ltlb-col-staff" data-column="staff"><?php echo esc_html($service['staff_name'] ?? __( 'Any', 'ltl-bookings' )); ?></td>
                                    <?php endif; ?>
                                    <td class="ltlb-col-resources" data-column="resources"><?php echo esc_html($service['resources'] ? implode(', ', $service['resources']) : ($is_hotel ? __('No rooms assigned', 'ltl-bookings') : __( 'All', 'ltl-bookings' ))); ?></td>
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

            var modeSelect = qs('select[name="template_mode"]');
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

            // Column visibility toggles
            var toggleBtn = qs('#ltlb-services-column-toggle');
            var toggleMenu = qs('#ltlb-services-column-menu');
            if (toggleBtn && toggleMenu) {
                toggleBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleMenu.hidden = !toggleMenu.hidden;
                });
                
                document.addEventListener('click', function() {
                    toggleMenu.hidden = true;
                });
                
                toggleMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
                
                var checkboxes = qsa('.ltlb-column-checkbox', toggleMenu);
                checkboxes.forEach(function(cb) {
                    cb.addEventListener('change', function() {
                        var col = this.dataset.column;
                        var isVisible = this.checked;
                        var cells = qsa('.ltlb-col-' + col);
                        cells.forEach(function(cell) {
                            cell.style.display = isVisible ? '' : 'none';
                        });
                        // Save preference
                        localStorage.setItem('ltlb_columns_services_' + col, isVisible ? '1' : '0');
                    });
                    
                    // Restore preference
                    var col = cb.dataset.column;
                    var saved = localStorage.getItem('ltlb_columns_services_' + col);
                    if (saved !== null) {
                        cb.checked = saved === '1';
                        cb.dispatchEvent(new Event('change'));
                    }
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
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_services'); } ?>
            <h1>
                <?php
                echo $is_edit
                    ? ( esc_html__( 'Edit', 'ltl-bookings' ) . ' ' . esc_html( $service_label_singular ) )
                    : ( esc_html__( 'Add New', 'ltl-bookings' ) . ' ' . esc_html( $service_label_singular ) );
                ?>
            </h1>

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
                                        <div class="ltlb-weekdays-container">
                                            <?php 
                                            $weekdays = ['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '0' => 'Sun'];
                                            foreach($weekdays as $day_val => $day_label) {
                                                echo '<label class="ltlb-weekday-checkbox"><input type="checkbox" name="available_weekdays[]" value="'.esc_attr($day_val).'" '.checked(in_array($day_val, $available_weekdays_arr), true, false).'> '.esc_html($day_label).'</label>';
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
                                        <div class="resources-checkbox-group">
                                            <?php
                                            foreach($all_resources as $resource) {
                                                echo '<label class="ltlb-resource-checkbox"><input type="checkbox" name="resources[]" value="'.esc_attr($resource['id']).'" '.checked(in_array($resource['id'], $selected_resources), true, false).'> '.esc_html($resource['name']).'</label>';
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
                var $errorMsg = $('.ltlb-validation-error');
                
                $currentInputs.each(function() {
                    if (!this.value) {
                        isValid = false;
                        $(this).addClass('ltlb-input-error');
                    } else {
                        $(this).removeClass('ltlb-input-error');
                    }
                });
                
                if (!isValid) {
                    if ($errorMsg.length === 0) {
                        $steps.eq(currentStep).prepend('<div class="notice notice-error ltlb-validation-error"><p><?php echo esc_js( __( 'Please fill in all required fields.', 'ltl-bookings' ) ); ?></p></div>');
                    }
                } else {
                    $errorMsg.remove();
                }
                
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
