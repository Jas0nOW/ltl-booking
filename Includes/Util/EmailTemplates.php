<?php
/**
 * Email Template Editor
 * 
 * Visual editor for email templates with variables, preview, and multi-language support.
 * Supports per-mode templates (service vs hotel) and professional layouts.
 *
 * @package LTL_Bookings
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LTLB_Email_Templates {
    
    /**
     * Available template types
     */
    private const TEMPLATE_TYPES = [
        'booking_confirmation' => 'Booking Confirmation',
        'booking_reminder' => 'Booking Reminder',
        'booking_cancelled' => 'Booking Cancelled',
        'booking_rescheduled' => 'Booking Rescheduled',
        'payment_received' => 'Payment Received',
        'payment_reminder' => 'Payment Reminder',
        'waitlist_offer' => 'Waitlist Slot Available',
        'package_purchased' => 'Package Purchased',
        'package_expiring' => 'Package Expiring Soon',
    ];
    
    /**
     * Available variables for templates
     */
    private const VARIABLES = [
        'customer' => [
            '{{customer_name}}' => 'Customer full name',
            '{{customer_first_name}}' => 'Customer first name',
            '{{customer_email}}' => 'Customer email',
            '{{customer_phone}}' => 'Customer phone',
        ],
        'booking' => [
            '{{booking_id}}' => 'Booking ID',
            '{{booking_date}}' => 'Booking date',
            '{{booking_time}}' => 'Booking time',
            '{{booking_duration}}' => 'Booking duration',
            '{{booking_status}}' => 'Booking status',
        ],
        'service' => [
            '{{service_name}}' => 'Service/Room name',
            '{{service_description}}' => 'Service description',
            '{{staff_name}}' => 'Staff member name',
        ],
        'payment' => [
            '{{amount}}' => 'Total amount',
            '{{amount_paid}}' => 'Amount paid',
            '{{amount_remaining}}' => 'Remaining balance',
            '{{payment_method}}' => 'Payment method',
        ],
        'site' => [
            '{{site_name}}' => 'Site name',
            '{{site_url}}' => 'Site URL',
            '{{support_email}}' => 'Support email',
        ],
    ];
    
    /**
     * Initialize email templates
     */
    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ], 20 );
        add_action( 'wp_ajax_ltlb_save_email_template', [ __CLASS__, 'ajax_save_template' ] );
        add_action( 'wp_ajax_ltlb_preview_email_template', [ __CLASS__, 'ajax_preview_template' ] );
        add_action( 'wp_ajax_ltlb_send_test_email', [ __CLASS__, 'ajax_send_test_email' ] );
        
        // Filter to use custom templates
        add_filter( 'ltlb_email_content', [ __CLASS__, 'apply_template' ], 10, 3 );
    }
    
    /**
     * Get template content
     *
     * @param string $type Template type
     * @param string $mode Mode (service/hotel)
     * @param string $language Language code
     * @return array Template data
     */
    public static function get_template( string $type, string $mode = 'service', string $language = 'en' ): array {
        $templates = get_option( 'ltlb_email_templates', [] );
        
        $key = "{$type}_{$mode}_{$language}";
        
        if ( isset( $templates[ $key ] ) ) {
            return $templates[ $key ];
        }
        
        // Return default template
        return self::get_default_template( $type, $mode );
    }
    
    /**
     * Save template
     *
     * @param string $type Template type
     * @param string $mode Mode
     * @param string $language Language
     * @param array $template_data Template content
     * @return bool Success
     */
    public static function save_template( string $type, string $mode, string $language, array $template_data ): bool {
        $templates = get_option( 'ltlb_email_templates', [] );
        
        $key = "{$type}_{$mode}_{$language}";
        
        $templates[ $key ] = [
            'subject' => sanitize_text_field( $template_data['subject'] ?? '' ),
            'body' => wp_kses_post( $template_data['body'] ?? '' ),
            'mode' => $mode,
            'language' => $language,
            'updated_at' => current_time( 'mysql' )
        ];
        
        return update_option( 'ltlb_email_templates', $templates );
    }
    
    /**
     * Get default template
     */
    private static function get_default_template( string $type, string $mode ): array {
        $defaults = [
            'booking_confirmation' => [
                'subject' => __( 'Booking Confirmed: {{service_name}} on {{booking_date}}', 'ltl-bookings' ),
                'body' => __(
                    "Hi {{customer_first_name}},\n\nYour booking has been confirmed!\n\n" .
                    "Details:\n" .
                    "- Service: {{service_name}}\n" .
                    "- Date: {{booking_date}} at {{booking_time}}\n" .
                    "- Amount: {{amount}}\n\n" .
                    "Thank you!\n{{site_name}}",
                    'ltl-bookings'
                ),
            ],
            'booking_reminder' => [
                'subject' => __( 'Reminder: {{service_name}} tomorrow', 'ltl-bookings' ),
                'body' => __(
                    "Hi {{customer_first_name}},\n\nThis is a friendly reminder about your booking tomorrow:\n\n" .
                    "- Service: {{service_name}}\n" .
                    "- Time: {{booking_time}}\n\n" .
                    "See you soon!\n{{site_name}}",
                    'ltl-bookings'
                ),
            ],
            'payment_received' => [
                'subject' => __( 'Payment Received - {{booking_id}}', 'ltl-bookings' ),
                'body' => __(
                    "Hi {{customer_first_name}},\n\nWe've received your payment of {{amount_paid}}.\n\n" .
                    "Thank you!\n{{site_name}}",
                    'ltl-bookings'
                ),
            ],
        ];
        
        if ( isset( $defaults[ $type ] ) ) {
            return $defaults[ $type ];
        }
        
        return [
            'subject' => __( 'Notification from {{site_name}}', 'ltl-bookings' ),
            'body' => __( 'Hi {{customer_first_name}},', 'ltl-bookings' ),
        ];
    }
    
    /**
     * Apply template to email
     *
     * @param string $content Original content
     * @param string $type Template type
     * @param array $data Variable data
     * @return string Processed content
     */
    public static function apply_template( string $content, string $type, array $data ): string {
        $mode = $data['mode'] ?? 'service';
        $language = $data['language'] ?? 'en';
        
        $template = self::get_template( $type, $mode, $language );
        
        $subject = $template['subject'] ?? '';
        $body = $template['body'] ?? '';
        
        // Replace variables
        $body = self::replace_variables( $body, $data );
        $subject = self::replace_variables( $subject, $data );
        
        return $body;
    }
    
    /**
     * Replace template variables
     *
     * @param string $content Content with variables
     * @param array $data Variable values
     * @return string Processed content
     */
    private static function replace_variables( string $content, array $data ): string {
        $replacements = [
            '{{customer_name}}' => $data['customer_name'] ?? '',
            '{{customer_first_name}}' => $data['customer_first_name'] ?? '',
            '{{customer_email}}' => $data['customer_email'] ?? '',
            '{{customer_phone}}' => $data['customer_phone'] ?? '',
            '{{booking_id}}' => $data['booking_id'] ?? '',
            '{{booking_date}}' => $data['booking_date'] ?? '',
            '{{booking_time}}' => $data['booking_time'] ?? '',
            '{{booking_duration}}' => $data['booking_duration'] ?? '',
            '{{booking_status}}' => $data['booking_status'] ?? '',
            '{{service_name}}' => $data['service_name'] ?? '',
            '{{service_description}}' => $data['service_description'] ?? '',
            '{{staff_name}}' => $data['staff_name'] ?? '',
            '{{amount}}' => $data['amount'] ?? '',
            '{{amount_paid}}' => $data['amount_paid'] ?? '',
            '{{amount_remaining}}' => $data['amount_remaining'] ?? '',
            '{{payment_method}}' => $data['payment_method'] ?? '',
            '{{site_name}}' => get_bloginfo( 'name' ),
            '{{site_url}}' => get_site_url(),
            '{{support_email}}' => get_option( 'admin_email' ),
        ];
        
        return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
    }
    
    /**
     * Add admin menu
     */
    public static function add_menu(): void {
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Email Templates', 'ltl-bookings' ),
            __( 'Email Templates', 'ltl-bookings' ),
            'manage_options',
            'ltlb_email_templates',
            [ __CLASS__, 'render_page' ]
        );
    }
    
    /**
     * Render admin page
     */
    public static function render_page(): void {
        $current_type = sanitize_key( $_GET['type'] ?? 'booking_confirmation' );
        $current_mode = sanitize_key( $_GET['mode'] ?? 'service' );
        $current_language = sanitize_key( $_GET['language'] ?? 'en' );
        
        $template = self::get_template( $current_type, $current_mode, $current_language );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Email Templates', 'ltl-bookings' ); ?></h1>
            
            <div class="ltlb-email-template-editor">
                <form method="get" action="" class="ltlb-template-selector">
                    <input type="hidden" name="page" value="ltlb_email_templates">
                    
                    <label><?php esc_html_e( 'Template Type:', 'ltl-bookings' ); ?></label>
                    <select name="type" onchange="this.form.submit()">
                        <?php foreach ( self::TEMPLATE_TYPES as $key => $label ): ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_type, $key ); ?>>
                                <?php echo esc_html__( $label, 'ltl-bookings' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label><?php esc_html_e( 'Mode:', 'ltl-bookings' ); ?></label>
                    <select name="mode" onchange="this.form.submit()">
                        <option value="service" <?php selected( $current_mode, 'service' ); ?>><?php esc_html_e( 'Service', 'ltl-bookings' ); ?></option>
                        <option value="hotel" <?php selected( $current_mode, 'hotel' ); ?>><?php esc_html_e( 'Hotel', 'ltl-bookings' ); ?></option>
                    </select>
                    
                    <label><?php esc_html_e( 'Language:', 'ltl-bookings' ); ?></label>
                    <select name="language" onchange="this.form.submit()">
                        <option value="en" <?php selected( $current_language, 'en' ); ?>>English</option>
                        <option value="de" <?php selected( $current_language, 'de' ); ?>>Deutsch</option>
                        <option value="es" <?php selected( $current_language, 'es' ); ?>>Español</option>
                    </select>
                </form>
                
                <div class="ltlb-template-form" style="margin-top: 20px;">
                    <h2><?php esc_html_e( 'Edit Template', 'ltl-bookings' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="template_subject"><?php esc_html_e( 'Subject', 'ltl-bookings' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="template_subject" class="large-text" 
                                       value="<?php echo esc_attr( $template['subject'] ?? '' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template_body"><?php esc_html_e( 'Body', 'ltl-bookings' ); ?></label>
                            </th>
                            <td>
                                <textarea id="template_body" rows="15" class="large-text code"><?php 
                                    echo esc_textarea( $template['body'] ?? '' ); 
                                ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Use HTML for formatting. Available variables:', 'ltl-bookings' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="ltlb-variables-list">
                        <h3><?php esc_html_e( 'Available Variables', 'ltl-bookings' ); ?></h3>
                        <?php foreach ( self::VARIABLES as $category => $variables ): ?>
                            <details>
                                <summary><strong><?php echo esc_html( ucfirst( $category ) ); ?></strong></summary>
                                <ul>
                                    <?php foreach ( $variables as $var => $description ): ?>
                                        <li>
                                            <code class="ltlb-variable-code" onclick="navigator.clipboard.writeText('<?php echo esc_js( $var ); ?>')"><?php echo esc_html( $var ); ?></code>
                                            - <?php echo esc_html( $description ); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Click a variable to copy it', 'ltl-bookings' ); ?></p>
                    </div>
                    
                    <p class="submit">
                        <button type="button" class="button button-primary" id="ltlb-save-template"
                                data-type="<?php echo esc_attr( $current_type ); ?>"
                                data-mode="<?php echo esc_attr( $current_mode ); ?>"
                                data-language="<?php echo esc_attr( $current_language ); ?>">
                            <?php esc_html_e( 'Save Template', 'ltl-bookings' ); ?>
                        </button>
                        <button type="button" class="button" id="ltlb-preview-template">
                            <?php esc_html_e( 'Preview', 'ltl-bookings' ); ?>
                        </button>
                        <button type="button" class="button" id="ltlb-send-test">
                            <?php esc_html_e( 'Send Test Email', 'ltl-bookings' ); ?>
                        </button>
                    </p>
                </div>
            </div>
        </div>
        
        <style>
            .ltlb-template-selector {
                background: #fff;
                padding: 15px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .ltlb-template-selector label {
                font-weight: 600;
                margin-left: 15px;
            }
            .ltlb-template-selector label:first-child {
                margin-left: 0;
            }
            .ltlb-template-selector select {
                margin-left: 8px;
            }
            .ltlb-variables-list {
                background: #f6f7f7;
                padding: 15px;
                border-radius: 4px;
                margin-top: 20px;
            }
            .ltlb-variables-list details {
                margin-bottom: 10px;
            }
            .ltlb-variables-list summary {
                cursor: pointer;
                user-select: none;
            }
            .ltlb-variables-list ul {
                margin-left: 20px;
                list-style: disc;
            }
            .ltlb-variable-code {
                cursor: pointer;
                padding: 2px 6px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            .ltlb-variable-code:hover {
                background: #2271b1;
                color: white;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ltlb-save-template').on('click', function() {
                var $btn = $(this);
                var subject = $('#template_subject').val();
                var body = $('#template_body').val();
                
                $.post(ajaxurl, {
                    action: 'ltlb_save_email_template',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_email_templates' ); ?>',
                    type: $btn.data('type'),
                    mode: $btn.data('mode'),
                    language: $btn.data('language'),
                    subject: subject,
                    body: body
                }, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js( __( 'Template saved!', 'ltl-bookings' ) ); ?>');
                    } else {
                        alert('<?php echo esc_js( __( 'Failed to save template', 'ltl-bookings' ) ); ?>');
                    }
                });
            });
            
            $('#ltlb-preview-template').on('click', function() {
                var subject = $('#template_subject').val();
                var body = $('#template_body').val();
                
                $.post(ajaxurl, {
                    action: 'ltlb_preview_email_template',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_email_templates' ); ?>',
                    subject: subject,
                    body: body
                }, function(response) {
                    if (response.success) {
                        var preview = window.open('', 'EmailPreview', 'width=800,height=600');
                        preview.document.write('<html><head><title>Email Preview</title></head><body>');
                        preview.document.write('<h1>' + response.data.subject + '</h1>');
                        preview.document.write('<hr>');
                        preview.document.write(response.data.body);
                        preview.document.write('</body></html>');
                    }
                });
            });
            
            $('#ltlb-send-test').on('click', function() {
                var email = prompt('<?php echo esc_js( __( 'Send test email to:', 'ltl-bookings' ) ); ?>', '<?php echo esc_js( get_option( 'admin_email' ) ); ?>');
                if (!email) return;
                
                var subject = $('#template_subject').val();
                var body = $('#template_body').val();
                
                $.post(ajaxurl, {
                    action: 'ltlb_send_test_email',
                    nonce: '<?php echo wp_create_nonce( 'ltlb_email_templates' ); ?>',
                    email: email,
                    subject: subject,
                    body: body
                }, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js( __( 'Test email sent!', 'ltl-bookings' ) ); ?>');
                    } else {
                        alert('<?php echo esc_js( __( 'Failed to send test email', 'ltl-bookings' ) ); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Save template
     */
    public static function ajax_save_template(): void {
        check_ajax_referer( 'ltlb_email_templates', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        
        $type = sanitize_key( $_POST['type'] ?? '' );
        $mode = sanitize_key( $_POST['mode'] ?? '' );
        $language = sanitize_key( $_POST['language'] ?? '' );
        
        $template_data = [
            'subject' => sanitize_text_field( $_POST['subject'] ?? '' ),
            'body' => wp_kses_post( $_POST['body'] ?? '' )
        ];
        
        if ( self::save_template( $type, $mode, $language, $template_data ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * AJAX: Preview template
     */
    public static function ajax_preview_template(): void {
        check_ajax_referer( 'ltlb_email_templates', 'nonce' );
        
        $subject = sanitize_text_field( $_POST['subject'] ?? '' );
        $body = wp_kses_post( $_POST['body'] ?? '' );
        
        // Sample data for preview
        $sample_data = [
            'customer_name' => 'John Doe',
            'customer_first_name' => 'John',
            'customer_email' => 'john@example.com',
            'booking_id' => '12345',
            'booking_date' => date_i18n( get_option( 'date_format' ), strtotime( '+1 day' ) ),
            'booking_time' => '14:00',
            'service_name' => 'Sample Service',
            'amount' => '€50.00',
        ];
        
        $subject = self::replace_variables( $subject, $sample_data );
        $body = self::replace_variables( $body, $sample_data );
        $body = nl2br( $body );
        
        wp_send_json_success( [
            'subject' => $subject,
            'body' => $body
        ] );
    }
    
    /**
     * AJAX: Send test email
     */
    public static function ajax_send_test_email(): void {
        check_ajax_referer( 'ltlb_email_templates', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        
        $to = sanitize_email( $_POST['email'] ?? '' );
        $subject = sanitize_text_field( $_POST['subject'] ?? '' );
        $body = wp_kses_post( $_POST['body'] ?? '' );
        
        // Sample data
        $sample_data = [
            'customer_name' => 'Test Customer',
            'customer_first_name' => 'Test',
            'booking_id' => 'TEST123',
            'booking_date' => date_i18n( get_option( 'date_format' ) ),
            'service_name' => 'Test Service',
            'amount' => '€100.00',
        ];
        
        $subject = self::replace_variables( $subject, $sample_data );
        $body = self::replace_variables( $body, $sample_data );
        
        if ( wp_mail( $to, $subject, $body ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
}

// Initialize
LTLB_Email_Templates::init();
