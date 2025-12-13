<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_ResourcesPage {

    private $resource_repository;

    public function __construct() {
        $this->resource_repository = new LTLB_ResourceRepository();
    }

    public function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('You do not have permission to view this page.', 'ltl-bookings') );
        }
        // Handle form submissions
        if ( isset( $_POST['ltlb_resource_save'] ) ) {
            if ( ! check_admin_referer( 'ltlb_resource_save_action', 'ltlb_resource_nonce' ) ) {
                wp_die( esc_html__('Nonce verification failed', 'ltl-bookings') );
            }

            $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
            $data = [];
            $data['name'] = LTLB_Sanitizer::text( $_POST['name'] ?? '' );
            $data['description'] = isset( $_POST['description'] ) ? wp_kses_post( $_POST['description'] ) : null;
            $data['capacity'] = LTLB_Sanitizer::int( $_POST['capacity'] ?? 1 );
            $data['is_active'] = isset( $_POST['is_active'] ) ? 1 : 0;

            if ( $id > 0 ) {
                $ok = $this->resource_repository->update( $id, $data );
            } else {
                $ok = $this->resource_repository->create( $data );
            }

            $redirect = admin_url( 'admin.php?page=ltlb_resources' );
            if ( $ok ) {
                LTLB_Notices::add( __( 'Resource saved.', 'ltl-bookings' ), 'success' );
            } else {
                LTLB_Notices::add( __( 'An error occurred.', 'ltl-bookings' ), 'error' );
            }
            wp_safe_redirect( $redirect );
            exit;
        }

        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $editing = false;
        $resource = null;
        if ( $action === 'edit' && ! empty( $_GET['id'] ) ) {
            $resource = $this->resource_repository->get_by_id( intval( $_GET['id'] ) );
            if ( $resource ) $editing = true;
        }

        $resources = $this->resource_repository->get_all();
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html__('Resources', 'ltl-bookings'); ?>
                <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_resources&action=add') ); ?>" class="page-title-action"><?php echo esc_html__('Add New', 'ltl-bookings'); ?></a>
            </h1>

            <?php if ( $action === 'add' || $editing ) :
                $form_id = $editing ? intval( $resource['id'] ) : 0;
                $name = $editing ? $resource['name'] : '';
                $description = $editing ? $resource['description'] : '';
                $capacity = $editing ? $resource['capacity'] : 1;
                $is_active = $editing ? ( ! empty( $resource['is_active'] ) ) : true;
                ?>
                <form method="post">
                    <?php wp_nonce_field( 'ltlb_resource_save_action', 'ltlb_resource_nonce' ); ?>
                    <input type="hidden" name="ltlb_resource_save" value="1" />
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
                                <th><label for="capacity"><?php echo esc_html__('Capacity', 'ltl-bookings'); ?></label></th>
                                <td><input name="capacity" id="capacity" type="number" value="<?php echo esc_attr( $capacity ); ?>" class="small-text" required></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('Active', 'ltl-bookings'); ?></th>
                                <td><label><input name="is_active" type="checkbox" value="1" <?php checked( $is_active ); ?>> <?php echo esc_html__('Yes', 'ltl-bookings'); ?></label></td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button( $editing ? esc_html__('Update Resource', 'ltl-bookings') : esc_html__('Create Resource', 'ltl-bookings') ); ?>
                </form>
            <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Name', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Capacity', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Active', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $resources ) ) : ?>
                            <tr>
                                <td colspan="4"><?php echo esc_html__('No resources yet', 'ltl-bookings'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $resources as $r ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $r['name'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( $r['capacity'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( ! empty( $r['is_active'] ) ? 'Yes' : 'No' ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_resources&action=edit&id=' . intval($r['id'])) ); ?>"><?php echo esc_html__('Edit', 'ltl-bookings'); ?></a>
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
