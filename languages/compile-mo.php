<?php
/**
 * Simple .po to .mo compiler
 */

class MO_Compiler {
    private $entries = [];

    public function compile($poFile, $moFile) {
        $lines = file($poFile);
        $msgid = null;
        $msgstr = null;
        $context = '';

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#') {
                if ($msgid !== null && $msgstr !== null) {
                    $this->entries[$msgid] = $msgstr;
                    $msgid = null;
                    $msgstr = null;
                }
                continue;
            }

            // Match msgid
            if (preg_match('/^msgid "(.*)"\s*$/', $line, $matches)) {
                if ($msgid !== null && $msgstr !== null) {
                    $this->entries[$msgid] = $msgstr;
                }
                $msgid = $this->unescape($matches[1]);
                $msgstr = null;
                $context = 'msgid';
            }
            // Match msgstr
            elseif (preg_match('/^msgstr "(.*)"\s*$/', $line, $matches)) {
                $msgstr = $this->unescape($matches[1]);
                $context = 'msgstr';
            }
            // Multiline continuation
            elseif (preg_match('/^"(.*)"\s*$/', $line, $matches)) {
                if ($context === 'msgid' && $msgid !== null) {
                    $msgid .= $this->unescape($matches[1]);
                } elseif ($context === 'msgstr' && $msgstr !== null) {
                    $msgstr .= $this->unescape($matches[1]);
                }
            }
        }

        // Don't forget the last entry
        if ($msgid !== null && $msgstr !== null) {
            $this->entries[$msgid] = $msgstr;
        }

        $this->writeMO($moFile);
        echo "✓ Compiled " . count($this->entries) . " entries to $moFile\n";
    }

    private function unescape($string) {
        return stripcslashes($string);
    }

    private function writeMO($file) {
        $count = count($this->entries);
        
        // Build string tables
        $ids = '';
        $strs = '';
        $offsets = [];
        
        $offset = 28 + 8 * $count * 2; // Header + hash tables
        
        foreach ($this->entries as $msgid => $msgstr) {
            $offsets[] = ['id_len' => strlen($msgid), 'id_offset' => $offset];
            $offset += strlen($msgid) + 1;
        }
        
        foreach ($this->entries as $msgid => $msgstr) {
            $offsets[] = ['str_len' => strlen($msgstr), 'str_offset' => $offset];
            $offset += strlen($msgstr) + 1;
        }

        // Original strings table
        $origTable = '';
        $i = 0;
        foreach ($this->entries as $msgid => $msgstr) {
            $origTable .= pack('V', $offsets[$i]['id_len']);
            $origTable .= pack('V', $offsets[$i]['id_offset']);
            $i++;
        }

        // Translation strings table
        $transTable = '';
        foreach ($this->entries as $msgid => $msgstr) {
            $transTable .= pack('V', $offsets[$i]['str_len']);
            $transTable .= pack('V', $offsets[$i]['str_offset']);
            $i++;
        }

        // String data
        foreach ($this->entries as $msgid => $msgstr) {
            $ids .= $msgid . "\0";
        }
        foreach ($this->entries as $msgid => $msgstr) {
            $strs .= $msgstr . "\0";
        }

        // Magic number (little endian)
        $magic = 0x950412de;
        $revision = 0;
        
        $header = pack('V', $magic);           // Magic
        $header .= pack('V', $revision);       // Revision
        $header .= pack('V', $count);          // Number of strings
        $header .= pack('V', 28);              // Offset of original strings table
        $header .= pack('V', 28 + 8 * $count); // Offset of translation strings table
        $header .= pack('V', 0);               // Size of hash table
        $header .= pack('V', 0);               // Offset of hash table

        file_put_contents($file, $header . $origTable . $transTable . $ids . $strs);
    }
}

$compiler = new MO_Compiler();
$compiler->compile(__DIR__ . '/de_DE.po', __DIR__ . '/de_DE.mo');
