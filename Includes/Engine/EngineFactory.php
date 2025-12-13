<?php
if ( ! defined('ABSPATH') ) exit;

require_once LTLB_PATH . 'includes/Engine/BookingEngineInterface.php';
require_once LTLB_PATH . 'includes/Engine/ServiceEngine.php';
require_once LTLB_PATH . 'includes/Engine/HotelEngine.php';

class EngineFactory {
    public static function get_engine(): BookingEngineInterface {
        $settings = get_option('lazy_settings', []);
        $mode = isset($settings['template_mode']) ? $settings['template_mode'] : 'service';
        if ( $mode === 'hotel' ) {
            return new HotelEngine();
        }
        return new ServiceEngine();
    }
}
