<?php
/**
 * Test script for .mo file parsing
 */

// Load the I18n class
require_once __DIR__ . '/Includes/Util/I18n.php';

// Use reflection to access private method
$class = new ReflectionClass('LTLB_I18n');
$method = $class->getMethod('parse_mo_file');
$method->setAccessible(true);

$mo_file = __DIR__ . '/languages/de_DE.mo';

if (!file_exists($mo_file)) {
	die("ERROR: .mo file not found at: $mo_file\n");
}

echo "Testing .mo file parsing...\n";
echo "File: $mo_file\n";
echo "File size: " . filesize($mo_file) . " bytes\n\n";

$translations = $method->invoke(null, $mo_file);

echo "Parsed translations: " . count($translations) . "\n\n";

if (count($translations) > 0) {
	echo "First 10 translations:\n";
	$count = 0;
	foreach ($translations as $msgid => $msgstr) {
		if ($count++ >= 10) break;
		if ($msgid !== '') {
			echo "  '$msgid' => '$msgstr'\n";
		}
	}
	
	// Test specific strings
	echo "\nTesting specific strings:\n";
	$test_strings = ['Dashboard', 'Settings', 'Services', 'Customers', 'Staff'];
	foreach ($test_strings as $str) {
		$translated = $translations[$str] ?? 'NOT FOUND';
		echo "  '$str' => '$translated'\n";
	}
} else {
	echo "ERROR: No translations parsed!\n";
}
