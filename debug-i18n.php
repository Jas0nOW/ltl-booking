<?php
/**
 * Debug script to check I18n functionality
 */
require_once __DIR__ . '/../../../wp-load.php';

if (!is_user_logged_in()) {
	die("You must be logged in to run this debug script.");
}

$user_id = get_current_user_id();

echo "=== I18n Debug Information ===\n\n";

// 1. Check user meta
$saved_locale = get_user_meta($user_id, 'ltlb_admin_lang', true);
echo "Current User ID: $user_id\n";
echo "Saved Locale (ltlb_admin_lang): " . ($saved_locale ? $saved_locale : 'NOT SET') . "\n\n";

// 2. Check .mo file
$plugin_dir = __DIR__;
$mo_file = $plugin_dir . '/languages/de_DE.mo';
echo "Plugin Directory: $plugin_dir\n";
echo ".mo File Path: $mo_file\n";
echo ".mo File Exists: " . (file_exists($mo_file) ? 'YES' : 'NO') . "\n";

if (file_exists($mo_file)) {
	echo ".mo File Size: " . filesize($mo_file) . " bytes\n";
	
	// Try to parse magic number
	$data = file_get_contents($mo_file);
	if ($data) {
		$magic = unpack('V', substr($data, 0, 4))[1];
		$count = unpack('V', substr($data, 8, 4))[1];
		echo ".mo Magic Number: 0x" . dechex($magic) . " " . ($magic === 0x950412de ? '(VALID)' : '(INVALID!)') . "\n";
		echo ".mo Translation Count: $count\n";
	}
}

echo "\n";

// 3. Test I18n class
require_once __DIR__ . '/Includes/Util/I18n.php';

echo "=== I18n Class Test ===\n";
$test_locale = LTLB_I18n::get_user_admin_locale();
echo "I18n::get_user_admin_locale(): $test_locale\n";

// 4. Test dictionary loading using reflection
$class = new ReflectionClass('LTLB_I18n');
$method = $class->getMethod('get_de_dictionary');
$method->setAccessible(true);
$dict = $method->invoke(null);

echo "Dictionary Size: " . count($dict) . " entries\n\n";

if (count($dict) > 0) {
	echo "First 10 translations:\n";
	$i = 0;
	foreach ($dict as $en => $de) {
		if ($i++ >= 10) break;
		echo "  '$en' => '$de'\n";
	}
	
	// Test specific strings
	echo "\nTest Translations:\n";
	$test_strings = ['Dashboard', 'Settings', 'Appointments', 'Services', 'Customers', 'Staff'];
	foreach ($test_strings as $str) {
		$trans = $dict[$str] ?? 'NOT FOUND';
		echo "  '$str' => '$trans'\n";
	}
} else {
	echo "ERROR: Dictionary is EMPTY!\n";
}

echo "\n=== End Debug ===\n";
