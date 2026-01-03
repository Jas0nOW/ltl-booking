<?php
/**
 * Accessibility (A11y) Helper
 * 
 * Ensures WCAG 2.1 AA compliance across admin and frontend booking flows.
 * Provides keyboard navigation, ARIA labels, focus management, and screen reader support.
 *
 * @package LTL_Bookings
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LTLB_Accessibility {
    
    /**
     * Initialize accessibility features
     */
    public static function init(): void {
        // Admin
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_a11y' ] );
        
        // Frontend
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_a11y' ] );
        
        // Add skip links
        add_action( 'ltlb_before_booking_form', [ __CLASS__, 'render_skip_links' ] );
    }
    
    /**
     * Enqueue admin accessibility styles and scripts
     */
    public static function enqueue_admin_a11y(): void {
        $page = isset( $_GET['page'] ) ? (string) $_GET['page'] : '';
        if ( $page === '' || strpos( (string) $page, 'ltlb_' ) !== 0 ) {
            return;
        }
        
        wp_add_inline_style( 'ltlb-admin', self::get_admin_a11y_css() );
        wp_add_inline_script( 'ltlb-admin', self::get_admin_a11y_js() );
    }
    
    /**
     * Enqueue frontend accessibility styles and scripts
     */
    public static function enqueue_frontend_a11y(): void {
        wp_add_inline_style( 'ltlb-public', self::get_frontend_a11y_css() );
        wp_add_inline_script( 'ltlb-public', self::get_frontend_a11y_js() );
    }
    
    /**
     * Admin A11y CSS - Focus styles, high contrast, visible outlines
     */
    private static function get_admin_a11y_css(): string {
        return <<<CSS
/* Focus Styles */
.ltlb-admin a:focus,
.ltlb-admin button:focus,
.ltlb-admin input:focus,
.ltlb-admin select:focus,
.ltlb-admin textarea:focus {
    outline: 3px solid #2271b1 !important;
    outline-offset: 2px !important;
    box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.2) !important;
}

/* Skip to content link */
.ltlb-skip-link {
    position: absolute;
    top: -40px;
    left: 0;
    background: #2271b1;
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    z-index: 100000;
    border-radius: 0 0 4px 0;
}
.ltlb-skip-link:focus {
    top: 0;
    outline: 3px solid white;
}

/* High Contrast Mode */
@media (prefers-contrast: high) {
    .ltlb-admin {
        border-width: 2px !important;
    }
    .ltlb-admin button,
    .ltlb-admin .button {
        border: 2px solid currentColor !important;
        font-weight: 600 !important;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .ltlb-admin * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Screen Reader Only Text */
.ltlb-sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}
.ltlb-sr-only-focusable:focus {
    position: static !important;
    width: auto !important;
    height: auto !important;
    overflow: visible !important;
    clip: auto !important;
    white-space: normal !important;
}

/* Focus trap visibility */
.ltlb-modal-open {
    overflow: hidden;
}
.ltlb-modal[aria-hidden="false"] {
    display: block;
}
.ltlb-modal[aria-hidden="true"] {
    display: none;
}

/* Calendar focus indicators */
.ltlb-calendar-day:focus {
    outline: 3px solid #2271b1;
    outline-offset: -3px;
    z-index: 10;
    position: relative;
}

/* Status indicators with icons for color-blind users */
.ltlb-status-badge::before {
    content: attr(data-status-icon);
    margin-right: 4px;
    font-weight: bold;
}
CSS;
    }
    
    /**
     * Admin A11y JavaScript - Keyboard navigation, focus management
     */
    private static function get_admin_a11y_js(): string {
        return <<<JS
(function() {
    'use strict';
    
    // Keyboard navigation for calendar
    document.addEventListener('keydown', function(e) {
        if (e.target.matches('.ltlb-calendar-day')) {
            handleCalendarKeyboard(e);
        }
        
        if (e.target.matches('.ltlb-modal')) {
            handleModalKeyboard(e);
        }
    });
    
    function handleCalendarKeyboard(e) {
        const current = e.target;
        let next = null;
        
        switch(e.key) {
            case 'ArrowRight':
                next = current.nextElementSibling;
                break;
            case 'ArrowLeft':
                next = current.previousElementSibling;
                break;
            case 'ArrowDown':
                const row = current.closest('tr').nextElementSibling;
                if (row) {
                    const index = Array.from(current.parentNode.children).indexOf(current);
                    next = row.children[index];
                }
                break;
            case 'ArrowUp':
                const prevRow = current.closest('tr').previousElementSibling;
                if (prevRow) {
                    const index = Array.from(current.parentNode.children).indexOf(current);
                    next = prevRow.children[index];
                }
                break;
            case 'Enter':
            case ' ':
                e.preventDefault();
                current.click();
                return;
        }
        
        if (next && next.matches('.ltlb-calendar-day')) {
            e.preventDefault();
            next.focus();
        }
    }
    
    function handleModalKeyboard(e) {
        // Escape closes modal
        if (e.key === 'Escape') {
            closeModal(e.target.closest('.ltlb-modal'));
        }
        
        // Tab trapping
        if (e.key === 'Tab') {
            trapFocus(e);
        }
    }
    
    function trapFocus(e) {
        const modal = e.target.closest('.ltlb-modal');
        if (!modal) return;
        
        const focusable = modal.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), ' +
            'input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );
        
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
    
    function closeModal(modal) {
        if (!modal) return;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ltlb-modal-open');
        
        // Return focus to trigger
        const trigger = document.querySelector('[data-modal="' + modal.id + '"]');
        if (trigger) trigger.focus();
    }
    
    // Announce dynamic updates to screen readers
    window.ltlbAnnounce = function(message, priority) {
        priority = priority || 'polite';
        
        let announcer = document.getElementById('ltlb-announcer');
        if (!announcer) {
            announcer = document.createElement('div');
            announcer.id = 'ltlb-announcer';
            announcer.className = 'ltlb-sr-only';
            announcer.setAttribute('aria-live', priority);
            announcer.setAttribute('aria-atomic', 'true');
            document.body.appendChild(announcer);
        }
        
        // Clear and update
        announcer.textContent = '';
        setTimeout(function() {
            announcer.textContent = message;
        }, 100);
    };
    
    // Form validation announcements
    document.addEventListener('submit', function(e) {
        if (!e.target.matches('.ltlb-form')) return;
        
        const errors = e.target.querySelectorAll('[aria-invalid="true"]');
        if (errors.length > 0) {
            e.preventDefault();
            
            const count = errors.length;
            const message = count === 1 
                ? 'There is 1 error in the form. Please correct it.'
                : 'There are ' + count + ' errors in the form. Please correct them.';
            
            window.ltlbAnnounce(message, 'assertive');
            errors[0].focus();
        }
    });
    
    // Mark invalid fields
    document.addEventListener('invalid', function(e) {
        if (e.target.form && e.target.form.matches('.ltlb-form')) {
            e.target.setAttribute('aria-invalid', 'true');
            
            // Add error message
            const errorId = e.target.id + '-error';
            let errorMsg = document.getElementById(errorId);
            if (!errorMsg) {
                errorMsg = document.createElement('span');
                errorMsg.id = errorId;
                errorMsg.className = 'ltlb-error-message';
                errorMsg.setAttribute('role', 'alert');
                e.target.parentNode.appendChild(errorMsg);
            }
            errorMsg.textContent = e.target.validationMessage;
            e.target.setAttribute('aria-describedby', errorId);
        }
    }, true);
    
    // Clear invalid on input
    document.addEventListener('input', function(e) {
        if (e.target.hasAttribute('aria-invalid')) {
            e.target.removeAttribute('aria-invalid');
            const errorId = e.target.getAttribute('aria-describedby');
            if (errorId) {
                const errorMsg = document.getElementById(errorId);
                if (errorMsg) errorMsg.remove();
                e.target.removeAttribute('aria-describedby');
            }
        }
    });
    
})();
JS;
    }
    
    /**
     * Frontend A11y CSS
     */
    private static function get_frontend_a11y_css(): string {
        return <<<CSS
/* Booking Form Accessibility */
.ltlb-booking-form *:focus {
    outline: 3px solid #0073aa !important;
    outline-offset: 2px !important;
}

/* Skip links */
.ltlb-skip-link {
    position: absolute;
    top: -40px;
    left: 6px;
    background: #0073aa;
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    z-index: 100000;
    border-radius: 4px;
    font-weight: 600;
}
.ltlb-skip-link:focus {
    top: 6px;
}

/* Screen reader text */
.ltlb-sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}

/* Fieldset and legend visibility */
.ltlb-booking-form fieldset {
    border: 1px solid #ddd;
    padding: 16px;
    margin-bottom: 16px;
    border-radius: 4px;
}
.ltlb-booking-form legend {
    font-weight: 600;
    padding: 0 8px;
    font-size: 1.1em;
}

/* Error messages */
.ltlb-error-message {
    display: block;
    color: #d63638;
    font-size: 0.9em;
    margin-top: 4px;
}
[aria-invalid="true"] {
    border-color: #d63638 !important;
    box-shadow: 0 0 0 1px #d63638 !important;
}

/* Loading states */
.ltlb-loading {
    position: relative;
}
.ltlb-loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    cursor: wait;
}

/* Calendar accessibility */
.ltlb-calendar [role="gridcell"] {
    cursor: pointer;
}
.ltlb-calendar [aria-selected="true"] {
    background: #0073aa;
    color: white;
    font-weight: bold;
}
.ltlb-calendar [aria-disabled="true"] {
    opacity: 0.5;
    cursor: not-allowed;
    text-decoration: line-through;
}

/* High Contrast */
@media (prefers-contrast: high) {
    .ltlb-booking-form button {
        border: 2px solid currentColor !important;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .ltlb-booking-form * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .ltlb-booking-form {
        color-scheme: dark;
    }
}
CSS;
    }
    
    /**
     * Frontend A11y JavaScript
     */
    private static function get_frontend_a11y_js(): string {
        return <<<JS
(function() {
    'use strict';
    
    // Multi-step form progress announcement
    document.addEventListener('ltlb_step_changed', function(e) {
        const step = e.detail.step;
        const total = e.detail.total;
        const message = 'Step ' + step + ' of ' + total + ': ' + e.detail.title;
        ltlbAnnounce(message);
    });
    
    // Booking submission announcement
    document.addEventListener('ltlb_booking_submitted', function(e) {
        ltlbAnnounce('Booking submitted successfully. Redirecting...', 'assertive');
    });
    
    // Price update announcement
    let priceAnnounceTimeout;
    document.addEventListener('ltlb_price_updated', function(e) {
        clearTimeout(priceAnnounceTimeout);
        priceAnnounceTimeout = setTimeout(function() {
            ltlbAnnounce('Price updated to ' + e.detail.formatted);
        }, 1000);
    });
    
    // Calendar keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.target.matches('[role="gridcell"]')) {
            handleCalendarNav(e);
        }
    });
    
    function handleCalendarNav(e) {
        const cell = e.target;
        const grid = cell.closest('[role="grid"]');
        if (!grid) return;
        
        const cells = Array.from(grid.querySelectorAll('[role="gridcell"]:not([aria-disabled="true"])'));
        const index = cells.indexOf(cell);
        
        let next = null;
        
        switch(e.key) {
            case 'ArrowRight':
                next = cells[index + 1];
                break;
            case 'ArrowLeft':
                next = cells[index - 1];
                break;
            case 'ArrowDown':
                next = cells[index + 7]; // Week has 7 days
                break;
            case 'ArrowUp':
                next = cells[index - 7];
                break;
            case 'Enter':
            case ' ':
                e.preventDefault();
                cell.click();
                return;
            case 'Home':
                e.preventDefault();
                next = cells[0];
                break;
            case 'End':
                e.preventDefault();
                next = cells[cells.length - 1];
                break;
        }
        
        if (next) {
            e.preventDefault();
            next.focus();
        }
    }
    
    // Screen reader announcements
    window.ltlbAnnounce = function(message, priority) {
        priority = priority || 'polite';
        
        let announcer = document.getElementById('ltlb-announcer');
        if (!announcer) {
            announcer = document.createElement('div');
            announcer.id = 'ltlb-announcer';
            announcer.className = 'ltlb-sr-only';
            announcer.setAttribute('aria-live', priority);
            announcer.setAttribute('aria-atomic', 'true');
            document.body.appendChild(announcer);
        }
        
        announcer.textContent = '';
        setTimeout(function() {
            announcer.textContent = message;
        }, 100);
    };
    
    // Form validation with ARIA
    document.querySelectorAll('.ltlb-booking-form').forEach(function(form) {
        form.setAttribute('novalidate', '');
        
        form.addEventListener('submit', function(e) {
            const invalid = form.querySelectorAll(':invalid');
            
            if (invalid.length > 0) {
                e.preventDefault();
                
                invalid.forEach(function(field) {
                    field.setAttribute('aria-invalid', 'true');
                    
                    const errorId = field.id + '-error';
                    let error = document.getElementById(errorId);
                    if (!error) {
                        error = document.createElement('span');
                        error.id = errorId;
                        error.className = 'ltlb-error-message';
                        error.setAttribute('role', 'alert');
                        field.parentNode.appendChild(error);
                    }
                    error.textContent = field.validationMessage;
                    field.setAttribute('aria-describedby', errorId);
                });
                
                const count = invalid.length;
                const message = count === 1
                    ? 'Please correct 1 error in the form.'
                    : 'Please correct ' + count + ' errors in the form.';
                
                ltlbAnnounce(message, 'assertive');
                invalid[0].focus();
            }
        });
        
        form.addEventListener('input', function(e) {
            if (e.target.hasAttribute('aria-invalid') && e.target.validity.valid) {
                e.target.removeAttribute('aria-invalid');
                const errorId = e.target.getAttribute('aria-describedby');
                if (errorId) {
                    const error = document.getElementById(errorId);
                    if (error) error.remove();
                    e.target.removeAttribute('aria-describedby');
                }
            }
        });
    });
    
})();
JS;
    }
    
    /**
     * Render skip navigation links
     */
    public static function render_skip_links(): void {
        ?>
        <a href="#ltlb-booking-form-main" class="ltlb-skip-link">
            <?php esc_html_e( 'Skip to booking form', 'ltl-bookings' ); ?>
        </a>
        <?php
    }
    
    /**
     * Add ARIA labels to calendar
     *
     * @param string $html Calendar HTML
     * @param array $dates Available dates
     * @return string Enhanced HTML
     */
    public static function enhance_calendar_aria( string $html, array $dates ): string {
        // Add role="grid" to table
        $html = str_replace( '<table class="ltlb-calendar"', '<table class="ltlb-calendar" role="grid" aria-label="' . esc_attr__( 'Select booking date', 'ltl-bookings' ) . '"', (string) $html );
        
        // Add role="row" to tr
        $html = preg_replace( '/<tr(?![^>]*role)/', '<tr role="row"', $html );
        
        // Add role="columnheader" to th
        $html = preg_replace( '/<th(?![^>]*role)/', '<th role="columnheader" scope="col"', $html );
        
        // Add role="gridcell" to td with dates
        $html = preg_replace_callback(
            '/<td([^>]*)data-date="([^"]*)"([^>]*)>/',
            function( $matches ) use ( $dates ) {
                $date = $matches[2];
                $is_available = in_array( $date, $dates, true );
                
                $aria = 'role="gridcell" tabindex="' . ( $is_available ? '0' : '-1' ) . '"';
                $aria .= ' aria-label="' . esc_attr( date_i18n( 'F j, Y', strtotime( $date ) ) ) . '"';
                
                if ( ! $is_available ) {
                    $aria .= ' aria-disabled="true"';
                }
                
                return '<td' . $matches[1] . 'data-date="' . $date . '"' . $matches[3] . ' ' . $aria . '>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Get ARIA status for appointment
     *
     * @param string $status Appointment status
     * @return string ARIA label
     */
    public static function get_status_aria_label( string $status ): string {
        $labels = [
            'pending' => __( 'Status: Pending confirmation', 'ltl-bookings' ),
            'confirmed' => __( 'Status: Confirmed', 'ltl-bookings' ),
            'cancelled' => __( 'Status: Cancelled', 'ltl-bookings' ),
            'completed' => __( 'Status: Completed', 'ltl-bookings' ),
            'no-show' => __( 'Status: No-show', 'ltl-bookings' ),
        ];
        
        return $labels[ $status ] ?? $status;
    }
    
    /**
     * Wrap with proper form structure
     *
     * @param string $content Form content
     * @param string $form_id Form ID
     * @param string $legend Form legend/title
     * @return string Wrapped HTML
     */
    public static function wrap_form( string $content, string $form_id, string $legend ): string {
        $output = '<form id="' . esc_attr( $form_id ) . '" class="ltlb-booking-form ltlb-form" novalidate>';
        $output .= '<fieldset>';
        $output .= '<legend>' . esc_html( $legend ) . '</legend>';
        $output .= $content;
        $output .= '</fieldset>';
        $output .= '</form>';
        
        return $output;
    }
}

// Initialize
LTLB_Accessibility::init();
