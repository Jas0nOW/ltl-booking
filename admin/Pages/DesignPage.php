<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_DesignPage {

    /**
     * Handle form save and redirect BEFORE any output
     */
    private function maybe_handle_save(): void {
        if ( ! isset( $_POST['ltlb_design_save'] ) ) {
            return;
        }

        if ( ! check_admin_referer( 'ltlb_design_save_action', 'ltlb_design_nonce' ) ) {
            wp_die( esc_html__( 'Security check failed. Please reload the page and try again.', 'ltl-bookings' ) );
        }

        $scope = $this->get_scope();

        $design = get_option( 'lazy_design', [] );
        if ( ! is_array( $design ) ) $design = [];

        $design_backend = get_option( 'lazy_design_backend', [] );
        if ( ! is_array( $design_backend ) ) {
            $design_backend = [];
        }

        // Sanitize color fields (hex colors)
        $color_fields = [
            'background',
            'primary',
            'primary_hover',
            'secondary',
            'secondary_hover',
            'text',
            'accent',
            'border_color',
            'panel_background',
            'button_text',
            'gradient_from',
            'gradient_to',
        ];
        foreach ( $color_fields as $f ) {
            $val = LTLB_Sanitizer::text( $_POST[ $f ] ?? '' );
            $val = trim( (string) $val );
            if ( preg_match( '/^#?[0-9A-Fa-f]{6}$/', (string) $val ) ) {
                if ( strpos( (string) $val, '#' ) !== 0 ) {
                    $val = '#' . $val;
                }
                if ( $scope === 'backend' ) {
                    $design_backend[ $f ] = strtolower( $val );
                } else {
                    $design[ $f ] = strtolower( $val );
                }
            } else {
                if ( $scope === 'backend' ) {
                    $design_backend[ $f ] = $design_backend[ $f ] ?? '';
                } else {
                    $design[ $f ] = $design[ $f ] ?? '';
                }
            }
        }

        // Sanitize numeric fields (pixels) - shared across frontend/backend
        $numeric_fields = [ 'border_width', 'border_radius', 'box_shadow_blur', 'box_shadow_spread', 'transition_duration' ];
        foreach ( $numeric_fields as $f ) {
            $val = isset( $_POST[ $f ] ) ? intval( $_POST[ $f ] ) : 0;
            $design[ $f ] = max( 0, $val );
        }

        // Custom CSS (shared)
        $custom_css = isset( $_POST['custom_css'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_css'] ) ) : '';
        $design['custom_css'] = $custom_css;

        // Boolean fields (shared)
        $design['use_gradient'] = isset( $_POST['use_gradient'] ) ? 1 : 0;
        $design['enable_animations'] = isset( $_POST['enable_animations'] ) ? 1 : 0;
        $design['auto_button_text'] = isset( $_POST['auto_button_text'] ) ? 1 : 0;

        // Separate shadow controls for different elements (shared)
        $design['shadow_container'] = isset( $_POST['shadow_container'] ) ? 1 : 0;
        $design['shadow_button'] = isset( $_POST['shadow_button'] ) ? 1 : 0;
        $design['shadow_input'] = isset( $_POST['shadow_input'] ) ? 1 : 0;
        $design['shadow_card'] = isset( $_POST['shadow_card'] ) ? 1 : 0;

        update_option( 'lazy_design', $design );
        update_option( 'lazy_design_backend', $design_backend );
        LTLB_Notices::add( __( 'Design saved.', 'ltl-bookings' ), 'success' );
        wp_safe_redirect( admin_url('admin.php?page=ltlb_design&scope=' . rawurlencode( $scope ) ) );
        exit;
    }

    private function get_contrast_text_color( $hex_color ): string {
        $hex_color = is_string( $hex_color ) ? trim( $hex_color ) : '';
        if ( ! preg_match( '/^#?[0-9A-Fa-f]{6}$/', $hex_color ) ) {
            return '#ffffff';
        }

        // Safely handle null or non-string hex_color
        $hex = str_replace( '#', '', (string)$hex_color );
        if ( ! is_string( $hex ) || strlen( $hex ) < 6 ) {
            return '#ffffff';
        }

        $r = hexdec( substr( $hex, 0, 2 ) ) / 255.0;
        $g = hexdec( substr( $hex, 2, 2 ) ) / 255.0;
        $b = hexdec( substr( $hex, 4, 2 ) ) / 255.0;

        $r = $r <= 0.03928 ? $r / 12.92 : pow( ( $r + 0.055 ) / 1.055, 2.4 );
        $g = $g <= 0.03928 ? $g / 12.92 : pow( ( $g + 0.055 ) / 1.055, 2.4 );
        $b = $b <= 0.03928 ? $b / 12.92 : pow( ( $b + 0.055 ) / 1.055, 2.4 );

        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }

    private function get_scope(): string {
        $scope = '';
        if ( isset( $_POST['ltlb_design_scope'] ) ) {
            $scope = sanitize_text_field( wp_unslash( $_POST['ltlb_design_scope'] ) );
        } elseif ( isset( $_GET['scope'] ) ) {
            $scope = sanitize_text_field( wp_unslash( $_GET['scope'] ) );
        }
        $scope = strtolower( trim( (string) $scope ) );
        return in_array( $scope, [ 'frontend', 'backend' ], true ) ? $scope : 'frontend';
    }

    public function render(): void {
        if ( ! current_user_can('manage_options') ) wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );

        // Handle form submission and redirect BEFORE any output
        $this->maybe_handle_save();

        $scope = $this->get_scope();

        $design = get_option( 'lazy_design', [] );
        if ( ! is_array( $design ) ) $design = [];

        $design_backend = get_option( 'lazy_design_backend', [] );
        if ( ! is_array( $design_backend ) ) {
            $design_backend = [];
        }

        // Active palette: backend overrides frontend colors; non-color tokens are shared.
        $palette = $design;
        if ( $scope === 'backend' ) {
            foreach ( [
                'background',
                'primary',
                'primary_hover',
                'secondary',
                'secondary_hover',
                'text',
                'accent',
                'border_color',
                'panel_background',
                'button_text',
            ] as $k ) {
                if ( isset( $design_backend[ $k ] ) && is_string( $design_backend[ $k ] ) && $design_backend[ $k ] !== '' ) {
                    $palette[ $k ] = $design_backend[ $k ];
                }
            }
        }

        // Color defaults
        $bg = $palette['background'] ?? '#ffffff';
        $primary = $palette['primary'] ?? '#2b7cff';
        $primary_hover = $palette['primary_hover'] ?? ( $palette['accent'] ?? '#ffcc00' );
        $secondary = $palette['secondary'] ?? $primary;
        $secondary_hover = $palette['secondary_hover'] ?? $secondary;
        $text = $palette['text'] ?? '#222222';
        $accent = $palette['accent'] ?? '#ffcc00';
        $border_color = $palette['border_color'] ?? '#cccccc';
        $panel_bg = $palette['panel_background'] ?? 'transparent';
        $button_text = $palette['button_text'] ?? '#ffffff';

        // Numeric defaults
        $border_width = $design['border_width'] ?? 1;
        $border_radius = $design['border_radius'] ?? 4;
        $box_shadow_blur = $design['box_shadow_blur'] ?? 4;
        $box_shadow_spread = $design['box_shadow_spread'] ?? 0;
        $transition_duration = $design['transition_duration'] ?? 200;
        $enable_animations = $design['enable_animations'] ?? 1;

        // Boolean defaults
        $use_gradient = $design['use_gradient'] ?? 0;
        $auto_button_text = $design['auto_button_text'] ?? 1;
        
        // Separate shadow controls
        $shadow_container = $design['shadow_container'] ?? 1;
        $shadow_button = $design['shadow_button'] ?? 1;
        $shadow_input = $design['shadow_input'] ?? 0;
        $shadow_card = $design['shadow_card'] ?? 1;

        // Custom CSS
        $custom_css = $design['custom_css'] ?? '';

        // Gradient background helper
        $gradient_bg = $use_gradient ? "linear-gradient(135deg, {$primary}, {$accent})" : $bg;

        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_design'); } ?>

            <!-- Page Header -->
            <div class="ltlb-page-header">
                <div class="ltlb-page-header__content">
                    <h1 class="ltlb-page-header__title">
                        <?php echo esc_html__('Design Settings', 'ltl-bookings'); ?>
                    </h1>
                    <p class="ltlb-page-header__subtitle">
                        <?php echo esc_html__('Customize the appearance of your booking wizard.', 'ltl-bookings'); ?>
                    </p>
                </div>
            </div>

            <p>
                <?php if ( $scope === 'backend' ) : ?>
                    <br><span class="description"><?php echo esc_html__('Backend tab controls the color palette used inside WP Admin (LazyBookings pages).', 'ltl-bookings'); ?></span>
                <?php else : ?>
                    <br><span class="description"><?php echo esc_html__('Frontend tab controls the color palette used on the booking widget.', 'ltl-bookings'); ?></span>
                <?php endif; ?>
            </p>

            <div class="ltlb-design-subnav" style="margin: 10px 0 0;">
                <?php
                $base = admin_url( 'admin.php?page=ltlb_design' );
                $frontend_url = $base . '&scope=frontend';
                $backend_url = $base . '&scope=backend';
                ?>
                <a class="ltlb-admin-tab <?php echo $scope === 'frontend' ? 'is-active' : ''; ?>" href="<?php echo esc_url( $frontend_url ); ?>"><?php echo esc_html__( 'Frontend', 'ltl-bookings' ); ?></a>
                <a class="ltlb-admin-tab <?php echo $scope === 'backend' ? 'is-active' : ''; ?>" href="<?php echo esc_url( $backend_url ); ?>"><?php echo esc_html__( 'Backend', 'ltl-bookings' ); ?></a>
            </div>

            <form method="post" class="ltlb-design-container">
                <?php wp_nonce_field( 'ltlb_design_save_action', 'ltlb_design_nonce' ); ?>
                <input type="hidden" name="ltlb_design_save" value="1">
                <input type="hidden" name="ltlb_design_scope" value="<?php echo esc_attr( $scope ); ?>">

                <!-- Save Button at Top -->
                <p class="submit" style="margin-top:10px; padding-top:0;">
                    <?php submit_button( esc_html__('Save Design', 'ltl-bookings'), 'primary', 'ltlb_design_save_top', false ); ?>
                </p>

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
                                        <span class="description"><?php echo esc_html__('Small highlights (required fields *), and the gradient end color when gradient is enabled.', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="border_color"><?php echo esc_html__('Border Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="border_color" id="border_color" type="color" value="<?php echo esc_attr( $border_color ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Color for input and card borders', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="panel_background"><?php echo esc_html__('Panel Background', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="panel_background" id="panel_background" type="color" value="<?php echo esc_attr( $panel_bg ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Background for inner panels (fieldsets/cards)', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- BUTTONS SECTION -->
                    <div class="ltlb-card">
                        <h2><?php echo esc_html__('Buttons', 'ltl-bookings'); ?></h2>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th><label for="primary"><?php echo esc_html__('Primary Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="primary" id="primary" type="color" value="<?php echo esc_attr( $primary ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Primary button background.', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="primary_hover"><?php echo esc_html__('Primary Hover Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="primary_hover" id="primary_hover" type="color" value="<?php echo esc_attr( $primary_hover ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Primary button hover background and border.', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="secondary"><?php echo esc_html__('Secondary Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="secondary" id="secondary" type="color" value="<?php echo esc_attr( $secondary ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Secondary button border and text (outline).', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="secondary_hover"><?php echo esc_html__('Secondary Hover Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="secondary_hover" id="secondary_hover" type="color" value="<?php echo esc_attr( $secondary_hover ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Secondary button hover fill background and border.', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="auto_button_text"><?php echo esc_html__('Auto Button Text Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="auto_button_text" id="auto_button_text" type="checkbox" value="1" <?php checked( $auto_button_text ); ?> class="ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Automatically choose readable text color for the primary button (black/white).', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="button_text"><?php echo esc_html__('Manual Button Text Color', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="button_text" id="button_text" type="color" value="<?php echo esc_attr( $button_text ); ?>" class="ltlb-color-input ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Used only if Auto Button Text Color is disabled.', 'ltl-bookings'); ?></span>
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
                        <p class="description" style="margin-top:0;"><?php echo esc_html__('Control which elements should have shadows. Uncheck all for a flat design.', 'ltl-bookings'); ?></p>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th><label for="shadow_container"><?php echo esc_html__('Container Shadow', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="shadow_container" id="shadow_container" type="checkbox" value="1" <?php checked( $shadow_container ); ?> class="ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Add shadow to the main booking form container', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="shadow_button"><?php echo esc_html__('Button Shadow', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="shadow_button" id="shadow_button" type="checkbox" value="1" <?php checked( $shadow_button ); ?> class="ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Add shadow to buttons (submit, primary, etc.)', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="shadow_input"><?php echo esc_html__('Input Shadow', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="shadow_input" id="shadow_input" type="checkbox" value="1" <?php checked( $shadow_input ); ?> class="ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Add shadow to input fields (text, select, etc.)', 'ltl-bookings'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="shadow_card"><?php echo esc_html__('Card Shadow', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="shadow_card" id="shadow_card" type="checkbox" value="1" <?php checked( $shadow_card ); ?> class="ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Add shadow to service/room cards', 'ltl-bookings'); ?></span>
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
                                <tr>
                                    <th><label for="enable_animations"><?php echo esc_html__('Enable Animations', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="enable_animations" id="enable_animations" type="checkbox" value="1" <?php checked( $enable_animations ); ?> class="ltlb-live-input">
                                        <span class="description"><?php echo esc_html__('Disable to remove all hover and focus transitions.', 'ltl-bookings'); ?></span>
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
                                        <textarea name="custom_css" id="custom_css" rows="8" class="large-text code ltlb-live-input" placeholder="<?php echo esc_attr__( '.ltlb-booking .service-card { /* your styles */ }', 'ltl-bookings' ); ?>"><?php echo esc_textarea( $custom_css ); ?></textarea>
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
                        <h3><?php echo esc_html__('Live Preview', 'ltl-bookings'); ?></h3>
                        
                        <!-- Preview Container using CSS Variables -->
                        <div id="ltlb-live-preview" data-ltlb-preview-scope="<?php echo esc_attr( $scope ); ?>" style="
                            --lazy-bg:<?php echo esc_attr( $use_gradient ? $gradient_bg : $bg ); ?>;
                            --lazy-primary:<?php echo esc_attr( $primary ); ?>;
                            --lazy-primary-hover:<?php echo esc_attr( $primary_hover ); ?>;
                            --lazy-secondary:<?php echo esc_attr( $secondary ); ?>;
                            --lazy-secondary-hover:<?php echo esc_attr( $secondary_hover ); ?>;
                            --lazy-text:<?php echo esc_attr( $text ); ?>;
                            --lazy-accent:<?php echo esc_attr( $accent ); ?>;
                            --lazy-accent-hover:<?php echo esc_attr( $primary_hover ); ?>;
                            --lazy-border-color:<?php echo esc_attr( $border_color ); ?>;
                            --lazy-panel-bg:<?php echo esc_attr( $panel_bg ); ?>;
                            --lazy-button-text:<?php echo esc_attr( $button_text ); ?>;
                            --lazy-secondary-text:<?php echo esc_attr( $this->get_contrast_text_color( $secondary_hover ) ); ?>;

                            /* Compatibility tokens (some components use these names) */
                            --lazy-bg-primary:<?php echo esc_attr( ($panel_bg && $panel_bg !== 'transparent') ? $panel_bg : $bg ); ?>;
                            --lazy-bg-secondary:<?php echo esc_attr( ($panel_bg && $panel_bg !== 'transparent') ? $panel_bg : $bg ); ?>;
                            --lazy-bg-tertiary:<?php echo esc_attr( ($panel_bg && $panel_bg !== 'transparent') ? $panel_bg : $bg ); ?>;
                            --lazy-text-primary:<?php echo esc_attr( $text ); ?>;
                            --lazy-text-secondary:<?php echo esc_attr( $text ); ?>;
                            --lazy-text-muted:<?php echo esc_attr( $text ); ?>;
                            --lazy-border-light:<?php echo esc_attr( $border_color ); ?>;
                            --lazy-border-medium:<?php echo esc_attr( $border_color ); ?>;
                            --lazy-border-strong:<?php echo esc_attr( $border_color ); ?>;
                            --lazy-border-width:<?php echo esc_attr( $border_width ); ?>px;
                            --lazy-border-radius:<?php echo esc_attr( $border_radius ); ?>px;
                            --lazy-shadow-container:<?php echo $shadow_container ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none'; ?>;
                            --lazy-shadow-button:<?php echo $shadow_button ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none'; ?>;
                            --lazy-shadow-input:<?php echo $shadow_input ? "0 1px 2px rgba(0,0,0,0.05)" : 'none'; ?>;
                            --lazy-shadow-card:<?php echo $shadow_card ? "0 {$box_shadow_blur}px {$box_shadow_spread}px rgba(0,0,0,0.1)" : 'none'; ?>;
                            --lazy-transition-duration:<?php echo esc_attr( ($enable_animations ? $transition_duration : 0) ); ?>ms;
                            background: var(--lazy-bg);
                            padding: 14px;
                            border-radius: var(--lazy-border-radius);
                            transition: all var(--lazy-transition-duration) ease;
                        ">
                            <div id="ltlb-live-preview-container" class="<?php echo $scope === 'backend' ? 'ltlb-admin ltlb-admin--preview' : 'ltlb-booking'; ?>" style="
                                <?php if ( $scope === 'backend' ) : ?>
                                padding: 0;
                                border: 0;
                                background: <?php echo esc_attr( $panel_bg !== 'transparent' ? $panel_bg : 'transparent' ); ?>;
                                color: var(--lazy-text);
                                border-radius: 0;
                                box-shadow: none;
                                <?php else : ?>
                                padding:20px;
                                border: var(--lazy-border-width) solid var(--lazy-border-color);
                                background: var(--lazy-panel-bg);
                                color: var(--lazy-text);
                                border-radius: var(--lazy-border-radius);
                                box-shadow: var(--lazy-shadow-container);
                                <?php endif; ?>
                                transition: all var(--lazy-transition-duration) ease;
                            ">
                                <style id="ltlb-live-custom-css"></style>
                            <?php if ( $scope === 'backend' ) : ?>
                                <div class="ltlb-admin-header">
                                    <div class="ltlb-admin-header__brand">
                                        <div class="ltlb-admin-header__icon">LB</div>
                                        <div class="ltlb-admin-header__titles">
                                            <div class="ltlb-admin-header__title"><?php echo esc_html__('LazyBookings', 'ltl-bookings'); ?></div>
                                            <div class="ltlb-admin-header__subtitle"><?php echo esc_html__('Backend Preview', 'ltl-bookings'); ?></div>
                                        </div>
                                    </div>
                                    <nav class="ltlb-admin-header__nav" aria-label="<?php echo esc_attr__('Preview navigation', 'ltl-bookings'); ?>">
                                        <span class="ltlb-admin-tab is-active"><?php echo esc_html__('Calendar', 'ltl-bookings'); ?></span>
                                        <span class="ltlb-admin-tab"><?php echo esc_html__('Appointments', 'ltl-bookings'); ?></span>
                                        <span class="ltlb-admin-tab"><?php echo esc_html__('Settings', 'ltl-bookings'); ?></span>
                                    </nav>
                                </div>

                                <div class="ltlb-card" data-ltlb-preview="card" style="margin-top: 0;">
                                    <h2 style="margin-top:0;">
                                        <?php echo esc_html__('Example Admin Panel', 'ltl-bookings'); ?>
                                        <span style="color:var(--lazy-accent); font-weight:700; margin-left:6px;">*</span>
                                    </h2>

                                    <p class="description" style="margin-top:-6px;">
                                        <?php echo esc_html__('This is a live preview of your backend color palette.', 'ltl-bookings'); ?>
                                    </p>

                                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin: 12px 0;">
                                        <button type="button" class="button button-primary" data-ltlb-preview="primary-button"><?php echo esc_html__('Primary Button', 'ltl-bookings'); ?></button>
                                        <button type="button" class="button" data-ltlb-preview="secondary-button"><?php echo esc_html__('Secondary', 'ltl-bookings'); ?></button>
                                        <span class="ltlb-status-badge status-active"><?php echo esc_html__('Confirmed', 'ltl-bookings'); ?></span>
                                    </div>

                                    <input type="text" class="regular-text" data-ltlb-preview="input" placeholder="<?php echo esc_attr__('Search…', 'ltl-bookings'); ?>" />

                                    <table class="widefat striped" style="margin-top: 12px;">
                                        <thead>
                                            <tr>
                                                <th><?php echo esc_html__('Customer', 'ltl-bookings'); ?></th>
                                                <th><?php echo esc_html__('Service', 'ltl-bookings'); ?></th>
                                                <th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php echo esc_html__('Jane Doe', 'ltl-bookings'); ?></td>
                                                <td><?php echo esc_html__('Yoga Session', 'ltl-bookings'); ?></td>
                                                <td><span class="ltlb-status-badge status-pending"><?php echo esc_html__('Pending', 'ltl-bookings'); ?></span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else : ?>
                                <h4><?php echo esc_html__('Booking Wizard', 'ltl-bookings'); ?></h4>
                                <p style="margin-top:-6px; font-size: 12px;">
                                    <span style="color:var(--lazy-accent); font-weight:700;">*</span>
                                    <span style="color:var(--lazy-text); opacity:0.9;"><?php echo esc_html__('Accent preview', 'ltl-bookings'); ?></span>
                                </p>
                                <p style="color:var(--lazy-text);"><?php echo esc_html__('This is a live preview of your design settings.', 'ltl-bookings'); ?></p>
                                
                                <div style="margin:20px 0; display:flex; gap:10px; flex-wrap:wrap;">
                                    <!-- Primary Button -->
                                    <button type="button" data-ltlb-preview="primary-button" style="
                                        background:var(--lazy-primary);
                                        color:var(--lazy-button-text, #fff);
                                        border:var(--lazy-border-width) solid var(--lazy-border-color);
                                        padding:10px 16px;
                                        border-radius:var(--lazy-border-radius);
                                        cursor:pointer;
                                        box-shadow:var(--lazy-shadow-button);
                                        transition:all var(--lazy-transition-duration) ease;
                                    "><?php echo esc_html__( 'Primary Button', 'ltl-bookings' ); ?></button>
                                    
                                    <!-- Secondary Button -->
                                    <button type="button" data-ltlb-preview="secondary-button" style="
                                        background:transparent;
                                        color:var(--lazy-secondary);
                                        border:var(--lazy-border-width) solid var(--lazy-secondary);
                                        padding:10px 16px;
                                        border-radius:var(--lazy-border-radius);
                                        cursor:pointer;
                                        box-shadow:var(--lazy-shadow-button);
                                        transition:all var(--lazy-transition-duration) ease;
                                    "><?php echo esc_html__( 'Secondary', 'ltl-bookings' ); ?></button>

                                    <!-- Input -->
                                    <input type="text" data-ltlb-preview="input" placeholder="<?php echo esc_attr__( 'Input field', 'ltl-bookings' ); ?>" style="
                                        border:var(--lazy-border-width) solid var(--lazy-border-color);
                                        padding:8px 12px;
                                        border-radius:var(--lazy-border-radius);
                                        color:var(--lazy-text);
                                        background:var(--lazy-bg);
                                        width:100%;
                                        margin-top:10px;
                                        box-shadow:var(--lazy-shadow-input) !important;
                                    ">
                                </div>

                                <!-- Service Card -->
                                <div data-ltlb-preview="card" style="
                                    background:var(--lazy-panel-bg, var(--lazy-bg));
                                    padding:15px;
                                    border-radius:var(--lazy-border-radius);
                                    border:var(--lazy-border-width) solid var(--lazy-border-color);
                                    margin-top:15px;
                                    box-shadow:var(--lazy-shadow-card);
                                ">
                                    <strong style="color:var(--lazy-text);"><?php echo esc_html__('Service Card', 'ltl-bookings'); ?></strong>
                                    <p style="margin-bottom:0; font-size:0.9em; color:var(--lazy-text);"><?php echo esc_html__('Example service description.', 'ltl-bookings'); ?></p>
                                </div>
                            <?php endif; ?>
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
            const preview = document.getElementById('ltlb-live-preview');
            const previewInner = document.getElementById('ltlb-live-preview-container');

            if (!preview || !previewInner) {
                return;
            }

            const previewPrimary = previewInner.querySelector('[data-ltlb-preview="primary-button"]');
            const previewSecondary = previewInner.querySelector('[data-ltlb-preview="secondary-button"]');
            const previewInput = previewInner.querySelector('[data-ltlb-preview="input"]');
            const previewCard = previewInner.querySelector('[data-ltlb-preview="card"]');

            const supportsColorMix = () => {
                try {
                    return !!(window.CSS && CSS.supports && CSS.supports('color', 'color-mix(in srgb, #000, #fff)'));
                } catch (e) {
                    return false;
                }
            };

            function getHoverBorderColor(hoverVarName) {
                if (supportsColorMix()) {
                    return `color-mix(in srgb, var(${hoverVarName}) 78%, var(--lazy-text) 22%)`;
                }
                return 'var(--lazy-border-color)';
            }

            function getContrastTextColor(hex) {
                if (!hex) return '#ffffff';
                const clean = hex.replace('#', '').trim();
                if (!/^[0-9a-fA-F]{6}$/.test(clean)) return '#ffffff';
                const r8 = parseInt(clean.substring(0, 2), 16);
                const g8 = parseInt(clean.substring(2, 4), 16);
                const b8 = parseInt(clean.substring(4, 6), 16);

                const toLinear = (c) => {
                    const x = c / 255;
                    return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
                };

                const r = toLinear(r8);
                const g = toLinear(g8);
                const b = toLinear(b8);
                const L = 0.2126 * r + 0.7152 * g + 0.0722 * b;
                return L > 0.5 ? '#000000' : '#ffffff';
            }

            function updatePreview() {
                // Colors
                const bg = document.getElementById('background').value;
                const primary = document.getElementById('primary').value;
                const text = document.getElementById('text').value;
                const accent = document.getElementById('accent').value;
                const primaryHover = document.getElementById('primary_hover').value;
                const secondary = document.getElementById('secondary').value;
                const secondaryHover = document.getElementById('secondary_hover').value;
                const borderColor = document.getElementById('border_color').value;
                const panelBg = document.getElementById('panel_background').value;
                const buttonText = document.getElementById('button_text').value;
                const autoButtonText = document.getElementById('auto_button_text').checked;

                // Dimensions
                const borderRadius = document.getElementById('border_radius').value + 'px';
                const borderWidth = document.getElementById('border_width').value + 'px';
                const transitionDurationValue = document.getElementById('transition_duration').value;
                const animationsEnabled = document.getElementById('enable_animations').checked;
                const transitionDuration = (animationsEnabled ? transitionDurationValue : 0) + 'ms';

                // Separate shadows
                const blur = document.getElementById('box_shadow_blur').value;
                const spread = document.getElementById('box_shadow_spread').value;
                const shadowContainer = document.getElementById('shadow_container').checked ? `0 ${blur}px ${spread}px rgba(0,0,0,0.1)` : 'none';
                const shadowButton = document.getElementById('shadow_button').checked ? `0 ${blur}px ${spread}px rgba(0,0,0,0.1)` : 'none';
                const shadowInput = document.getElementById('shadow_input').checked ? `0 ${blur}px ${spread}px rgba(0,0,0,0.12)` : 'none';
                const shadowCard = document.getElementById('shadow_card').checked ? `0 ${blur}px ${spread}px rgba(0,0,0,0.1)` : 'none';

                // Custom CSS (scoped to this preview)
                const rawCustomCss = (document.getElementById('custom_css') && document.getElementById('custom_css').value) ? document.getElementById('custom_css').value : '';
                const customStyleEl = document.getElementById('ltlb-live-custom-css');
                if (customStyleEl) {
                    // Most users target `.ltlb-booking` in custom CSS. Replace with the preview container id to avoid leaking into wp-admin.
                    const scoped = String(rawCustomCss || '').replace(/\.ltlb-booking\b/g, '#ltlb-live-preview-container');
                    customStyleEl.textContent = scoped;
                }

                // Gradient
                const useGradient = document.getElementById('use_gradient').checked;
                const gradientFrom = (document.getElementById('gradient_from') && document.getElementById('gradient_from').value) ? document.getElementById('gradient_from').value : primary;
                const gradientTo = (document.getElementById('gradient_to') && document.getElementById('gradient_to').value) ? document.getElementById('gradient_to').value : accent;
                const finalBg = useGradient ? `linear-gradient(135deg, ${gradientFrom}, ${gradientTo})` : bg;

                const isBackend = (preview && preview.getAttribute('data-ltlb-preview-scope') === 'backend');

                // Apply CSS Variables
                preview.style.setProperty('--lazy-bg', finalBg);
                preview.style.setProperty('--lazy-primary', primary);
                preview.style.setProperty('--lazy-primary-hover', primaryHover);
                preview.style.setProperty('--lazy-secondary', secondary);
                preview.style.setProperty('--lazy-secondary-hover', secondaryHover);
                preview.style.setProperty('--lazy-text', text);
                preview.style.setProperty('--lazy-accent', accent);
                preview.style.setProperty('--lazy-accent-hover', primaryHover);
                preview.style.setProperty('--lazy-border-color', borderColor);
                preview.style.setProperty('--lazy-panel-bg', panelBg);
                const computedPrimaryText = autoButtonText ? getContrastTextColor(primary) : buttonText;
                preview.style.setProperty('--lazy-button-text', computedPrimaryText);
                preview.style.setProperty('--lazy-secondary-text', getContrastTextColor(secondaryHover));

                // Compatibility token mapping for preview parity
                const surfacePrimary = (panelBg && panelBg !== 'transparent') ? panelBg : bg;
                preview.style.setProperty('--lazy-bg-primary', surfacePrimary);
                preview.style.setProperty('--lazy-bg-secondary', surfacePrimary);
                preview.style.setProperty('--lazy-bg-tertiary', surfacePrimary);
                preview.style.setProperty('--lazy-text-primary', text);
                preview.style.setProperty('--lazy-text-secondary', text);
                preview.style.setProperty('--lazy-text-muted', text);
                preview.style.setProperty('--lazy-border-light', borderColor);
                preview.style.setProperty('--lazy-border-medium', borderColor);
                preview.style.setProperty('--lazy-border-strong', borderColor);

                preview.style.setProperty('--lazy-border-width', borderWidth);
                preview.style.setProperty('--lazy-border-radius', borderRadius);
                preview.style.setProperty('--lazy-shadow-container', shadowContainer);
                preview.style.setProperty('--lazy-shadow-button', shadowButton);
                preview.style.setProperty('--lazy-shadow-input', shadowInput);
                preview.style.setProperty('--lazy-shadow-card', shadowCard);
                preview.style.setProperty('--lazy-transition-duration', transitionDuration);

                // Also apply styles directly to ensure the preview always visibly updates
                if (previewInner) {
                    if (isBackend) {
                        previewInner.style.background = (panelBg && panelBg !== 'transparent') ? panelBg : 'transparent';
                        previewInner.style.color = text;
                        previewInner.style.border = '0';
                        previewInner.style.borderRadius = '0';
                        previewInner.style.boxShadow = 'none';
                    } else {
                        previewInner.style.background = panelBg;
                        previewInner.style.color = text;
                        previewInner.style.borderColor = borderColor;
                        previewInner.style.borderWidth = borderWidth;
                        previewInner.style.borderStyle = 'solid';
                        previewInner.style.borderRadius = borderRadius;
                        previewInner.style.boxShadow = shadowContainer;
                    }
                }

                if (previewPrimary) {
                    previewPrimary.style.background = primary;
                    previewPrimary.style.color = computedPrimaryText;
                    previewPrimary.style.borderColor = borderColor;
                    previewPrimary.style.borderWidth = borderWidth;
                    previewPrimary.style.borderStyle = 'solid';
                    previewPrimary.style.borderRadius = borderRadius;
                    previewPrimary.style.boxShadow = shadowButton;
                }

                if (previewSecondary) {
                    previewSecondary.style.color = secondary;
                    previewSecondary.style.borderColor = secondary;
                    previewSecondary.style.borderWidth = borderWidth;
                    previewSecondary.style.borderStyle = 'solid';
                    previewSecondary.style.borderRadius = borderRadius;
                    previewSecondary.style.boxShadow = shadowButton;
                }

                if (previewInput) {
                    previewInput.style.borderColor = borderColor;
                    previewInput.style.borderWidth = borderWidth;
                    previewInput.style.borderStyle = 'solid';
                    previewInput.style.borderRadius = borderRadius;
                    previewInput.style.color = text;
                    previewInput.style.boxShadow = shadowInput;
                }

                if (previewCard) {
                    previewCard.style.borderColor = borderColor;
                    previewCard.style.borderWidth = borderWidth;
                    previewCard.style.borderStyle = 'solid';
                    previewCard.style.borderRadius = borderRadius;
                    previewCard.style.boxShadow = shadowCard;
                    if (isBackend) {
                        previewCard.style.background = panelBg;
                        previewCard.style.color = text;
                    }
                }
            }

            inputs.forEach(input => {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });

            // Simulate hover effects in preview
            function addHoverSim(el, hoverStyles) {
                if (!el) return;
                el.addEventListener('mouseenter', () => {
                    Object.entries(hoverStyles).forEach(([prop, val]) => el.style.setProperty(prop, val));
                });
                el.addEventListener('mouseleave', () => {
                    Object.entries(hoverStyles).forEach(([prop]) => el.style.removeProperty(prop));
                });
            }

            addHoverSim(previewPrimary, {
                'box-shadow': 'var(--lazy-shadow-button, 0 6px 20px rgba(43,124,255,0.3))',
                'transform': 'translateY(-2px)'
            });

            addHoverSim(previewPrimary, {
                'background': 'var(--lazy-primary-hover)',
                'border-color': getHoverBorderColor('--lazy-primary-hover')
            });
            addHoverSim(previewSecondary, {
                'box-shadow': 'var(--lazy-shadow-button, 0 4px 12px rgba(43,124,255,0.2))',
                'transform': 'translateY(-2px)',
                'background': 'var(--lazy-secondary-hover)',
                'border-color': getHoverBorderColor('--lazy-secondary-hover'),
                'color': 'var(--lazy-secondary-text, #fff)'
            });
            addHoverSim(previewInput, {
                'box-shadow': '0 0 0 3px rgba(43,124,255,0.1), var(--lazy-shadow-input, 0 1px 2px rgba(0,0,0,0.05))',
                'border-color': 'var(--lazy-primary)'
            });

            // Initial sync (including auto button text)
            updatePreview();
        });
        </script>
        
        <!-- DESIGN SYSTEM SHOWCASE -->
        <div class="ltlb-card" style="margin-top: 24px;">
            <h2><?php echo esc_html__('Design System Components', 'ltl-bookings'); ?></h2>
            <p class="description"><?php echo esc_html__('Preview of available components from the new design system.', 'ltl-bookings'); ?></p>
            
            <h3><?php echo esc_html__('Buttons', 'ltl-bookings'); ?></h3>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;">
                <button class="ltlb-btn ltlb-btn--primary"><?php echo esc_html__('Primary', 'ltl-bookings'); ?></button>
                <button class="ltlb-btn ltlb-btn--secondary"><?php echo esc_html__('Secondary', 'ltl-bookings'); ?></button>
                <button class="ltlb-btn ltlb-btn--danger"><?php echo esc_html__('Danger', 'ltl-bookings'); ?></button>
                <button class="ltlb-btn ltlb-btn--ghost"><?php echo esc_html__('Ghost', 'ltl-bookings'); ?></button>
                <button class="ltlb-btn ltlb-btn--primary ltlb-btn--small"><?php echo esc_html__('Small', 'ltl-bookings'); ?></button>
            </div>
            
            <h3><?php echo esc_html__('Badges', 'ltl-bookings'); ?></h3>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;">
                <span class="ltlb-badge ltlb-badge--success"><?php echo esc_html__('Confirmed', 'ltl-bookings'); ?></span>
                <span class="ltlb-badge ltlb-badge--warning"><?php echo esc_html__('Pending', 'ltl-bookings'); ?></span>
                <span class="ltlb-badge ltlb-badge--danger"><?php echo esc_html__('Cancelled', 'ltl-bookings'); ?></span>
                <span class="ltlb-badge ltlb-badge--info"><?php echo esc_html__('Info', 'ltl-bookings'); ?></span>
                <span class="ltlb-badge ltlb-badge--neutral"><?php echo esc_html__('Neutral', 'ltl-bookings'); ?></span>
            </div>
            
            <h3><?php echo esc_html__('Alerts', 'ltl-bookings'); ?></h3>
            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
                <div class="ltlb-alert ltlb-alert--success">
                    <strong><?php echo esc_html__('Success:', 'ltl-bookings'); ?></strong> <?php echo esc_html__('Your booking has been confirmed!', 'ltl-bookings'); ?>
                </div>
                <div class="ltlb-alert ltlb-alert--warning">
                    <strong><?php echo esc_html__('Warning:', 'ltl-bookings'); ?></strong> <?php echo esc_html__('This action cannot be undone.', 'ltl-bookings'); ?>
                </div>
                <div class="ltlb-alert ltlb-alert--danger">
                    <strong><?php echo esc_html__('Error:', 'ltl-bookings'); ?></strong> <?php echo esc_html__('Payment failed. Please try again.', 'ltl-bookings'); ?>
                </div>
                <div class="ltlb-alert ltlb-alert--info">
                    <strong><?php echo esc_html__('Info:', 'ltl-bookings'); ?></strong> <?php echo esc_html__('You can change this setting later.', 'ltl-bookings'); ?>
                </div>
            </div>
            
            <h3><?php echo esc_html__('Form Elements', 'ltl-bookings'); ?></h3>
            <div style="display: flex; flex-direction: column; gap: 12px; max-width: 400px; margin-bottom: 24px;">
                <input type="text" class="ltlb-input" placeholder="<?php echo esc_attr__('Enter your name', 'ltl-bookings'); ?>">
                <input type="text" class="ltlb-input ltlb-input--error" placeholder="<?php echo esc_attr__('Error state', 'ltl-bookings'); ?>">
                <select class="ltlb-input">
                    <option><?php echo esc_html__('Select option', 'ltl-bookings'); ?></option>
                    <option><?php echo esc_html__('Option 1', 'ltl-bookings'); ?></option>
                    <option><?php echo esc_html__('Option 2', 'ltl-bookings'); ?></option>
                </select>
                <textarea class="ltlb-input" rows="3" placeholder="<?php echo esc_attr__('Enter message', 'ltl-bookings'); ?>"></textarea>
            </div>
            
            <h3><?php echo esc_html__('Documentation', 'ltl-bookings'); ?></h3>
            <p>
                <?php 
                printf(
                    esc_html__('For complete documentation, see %s', 'ltl-bookings'),
                    '<a href="' . esc_url(plugins_url('docs/DESIGN_SYSTEM.md', dirname(__DIR__))) . '" target="_blank">' . esc_html__('DESIGN_SYSTEM.md', 'ltl-bookings') . '</a>'
                ); 
                ?>
            </p>
        </div>
        <?php
    }
}
