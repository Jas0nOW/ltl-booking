<?php
/**
 * Free Translation Helper - Using LibreTranslate
 * 
 * This creates a batch file that can be translated using free online tools.
 * Then you import the translations back.
 * 
 * Usage:
 * 1. Run: php scripts/export-for-translation.php es
 * 2. Copy the output from languages/es_ES_to_translate.txt
 * 3. Paste into Google Translate or DeepL
 * 4. Copy the result back
 * 5. Run: php scripts/import-translation.php es
 */

$target_lang = $argv[1] ?? 'es';
$po_file = __DIR__ . '/../languages/' . ($target_lang === 'es' ? 'es_ES' : 'de_DE') . '.po';
$de_file = __DIR__ . '/../languages/de_DE.po';
$output_file = __DIR__ . '/../languages/' . ($target_lang === 'es' ? 'es_ES' : 'de_DE') . '_to_translate.txt';

if (!file_exists($de_file)) {
    die("German PO file not found\n");
}

// Get all German translations
$de_content = file_get_contents($de_file);
$de_translations = [];
preg_match_all('/msgid "(.+?)"\s*\nmsgstr "(.+?)"/s', $de_content, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    $msgid = stripcslashes($m[1]);
    $msgstr = stripcslashes($m[2]);
    if ($msgid && $msgstr) {
        $de_translations[$msgid] = $msgstr;
    }
}

// Get strings that need translation in target language
$target_content = file_exists($po_file) ? file_get_contents($po_file) : '';
$needs_translation = [];
preg_match_all('/msgid "(.+?)"\s*\nmsgstr ""/s', $target_content, $matches);
foreach ($matches[1] as $msgid) {
    $msgid = stripcslashes($msgid);
    if (isset($de_translations[$msgid])) {
        $needs_translation[] = $de_translations[$msgid];
    }
}

echo "Found " . count($needs_translation) . " strings to translate\n";

// Export for translation
$output = "=== TRANSLATE FROM GERMAN TO SPANISH ===\n";
$output .= "=== ONE LINE = ONE TRANSLATION ===\n";
$output .= "=== KEEP THE ORDER! ===\n\n";

foreach ($needs_translation as $i => $text) {
    $output .= ($i + 1) . ". " . $text . "\n";
}

file_put_contents($output_file, $output);
echo "Exported to: $output_file\n";
echo "\nNext steps:\n";
echo "1. Open $output_file\n";
echo "2. Copy all lines after the header\n";
echo "3. Paste into Google Translate (German → Spanish)\n";
echo "4. Copy the translated result\n";
echo "5. Save as languages/es_ES_translated.txt\n";
echo "6. Run: php scripts/import-translation.php es\n";
