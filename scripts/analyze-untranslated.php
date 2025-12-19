<?php
/**
 * Show and translate remaining untranslated strings
 */
$po_file = __DIR__ . '/../languages/de_DE.po';
$content = file_get_contents($po_file);

// Find all untranslated strings (msgid followed by empty msgstr)
preg_match_all('/msgid "([^"]+)"\s*\nmsgstr ""/', $content, $matches);

$untranslated = $matches[1];
$total = count($untranslated);

echo "=== $total Untranslated Strings ===\n\n";

// Group by first word/type
$groups = [
    'validation' => [],
    'error' => [],
    'success' => [],
    'button' => [],
    'label' => [],
    'message' => [],
    'other' => [],
];

foreach ($untranslated as $str) {
    $lower = strtolower($str);
    if (strpos($lower, 'please') !== false || strpos($lower, 'must') !== false || strpos($lower, 'required') !== false) {
        $groups['validation'][] = $str;
    } elseif (strpos($lower, 'error') !== false || strpos($lower, 'failed') !== false || strpos($lower, 'could not') !== false) {
        $groups['error'][] = $str;
    } elseif (strpos($lower, 'success') !== false || strpos($lower, 'saved') !== false || strpos($lower, 'created') !== false || strpos($lower, 'deleted') !== false) {
        $groups['success'][] = $str;
    } elseif (strlen($str) < 20 && !strpos($str, ' ')) {
        $groups['button'][] = $str;
    } elseif (strlen($str) < 30) {
        $groups['label'][] = $str;
    } elseif (strpos($str, '.') !== false || strlen($str) > 50) {
        $groups['message'][] = $str;
    } else {
        $groups['other'][] = $str;
    }
}

foreach ($groups as $group => $strings) {
    if (empty($strings)) continue;
    echo "\n=== " . strtoupper($group) . " (" . count($strings) . ") ===\n";
    foreach (array_slice($strings, 0, 30) as $s) {
        echo "  '$s' => '',\n";
    }
    if (count($strings) > 30) {
        echo "  ... and " . (count($strings) - 30) . " more\n";
    }
}
