<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Shortcodes Reference Admin Page
 * 
 * Displays all available shortcodes with usage examples,
 * attributes, and copy-to-clipboard functionality.
 * 
 * @since 2.0.0
 */
class LTLB_Admin_ShortcodesPage {

    public function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }
        
        $settings = get_option( 'lazy_settings', [] );
        $template_mode = is_array( $settings ) && isset( $settings['template_mode'] ) ? $settings['template_mode'] : 'service';
        $is_hotel_mode = $template_mode === 'hotel';
        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_shortcodes'); } ?>
            
            <h1 class="wp-heading-inline"><?php echo esc_html__('Shortcodes Reference', 'ltl-bookings'); ?></h1>
            <hr class="wp-header-end">
            
            <p class="description">
                <?php echo esc_html__('Copy and paste these shortcodes into your pages, posts, or widgets to display booking forms and components.', 'ltl-bookings'); ?>
            </p>

            <style>
                .ltlb-shortcode-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
                    gap: 20px;
                    margin-top: 24px;
                }
                .ltlb-shortcode-card {
                    background: #fff;
                    border: 1px solid #c3c4c7;
                    border-radius: 8px;
                    padding: 20px;
                    transition: all 0.2s ease;
                }
                .ltlb-shortcode-card:hover {
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    border-color: #2271b1;
                }
                .ltlb-shortcode-card h3 {
                    margin: 0 0 8px 0;
                    font-size: 18px;
                    color: #1d2327;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .ltlb-shortcode-card .shortcode-tag {
                    display: inline-block;
                    background: #f0f0f1;
                    color: #2271b1;
                    padding: 2px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .ltlb-shortcode-card .description {
                    color: #646970;
                    font-size: 14px;
                    margin: 0 0 16px 0;
                    line-height: 1.6;
                }
                .ltlb-shortcode-code {
                    position: relative;
                    background: #1d2327;
                    color: #50e3c2;
                    padding: 12px 16px;
                    border-radius: 4px;
                    font-family: 'Courier New', monospace;
                    font-size: 13px;
                    margin: 12px 0;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .ltlb-shortcode-code:hover {
                    background: #2c3338;
                }
                .ltlb-shortcode-code::after {
                    content: 'Click to copy';
                    position: absolute;
                    right: 12px;
                    top: 50%;
                    transform: translateY(-50%);
                    background: #2271b1;
                    color: #fff;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    opacity: 0;
                    transition: opacity 0.2s ease;
                    pointer-events: none;
                }
                .ltlb-shortcode-code:hover::after {
                    opacity: 1;
                }
                .ltlb-shortcode-code.copied::after {
                    content: '✓ Copied!';
                    background: #46b450;
                    opacity: 1;
                }
                .ltlb-attributes {
                    margin: 16px 0 0 0;
                    font-size: 13px;
                }
                .ltlb-attributes dt {
                    color: #2271b1;
                    font-weight: 600;
                    margin-top: 8px;
                    font-family: monospace;
                }
                .ltlb-attributes dd {
                    color: #646970;
                    margin: 2px 0 0 20px;
                }
                .ltlb-section-header {
                    margin: 40px 0 20px 0;
                    padding-bottom: 12px;
                    border-bottom: 2px solid #2271b1;
                }
                .ltlb-section-header h2 {
                    margin: 0;
                    color: #1d2327;
                    font-size: 22px;
                }
                .ltlb-examples {
                    background: #f6f7f7;
                    padding: 12px;
                    border-radius: 4px;
                    margin-top: 12px;
                }
                .ltlb-examples h4 {
                    margin: 0 0 8px 0;
                    font-size: 13px;
                    color: #646970;
                    text-transform: uppercase;
                    font-weight: 600;
                }
                .ltlb-example-code {
                    background: #1d2327;
                    color: #50e3c2;
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    margin: 4px 0;
                    cursor: pointer;
                }
                .ltlb-example-code:hover {
                    background: #2c3338;
                }
            </style>

            <!-- BOOKING SHORTCODES -->
            <div class="ltlb-section-header">
                <h2><?php echo esc_html__('📅 Booking Forms', 'ltl-bookings'); ?></h2>
            </div>

            <div class="ltlb-shortcode-grid">
                <!-- lazy_book -->
                <div class="ltlb-shortcode-card">
                    <h3>
                        <span class="dashicons dashicons-calendar-alt" style="color: #2271b1;"></span>
                        <?php echo esc_html__('Main Booking Form', 'ltl-bookings'); ?>
                        <span class="shortcode-tag">Primary</span>
                    </h3>
                    <p class="description">
                        <?php echo esc_html__('Step-by-step wizard for booking services or rooms. The main booking interface for your customers.', 'ltl-bookings'); ?>
                    </p>
                    
                    <div class="ltlb-shortcode-code" onclick="copyShortcode(this)">
                        [lazy_book]
                    </div>

                    <dl class="ltlb-attributes">
                        <dt>service</dt>
                        <dd><?php echo esc_html__('Preselect a service by ID (optional)', 'ltl-bookings'); ?></dd>
                        <dt>mode</dt>
                        <dd><?php echo esc_html__('Display mode: "wizard" (default) or "calendar"', 'ltl-bookings'); ?></dd>
                    </dl>

                    <div class="ltlb-examples">
                        <h4><?php echo esc_html__('Examples:', 'ltl-bookings'); ?></h4>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_book service="5"]</div>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_book mode="calendar"]</div>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_book service="3" mode="calendar"]</div>
                    </div>
                </div>

                <!-- lazy_book_calendar -->
                <div class="ltlb-shortcode-card">
                    <h3>
                        <span class="dashicons dashicons-calendar" style="color: #2271b1;"></span>
                        <?php echo esc_html__('Calendar View', 'ltl-bookings'); ?>
                    </h3>
                    <p class="description">
                        <?php echo esc_html__('Starts directly with calendar view. Identical to [lazy_book mode="calendar"].', 'ltl-bookings'); ?>
                    </p>
                    
                    <div class="ltlb-shortcode-code" onclick="copyShortcode(this)">
                        [lazy_book_calendar]
                    </div>

                    <dl class="ltlb-attributes">
                        <dt>service</dt>
                        <dd><?php echo esc_html__('Preselect a service by ID (optional)', 'ltl-bookings'); ?></dd>
                    </dl>

                    <div class="ltlb-examples">
                        <h4><?php echo esc_html__('Example:', 'ltl-bookings'); ?></h4>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_book_calendar service="3"]</div>
                    </div>
                </div>
            </div>

            <!-- BOOKING BARS -->
            <div class="ltlb-section-header">
                <h2><?php echo esc_html__('🎯 Booking Bars & Widgets', 'ltl-bookings'); ?></h2>
            </div>

            <div class="ltlb-shortcode-grid">
                <!-- lazy_book_bar -->
                <div class="ltlb-shortcode-card">
                    <h3>
                        <span class="dashicons dashicons-editor-justify" style="color: #2271b1;"></span>
                        <?php echo $is_hotel_mode ? esc_html__('Hotel Booking Bar', 'ltl-bookings') : esc_html__('Service Booking Bar', 'ltl-bookings'); ?>
                    </h3>
                    <p class="description">
                        <?php echo esc_html__('Compact booking bar for quick date/service selection. Perfect for page headers.', 'ltl-bookings'); ?>
                    </p>
                    
                    <div class="ltlb-shortcode-code" onclick="copyShortcode(this)">
                        <?php echo $is_hotel_mode ? '[lazy_hotel_bar]' : '[lazy_book_bar]'; ?>
                    </div>

                    <dl class="ltlb-attributes">
                        <dt>sticky</dt>
                        <dd><?php echo esc_html__('Fixed position when scrolling: "true" or "false" (default)', 'ltl-bookings'); ?></dd>
                        <dt>background</dt>
                        <dd><?php echo esc_html__('Style: "primary" (default), "dark", or "light"', 'ltl-bookings'); ?></dd>
                        <dt>target</dt>
                        <dd><?php echo esc_html__('Target URL for booking page', 'ltl-bookings'); ?></dd>
                        <?php if (!$is_hotel_mode): ?>
                        <dt>mode</dt>
                        <dd><?php echo esc_html__('Booking mode: "wizard" (default) or "calendar"', 'ltl-bookings'); ?></dd>
                        <?php endif; ?>
                    </dl>

                    <div class="ltlb-examples">
                        <h4><?php echo esc_html__('Examples:', 'ltl-bookings'); ?></h4>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)"><?php echo $is_hotel_mode ? '[lazy_hotel_bar sticky="true"]' : '[lazy_book_bar sticky="true"]'; ?></div>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)"><?php echo $is_hotel_mode ? '[lazy_hotel_bar background="dark"]' : '[lazy_book_bar background="dark"]'; ?></div>
                        <?php if (!$is_hotel_mode): ?>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_book_bar target="/booking/" mode="calendar"]</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- lazy_book_widget / lazy_hotel_widget -->
                <div class="ltlb-shortcode-card">
                    <h3>
                        <span class="dashicons dashicons-admin-page" style="color: #2271b1;"></span>
                        <?php echo esc_html__('Booking Widget', 'ltl-bookings'); ?>
                    </h3>
                    <p class="description">
                        <?php echo esc_html__('Compact widget for sidebars or footer. Minimal design with essential fields.', 'ltl-bookings'); ?>
                    </p>
                    
                    <div class="ltlb-shortcode-code" onclick="copyShortcode(this)">
                        <?php echo $is_hotel_mode ? '[lazy_hotel_widget]' : '[lazy_book_widget]'; ?>
                    </div>

                    <dl class="ltlb-attributes">
                        <dt>target</dt>
                        <dd><?php echo esc_html__('Target URL for booking page', 'ltl-bookings'); ?></dd>
                        <?php if (!$is_hotel_mode): ?>
                        <dt>mode</dt>
                        <dd><?php echo esc_html__('Booking mode: "wizard" (default) or "calendar"', 'ltl-bookings'); ?></dd>
                        <?php endif; ?>
                    </dl>

                    <div class="ltlb-examples">
                        <h4><?php echo esc_html__('Example:', 'ltl-bookings'); ?></h4>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)"><?php echo $is_hotel_mode ? '[lazy_hotel_widget target="/rooms/"]' : '[lazy_book_widget target="/booking/"]'; ?></div>
                    </div>
                </div>
            </div>

            <!-- DISPLAY SHORTCODES -->
            <div class="ltlb-section-header">
                <h2><?php echo esc_html__('🎨 Display Components', 'ltl-bookings'); ?></h2>
            </div>

            <div class="ltlb-shortcode-grid">
                <!-- lazy_services / lazy_room_types -->
                <div class="ltlb-shortcode-card">
                    <h3>
                        <span class="dashicons dashicons-grid-view" style="color: #2271b1;"></span>
                        <?php echo $is_hotel_mode ? esc_html__('Room Types Grid', 'ltl-bookings') : esc_html__('Services Grid', 'ltl-bookings'); ?>
                        <span class="shortcode-tag">Popular</span>
                    </h3>
                    <p class="description">
                        <?php echo $is_hotel_mode 
                            ? esc_html__('Display all room types in a responsive grid with images, descriptions, and prices.', 'ltl-bookings')
                            : esc_html__('Display all services in a responsive grid with images, descriptions, and prices.', 'ltl-bookings'); ?>
                    </p>
                    
                    <div class="ltlb-shortcode-code" onclick="copyShortcode(this)">
                        <?php echo $is_hotel_mode ? '[lazy_room_types]' : '[lazy_services]'; ?>
                    </div>

                    <dl class="ltlb-attributes">
                        <dt>columns</dt>
                        <dd><?php echo esc_html__('Number of columns (1-6, default: 3)', 'ltl-bookings'); ?></dd>
                        <dt>show_price</dt>
                        <dd><?php echo esc_html__('Show prices: "true" (default) or "false"', 'ltl-bookings'); ?></dd>
                        <dt>show_description</dt>
                        <dd><?php echo esc_html__('Show descriptions: "true" (default) or "false"', 'ltl-bookings'); ?></dd>
                        <dt>target</dt>
                        <dd><?php echo esc_html__('Target URL for booking buttons', 'ltl-bookings'); ?></dd>
                        <?php if (!$is_hotel_mode): ?>
                        <dt>mode</dt>
                        <dd><?php echo esc_html__('Booking mode: "wizard" or "calendar"', 'ltl-bookings'); ?></dd>
                        <?php endif; ?>
                    </dl>

                    <div class="ltlb-examples">
                        <h4><?php echo esc_html__('Examples:', 'ltl-bookings'); ?></h4>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)"><?php echo $is_hotel_mode ? '[lazy_room_types columns="2"]' : '[lazy_services columns="4"]'; ?></div>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)"><?php echo $is_hotel_mode ? '[lazy_room_types show_description="false"]' : '[lazy_services show_description="false"]'; ?></div>
                        <?php if (!$is_hotel_mode): ?>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_services target="/booking/" mode="calendar"]</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- lazy_trust -->
                <div class="ltlb-shortcode-card">
                    <h3>
                        <span class="dashicons dashicons-awards" style="color: #2271b1;"></span>
                        <?php echo esc_html__('Trust Section', 'ltl-bookings'); ?>
                    </h3>
                    <p class="description">
                        <?php echo esc_html__('Social proof section with statistics, ratings, and guarantees to build customer confidence.', 'ltl-bookings'); ?>
                    </p>
                    
                    <div class="ltlb-shortcode-code" onclick="copyShortcode(this)">
                        [lazy_trust]
                    </div>

                    <dl class="ltlb-attributes">
                        <dt>title</dt>
                        <dd><?php echo esc_html__('Custom section title (optional)', 'ltl-bookings'); ?></dd>
                        <dt>subtitle</dt>
                        <dd><?php echo esc_html__('Custom subtitle (optional)', 'ltl-bookings'); ?></dd>
                        <dt>style</dt>
                        <dd><?php echo esc_html__('Design style: "default", "compact", or "flat"', 'ltl-bookings'); ?></dd>
                        <dt>button_url</dt>
                        <dd><?php echo esc_html__('URL for the call-to-action button', 'ltl-bookings'); ?></dd>
                        <dt>button_text</dt>
                        <dd><?php echo esc_html__('Custom button text (default: "Start booking")', 'ltl-bookings'); ?></dd>
                    </dl>

                    <div class="ltlb-examples">
                        <h4><?php echo esc_html__('Examples:', 'ltl-bookings'); ?></h4>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_trust style="compact"]</div>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_trust title="5000+ Happy Customers" button_url="/booking/"]</div>
                    </div>
                </div>
            </div>

            <!-- UTILITY SHORTCODES -->
            <div class="ltlb-section-header">
                <h2><?php echo esc_html__('🔧 Utility Components', 'ltl-bookings'); ?></h2>
            </div>

            <div class="ltlb-shortcode-grid">
                <!-- lazy_lang_switcher -->
                <div class="ltlb-shortcode-card">
                    <h3>
                        <span class="dashicons dashicons-translation" style="color: #2271b1;"></span>
                        <?php echo esc_html__('Language Switcher', 'ltl-bookings'); ?>
                    </h3>
                    <p class="description">
                        <?php echo esc_html__('Multi-language switcher for your booking forms. Supports German, English, and Spanish.', 'ltl-bookings'); ?>
                    </p>
                    
                    <div class="ltlb-shortcode-code" onclick="copyShortcode(this)">
                        [lazy_lang_switcher]
                    </div>

                    <dl class="ltlb-attributes">
                        <dt>style</dt>
                        <dd><?php echo esc_html__('Display style: "dropdown" (default) or "buttons"', 'ltl-bookings'); ?></dd>
                        <dt>show_flags</dt>
                        <dd><?php echo esc_html__('Show flag emojis: "yes" (default) or "no"', 'ltl-bookings'); ?></dd>
                    </dl>

                    <div class="ltlb-examples">
                        <h4><?php echo esc_html__('Examples:', 'ltl-bookings'); ?></h4>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_lang_switcher style="buttons"]</div>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)">[lazy_lang_switcher show_flags="no"]</div>
                    </div>
                </div>
            </div>

            <!-- BEST PRACTICES -->
            <?php LTLB_Admin_Component::card_start(__( '💡 Best Practices & Tips', 'ltl-bookings' )); ?>
                <div class="ltlb-grid ltlb-grid--2">
                    <div>
                        <h3><?php echo esc_html__('Landing Page Setup', 'ltl-bookings'); ?></h3>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)" style="margin: 8px 0;"><?php echo $is_hotel_mode ? '[lazy_hotel_bar sticky="true" background="dark"]' : '[lazy_book_bar sticky="true" background="dark"]'; ?></div>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)" style="margin: 8px 0;"><?php echo $is_hotel_mode ? '[lazy_room_types columns="3"]' : '[lazy_services columns="3"]'; ?></div>
                        <div class="ltlb-example-code" onclick="copyShortcode(this)" style="margin: 8px 0;">[lazy_trust title="Why Choose Us?" button_url="/booking/"]</div>
                    </div>
                    
                    <div>
                        <h3><?php echo esc_html__('Integration Tips', 'ltl-bookings'); ?></h3>
                        <ul style="color: #646970; font-size: 14px; line-height: 1.8;">
                            <li><?php echo esc_html__('Use sticky booking bars on long landing pages', 'ltl-bookings'); ?></li>
                            <li><?php echo esc_html__('Add language switcher to header/footer', 'ltl-bookings'); ?></li>
                            <li><?php echo esc_html__('Widgets work great in sidebars and Elementor sections', 'ltl-bookings'); ?></li>
                            <li><?php echo esc_html__('All shortcodes are also available as Gutenberg blocks', 'ltl-bookings'); ?></li>
                            <li><?php echo esc_html__('Test on mobile devices for responsive behavior', 'ltl-bookings'); ?></li>
                        </ul>
                    </div>
                </div>

                <div style="margin-top: 24px; padding: 16px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                    <h4 style="margin: 0 0 8px 0; color: #2271b1;">
                        <span class="dashicons dashicons-info" style="font-size: 20px; vertical-align: middle;"></span>
                        <?php echo esc_html__('Design System Integration', 'ltl-bookings'); ?>
                    </h4>
                    <p style="margin: 0; color: #646970; font-size: 14px;">
                        <?php echo esc_html__('All shortcodes use the Agency Design System from your Design settings. Colors, fonts, and spacing are automatically applied for a consistent brand experience.', 'ltl-bookings'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_design')); ?>" style="margin-left: 8px;">
                            <?php echo esc_html__('→ Customize Design', 'ltl-bookings'); ?>
                        </a>
                    </p>
                </div>
            <?php LTLB_Admin_Component::card_end(); ?>

            <script>
            function copyShortcode(element) {
                const text = element.textContent.trim();
                navigator.clipboard.writeText(text).then(() => {
                    element.classList.add('copied');
                    setTimeout(() => {
                        element.classList.remove('copied');
                    }, 2000);
                }).catch(err => {
                    console.error('Copy failed:', err);
                });
            }
            </script>
        </div>
        <?php
    }
}
