<?php
/**
 * Show untranslated strings in PO file
 */
$po_file = __DIR__ . '/../languages/de_DE.po';
$content = file_get_contents($po_file);

preg_match_all('/msgid "([^"]+)"\s*\nmsgstr ""/', $content, $matches);

echo "=== First 100 Untranslated Strings ===\n\n";

$count = min(100, count($matches[1]));
for ($i = 0; $i < $count; $i++) {
    $str = $matches[1][$i];
    echo ($i + 1) . ". " . $str . "\n";
}

echo "\n=== Total untranslated: " . count($matches[1]) . " ===\n";
