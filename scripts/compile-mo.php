<?php
/**
 * Simple .po to .mo compiler
 * Run with: php compile-mo.php
 * 
 * Compiles all .po files in the languages folder
 */

// Find all .po files
$langDir = __DIR__ . '/../languages/';
$poFiles = glob($langDir . '*.po');

if (empty($poFiles)) {
    die("Error: No .po files found in $langDir\n");
}

foreach ($poFiles as $poFile) {
    $moFile = preg_replace('/\.po$/', '.mo', $poFile);
    compilePo($poFile, $moFile);
}

echo "\n✅ All translations compiled!\n";
exit(0);

function compilePo($poFile, $moFile) {
if (!file_exists($poFile)) {
    echo "⚠️ Skipping: $poFile not found\n";
    return;
}

echo "Compiling $poFile to $moFile...\n";

// Read .po file
$poContent = file_get_contents($poFile);

// Parse translations
$translations = [];
$currentMsgid = null;
$currentMsgstr = null;
$inMsgid = false;
$inMsgstr = false;

$lines = explode("\n", (string) $poContent);
foreach ($lines as $line) {
    $line = trim($line);
    
    // Skip comments and empty lines
    if (empty($line) || $line[0] === '#') {
        continue;
    }
    
    // Start of msgid
    if (strpos((string) $line, 'msgid "') === 0) {
        // Save previous translation
        if ($currentMsgid !== null && $currentMsgstr !== null) {
            $translations[$currentMsgid] = $currentMsgstr;
        }
        
        $currentMsgid = substr($line, 7, -1); // Remove 'msgid "' and '"'
        $currentMsgstr = null;
        $inMsgid = true;
        $inMsgstr = false;
        continue;
    }
    
    // Start of msgstr
    if (strpos((string) $line, 'msgstr "') === 0) {
        $currentMsgstr = substr($line, 8, -1); // Remove 'msgstr "' and '"'
        $inMsgid = false;
        $inMsgstr = true;
        continue;
    }
    
    // Continuation line
    if ($line[0] === '"' && substr($line, -1) === '"') {
        $content = substr($line, 1, -1);
        if ($inMsgid) {
            $currentMsgid .= $content;
        } elseif ($inMsgstr) {
            $currentMsgstr .= $content;
        }
    }
}

// Save last translation
if ($currentMsgid !== null && $currentMsgstr !== null) {
    $translations[$currentMsgid] = $currentMsgstr;
}

// Remove empty msgstr and header
unset($translations['']);

echo "Found " . count($translations) . " translations\n";

// Build .mo file (simplified format)
// MO file format: magic number, revision, string count, offset table, hash table, strings
$magic = 0x950412de; // Little endian magic number
$revision = 0;
$numStrings = count($translations);

// Build string tables
$origTable = [];
$transTable = [];
$origStrings = '';
$transStrings = '';

foreach ($translations as $msgid => $msgstr) {
    // Skip empty translations
    if (empty($msgstr)) {
        continue;
    }
    
    $origTable[] = [strlen($msgid), strlen($origStrings)];
    $origStrings .= $msgid . "\0";
    
    $transTable[] = [strlen($msgstr), strlen($transStrings)];
    $transStrings .= $msgstr . "\0";
}

$numStrings = count($origTable);

// Calculate offsets
$headerSize = 28; // 7 * 4 bytes
$origTableOffset = $headerSize;
$transTableOffset = $origTableOffset + ($numStrings * 8); // 2 * 4 bytes per entry
$hashTableOffset = 0; // We're not using hash table
$origStringsOffset = $transTableOffset + ($numStrings * 8);
$transStringsOffset = $origStringsOffset + strlen($origStrings);

// Build binary data
$data = pack('V7',
    $magic,                  // magic number
    $revision,               // revision
    $numStrings,             // number of strings
    $origTableOffset,        // offset of original strings table
    $transTableOffset,       // offset of translated strings table
    0,                       // size of hash table (not used)
    $hashTableOffset         // offset of hash table (not used)
);

// Add original strings table
foreach ($origTable as $entry) {
    $data .= pack('V2', $entry[0], $origStringsOffset + $entry[1]);
}

// Add translated strings table
foreach ($transTable as $entry) {
    $data .= pack('V2', $entry[0], $transStringsOffset + $entry[1]);
}

// Add strings
$data .= $origStrings;
$data .= $transStrings;

// Write .mo file
file_put_contents($moFile, $data);

$basename = basename($moFile);
echo "✅ $basename - " . count($translations) . " entries, " . filesize($moFile) . " bytes\n";
}
