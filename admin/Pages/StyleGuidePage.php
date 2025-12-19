<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Style Guide Admin Page
 * 
 * Displays the complete design system component library
 * for reference and testing purposes.
 * 
 * @since 2.0.0
 */
class LTLB_Admin_StyleGuidePage {

    public function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }
        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_styleguide'); } ?>
            
            <h1 class="wp-heading-inline"><?php echo esc_html__('Design System Style Guide', 'ltl-bookings'); ?></h1>
            <hr class="wp-header-end">
            
            <p class="description">
                <?php echo esc_html__('Complete reference of available design system components. Use this page to test styling and copy code examples.', 'ltl-bookings'); ?>
            </p>

            <!-- COLOR TOKENS -->
            <?php LTLB_Admin_Component::card_start(__( 'Color Tokens', 'ltl-bookings' )); ?>
                <div class="ltlb-grid ltlb-grid--3">
                    <div>
                        <h3><?php echo esc_html__('Brand Colors', 'ltl-bookings'); ?></h3>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php 
                            $brand_colors = [
                                'primary' => '#2271b1',
                                'primary-hover' => '#135e96',
                                'primary-rgb' => '34, 113, 177',
                            ];
                            foreach ($brand_colors as $name => $value) {
                                echo '<div style="display: flex; align-items: center; gap: 12px;">';
                                echo '<div style="width: 48px; height: 48px; background: ' . esc_attr($value) . '; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;"></div>';
                                echo '<div><code>--ltlb-' . esc_html($name) . '</code><br><small>' . esc_html($value) . '</small></div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    <div>
                        <h3><?php echo esc_html__('Semantic Colors', 'ltl-bookings'); ?></h3>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php 
                            $semantic_colors = [
                                'success' => '#46b450',
                                'warning' => '#f0ad4e',
                                'danger' => '#dc3545',
                                'info' => '#3582c4',
                            ];
                            foreach ($semantic_colors as $name => $value) {
                                echo '<div style="display: flex; align-items: center; gap: 12px;">';
                                echo '<div style="width: 48px; height: 48px; background: ' . esc_attr($value) . '; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;"></div>';
                                echo '<div><code>--ltlb-color-' . esc_html($name) . '</code><br><small>' . esc_html($value) . '</small></div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    <div>
                        <h3><?php echo esc_html__('Neutral Colors', 'ltl-bookings'); ?></h3>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php 
                            $neutral_colors = [
                                'text' => '#1d2327',
                                'text-muted' => '#646970',
                                'border' => '#c3c4c7',
                                'bg' => '#f0f0f1',
                            ];
                            foreach ($neutral_colors as $name => $value) {
                                echo '<div style="display: flex; align-items: center; gap: 12px;">';
                                echo '<div style="width: 48px; height: 48px; background: ' . esc_attr($value) . '; border: 1px solid rgba(0,0,0,0.1); border-radius: 4px;"></div>';
                                echo '<div><code>--ltlb-color-' . esc_html($name) . '</code><br><small>' . esc_html($value) . '</small></div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php LTLB_Admin_Component::card_end(); ?>

            <!-- BUTTONS -->
            <?php LTLB_Admin_Component::card_start(__( 'Buttons', 'ltl-bookings' )); ?>
                <h3><?php echo esc_html__('Button Variants', 'ltl-bookings'); ?></h3>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;">
                    <button class="ltlb-btn ltlb-btn--primary"><?php echo esc_html__('Primary', 'ltl-bookings'); ?></button>
                    <button class="ltlb-btn ltlb-btn--secondary"><?php echo esc_html__('Secondary', 'ltl-bookings'); ?></button>
                    <button class="ltlb-btn ltlb-btn--danger"><?php echo esc_html__('Danger', 'ltl-bookings'); ?></button>
                    <button class="ltlb-btn ltlb-btn--ghost"><?php echo esc_html__('Ghost', 'ltl-bookings'); ?></button>
                    <button class="ltlb-btn ltlb-btn--link"><?php echo esc_html__('Link', 'ltl-bookings'); ?></button>
                </div>

                <h3><?php echo esc_html__('Button Sizes', 'ltl-bookings'); ?></h3>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 24px;">
                    <button class="ltlb-btn ltlb-btn--primary ltlb-btn--small"><?php echo esc_html__('Small', 'ltl-bookings'); ?></button>
                    <button class="ltlb-btn ltlb-btn--primary"><?php echo esc_html__('Default', 'ltl-bookings'); ?></button>
                </div>

                <h3><?php echo esc_html__('Button States', 'ltl-bookings'); ?></h3>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;">
                    <button class="ltlb-btn ltlb-btn--primary"><?php echo esc_html__('Normal', 'ltl-bookings'); ?></button>
                    <button class="ltlb-btn ltlb-btn--primary" disabled><?php echo esc_html__('Disabled', 'ltl-bookings'); ?></button>
                    <button class="ltlb-btn ltlb-btn--primary ltlb-btn--loading">
                        <span class="ltlb-spinner" aria-hidden="true"></span>
                        <?php echo esc_html__('Loading', 'ltl-bookings'); ?>
                    </button>
                </div>

                <h3><?php echo esc_html__('Code Example', 'ltl-bookings'); ?></h3>
                <pre><code>&lt;button class="ltlb-btn ltlb-btn--primary"&gt;<?php echo esc_html__('Click me', 'ltl-bookings'); ?>&lt;/button&gt;</code></pre>
            <?php LTLB_Admin_Component::card_end(); ?>

            <!-- BADGES -->
            <?php LTLB_Admin_Component::card_start(__( 'Badges', 'ltl-bookings' )); ?>
                <h3><?php echo esc_html__('Badge Colors', 'ltl-bookings'); ?></h3>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;">
                    <span class="ltlb-badge ltlb-badge--success"><?php echo esc_html__('Success', 'ltl-bookings'); ?></span>
                    <span class="ltlb-badge ltlb-badge--warning"><?php echo esc_html__('Warning', 'ltl-bookings'); ?></span>
                    <span class="ltlb-badge ltlb-badge--danger"><?php echo esc_html__('Danger', 'ltl-bookings'); ?></span>
                    <span class="ltlb-badge ltlb-badge--info"><?php echo esc_html__('Info', 'ltl-bookings'); ?></span>
                    <span class="ltlb-badge ltlb-badge--neutral"><?php echo esc_html__('Neutral', 'ltl-bookings'); ?></span>
                </div>

                <h3><?php echo esc_html__('Usage Examples', 'ltl-bookings'); ?></h3>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;">
                    <span class="ltlb-badge ltlb-badge--success"><?php echo esc_html__('Confirmed', 'ltl-bookings'); ?></span>
                    <span class="ltlb-badge ltlb-badge--warning"><?php echo esc_html__('Pending', 'ltl-bookings'); ?></span>
                    <span class="ltlb-badge ltlb-badge--danger"><?php echo esc_html__('Cancelled', 'ltl-bookings'); ?></span>
                    <span class="ltlb-badge ltlb-badge--info"><?php echo esc_html__('New', 'ltl-bookings'); ?></span>
                    <span class="ltlb-badge ltlb-badge--neutral">42</span>
                </div>

                <h3><?php echo esc_html__('Code Example', 'ltl-bookings'); ?></h3>
                <pre><code>&lt;span class="ltlb-badge ltlb-badge--success"&gt;<?php echo esc_html__('Confirmed', 'ltl-bookings'); ?>&lt;/span&gt;</code></pre>
            <?php LTLB_Admin_Component::card_end(); ?>

            <!-- ALERTS -->
            <?php LTLB_Admin_Component::card_start(__( 'Alerts', 'ltl-bookings' )); ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div class="ltlb-alert ltlb-alert--success">
                        <strong><?php echo esc_html__('Success:', 'ltl-bookings'); ?></strong> 
                        <?php echo esc_html__('Your booking has been confirmed!', 'ltl-bookings'); ?>
                    </div>
                    <div class="ltlb-alert ltlb-alert--warning">
                        <strong><?php echo esc_html__('Warning:', 'ltl-bookings'); ?></strong> 
                        <?php echo esc_html__('This action cannot be undone.', 'ltl-bookings'); ?>
                    </div>
                    <div class="ltlb-alert ltlb-alert--danger">
                        <strong><?php echo esc_html__('Error:', 'ltl-bookings'); ?></strong> 
                        <?php echo esc_html__('Payment failed. Please try again.', 'ltl-bookings'); ?>
                    </div>
                    <div class="ltlb-alert ltlb-alert--info">
                        <strong><?php echo esc_html__('Info:', 'ltl-bookings'); ?></strong> 
                        <?php echo esc_html__('You can change this setting later.', 'ltl-bookings'); ?>
                    </div>
                </div>

                <h3 style="margin-top: 24px;"><?php echo esc_html__('Code Example', 'ltl-bookings'); ?></h3>
                <pre><code>&lt;div class="ltlb-alert ltlb-alert--success"&gt;
    &lt;strong&gt;Success:&lt;/strong&gt; Your booking has been confirmed!
&lt;/div&gt;</code></pre>
            <?php LTLB_Admin_Component::card_end(); ?>

            <!-- FORM ELEMENTS -->
            <?php LTLB_Admin_Component::card_start(__( 'Form Elements', 'ltl-bookings' )); ?>
                <div style="max-width: 600px;">
                    <h3><?php echo esc_html__('Text Input', 'ltl-bookings'); ?></h3>
                    <div style="margin-bottom: 24px;">
                        <input type="text" class="ltlb-input" placeholder="<?php echo esc_attr__('Enter your name', 'ltl-bookings'); ?>">
                    </div>

                    <h3><?php echo esc_html__('Input States', 'ltl-bookings'); ?></h3>
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
                        <input type="text" class="ltlb-input" placeholder="<?php echo esc_attr__('Normal state', 'ltl-bookings'); ?>">
                        <input type="text" class="ltlb-input ltlb-input--error" placeholder="<?php echo esc_attr__('Error state', 'ltl-bookings'); ?>">
                        <input type="text" class="ltlb-input" disabled placeholder="<?php echo esc_attr__('Disabled', 'ltl-bookings'); ?>">
                    </div>

                    <h3><?php echo esc_html__('Select', 'ltl-bookings'); ?></h3>
                    <div style="margin-bottom: 24px;">
                        <select class="ltlb-input">
                            <option><?php echo esc_html__('Select option', 'ltl-bookings'); ?></option>
                            <option><?php echo esc_html__('Option 1', 'ltl-bookings'); ?></option>
                            <option><?php echo esc_html__('Option 2', 'ltl-bookings'); ?></option>
                            <option><?php echo esc_html__('Option 3', 'ltl-bookings'); ?></option>
                        </select>
                    </div>

                    <h3><?php echo esc_html__('Textarea', 'ltl-bookings'); ?></h3>
                    <div style="margin-bottom: 24px;">
                        <textarea class="ltlb-input" rows="4" placeholder="<?php echo esc_attr__('Enter your message', 'ltl-bookings'); ?>"></textarea>
                    </div>
                </div>

                <h3><?php echo esc_html__('Code Example', 'ltl-bookings'); ?></h3>
                <pre><code>&lt;input type="text" class="ltlb-input" placeholder="Enter your name"&gt;</code></pre>
            <?php LTLB_Admin_Component::card_end(); ?>

            <!-- TABLES -->
            <?php LTLB_Admin_Component::card_start(__( 'Tables', 'ltl-bookings' )); ?>
                <h3><?php echo esc_html__('Basic Table', 'ltl-bookings'); ?></h3>
                <table class="ltlb-table" style="margin-bottom: 24px;">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Name', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Email', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>John Doe</td>
                            <td>john@example.com</td>
                            <td><span class="ltlb-badge ltlb-badge--success"><?php echo esc_html__('Active', 'ltl-bookings'); ?></span></td>
                        </tr>
                        <tr>
                            <td>Jane Smith</td>
                            <td>jane@example.com</td>
                            <td><span class="ltlb-badge ltlb-badge--warning"><?php echo esc_html__('Pending', 'ltl-bookings'); ?></span></td>
                        </tr>
                    </tbody>
                </table>

                <h3><?php echo esc_html__('Hoverable Table', 'ltl-bookings'); ?></h3>
                <table class="ltlb-table ltlb-table--hoverable">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Service', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Duration', 'ltl-bookings'); ?></th>
                            <th><?php echo esc_html__('Price', 'ltl-bookings'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo esc_html__('Yoga Class', 'ltl-bookings'); ?></td>
                            <td>60 min</td>
                            <td>€25.00</td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Massage', 'ltl-bookings'); ?></td>
                            <td>90 min</td>
                            <td>€80.00</td>
                        </tr>
                    </tbody>
                </table>

                <h3 style="margin-top: 24px;"><?php echo esc_html__('Code Example', 'ltl-bookings'); ?></h3>
                <pre><code>&lt;table class="ltlb-table ltlb-table--hoverable"&gt;
    &lt;thead&gt;...&lt;/thead&gt;
    &lt;tbody&gt;...&lt;/tbody&gt;
&lt;/table&gt;</code></pre>
            <?php LTLB_Admin_Component::card_end(); ?>

            <!-- CARDS -->
            <?php LTLB_Admin_Component::card_start(__( 'Cards', 'ltl-bookings' )); ?>
                <div class="ltlb-grid ltlb-grid--2" style="margin-bottom: 24px;">
                    <div class="ltlb-card">
                        <h3><?php echo esc_html__('Basic Card', 'ltl-bookings'); ?></h3>
                        <p><?php echo esc_html__('This is a basic card with default styling.', 'ltl-bookings'); ?></p>
                    </div>
                    <div class="ltlb-card ltlb-card--elevated">
                        <h3><?php echo esc_html__('Elevated Card', 'ltl-bookings'); ?></h3>
                        <p><?php echo esc_html__('This card has a shadow for emphasis.', 'ltl-bookings'); ?></p>
                    </div>
                </div>

                <h3><?php echo esc_html__('Code Example', 'ltl-bookings'); ?></h3>
                <pre><code>&lt;div class="ltlb-card ltlb-card--elevated"&gt;
    &lt;h3&gt;Card Title&lt;/h3&gt;
    &lt;p&gt;Card content goes here.&lt;/p&gt;
&lt;/div&gt;</code></pre>
            <?php LTLB_Admin_Component::card_end(); ?>

            <!-- SPACING & LAYOUT -->
            <?php LTLB_Admin_Component::card_start(__( 'Spacing & Layout', 'ltl-bookings' )); ?>
                <h3><?php echo esc_html__('Spacing Scale (4px base)', 'ltl-bookings'); ?></h3>
                <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px;">
                    <?php 
                    $spacings = [1 => '4px', 2 => '8px', 3 => '12px', 4 => '16px', 6 => '24px', 8 => '32px', 12 => '48px'];
                    foreach ($spacings as $unit => $px) {
                        echo '<div style="display: flex; align-items: center; gap: 12px;">';
                        echo '<div style="width: 100px;"><code>--ltlb-space-' . $unit . '</code></div>';
                        echo '<div style="width: ' . esc_attr($px) . '; height: 24px; background: var(--ltlb-primary); border-radius: 2px;"></div>';
                        echo '<div>' . esc_html($px) . '</div>';
                        echo '</div>';
                    }
                    ?>
                </div>

                <h3><?php echo esc_html__('Grid Layouts', 'ltl-bookings'); ?></h3>
                <p class="description"><?php echo esc_html__('Responsive grid classes:', 'ltl-bookings'); ?> <code>.ltlb-grid--2</code>, <code>.ltlb-grid--3</code>, <code>.ltlb-grid--4</code></p>
            <?php LTLB_Admin_Component::card_end(); ?>

            <!-- DOCUMENTATION LINK -->
            <?php LTLB_Admin_Component::card_start(__( 'Documentation', 'ltl-bookings' )); ?>
                <p>
                    <?php 
                    printf(
                        esc_html__('For complete documentation including all component variants, accessibility guidelines, and implementation notes, see %s', 'ltl-bookings'),
                        '<code>/docs/DESIGN_SYSTEM.md</code>'
                    ); 
                    ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_design')); ?>" class="ltlb-btn ltlb-btn--secondary">
                        <?php echo esc_html__('Customize Design', 'ltl-bookings'); ?>
                    </a>
                </p>
            <?php LTLB_Admin_Component::card_end(); ?>

        </div>
        <?php
    }
}
