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
        // Handle form submissions
        if ( isset( $_POST['ltlb_service_save'] ) ) {
            if ( ! check_admin_referer( 'ltlb_service_save_action', 'ltlb_service_nonce' ) ) {
                wp_die( esc_html__('Nonce verification failed', 'ltl-bookings') );
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
                LTLB_Notices::add( __( 'Service saved.', 'ltl-bookings' ), 'success' );
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
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Services', 'ltl-bookings'); ?></h1>
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
                ?>
                
                <div class="ltlb-card" style="max-width: 800px; margin-top: 20px;">
                    <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">
                        <?php echo $editing ? esc_html__('Edit Service', 'ltl-bookings') : esc_html__('Add New Service', 'ltl-bookings'); ?>
                    </h2>
                    
                    <form method="post">
                        <?php wp_nonce_field( 'ltlb_service_save_action', 'ltlb_service_nonce' ); ?>
                        <input type="hidden" name="ltlb_service_save" value="1" />
                        <input type="hidden" name="id" value="<?php echo esc_attr( $form_id ); ?>" />

                        <table class="form-table">
                            <tr>
                                <th><label for="name"><?php echo esc_html__('Service Name', 'ltl-bookings'); ?></label></th>
                                <td><input name="name" type="text" id="name" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="description"><?php echo esc_html__('Description', 'ltl-bookings'); ?></label></th>
                                <td><textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="duration_min"><?php echo esc_html__('Duration (minutes)', 'ltl-bookings'); ?></label></th>
                                <td><input name="duration_min" type="number" id="duration_min" value="<?php echo esc_attr( $duration ); ?>" class="small-text" min="1" required></td>
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
                        </table>

                        <p class="submit">
                            <?php submit_button( esc_html__('Save Service', 'ltl-bookings'), 'primary', 'ltlb_service_save', false ); ?>
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
}
