<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Progressive Disclosure UI Helper
 * 
 * Features:
 * - Basic/Advanced settings sections
 * - Mode-specific field visibility
 * - Collapsible sections
 * - Smart defaults
 * - Context-aware help text
 * 
 * @package LazyBookings
 */
class LTLB_Progressive_UI {

    /**
     * Render settings section with progressive disclosure
     * 
     * @param array $args Section configuration
     */
    public static function render_section( array $args ): void {
        $defaults = [
            'id' => '',
            'title' => '',
            'fields' => [],
            'level' => 'basic', // 'basic' or 'advanced'
            'mode' => 'all', // 'all', 'service', 'hotel'
            'collapsed' => false,
            'help_text' => ''
        ];

        $config = wp_parse_args( $args, $defaults );

        // Check mode visibility
        if ( ! self::is_visible_in_current_mode( $config['mode'] ) ) {
            return;
        }

        $section_class = 'ltlb-settings-section';
        $section_class .= ' ltlb-level-' . esc_attr( $config['level'] );
        if ( $config['collapsed'] ) {
            $section_class .= ' ltlb-collapsed';
        }

        ?>
        <div class="<?php echo esc_attr( $section_class ); ?>" data-section-id="<?php echo esc_attr( $config['id'] ); ?>">
            <div class="ltlb-section-header" onclick="ltlbToggleSection(this)">
                <h3>
                    <?php echo esc_html( $config['title'] ); ?>
                    <?php if ( $config['level'] === 'advanced' ): ?>
                        <span class="ltlb-badge ltlb-badge-advanced"><?php esc_html_e( 'Advanced', 'ltl-bookings' ); ?></span>
                    <?php endif; ?>
                </h3>
                <?php if ( $config['help_text'] ): ?>
                    <span class="ltlb-help-icon" title="<?php echo esc_attr( $config['help_text'] ); ?>">
                        <span class="dashicons dashicons-info"></span>
                    </span>
                <?php endif; ?>
                <span class="ltlb-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
            </div>
            
            <div class="ltlb-section-content">
                <table class="form-table">
                    <?php foreach ( $config['fields'] as $field ): ?>
                        <?php self::render_field( $field ); ?>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render individual field
     * 
     * @param array $field Field configuration
     */
    public static function render_field( array $field ): void {
        $defaults = [
            'id' => '',
            'name' => '',
            'label' => '',
            'type' => 'text',
            'value' => '',
            'options' => [],
            'description' => '',
            'help_text' => '',
            'mode' => 'all',
            'required' => false,
            'class' => '',
            'placeholder' => ''
        ];

        $config = wp_parse_args( $field, $defaults );

        // Check mode visibility
        if ( ! self::is_visible_in_current_mode( $config['mode'] ) ) {
            return;
        }

        $field_id = esc_attr( $config['id'] );
        $field_name = esc_attr( $config['name'] );
        $field_class = 'ltlb-field ' . esc_attr( $config['class'] );

        ?>
        <tr class="<?php echo esc_attr( $field_class ); ?>" data-field-id="<?php echo $field_id; ?>">
            <th scope="row">
                <label for="<?php echo $field_id; ?>">
                    <?php echo esc_html( $config['label'] ); ?>
                    <?php if ( $config['required'] ): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
                <?php if ( $config['help_text'] ): ?>
                    <span class="ltlb-help-tooltip dashicons dashicons-editor-help" 
                          title="<?php echo esc_attr( $config['help_text'] ); ?>"></span>
                <?php endif; ?>
            </th>
            <td>
                <?php self::render_field_input( $config ); ?>
                <?php if ( $config['description'] ): ?>
                    <p class="description"><?php echo wp_kses_post( $config['description'] ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Render field input element
     * 
     * @param array $config Field configuration
     */
    private static function render_field_input( array $config ): void {
        $field_id = esc_attr( $config['id'] );
        $field_name = esc_attr( $config['name'] );
        $value = $config['value'];

        switch ( $config['type'] ) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
                ?>
                <input type="<?php echo esc_attr( $config['type'] ); ?>" 
                       id="<?php echo $field_id; ?>" 
                       name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr( $value ); ?>"
                       placeholder="<?php echo esc_attr( $config['placeholder'] ); ?>"
                       <?php echo $config['required'] ? 'required' : ''; ?>
                       class="regular-text" />
                <?php
                break;

            case 'textarea':
                ?>
                <textarea id="<?php echo $field_id; ?>" 
                          name="<?php echo $field_name; ?>"
                          placeholder="<?php echo esc_attr( $config['placeholder'] ); ?>"
                          <?php echo $config['required'] ? 'required' : ''; ?>
                          rows="5" 
                          class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
                <?php
                break;

            case 'checkbox':
                ?>
                <label>
                    <input type="checkbox" 
                           id="<?php echo $field_id; ?>" 
                           name="<?php echo $field_name; ?>"
                           value="1"
                           <?php checked( $value, 1 ); ?> />
                    <?php echo esc_html( $config['description'] ?? '' ); ?>
                </label>
                <?php
                break;

            case 'select':
                ?>
                <select id="<?php echo $field_id; ?>" 
                        name="<?php echo $field_name; ?>"
                        <?php echo $config['required'] ? 'required' : ''; ?>>
                    <?php foreach ( $config['options'] as $opt_value => $opt_label ): ?>
                        <option value="<?php echo esc_attr( $opt_value ); ?>" 
                                <?php selected( $value, $opt_value ); ?>>
                            <?php echo esc_html( $opt_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;

            case 'radio':
                ?>
                <fieldset>
                    <?php foreach ( $config['options'] as $opt_value => $opt_label ): ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" 
                                   name="<?php echo $field_name; ?>"
                                   value="<?php echo esc_attr( $opt_value ); ?>"
                                   <?php checked( $value, $opt_value ); ?> />
                            <?php echo esc_html( $opt_label ); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php
                break;

            case 'color':
                ?>
                <input type="color" 
                       id="<?php echo $field_id; ?>" 
                       name="<?php echo $field_name; ?>"
                       value="<?php echo esc_attr( $value ); ?>" />
                <?php
                break;
        }
    }

    /**
     * Check if field/section should be visible in current mode
     * 
     * @param string $mode Required mode ('all', 'service', 'hotel')
     * @return bool Visibility
     */
    private static function is_visible_in_current_mode( string $mode ): bool {
        if ( $mode === 'all' ) {
            return true;
        }

        $current_mode = self::get_current_mode();
        return $mode === $current_mode;
    }

    /**
     * Get current template mode
     * 
     * @return string Current mode ('service' or 'hotel')
     */
    public static function get_current_mode(): string {
        $settings = get_option( 'lazy_settings', [] );
        return $settings['template_mode'] ?? 'service';
    }

    /**
     * Render mode switcher
     */
    public static function render_mode_switcher(): void {
        $current_mode = self::get_current_mode();
        ?>
        <div class="ltlb-mode-switcher">
            <h3><?php esc_html_e( 'Booking Mode', 'ltl-bookings' ); ?></h3>
            <div class="ltlb-mode-buttons">
                <button type="button" 
                        class="ltlb-mode-btn <?php echo $current_mode === 'service' ? 'active' : ''; ?>"
                        data-mode="service"
                        onclick="ltlbSwitchMode('service')">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e( 'Service Booking', 'ltl-bookings' ); ?>
                    <span class="ltlb-mode-desc"><?php esc_html_e( 'Appointments, classes, consultations', 'ltl-bookings' ); ?></span>
                </button>
                <button type="button" 
                        class="ltlb-mode-btn <?php echo $current_mode === 'hotel' ? 'active' : ''; ?>"
                        data-mode="hotel"
                        onclick="ltlbSwitchMode('hotel')">
                    <span class="dashicons dashicons-building"></span>
                    <?php esc_html_e( 'Hotel Booking', 'ltl-bookings' ); ?>
                    <span class="ltlb-mode-desc"><?php esc_html_e( 'Rooms, accommodations, rentals', 'ltl-bookings' ); ?></span>
                </button>
            </div>
            <input type="hidden" name="template_mode" id="template_mode" value="<?php echo esc_attr( $current_mode ); ?>" />
        </div>
        <?php
    }

    /**
     * Render basic/advanced toggle
     */
    public static function render_level_toggle(): void {
        ?>
        <div class="ltlb-level-toggle">
            <button type="button" class="button" onclick="ltlbToggleAdvanced()">
                <span class="show-advanced"><?php esc_html_e( 'Show Advanced Settings', 'ltl-bookings' ); ?></span>
                <span class="hide-advanced" style="display:none;"><?php esc_html_e( 'Hide Advanced Settings', 'ltl-bookings' ); ?></span>
            </button>
        </div>
        <?php
    }

    /**
     * Get default field values
     * 
     * @return array Default values by mode
     */
    public static function get_defaults(): array {
        return [
            'service' => [
                'working_hours_start' => 9,
                'working_hours_end' => 17,
                'slot_size_minutes' => 60,
                'default_status' => 'pending',
                'pending_blocks' => 1,
                'timezone' => wp_timezone_string()
            ],
            'hotel' => [
                'working_hours_start' => 14, // Check-in time
                'working_hours_end' => 11, // Check-out time
                'slot_size_minutes' => 1440, // Full day
                'default_status' => 'pending',
                'pending_blocks' => 1,
                'timezone' => wp_timezone_string()
            ]
        ];
    }

    /**
     * Enqueue progressive UI scripts
     */
    public static function enqueue_scripts(): void {
        ?>
        <script>
        function ltlbToggleSection(header) {
            const section = header.closest('.ltlb-settings-section');
            section.classList.toggle('ltlb-collapsed');
        }

        function ltlbToggleAdvanced() {
            document.body.classList.toggle('ltlb-show-advanced');
            const showText = document.querySelector('.show-advanced');
            const hideText = document.querySelector('.hide-advanced');
            
            if (document.body.classList.contains('ltlb-show-advanced')) {
                showText.style.display = 'none';
                hideText.style.display = 'inline';
            } else {
                showText.style.display = 'inline';
                hideText.style.display = 'none';
            }
        }

        function ltlbSwitchMode(mode) {
            const modeField = document.getElementById('template_mode');
            if (modeField) {
                modeField.value = mode;
            }

            // Update active button
            document.querySelectorAll('.ltlb-mode-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector('[data-mode="' + mode + '"]').classList.add('active');

            // Update field visibility
            ltlbUpdateModeVisibility(mode);
        }

        function ltlbUpdateModeVisibility(mode) {
            // Hide all mode-specific fields first
            document.querySelectorAll('[data-mode]').forEach(el => {
                const fieldMode = el.dataset.mode;
                if (fieldMode && fieldMode !== 'all' && fieldMode !== mode) {
                    el.style.display = 'none';
                } else {
                    el.style.display = '';
                }
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentMode = document.getElementById('template_mode')?.value || 'service';
            ltlbUpdateModeVisibility(currentMode);
        });
        </script>
        <?php
    }

    /**
     * Enqueue progressive UI styles
     */
    public static function enqueue_styles(): void {
        ?>
        <style>
        .ltlb-settings-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }

        .ltlb-section-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ccd0d4;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
        }

        .ltlb-section-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ltlb-section-header:hover {
            background: #f9f9f9;
        }

        .ltlb-section-content {
            padding: 20px;
        }

        .ltlb-settings-section.ltlb-collapsed .ltlb-section-content {
            display: none;
        }

        .ltlb-settings-section.ltlb-collapsed .ltlb-toggle-icon {
            transform: rotate(-90deg);
        }

        .ltlb-toggle-icon {
            transition: transform 0.2s;
            color: #50575e;
        }

        /* Advanced sections hidden by default */
        .ltlb-level-advanced {
            display: none;
        }

        body.ltlb-show-advanced .ltlb-level-advanced {
            display: block;
        }

        .ltlb-badge-advanced {
            background: #2271b1;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .ltlb-help-icon, .ltlb-help-tooltip {
            color: #50575e;
            cursor: help;
        }

        .ltlb-mode-switcher {
            background: #f0f0f1;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .ltlb-mode-switcher h3 {
            margin-top: 0;
        }

        .ltlb-mode-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .ltlb-mode-btn {
            padding: 20px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .ltlb-mode-btn .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
        }

        .ltlb-mode-btn.active {
            border-color: #2271b1;
            background: #f0f6fc;
            box-shadow: 0 0 0 1px #2271b1;
        }

        .ltlb-mode-desc {
            font-size: 12px;
            color: #646970;
        }

        .ltlb-level-toggle {
            margin: 20px 0;
        }

        .required {
            color: #d63638;
        }
        </style>
        <?php
    }
}
