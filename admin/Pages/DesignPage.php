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

            // Sanitize color fields (hex colors)
            $color_fields = [ 'background', 'primary', 'text', 'accent', 'border_color' ];
            foreach ( $color_fields as $f ) {
                $val = LTLB_Sanitizer::text( $_POST[ $f ] ?? '' );
                $val = trim( $val );
                if ( preg_match('/^#?[0-9A-Fa-f]{6}$/', $val ) ) {
                    if ( strpos( $val, '#' ) !== 0 ) $val = '#' . $val;
                    $design[ $f ] = strtolower( $val );
                } else {
                    $design[ $f ] = $design[ $f ] ?? '';
                }
            }

            // Sanitize numeric fields (pixels)
            $numeric_fields = [ 'border_width', 'border_radius', 'box_shadow_blur', 'box_shadow_spread', 'transition_duration' ];
            foreach ( $numeric_fields as $f ) {
                $val = isset($_POST[ $f ]) ? intval( $_POST[ $f ] ) : 0;
                $design[ $f ] = max( 0, $val );
            }

            // Sanitize custom CSS
            $custom_css = isset( $_POST['custom_css'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_css'] ) ) : '';
            $design['custom_css'] = $custom_css;

            // Boolean fields
            $design['use_gradient'] = isset( $_POST['use_gradient'] ) ? 1 : 0;
            $design['use_box_shadow'] = isset( $_POST['use_box_shadow'] ) ? 1 : 0;

            update_option( 'lazy_design', $design );
            LTLB_Notices::add( __( 'Design saved.', 'ltl-bookings' ), 'success' );
            wp_safe_redirect( admin_url('admin.php?page=ltlb_design') );
            exit;
        }

        $design = get_option( 'lazy_design', [] );
        if ( ! is_array( $design ) ) $design = [];

        // Color defaults
        $bg = $design['background'] ?? '#ffffff';
        $primary = $design['primary'] ?? '#2b7cff';
        $text = $design['text'] ?? '#222222';
        $accent = $design['accent'] ?? '#ffcc00';
        $border_color = $design['border_color'] ?? '#cccccc';

        // Numeric defaults
        $border_width = $design['border_width'] ?? 1;
        $border_radius = $design['border_radius'] ?? 4;
        $box_shadow_blur = $design['box_shadow_blur'] ?? 4;
        $box_shadow_spread = $design['box_shadow_spread'] ?? 0;
        $transition_duration = $design['transition_duration'] ?? 200;

        // Boolean defaults
        $use_gradient = $design['use_gradient'] ?? 0;
        $use_box_shadow = $design['use_box_shadow'] ?? 0;

        // Custom CSS
        $custom_css = $design['custom_css'] ?? '';

        // Gradient background helper
        $gradient_bg = $use_gradient ? "linear-gradient(135deg, {$primary}, {$accent})" : $bg;
        $shadow = $use_box_shadow ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none';

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Design Settings', 'ltl-bookings'); ?></h1>
            <hr class="wp-header-end">
            <p><?php echo esc_html__('Customize the appearance of your booking wizard.', 'ltl-bookings'); ?></p>

            <form method="post" class="ltlb-design-container">
                <?php wp_nonce_field( 'ltlb_design_save_action', 'ltlb_design_nonce' ); ?>
                <input type="hidden" name="ltlb_design_save" value="1">

                <!-- LEFT COLUMN: SETTINGS -->
                <div class="ltlb-design-settings">
                    
                    <!-- COLORS SECTION -->
                    <div class="ltlb-card">
                        <h2><?php echo esc_html__('Colors', 'ltl-bookings'); ?></h2>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th><label for="background"><?php echo esc_html__('Background Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="background" id="background" type="color" value="<?php echo esc_attr( $bg ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Main background color for booking form', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="primary"><?php echo esc_html__('Primary Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="primary" id="primary" type="color" value="<?php echo esc_attr( $primary ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Buttons, links, and highlights', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="text"><?php echo esc_html__('Text Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="text" id="text" type="color" value="<?php echo esc_attr( $text ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Main text color', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="accent"><?php echo esc_html__('Accent Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="accent" id="accent" type="color" value="<?php echo esc_attr( $accent ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Hover effects and secondary accents', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="border_color"><?php echo esc_html__('Border Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="border_color" id="border_color" type="color" value="<?php echo esc_attr( $border_color ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Color for input and card borders', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- SPACING & SHAPES SECTION -->
                    <div class="ltlb-card">
                        <h2><?php echo esc_html__('Spacing & Shapes', 'ltl-bookings'); ?></h2>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th><label for="border_radius"><?php echo esc_html__('Border Radius (px)', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="border_radius" id="border_radius" type="number" value="<?php echo esc_attr( $border_radius ); ?>" min="0" max="50" class="small-text ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Roundness of buttons and inputs', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="border_width"><?php echo esc_html__('Border Width (px)', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="border_width" id="border_width" type="number" value="<?php echo esc_attr( $border_width ); ?>" min="0" max="10" class="small-text ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Thickness of input and card borders', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- SHADOW & EFFECTS SECTION -->
                    <div class="ltlb-card">
                        <h2><?php echo esc_html__('Shadow & Effects', 'ltl-bookings'); ?></h2>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th><label for="use_box_shadow"><?php echo esc_html__('Enable Box Shadow', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="use_box_shadow" id="use_box_shadow" type="checkbox" value="1" <?php checked( $use_box_shadow ); ?> class="ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Add subtle shadows to cards and buttons', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="box_shadow_blur"><?php echo esc_html__('Shadow Blur (px)', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="box_shadow_blur" id="box_shadow_blur" type="number" value="<?php echo esc_attr( $box_shadow_blur ); ?>" min="0" max="20" class="small-text ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Softness of the shadow effect', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="box_shadow_spread"><?php echo esc_html__('Shadow Spread (px)', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="box_shadow_spread" id="box_shadow_spread" type="number" value="<?php echo esc_attr( $box_shadow_spread ); ?>" min="0" max="10" class="small-text ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('How far the shadow spreads', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="use_gradient"><?php echo esc_html__('Enable Gradient Background', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="use_gradient" id="use_gradient" type="checkbox" value="1" <?php checked( $use_gradient ); ?> class="ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Use gradient from Primary to Accent color', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="transition_duration"><?php echo esc_html__('Animation Duration (ms)', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="transition_duration" id="transition_duration" type="number" value="<?php echo esc_attr( $transition_duration ); ?>" min="0" max="1000" step="50" class="small-text ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Speed of hover animations', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- CUSTOM CSS SECTION -->
                    <div class="ltlb-card">
                        <h2><?php echo esc_html__('Custom CSS', 'ltl-bookings'); ?></h2>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th><label for="custom_css"><?php echo esc_html__('Custom CSS Rules', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <textarea name="custom_css" id="custom_css" rows="8" class="large-text code" placeholder=".ltlb-booking .service-card { /* your styles */ }"><?php echo esc_textarea( $custom_css ); ?></textarea>
                                        <span class="description"><?php echo esc_html__('Add custom CSS for advanced styling.', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php submit_button( esc_html__('Save Design', 'ltl-bookings') ); ?>
                </div>

                <!-- RIGHT COLUMN: PREVIEW (STICKY) -->
                <div class="ltlb-design-preview-sidebar">
                    <div class="ltlb-preview-card">
                        <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><?php echo esc_html__('Live Preview', 'ltl-bookings'); ?></h3>
                        
                        <!-- Preview Container using CSS Variables -->
                        <div id="ltlb-live-preview-container" style="
                            padding:20px;
                            border:1px solid #ddd;
                            --lazy-bg:<?php echo esc_attr( $use_gradient ? $gradient_bg : $bg ); ?>;
                            --lazy-primary:<?php echo esc_attr( $primary ); ?>;
                            --lazy-text:<?php echo esc_attr( $text ); ?>;
                            --lazy-accent:<?php echo esc_attr( $accent ); ?>;
                            --lazy-border-color:<?php echo esc_attr( $border_color ); ?>;
                            --lazy-border-width:<?php echo esc_attr( $border_width ); ?>px;
                            --lazy-border-radius:<?php echo esc_attr( $border_radius ); ?>px;
                            --lazy-box-shadow:<?php echo $use_box_shadow ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none'; ?>;
                            --lazy-transition-duration:<?php echo esc_attr( $transition_duration ); ?>ms;
                            background: var(--lazy-bg);
                            color: var(--lazy-text);
                            border-radius: var(--lazy-border-radius);
                            box-shadow: var(--lazy-box-shadow);
                            transition: all var(--lazy-transition-duration) ease;
                        ">
                            <h4 style="margin-top:0; color:var(--lazy-text);"><?php echo esc_html__('Booking Wizard', 'ltl-bookings'); ?></h4>
                            <p style="color:var(--lazy-text);"><?php echo esc_html__('This is a live preview of your design settings.', 'ltl-bookings'); ?></p>
                            
                            <div style="margin:20px 0; display:flex; gap:10px; flex-wrap:wrap;">
                                <!-- Primary Button -->
                                <button type="button" style="
                                    background:var(--lazy-primary);
                                    color:#fff;
                                    border:var(--lazy-border-width) solid var(--lazy-border-color);
                                    padding:10px 16px;
                                    border-radius:var(--lazy-border-radius);
                                    cursor:pointer;
                                    box-shadow:var(--lazy-box-shadow);
                                    transition:all var(--lazy-transition-duration) ease;
                                ">Primary Button</button>
                                
                                <!-- Secondary Button -->
                                <button type="button" style="
                                    background:transparent;
                                    color:var(--lazy-primary);
                                    border:var(--lazy-border-width) solid var(--lazy-primary);
                                    padding:10px 16px;
                                    border-radius:var(--lazy-border-radius);
                                    cursor:pointer;
                                    transition:all var(--lazy-transition-duration) ease;
                                ">Secondary</button>

                                <!-- Input -->
                                <input type="text" placeholder="Input field" style="
                                    border:var(--lazy-border-width) solid var(--lazy-border-color);
                                    padding:8px 12px;
                                    border-radius:var(--lazy-border-radius);
                                    color:var(--lazy-text);
                                    background:#fff;
                                    width:100%;
                                    margin-top:10px;
                                ">
                            </div>

                            <!-- Service Card -->
                            <div style="
                                background:rgba(255,255,255,0.8);
                                padding:15px;
                                border-radius:var(--lazy-border-radius);
                                border:var(--lazy-border-width) solid var(--lazy-border-color);
                                margin-top:15px;
                                box-shadow:var(--lazy-box-shadow);
                            ">
                                <strong style="color:var(--lazy-text);"><?php echo esc_html__('Service Card', 'ltl-bookings'); ?></strong>
                                <p style="margin-bottom:0; font-size:0.9em; color:var(--lazy-text);"><?php echo esc_html__('Example service description.', 'ltl-bookings'); ?></p>
                            </div>
                        </div>
                        <p class="description" style="margin-top:10px; text-align:center;"><?php echo esc_html__('Changes update automatically.', 'ltl-bookings'); ?></p>
                    </div>
                </div>

            </form>
        </div>



        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.ltlb-live-input');
            const preview = document.getElementById('ltlb-live-preview-container');

            function updatePreview() {
                // Colors
                const bg = document.getElementById('background').value;
                const primary = document.getElementById('primary').value;
                const text = document.getElementById('text').value;
                const accent = document.getElementById('accent').value;
                const borderColor = document.getElementById('border_color').value;

                // Dimensions
                const borderRadius = document.getElementById('border_radius').value + 'px';
                const borderWidth = document.getElementById('border_width').value + 'px';
                const transitionDuration = document.getElementById('transition_duration').value + 'ms';

                // Shadow
                const useShadow = document.getElementById('use_box_shadow').checked;
                const blur = document.getElementById('box_shadow_blur').value;
                const spread = document.getElementById('box_shadow_spread').value;
                const shadow = useShadow ? `0 ${blur}px ${spread}px rgba(0,0,0,0.1)` : 'none';

                // Gradient
                const useGradient = document.getElementById('use_gradient').checked;
                const finalBg = useGradient ? `linear-gradient(135deg, ${primary}, ${accent})` : bg;

                // Apply CSS Variables
                preview.style.setProperty('--lazy-bg', finalBg);
                preview.style.setProperty('--lazy-primary', primary);
                preview.style.setProperty('--lazy-text', text);
                preview.style.setProperty('--lazy-accent', accent);
                preview.style.setProperty('--lazy-border-color', borderColor);
                preview.style.setProperty('--lazy-border-width', borderWidth);
                preview.style.setProperty('--lazy-border-radius', borderRadius);
                preview.style.setProperty('--lazy-box-shadow', shadow);
                preview.style.setProperty('--lazy-transition-duration', transitionDuration);
            }

            inputs.forEach(input => {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });
        });
        </script>
        <?php
    }
}
