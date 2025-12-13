<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_DesignPage {

    public function render(): void {
        if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

        if ( isset( $_POST['ltlb_design_save'] ) ) {
            if ( ! check_admin_referer( 'ltlb_design_save_action', 'ltlb_design_nonce' ) ) {
                wp_die( esc_html__('Nonce verification failed', 'ltl-bookings') );
            }

            $design = get_option( 'lazy_design', [] );
            if ( ! is_array( $design ) ) $design = [];

            // sanitize hex colors and normalize to #rrggbb
            $fields = [ 'background', 'primary', 'text', 'accent' ];
            foreach ( $fields as $f ) {
                $val = LTLB_Sanitizer::text( $_POST[ $f ] ?? '' );
                $val = trim( $val );
                if ( preg_match('/^#?[0-9A-Fa-f]{6}$/', $val ) ) {
                    if ( strpos( $val, '#' ) !== 0 ) $val = '#' . $val;
                    $design[ $f ] = strtolower( $val );
                } else {
                    $design[ $f ] = $design[ $f ] ?? '';
                }
            }

            update_option( 'lazy_design', $design );
            LTLB_Notices::add( __( 'Design saved.', 'ltl-bookings' ), 'success' );
            wp_safe_redirect( admin_url('admin.php?page=ltlb_design') );
            exit;
        }

        $design = get_option( 'lazy_design', [] );
        if ( ! is_array( $design ) ) $design = [];

        $bg = $design['background'] ?? '#ffffff';
        $primary = $design['primary'] ?? '#2b7cff';
        $text = $design['text'] ?? '#222222';
        $accent = $design['accent'] ?? '#ffcc00';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Design', 'ltl-bookings'); ?></h1>

            <form method="post">
                <?php wp_nonce_field( 'ltlb_design_save_action', 'ltlb_design_nonce' ); ?>
                <input type="hidden" name="ltlb_design_save" value="1">

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="background"><?php echo esc_html__('Background', 'ltl-bookings'); ?></label></th>
                            <td><input name="background" id="background" type="text" value="<?php echo esc_attr( $bg ); ?>" class="regular-text" placeholder="#ffffff"></td>
                        </tr>
                        <tr>
                            <th><label for="primary"><?php echo esc_html__('Primary', 'ltl-bookings'); ?></label></th>
                            <td><input name="primary" id="primary" type="text" value="<?php echo esc_attr( $primary ); ?>" class="regular-text" placeholder="#2b7cff"></td>
                        </tr>
                        <tr>
                            <th><label for="text"><?php echo esc_html__('Text', 'ltl-bookings'); ?></label></th>
                            <td><input name="text" id="text" type="text" value="<?php echo esc_attr( $text ); ?>" class="regular-text" placeholder="#222222"></td>
                        </tr>
                        <tr>
                            <th><label for="accent"><?php echo esc_html__('Accent', 'ltl-bookings'); ?></label></th>
                            <td><input name="accent" id="accent" type="text" value="<?php echo esc_attr( $accent ); ?>" class="regular-text" placeholder="#ffcc00"></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__('Preview', 'ltl-bookings'); ?></th>
                            <td>
                                <div style="padding:16px;border:1px solid #ddd;background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $text ); ?>;">
                                    <p><?php echo esc_html__('This is a preview of background and text color.', 'ltl-bookings'); ?></p>
                                    <button type="button" style="background:<?php echo esc_attr( $primary ); ?>;color:<?php echo esc_attr( $text ); ?>;border:none;padding:8px 12px;border-radius:4px;">Primary Button</button>
                                    <span style="display:inline-block;margin-left:8px;color:<?php echo esc_attr( $accent ); ?>;">Accent text</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( esc_html__('Save Design', 'ltl-bookings') ); ?>
            </form>
        </div>
        <?php
    }
}
