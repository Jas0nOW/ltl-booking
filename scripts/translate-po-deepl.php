<?php
/**
 * Auto-Translate PO File using DeepL API
 * 
 * Usage: php scripts/translate-po-deepl.php [--api-key=YOUR_KEY] [--dry-run]
 * 
 * Set DEEPL_API_KEY environment variable or pass via --api-key
 */

if ( php_sapi_name() !== 'cli' ) {
    die( 'CLI only' );
}

// Parse arguments
$args = [];
foreach ( $argv as $arg ) {
    if ( strpos( $arg, '--' ) === 0 ) {
        $parts = explode( '=', substr( $arg, 2 ), 2 );
        $args[ $parts[0] ] = $parts[1] ?? true;
    }
}

$dry_run = isset( $args['dry-run'] );
$api_key = $args['api-key'] ?? getenv( 'DEEPL_API_KEY' );

if ( ! $api_key ) {
    echo "❌ DeepL API key required!\n";
    echo "Set DEEPL_API_KEY environment variable or use --api-key=YOUR_KEY\n";
    echo "Get a free API key at: https://www.deepl.com/pro-api\n";
    exit( 1 );
}

$po_file = __DIR__ . '/../languages/de_DE.po';

if ( ! file_exists( $po_file ) ) {
    echo "❌ PO file not found: $po_file\n";
    exit( 1 );
}

echo "📂 Loading PO file...\n";
$content = file_get_contents( $po_file );
$lines = explode( "\n", $content );

// Parse PO file
$entries = [];
$current = null;
$in_msgid = false;
$in_msgstr = false;

foreach ( $lines as $i => $line ) {
    $line_trimmed = trim( $line );
    
    // Start of new msgid
    if ( preg_match( '/^msgid "(.*)"\s*$/', $line_trimmed, $m ) ) {
        if ( $current !== null ) {
            $entries[] = $current;
        }
        $current = [
            'msgid'        => $m[1],
            'msgstr'       => '',
            'msgid_line'   => $i,
            'msgstr_line'  => null,
            'is_multiline' => false,
        ];
        $in_msgid = true;
        $in_msgstr = false;
    }
    // Continuation of msgid (multiline)
    elseif ( $in_msgid && preg_match( '/^"(.*)"\s*$/', $line_trimmed, $m ) ) {
        $current['msgid'] .= $m[1];
        $current['is_multiline'] = true;
    }
    // Start of msgstr
    elseif ( preg_match( '/^msgstr "(.*)"\s*$/', $line_trimmed, $m ) ) {
        if ( $current !== null ) {
            $current['msgstr'] = $m[1];
            $current['msgstr_line'] = $i;
        }
        $in_msgid = false;
        $in_msgstr = true;
    }
    // Continuation of msgstr (multiline)
    elseif ( $in_msgstr && preg_match( '/^"(.*)"\s*$/', $line_trimmed, $m ) ) {
        $current['msgstr'] .= $m[1];
    }
    // End of entry
    elseif ( $line_trimmed === '' || strpos( $line_trimmed, '#' ) === 0 ) {
        $in_msgid = false;
        $in_msgstr = false;
    }
}

// Don't forget last entry
if ( $current !== null ) {
    $entries[] = $current;
}

// Filter entries that need translation
$to_translate = [];
foreach ( $entries as $entry ) {
    // Skip header, empty msgid, or already translated
    if ( empty( $entry['msgid'] ) || ! empty( $entry['msgstr'] ) ) {
        continue;
    }
    // Skip plural forms for now
    if ( strpos( $entry['msgid'], '%' ) !== false && strpos( $entry['msgid'], '%s' ) === false && strpos( $entry['msgid'], '%d' ) === false ) {
        continue;
    }
    $to_translate[] = $entry;
}

$total = count( $to_translate );
echo "📊 Found $total strings to translate\n";

if ( $total === 0 ) {
    echo "✅ All strings already translated!\n";
    exit( 0 );
}

if ( $dry_run ) {
    echo "🔍 Dry run - would translate $total strings\n";
    echo "First 10 strings:\n";
    for ( $i = 0; $i < min( 10, $total ); $i++ ) {
        echo "  - " . substr( $to_translate[$i]['msgid'], 0, 60 ) . "...\n";
    }
    exit( 0 );
}

// DeepL API function
function deepl_translate( string $text, string $api_key ): ?string {
    // Preserve placeholders
    $placeholders = [];
    $text = preg_replace_callback( '/(%[sd]|%\d+\$[sd]|\{[^}]+\})/', function( $m ) use ( &$placeholders ) {
        $key = '[[PH' . count( $placeholders ) . ']]';
        $placeholders[ $key ] = $m[0];
        return $key;
    }, $text );
    
    $url = 'https://api-free.deepl.com/v2/translate';
    
    // Check if it's a paid API key
    if ( strpos( $api_key, ':fx' ) === false ) {
        $url = 'https://api.deepl.com/v2/translate';
    }
    
    $data = [
        'auth_key'    => $api_key,
        'text'        => $text,
        'source_lang' => 'EN',
        'target_lang' => 'DE',
    ];
    
    $ch = curl_init();
    curl_setopt_array( $ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query( $data ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );
    
    if ( $http_code !== 200 ) {
        return null;
    }
    
    $result = json_decode( $response, true );
    if ( ! isset( $result['translations'][0]['text'] ) ) {
        return null;
    }
    
    $translated = $result['translations'][0]['text'];
    
    // Restore placeholders
    foreach ( $placeholders as $key => $value ) {
        $translated = str_replace( $key, $value, $translated );
    }
    
    return $translated;
}

// Batch translate (DeepL supports up to 50 texts per request)
$batch_size = 50;
$batches = array_chunk( $to_translate, $batch_size );
$translations = [];

echo "🚀 Starting translation...\n";

foreach ( $batches as $batch_num => $batch ) {
    $texts = array_column( $batch, 'msgid' );
    
    // Preserve placeholders in all texts
    $placeholder_maps = [];
    $safe_texts = [];
    
    foreach ( $texts as $idx => $text ) {
        $placeholders = [];
        $safe = preg_replace_callback( '/(%[sd]|%\d+\$[sd]|\{[^}]+\})/', function( $m ) use ( &$placeholders ) {
            $key = '[[PH' . count( $placeholders ) . ']]';
            $placeholders[ $key ] = $m[0];
            return $key;
        }, $text );
        $placeholder_maps[ $idx ] = $placeholders;
        $safe_texts[] = $safe;
    }
    
    // DeepL batch request
    $url = strpos( $api_key, ':fx' ) === false 
        ? 'https://api.deepl.com/v2/translate' 
        : 'https://api-free.deepl.com/v2/translate';
    
    $post_data = [
        'auth_key'    => $api_key,
        'source_lang' => 'EN',
        'target_lang' => 'DE',
    ];
    
    // Add each text as separate parameter
    $post_string = http_build_query( $post_data );
    foreach ( $safe_texts as $text ) {
        $post_string .= '&' . http_build_query( [ 'text' => $text ] );
    }
    
    $ch = curl_init();
    curl_setopt_array( $ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post_string,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );
    
    if ( $http_code !== 200 ) {
        echo "❌ API error (batch $batch_num): HTTP $http_code\n";
        echo "Response: $response\n";
        continue;
    }
    
    $result = json_decode( $response, true );
    if ( ! isset( $result['translations'] ) ) {
        echo "❌ Invalid response (batch $batch_num)\n";
        continue;
    }
    
    // Map translations back
    foreach ( $result['translations'] as $idx => $trans ) {
        $translated = $trans['text'];
        
        // Restore placeholders
        foreach ( $placeholder_maps[ $idx ] as $key => $value ) {
            $translated = str_replace( $key, $value, $translated );
        }
        
        $batch[ $idx ]['translation'] = $translated;
        $translations[] = $batch[ $idx ];
    }
    
    $done = count( $translations );
    $pct = round( ( $done / $total ) * 100 );
    echo "📝 Translated $done / $total ($pct%)\r";
    
    // Rate limiting - DeepL free tier is quite generous
    usleep( 100000 ); // 100ms between batches
}

echo "\n✅ Translation complete: " . count( $translations ) . " strings\n";

// Update PO file
echo "💾 Updating PO file...\n";

// Build a map of msgid => translation
$translation_map = [];
foreach ( $translations as $t ) {
    $translation_map[ $t['msgid'] ] = $t['translation'];
}

// Re-read and update the PO file
$output_lines = [];
$current_msgid = null;
$msgid_buffer = '';
$in_msgid = false;
$in_msgstr = false;
$skip_next_empty_msgstr = false;

foreach ( $lines as $line ) {
    $line_trimmed = trim( $line );
    
    // Start of msgid
    if ( preg_match( '/^msgid "(.*)"\s*$/', $line_trimmed, $m ) ) {
        $msgid_buffer = $m[1];
        $in_msgid = true;
        $in_msgstr = false;
        $output_lines[] = $line;
    }
    // Continuation of msgid
    elseif ( $in_msgid && preg_match( '/^"(.*)"\s*$/', $line_trimmed, $m ) ) {
        $msgid_buffer .= $m[1];
        $output_lines[] = $line;
    }
    // Start of msgstr
    elseif ( preg_match( '/^msgstr "(.*)"\s*$/', $line_trimmed, $m ) ) {
        $in_msgid = false;
        $in_msgstr = true;
        
        $existing = $m[1];
        
        // Check if this msgid has a translation and msgstr is empty
        if ( empty( $existing ) && isset( $translation_map[ $msgid_buffer ] ) ) {
            $translation = $translation_map[ $msgid_buffer ];
            // Escape quotes in translation
            $translation = str_replace( '"', '\\"', $translation );
            $output_lines[] = 'msgstr "' . $translation . '"';
        } else {
            $output_lines[] = $line;
        }
        
        $msgid_buffer = '';
    }
    // Continuation of msgstr
    elseif ( $in_msgstr && preg_match( '/^"(.*)"\s*$/', $line_trimmed, $m ) ) {
        $output_lines[] = $line;
    }
    // Other lines (comments, empty, etc.)
    else {
        $in_msgid = false;
        $in_msgstr = false;
        $output_lines[] = $line;
    }
}

// Write updated PO file
$backup_file = $po_file . '.bak';
copy( $po_file, $backup_file );
file_put_contents( $po_file, implode( "\n", $output_lines ) );

echo "✅ PO file updated! Backup saved to: $backup_file\n";
echo "\n📌 Next steps:\n";
echo "   1. Review translations in Poedit or text editor\n";
echo "   2. Compile MO file: php scripts/compile-mo.php\n";
