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
            <h1>
                <?php echo esc_html__('Services', 'ltl-bookings'); ?>
                <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_services&action=add') ); ?>" class="page-title-action"><?php echo esc_html__('Add New', 'ltl-bookings'); ?></a>
            </h1>

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
                $available_resources = $this->resource_repository->get_all();
                $selected_resources = $editing ? $this->service_resources_repository->get_resources_for_service( intval( $service['id'] ) ) : [];
                ?>
                <form method="post">
                    <?php wp_nonce_field( 'ltlb_service_save_action', 'ltlb_service_nonce' ); ?>
                    <input type="hidden" name="ltlb_service_save" value="1" />
                    <input type="hidden" name="id" value="<?php echo esc_attr( $form_id ); ?>" />

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="name"><?php echo esc_html__('Name', 'ltl-bookings'); ?></label></th>
                                <td><input name="name" id="name" type="text" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="description"><?php echo esc_html__('Description', 'ltl-bookings'); ?></label></th>
                                <td><textarea name="description" id="description" class="large-text" rows="5"><?php echo esc_textarea( $description ); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="duration_min"><?php echo esc_html__('Duration (min)', 'ltl-bookings'); ?></label></th>
                                <td><input name="duration_min" id="duration_min" type="number" value="<?php echo esc_attr( $duration ); ?>" class="small-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="buffer_before_min"><?php echo esc_html__('Buffer before (min)', 'ltl-bookings'); ?></label></th>
                                <td><input name="buffer_before_min" id="buffer_before_min" type="number" value="<?php echo esc_attr( $buffer_before ); ?>" class="small-text"></td>
                            </tr>
                            <tr>
                                <th><label for="buffer_after_min"><?php echo esc_html__('Buffer after (min)', 'ltl-bookings'); ?></label></th>
                                <td><input name="buffer_after_min" id="buffer_after_min" type="number" value="<?php echo esc_attr( $buffer_after ); ?>" class="small-text"></td>
                            </tr>
                            <tr>
                                <th><label for="price_eur"><?php echo esc_html__('Price (EUR)', 'ltl-bookings'); ?></label></th>
                                <td><input name="price_eur" id="price_eur" type="text" value="<?php echo esc_attr( $price ); ?>" class="small-text"> <input name="currency" type="hidden" value="<?php echo esc_attr( $currency ); ?>"></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('Active', 'ltl-bookings'); ?></th>
                                <td><label><input name="is_active" type="checkbox" value="1" <?php checked( $is_active ); ?>> <?php echo esc_html__('Yes', 'ltl-bookings'); ?></label></td>
                            </tr>
                            <tr>
                                <th><label for="resource_ids"><?php echo esc_html__('Resources', 'ltl-bookings'); ?></label></th>
                                <td>
                                    <select name="resource_ids[]" id="resource_ids" multiple style="min-width:300px;">
                                        <?php foreach ( $available_resources as $r ) : ?>
                                            <option value="<?php echo intval($r['id']); ?>" <?php echo in_array(intval($r['id']), $selected_resources, true) ? 'selected' : ''; ?>><?php echo esc_html($r['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php echo esc_html__('Select resources that can be used for this service. Leave empty to allow any resource.', 'ltl-bookings'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button( $editing ? esc_html__('Update Service', 'ltl-bookings') : esc_html__('Create Service', 'ltl-bookings') ); ?>
                </form>
            <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Name', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Duration (min)', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Price', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Active', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $services ) ) : ?>
                            <tr>
                                <td colspan="5"><?php echo esc_html__('No services yet', 'ltl-bookings'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $services as $s ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $s['name'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( $s['duration_min'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( isset($s['price_cents']) ? number_format($s['price_cents']/100,2) . ' ' . ($s['currency'] ?? 'EUR') : '' ); ?></td>
                                    <td><?php echo esc_html( ! empty( $s['is_active'] ) ? 'Yes' : 'No' ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_services&action=edit&id=' . intval($s['id'])) ); ?>"><?php echo esc_html__('Edit', 'ltl-bookings'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
