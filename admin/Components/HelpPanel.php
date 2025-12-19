<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * In-App Help Panel
 * 
 * Context-sensitive help for each admin page.
 */
class LTLB_Admin_HelpPanel {

    public static function init(): void {
        add_action( 'admin_head', [ __CLASS__, 'add_contextual_help' ] );
    }

    public static function add_contextual_help(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! is_string( $screen->id ) || strpos( (string) $screen->id, 'ltlb_' ) === false ) {
            return;
        }

        $help_content = self::get_help_content_for_page( $screen->id );
        if ( ! empty( $help_content ) ) {
            $screen->add_help_tab([
                'id' => 'ltlb_help',
                'title' => __('LazyBookings Help', 'ltl-bookings'),
                'content' => $help_content,
            ]);

            $screen->set_help_sidebar(
                '<p><strong>' . __('More Resources:', 'ltl-bookings') . '</strong></p>' .
                '<p><a href="' . esc_url('https://lazybookings.com/docs') . '" target="_blank">' . __('Documentation', 'ltl-bookings') . '</a></p>' .
                '<p><a href="' . esc_url('https://lazybookings.com/support') . '" target="_blank">' . __('Support Forum', 'ltl-bookings') . '</a></p>' .
                '<p><a href="' . esc_url( admin_url('admin.php?page=ltlb_diagnostics') ) . '">' . __('Run Diagnostics', 'ltl-bookings') . '</a></p>'
            );
        }
    }

    private static function get_help_content_for_page( string $screen_id ): string {
        $content = '';

        switch ( $screen_id ) {
            case 'toplevel_page_ltlb_dashboard':
                $content = '<h3>' . __('Dashboard Overview', 'ltl-bookings') . '</h3>';
                $content .= '<p>' . __('The dashboard shows your upcoming bookings, revenue, and key metrics at a glance.', 'ltl-bookings') . '</p>';
                $content .= '<p><strong>' . __('Quick Actions:', 'ltl-bookings') . '</strong></p>';
                $content .= '<ul>';
                $content .= '<li>' . __('Click on any booking to view details', 'ltl-bookings') . '</li>';
                $content .= '<li>' . __('Use filters to find specific bookings', 'ltl-bookings') . '</li>';
                $content .= '<li>' . __('Check the calendar for availability', 'ltl-bookings') . '</li>';
                $content .= '</ul>';
                break;

            case 'ltlb_page_ltlb_appointments':
                $content = '<h3>' . __('Managing Appointments', 'ltl-bookings') . '</h3>';
                $content .= '<p>' . __('View, edit, and manage all your bookings in one place.', 'ltl-bookings') . '</p>';
                $content .= '<p><strong>' . __('Tips:', 'ltl-bookings') . '</strong></p>';
                $content .= '<ul>';
                $content .= '<li>' . __('Use status filters to find pending or confirmed bookings', 'ltl-bookings') . '</li>';
                $content .= '<li>' . __('Click "Edit" to change booking details', 'ltl-bookings') . '</li>';
                $content .= '<li>' . __('Bulk actions let you confirm or cancel multiple bookings', 'ltl-bookings') . '</li>';
                $content .= '</ul>';
                break;

            case 'ltlb_page_ltlb_services':
                $content = '<h3>' . __('Services & Room Types', 'ltl-bookings') . '</h3>';
                $content .= '<p>' . __('Define what customers can book. Each service has a name, duration, price, and description.', 'ltl-bookings') . '</p>';
                $content .= '<p><strong>' . __('Best Practices:', 'ltl-bookings') . '</strong></p>';
                $content .= '<ul>';
                $content .= '<li>' . __('Use clear, descriptive names (e.g., "60-Minute Massage" instead of just "Massage")', 'ltl-bookings') . '</li>';
                $content .= '<li>' . __('Set accurate durations to avoid overbooking', 'ltl-bookings') . '</li>';
                $content .= '<li>' . __('Add detailed descriptions for customers', 'ltl-bookings') . '</li>';
                $content .= '</ul>';
                break;

            case 'ltlb_page_ltlb_settings':
                $content = '<h3>' . __('Plugin Settings', 'ltl-bookings') . '</h3>';
                $content .= '<p>' . __('Configure payments, emails, and booking behavior.', 'ltl-bookings') . '</p>';
                $content .= '<p><strong>' . __('Important Settings:', 'ltl-bookings') . '</strong></p>';
                $content .= '<ul>';
                $content .= '<li><strong>' . __('Booking Mode:', 'ltl-bookings') . '</strong> ' . __('Switch between service appointments and hotel mode', 'ltl-bookings') . '</li>';
                $content .= '<li><strong>' . __('Payment Methods:', 'ltl-bookings') . '</strong> ' . __('Enable Stripe, PayPal, or on-site payment', 'ltl-bookings') . '</li>';
                $content .= '<li><strong>' . __('Email Notifications:', 'ltl-bookings') . '</strong> ' . __('Configure confirmation and reminder emails', 'ltl-bookings') . '</li>';
                $content .= '</ul>';
                break;

            case 'ltlb_page_ltlb_diagnostics':
                $content = '<h3>' . __('System Diagnostics', 'ltl-bookings') . '</h3>';
                $content .= '<p>' . __('Check your system health and troubleshoot issues.', 'ltl-bookings') . '</p>';
                $content .= '<p><strong>' . __('What to Check:', 'ltl-bookings') . '</strong></p>';
                $content .= '<ul>';
                $content .= '<li>' . __('WP Cron status (needed for automated reminders)', 'ltl-bookings') . '</li>';
                $content .= '<li>' . __('Payment gateway configuration', 'ltl-bookings') . '</li>';
                $content .= '<li>' . __('Email delivery (test sends an email to admin)', 'ltl-bookings') . '</li>';
                $content .= '<li>' . __('Database tables (all should show "✓ Present")', 'ltl-bookings') . '</li>';
                $content .= '</ul>';
                break;

            default:
                $content = '<h3>' . __('LazyBookings Help', 'ltl-bookings') . '</h3>';
                $content .= '<p>' . __('For detailed guides and tutorials, visit our documentation site.', 'ltl-bookings') . '</p>';
                break;
        }

        return $content;
    }

    /**
     * Quick FAQ for common questions
     */
    public static function get_faq(): array {
        return [
            [
                'question' => __('How do I add the booking form to my website?', 'ltl-bookings'),
                'answer' => __('Use the shortcode [lazy_book] on any page or post. You can also use Gutenberg blocks or Elementor widgets.', 'ltl-bookings'),
            ],
            [
                'question' => __('How do I enable online payments?', 'ltl-bookings'),
                'answer' => __('Go to Settings → Payments and enter your Stripe or PayPal credentials. Make sure to test in sandbox mode first.', 'ltl-bookings'),
            ],
            [
                'question' => __('Can customers cancel their own bookings?', 'ltl-bookings'),
                'answer' => __('Yes, if you enable the customer portal. They can manage their bookings through the [lazy_customer_portal] shortcode.', 'ltl-bookings'),
            ],
            [
                'question' => __('How do I prevent double bookings?', 'ltl-bookings'),
                'answer' => __('The plugin uses database locks to prevent race conditions. Make sure your MySQL version supports named locks (MySQL 5.7.5+).', 'ltl-bookings'),
            ],
            [
                'question' => __('Can I translate the plugin to my language?', 'ltl-bookings'),
                'answer' => __('Yes, LazyBookings is translation-ready. German translations are included, and you can add more via Loco Translate or Poedit.', 'ltl-bookings'),
            ],
        ];
    }
}
