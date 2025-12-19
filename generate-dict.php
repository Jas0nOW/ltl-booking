<?php
/**
 * Generate PHP dictionary array from .po file
 */

$po_file = __DIR__ . '/languages/de_DE.po';
$output_file = __DIR__ . '/dictionary-generated.php';

if (!file_exists($po_file)) {
    die("ERROR: .po file not found at: $po_file\n");
}

$content = file_get_contents($po_file);
$lines = explode("\n", (string) $content);

$translations = [];
$msgid = null;
$msgstr = null;

foreach ($lines as $line) {
    $line = trim($line);
    
    // Start of msgid
    if (strpos((string) $line, 'msgid "') === 0) {
        $msgid = substr($line, 7, -1); // Remove 'msgid "' and trailing '"'
    }
    // Continuation of msgid
    elseif ($msgid !== null && $msgstr === null && strpos((string) $line, '"') === 0) {
        $msgid .= substr($line, 1, -1);
    }
    // Start of msgstr
    elseif (strpos((string) $line, 'msgstr "') === 0) {
        $msgstr = substr($line, 8, -1); // Remove 'msgstr "' and trailing '"'
    }
    // Continuation of msgstr
    elseif ($msgstr !== null && strpos((string) $line, '"') === 0) {
        $msgstr .= substr($line, 1, -1);
    }
    // Empty line or new entry - save current translation
    elseif (($line === '' || strpos((string) $line, '#') === 0 || strpos((string) $line, 'msgid') === 0) && $msgid !== null && $msgstr !== null) {
        // Skip empty msgid (header) and empty translations
        if ($msgid !== '' && $msgstr !== '') {
            // Unescape
            $msgid = stripcslashes($msgid);
            $msgstr = stripcslashes($msgstr);
            $translations[$msgid] = $msgstr;
        }
        $msgid = null;
        $msgstr = null;
        
        // If this line starts a new msgid, process it
        if (strpos((string) $line, 'msgid "') === 0) {
            $msgid = substr($line, 7, -1);
        }
    }
}

// Save last entry
if ($msgid !== null && $msgstr !== null && $msgid !== '' && $msgstr !== '') {
    $msgid = stripcslashes($msgid);
    $msgstr = stripcslashes($msgstr);
    $translations[$msgid] = $msgstr;
}

echo "Parsed " . count($translations) . " translations\n";

// Generate PHP array code
$output = "<?php\n// Auto-generated dictionary from de_DE.po\n// Generated: " . date('Y-m-d H:i:s') . "\n\n";
$output .= "return [\n";

foreach ($translations as $en => $de) {
    // Escape for PHP
    $en_escaped = addslashes($en);
    $de_escaped = addslashes($de);
    $output .= "\t'{$en_escaped}' => '{$de_escaped}',\n";
}

$output .= "];\n";

file_put_contents($output_file, $output);
echo "Dictionary written to: $output_file\n";
echo "Size: " . strlen($output) . " bytes\n";
