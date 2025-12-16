<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Branding & Design System Page
 * 
 * Provides design tokens (colors, typography, spacing) with presets
 * for frontend booking forms and admin components.
 */
class LTLB_Admin_BrandingPage {

    public static function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }

        // Handle save
        if ( isset( $_POST['ltlb_save_branding'] ) && check_admin_referer('ltlb_save_branding', 'ltlb_branding_nonce') ) {
            $branding = [
                'preset' => sanitize_key( $_POST['branding_preset'] ?? 'default' ),
                'primary_color' => sanitize_hex_color( $_POST['primary_color'] ?? '#2271b1' ),
                'accent_color' => sanitize_hex_color( $_POST['accent_color'] ?? '#135e96' ),
                'success_color' => sanitize_hex_color( $_POST['success_color'] ?? '#00a32a' ),
                'error_color' => sanitize_hex_color( $_POST['error_color'] ?? '#d63638' ),
                'text_color' => sanitize_hex_color( $_POST['text_color'] ?? '#1d2327' ),
                'background_color' => sanitize_hex_color( $_POST['background_color'] ?? '#ffffff' ),
                'border_radius' => intval( $_POST['border_radius'] ?? 4 ),
                'font_family' => sanitize_text_field( $_POST['font_family'] ?? 'inherit' ),
                'button_style' => sanitize_key( $_POST['button_style'] ?? 'solid' ),
                'custom_css' => wp_kses_post( $_POST['custom_css'] ?? '' ),
            ];
            
            update_option( 'ltlb_branding', $branding );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Branding settings saved successfully.', 'ltl-bookings' ) . '</p></div>';
        }

        $branding = get_option( 'ltlb_branding', [] );
        $preset = $branding['preset'] ?? 'default';
        $primary_color = $branding['primary_color'] ?? '#2271b1';
        $accent_color = $branding['accent_color'] ?? '#135e96';
        $success_color = $branding['success_color'] ?? '#00a32a';
        $error_color = $branding['error_color'] ?? '#d63638';
        $text_color = $branding['text_color'] ?? '#1d2327';
        $background_color = $branding['background_color'] ?? '#ffffff';
        $border_radius = $branding['border_radius'] ?? 4;
        $font_family = $branding['font_family'] ?? 'inherit';
        $button_style = $branding['button_style'] ?? 'solid';
        $custom_css = $branding['custom_css'] ?? '';

        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_branding'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__('Branding & Design', 'ltl-bookings'); ?></h1>
            <hr class="wp-header-end">

            <form method="post" class="ltlb-branding-form">
                <?php wp_nonce_field('ltlb_save_branding', 'ltlb_branding_nonce'); ?>

                <div class="ltlb-card">
                    <h2><?php echo esc_html__('Design Presets', 'ltl-bookings'); ?></h2>
                    <p class="description"><?php echo esc_html__('Choose a preset to quickly style your booking forms. You can customize colors and styles below.', 'ltl-bookings'); ?></p>
                    
                    <div class="ltlb-preset-selector" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 20px;">
                        <?php
                        $presets = [
                            'default' => [
                                'name' => __('Default Blue', 'ltl-bookings'),
                                'primary' => '#2271b1',
                                'accent' => '#135e96',
                            ],
                            'minimal' => [
                                'name' => __('Minimal Black', 'ltl-bookings'),
                                'primary' => '#1d2327',
                                'accent' => '#646970',
                            ],
                            'vibrant' => [
                                'name' => __('Vibrant Purple', 'ltl-bookings'),
                                'primary' => '#7c3aed',
                                'accent' => '#5b21b6',
                            ],
                            'nature' => [
                                'name' => __('Nature Green', 'ltl-bookings'),
                                'primary' => '#059669',
                                'accent' => '#047857',
                            ],
                            'ocean' => [
                                'name' => __('Ocean Teal', 'ltl-bookings'),
                                'primary' => '#0891b2',
                                'accent' => '#0e7490',
                            ],
                            'sunset' => [
                                'name' => __('Sunset Orange', 'ltl-bookings'),
                                'primary' => '#ea580c',
                                'accent' => '#c2410c',
                            ],
                        ];

                        foreach ( $presets as $key => $data ) {
                            $checked = $preset === $key ? 'checked' : '';
                            ?>
                            <label class="ltlb-preset-card" style="border: 2px solid <?php echo $checked ? $data['primary'] : '#ddd'; ?>; padding: 16px; border-radius: 8px; cursor: pointer;">
                                <input type="radio" name="branding_preset" value="<?php echo esc_attr($key); ?>" <?php echo $checked; ?> 
                                       data-primary="<?php echo esc_attr($data['primary']); ?>" 
                                       data-accent="<?php echo esc_attr($data['accent']); ?>"
                                       style="margin-bottom: 8px;">
                                <div style="font-weight: 600; margin-bottom: 8px;"><?php echo esc_html($data['name']); ?></div>
                                <div style="display: flex; gap: 8px;">
                                    <div style="width: 40px; height: 40px; background: <?php echo esc_attr($data['primary']); ?>; border-radius: 4px;"></div>
                                    <div style="width: 40px; height: 40px; background: <?php echo esc_attr($data['accent']); ?>; border-radius: 4px;"></div>
                                </div>
                            </label>
                            <?php
                        }
                        ?>
                    </div>
                </div>

                <div class="ltlb-card" style="margin-top: 20px;">
                    <h2><?php echo esc_html__('Color Customization', 'ltl-bookings'); ?></h2>
                    <p class="description"><?php echo esc_html__('Fine-tune your brand colors. Changes apply to all booking forms and buttons.', 'ltl-bookings'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="primary_color"><?php echo esc_html__('Primary Color', 'ltl-bookings'); ?></label></th>
                            <td>
                                <input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr($primary_color); ?>" style="height: 40px; width: 100px;">
                                <p class="description"><?php echo esc_html__('Used for buttons, links, and active states.', 'ltl-bookings'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="accent_color"><?php echo esc_html__('Accent Color', 'ltl-bookings'); ?></label></th>
                            <td>
                                <input type="color" id="accent_color" name="accent_color" value="<?php echo esc_attr($accent_color); ?>" style="height: 40px; width: 100px;">
                                <p class="description"><?php echo esc_html__('Used for hover states and highlights.', 'ltl-bookings'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="success_color"><?php echo esc_html__('Success Color', 'ltl-bookings'); ?></label></th>
                            <td>
                                <input type="color" id="success_color" name="success_color" value="<?php echo esc_attr($success_color); ?>" style="height: 40px; width: 100px;">
                                <p class="description"><?php echo esc_html__('Used for success messages and confirmations.', 'ltl-bookings'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="error_color"><?php echo esc_html__('Error Color', 'ltl-bookings'); ?></label></th>
                            <td>
                                <input type="color" id="error_color" name="error_color" value="<?php echo esc_attr($error_color); ?>" style="height: 40px; width: 100px;">
                                <p class="description"><?php echo esc_html__('Used for error messages and required field indicators.', 'ltl-bookings'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="background_color"><?php echo esc_html__('Background Color', 'ltl-bookings'); ?></label></th>
                            <td>
                                <input type="color" id="background_color" name="background_color" value="<?php echo esc_attr($background_color); ?>" style="height: 40px; width: 100px;">
                                <p class="description"><?php echo esc_html__('Background color for form containers.', 'ltl-bookings'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="text_color"><?php echo esc_html__('Text Color', 'ltl-bookings'); ?></label></th>
                            <td>
                                <input type="color" id="text_color" name="text_color" value="<?php echo esc_attr($text_color); ?>" style="height: 40px; width: 100px;">
                                <p class="description"><?php echo esc_html__('Primary text color.', 'ltl-bookings'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ltlb-card" style="margin-top: 20px;">
                    <h2><?php echo esc_html__('Typography & Style', 'ltl-bookings'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="font_family"><?php echo esc_html__('Font Family', 'ltl-bookings'); ?></label></th>
                            <td>
                                <select id="font_family" name="font_family">
                                    <option value="inherit" <?php selected($font_family, 'inherit'); ?>><?php echo esc_html__('Inherit from theme', 'ltl-bookings'); ?></option>
                                    <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif" <?php selected($font_family, "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"); ?>>System UI</option>
                                    <option value="'Inter', sans-serif" <?php selected($font_family, "'Inter', sans-serif"); ?>>Inter</option>
                                    <option value="'Poppins', sans-serif" <?php selected($font_family, "'Poppins', sans-serif"); ?>>Poppins</option>
                                    <option value="'Montserrat', sans-serif" <?php selected($font_family, "'Montserrat', sans-serif"); ?>>Montserrat</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="border_radius"><?php echo esc_html__('Border Radius (px)', 'ltl-bookings'); ?></label></th>
                            <td>
                                <input type="number" id="border_radius" name="border_radius" value="<?php echo esc_attr($border_radius); ?>" min="0" max="50" step="1" style="width: 100px;">
                                <p class="description"><?php echo esc_html__('Roundness of buttons and inputs (0 = square, 20 = very round).', 'ltl-bookings'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="button_style"><?php echo esc_html__('Button Style', 'ltl-bookings'); ?></label></th>
                            <td>
                                <select id="button_style" name="button_style">
                                    <option value="solid" <?php selected($button_style, 'solid'); ?>><?php echo esc_html__('Solid', 'ltl-bookings'); ?></option>
                                    <option value="outline" <?php selected($button_style, 'outline'); ?>><?php echo esc_html__('Outline', 'ltl-bookings'); ?></option>
                                    <option value="ghost" <?php selected($button_style, 'ghost'); ?>><?php echo esc_html__('Ghost', 'ltl-bookings'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ltlb-card" style="margin-top: 20px;">
                    <h2><?php echo esc_html__('Custom CSS', 'ltl-bookings'); ?></h2>
                    <p class="description"><?php echo esc_html__('Add custom CSS to override or extend the design. Use .ltlb-booking as the wrapper class.', 'ltl-bookings'); ?></p>
                    <textarea name="custom_css" id="custom_css" rows="10" class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
                    <p class="description" style="margin-top: 8px;">
                        <strong><?php echo esc_html__('Example:', 'ltl-bookings'); ?></strong><br>
                        <code>.ltlb-booking .ltlb-button { font-weight: 700; }</code>
                    </p>
                </div>

                <p class="submit">
                    <button type="submit" name="ltlb_save_branding" class="button button-primary"><?php echo esc_html__('Save Branding Settings', 'ltl-bookings'); ?></button>
                </p>
            </form>

            <div class="ltlb-card" style="margin-top: 20px;">
                <h2><?php echo esc_html__('Preview', 'ltl-bookings'); ?></h2>
                <div class="ltlb-branding-preview" style="padding: 20px; background: <?php echo esc_attr($background_color); ?>; border-radius: <?php echo esc_attr($border_radius); ?>px;">
                    <p style="color: <?php echo esc_attr($text_color); ?>; font-family: <?php echo esc_attr($font_family); ?>; margin-bottom: 16px;">
                        <?php echo esc_html__('This is how your booking forms will look with the current settings.', 'ltl-bookings'); ?>
                    </p>
                    <button type="button" style="
                        background: <?php echo $button_style === 'solid' ? esc_attr($primary_color) : 'transparent'; ?>;
                        color: <?php echo $button_style === 'solid' ? '#ffffff' : esc_attr($primary_color); ?>;
                        border: 2px solid <?php echo esc_attr($primary_color); ?>;
                        padding: 12px 24px;
                        border-radius: <?php echo esc_attr($border_radius); ?>px;
                        font-family: <?php echo esc_attr($font_family); ?>;
                        font-weight: 600;
                        cursor: pointer;
                    "><?php echo esc_html__('Book Now', 'ltl-bookings'); ?></button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Auto-update colors when preset changes
            $('input[name="branding_preset"]').on('change', function() {
                var $selected = $(this);
                var primary = $selected.data('primary');
                var accent = $selected.data('accent');
                
                if (primary) $('#primary_color').val(primary);
                if (accent) $('#accent_color').val(accent);
            });
        });
        </script>
        <?php
    }

    /**
     * Generate CSS variables from branding settings
     */
    public static function get_css_variables(): string {
        $branding = get_option( 'ltlb_branding', [] );
        
        $primary = $branding['primary_color'] ?? '#2271b1';
        $accent = $branding['accent_color'] ?? '#135e96';
        $success = $branding['success_color'] ?? '#00a32a';
        $error = $branding['error_color'] ?? '#d63638';
        $text = $branding['text_color'] ?? '#1d2327';
        $bg = $branding['background_color'] ?? '#ffffff';
        $radius = $branding['border_radius'] ?? 4;
        $font = $branding['font_family'] ?? 'inherit';
        $button_style = $branding['button_style'] ?? 'solid';
        
        $css = ":root {\n";
        $css .= "  --ltlb-primary: {$primary};\n";
        $css .= "  --ltlb-accent: {$accent};\n";
        $css .= "  --ltlb-success: {$success};\n";
        $css .= "  --ltlb-error: {$error};\n";
        $css .= "  --ltlb-text: {$text};\n";
        $css .= "  --ltlb-bg: {$bg};\n";
        $css .= "  --ltlb-radius: {$radius}px;\n";
        $css .= "  --ltlb-font: {$font};\n";
        $css .= "  --ltlb-button-style: {$button_style};\n";
        $css .= "}\n";
        
        // Add custom CSS
        if ( ! empty( $branding['custom_css'] ) ) {
            $css .= "\n/* Custom CSS */\n" . $branding['custom_css'];
        }
        
        return $css;
    }

    /**
     * Enqueue branding CSS to frontend
     */
    public static function enqueue_frontend_styles(): void {
        $css = self::get_css_variables();
        wp_add_inline_style( 'ltlb-public', $css );
    }
}
