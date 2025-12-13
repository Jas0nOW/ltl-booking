<?php
/**
 * Template: Calendar Start
 *
 * Calendar-start currently reuses the wizard template, but starts in
 * a calendar-first flow (handled by markup + JS).
 *
 * Variables available from shortcode render:
 * $services (array)
 * $is_hotel_mode (bool)
 * $start_mode (string)
 * $prefill_service_id (int)
 */
if ( ! defined('ABSPATH') ) exit;

// Force calendar start mode.
$start_mode = 'calendar';

include __DIR__ . '/wizard.php';

