<?php
/**
 * Compile .po to .mo file
 * Quick helper script for translation compilation
 */

if ( php_sapi_name() !== 'cli' ) {
    die( 'This script must be run from the command line.' );
}

$po_file = __DIR__ . '/../languages/de_DE.po';
$mo_file = __DIR__ . '/../languages/de_DE.mo';

if ( ! file_exists( $po_file ) ) {
    die( "PO file not found: {$po_file}\n" );
}

echo "Compiling {$po_file} to {$mo_file}...\n";

// Simple PO to MO compiler
class PoToMo {
    public static function compile( string $po_file, string $mo_file ): bool {
        $po_content = file_get_contents( $po_file );
        $entries = self::parse_po( $po_content );
        
        return self::write_mo( $entries, $mo_file );
    }
    
    private static function parse_po( string $content ): array {
        $entries = [];
        $lines = explode( "\n", $content );
        $msgid = '';
        $msgstr = '';
        $in_msgid = false;
        $in_msgstr = false;
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            
            if ( empty( $line ) || $line[0] === '#' ) {
                if ( ! empty( $msgid ) && ! empty( $msgstr ) ) {
                    $entries[ $msgid ] = $msgstr;
                }
                $msgid = '';
                $msgstr = '';
                $in_msgid = false;
                $in_msgstr = false;
                continue;
            }
            
            if ( strpos( $line, 'msgid' ) === 0 ) {
                if ( ! empty( $msgid ) && ! empty( $msgstr ) ) {
                    $entries[ $msgid ] = $msgstr;
                    $msgstr = '';
                }
                preg_match( '/msgid\s+"(.*)"/U', $line, $matches );
                $msgid = $matches[1] ?? '';
                $in_msgid = true;
                $in_msgstr = false;
            } elseif ( strpos( $line, 'msgstr' ) === 0 ) {
                preg_match( '/msgstr\s+"(.*)"/U', $line, $matches );
                $msgstr = $matches[1] ?? '';
                $in_msgid = false;
                $in_msgstr = true;
            } elseif ( $line[0] === '"' && $in_msgid ) {
                preg_match( '/"(.*)"/U', $line, $matches );
                $msgid .= $matches[1] ?? '';
            } elseif ( $line[0] === '"' && $in_msgstr ) {
                preg_match( '/"(.*)"/U', $line, $matches );
                $msgstr .= $matches[1] ?? '';
            }
        }
        
        if ( ! empty( $msgid ) && ! empty( $msgstr ) ) {
            $entries[ $msgid ] = $msgstr;
        }
        
        return $entries;
    }
    
    private static function write_mo( array $entries, string $mo_file ): bool {
        $magic = 0x950412de;
        $revision = 0;
        $count = count( $entries );
        
        // Build tables
        $originals = '';
        $translations = '';
        $orig_table = '';
        $trans_table = '';
        $offset = 28 + $count * 16;
        
        foreach ( $entries as $orig => $trans ) {
            $orig_len = strlen( $orig );
            $trans_len = strlen( $trans );
            
            $orig_table .= pack( 'VV', $orig_len, $offset );
            $originals .= $orig . "\0";
            $offset += $orig_len + 1;
            
            $trans_table .= pack( 'VV', $trans_len, $offset );
            $translations .= $trans . "\0";
            $offset += $trans_len + 1;
        }
        
        // Build header
        $header = pack( 'Vx', $magic ); // Magic number
        $header .= pack( 'V', $revision ); // Revision
        $header .= pack( 'V', $count ); // Number of strings
        $header .= pack( 'V', 28 ); // Offset of original table
        $header .= pack( 'V', 28 + $count * 8 ); // Offset of translation table
        $header .= pack( 'V', 0 ); // Hash table size
        $header .= pack( 'V', 0 ); // Hash table offset
        
        $mo_content = $header . $orig_table . $trans_table . $originals . $translations;
        
        return file_put_contents( $mo_file, $mo_content ) !== false;
    }
}

$result = PoToMo::compile( $po_file, $mo_file );

if ( $result ) {
    echo "✓ Successfully compiled to {$mo_file}\n";
    echo "File size: " . filesize( $mo_file ) . " bytes\n";
    exit( 0 );
} else {
    echo "✗ Failed to compile MO file\n";
    exit( 1 );
}
