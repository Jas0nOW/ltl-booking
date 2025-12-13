<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Activator {

    public static function activate(): void {
        // Tabellen anlegen
        LTLB_DB_Migrator::migrate();

        // Default-Options (nur anlegen, wenn nicht vorhanden)
        add_option('lazy_settings', [
            'timezone' => 'Europe/Berlin',
        ]);

        add_option('lazy_design', [
            'background' => '#FDFCF8',
            'primary'    => '#A67B5B',
            'text'       => '#3D3D3D',
            'accent'     => '#8DA399',
        ]);
    }
}