<?php
/**
 * Test I18n AJAX handler
 * Access via: /wp-admin/admin.php?page=ltlb_test_i18n
 */

// Simulate AJAX request to test handler
add_action('admin_menu', function() {
    add_submenu_page(
        null, // Hidden
        'Test I18n',
        'Test I18n',
        'manage_options',
        'ltlb_test_i18n',
        function() {
            echo '<h1>I18n Test</h1>';
            
            // Check if class exists
            if (!class_exists('LTLB_I18n')) {
                echo '<p style="color:red;">❌ LTLB_I18n class not found!</p>';
                return;
            }
            
            echo '<p style="color:green;">✅ LTLB_I18n class exists</p>';
            
            // Check current locale
            $current = LTLB_I18n::get_current_locale();
            echo '<p>Current locale: <strong>' . esc_html($current) . '</strong></p>';
            
            // Check user admin locale
            $user_locale = LTLB_I18n::get_user_admin_locale();
            echo '<p>User admin locale (meta): <strong>' . esc_html($user_locale ?? 'not set') . '</strong></p>';
            
            // Check cookie locale
            $cookie_locale = LTLB_I18n::get_cookie_locale();
            echo '<p>Cookie locale: <strong>' . esc_html($cookie_locale ?? 'not set') . '</strong></p>';
            
            // Test setting locale
            echo '<h2>Test AJAX</h2>';
            echo '<p>AJAX URL: <code>' . esc_html(admin_url('admin-ajax.php')) . '</code></p>';
            echo '<p>Action: <code>ltlb_set_admin_lang</code></p>';
            
            // Show registered AJAX actions
            global $wp_filter;
            $ajax_action = 'wp_ajax_ltlb_set_admin_lang';
            if (isset($wp_filter[$ajax_action])) {
                echo '<p style="color:green;">✅ AJAX handler registered</p>';
            } else {
                echo '<p style="color:red;">❌ AJAX handler NOT registered!</p>';
            }
            
            // Manual test form
            echo '<h2>Manual Test</h2>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-ajax.php')) . '">';
            echo '<input type="hidden" name="action" value="ltlb_set_admin_lang">';
            echo '<select name="locale">';
            echo '<option value="en_US">English</option>';
            echo '<option value="de_DE">Deutsch</option>';
            echo '<option value="es_ES">Español</option>';
            echo '</select>';
            echo '<button type="submit">Test POST</button>';
            echo '</form>';
            
            // Check MO file
            echo '<h2>MO File Check</h2>';
            $mo_file = LTLB_PATH . 'languages/de_DE.mo';
            if (file_exists($mo_file)) {
                echo '<p style="color:green;">✅ de_DE.mo exists (' . filesize($mo_file) . ' bytes)</p>';
            } else {
                echo '<p style="color:red;">❌ de_DE.mo not found at: ' . esc_html($mo_file) . '</p>';
            }
        }
    );
});
