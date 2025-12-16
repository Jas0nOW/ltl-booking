<?php
function compile_mo($po_file, $mo_file) {
    $lines = file($po_file, FILE_IGNORE_NEW_LINES);
    $entries = [];
    $msgid = null;
    $msgstr = null;
    foreach ($lines as $line) {
        if (preg_match('/^msgid\s+"(.*)"/', $line, $m)) {
            if ($msgid && $msgstr) {
                $entries[$msgid] = $msgstr;
            }
            $msgid = stripcslashes($m[1]);
            $msgstr = null;
        } elseif (preg_match('/^msgstr\s+"(.*)"/', $line, $m)) {
            $msgstr = stripcslashes($m[1]);
        }
    }
    if ($msgid && $msgstr) {
        $entries[$msgid] = $msgstr;
    }
    unset($entries['']);
    $magic = pack('V', 0x950412de);
    $count = count($entries);
    $data = $magic . pack('VVVVV', 0, $count, 28, 28 + $count * 8, 0);
    $originals = [];
    $translations = [];
    foreach ($entries as $k => $v) {
        $originals[] = $k;
        $translations[] = $v;
    }
    $origOffset = strlen($data) + $count * 8 * 2;
    $transOffset = $origOffset;
    foreach ($originals as $str) {
        $transOffset += strlen($str) + 1;
    }
    foreach ($originals as $str) {
        $data .= pack('VV', strlen($str), $origOffset);
        $origOffset += strlen($str) + 1;
    }
    foreach ($translations as $str) {
        $data .= pack('VV', strlen($str), $transOffset);
        $transOffset += strlen($str) + 1;
    }
    foreach ($originals as $str) {
        $data .= $str . chr(0);
    }
    foreach ($translations as $str) {
        $data .= $str . chr(0);
    }
    file_put_contents($mo_file, $data);
    return $count;
}
$count = compile_mo('de_DE.po', 'de_DE.mo');
echo "Compiled $count unique entries\n";
$info = stat('de_DE.mo');
echo "File: " . $info['size'] . " bytes\n";
