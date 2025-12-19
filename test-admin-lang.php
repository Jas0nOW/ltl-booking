<?php
/**
 * Test Admin Language Switching
 * 
 * Usage: Visit this URL as logged-in admin:
 * /wp-content/plugins/ltl-bookings/test-admin-lang.php?set=de_DE
 * 
 * Then reload any admin page to see the change.
 */

// Load WordPress
$wp_load_paths = [
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
];

$loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die('Could not load WordPress');
}

if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

$user_id = get_current_user_id();

// Include I18n if not loaded
if (!class_exists('LTLB_I18n')) {
    require_once dirname(__FILE__) . '/Includes/Util/I18n.php';
}

echo '<h1>Admin Language Test</h1>';
echo '<pre>';

// Current state
echo "User ID: $user_id\n";
echo "WordPress Locale: " . get_locale() . "\n";

$current_admin_lang = get_user_meta($user_id, 'ltlb_admin_lang', true);
echo "Current ltlb_admin_lang meta: " . ($current_admin_lang ?: '(not set)') . "\n\n";

// Set new language if requested
if (isset($_GET['set']) && $_GET['set']) {
    $new_locale = sanitize_text_field($_GET['set']);
    echo "Setting admin language to: $new_locale\n";
    
    if (LTLB_I18n::is_supported_locale($new_locale)) {
        LTLB_I18n::set_user_admin_locale($user_id, $new_locale);
        echo "✅ Language set successfully!\n\n";
        
        $verify = get_user_meta($user_id, 'ltlb_admin_lang', true);
        echo "Verification - new value: $verify\n";
    } else {
        echo "❌ Invalid locale!\n";
    }
}

// Test AJAX handler directly
if (isset($_GET['ajax_test'])) {
    echo "\n--- Testing AJAX Handler ---\n";
    $_POST['locale'] = $_GET['ajax_test'];
    
    ob_start();
    LTLB_I18n::ajax_set_admin_lang();
    $output = ob_get_clean();
    echo "AJAX Response: $output\n";
}

echo "\n--- Supported Locales ---\n";
foreach (LTLB_I18n::SUPPORTED_LOCALES as $code => $info) {
    echo "$code => {$info['name']} ({$info['flag']})\n";
}

echo "\n--- Test Links ---\n";
echo '</pre>';

$base_url = plugin_dir_url(__FILE__) . 'test-admin-lang.php';
?>
<p><a href="<?php echo esc_url($base_url . '?set=de_DE'); ?>">Set German (de_DE)</a></p>
<p><a href="<?php echo esc_url($base_url . '?set=en_US'); ?>">Set English (en_US)</a></p>
<p><a href="<?php echo esc_url($base_url . '?set=es_ES'); ?>">Set Spanish (es_ES)</a></p>
<p><a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_dashboard')); ?>">Go to Dashboard</a></p>

<hr>
<h2>Browser Console Check</h2>
<p>Open browser DevTools (F12) and check for:</p>
<ul>
    <li><code>[LTLB] Language selector found</code> - Selector is working</li>
    <li><code>[LTLB] Changing language to:</code> - Change event fired</li>
    <li><code>[LTLB] AJAX response:</code> - Server response</li>
</ul>

<h2>Manual AJAX Test</h2>
<p>Run this in browser console while on any admin page:</p>
<pre style="background:#f0f0f0;padding:10px;">
fetch(ajaxurl, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=ltlb_set_admin_lang&locale=de_DE'
}).then(r => r.json()).then(console.log);
</pre>
