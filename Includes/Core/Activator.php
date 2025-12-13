<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Activator {

    public static function activate(): void {
        // Tabellen anlegen
        LTLB_DB_Migrator::migrate();

        // Default-Options (nur anlegen, wenn nicht vorhanden)
        add_option('lazy_settings', [
            'timezone' => 'Europe/Berlin',
            // Production defaults
            'logging_enabled' => 0,
            'log_level' => 'error',
            // Reserved flags for future privacy/rate limiting/email tests
            'rate_limit_enabled' => 0,
            'delete_data_on_uninstall' => 0,
        ]);

        add_option('lazy_design', [
            'background' => '#FDFCF8',
            'primary'    => '#A67B5B',
            'text'       => '#3D3D3D',
            'accent'     => '#8DA399',
        ]);
    }
}