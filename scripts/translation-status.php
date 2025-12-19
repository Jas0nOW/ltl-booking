<?php
/**
 * Count translation status
 */

$de = file_get_contents(__DIR__ . '/../languages/de_DE.po');
$es = file_get_contents(__DIR__ . '/../languages/es_ES.po');

preg_match_all('/^msgstr ""$/m', $de, $de_empty);
preg_match_all('/^msgstr ""$/m', $es, $es_empty);
preg_match_all('/^msgid /m', $de, $de_total);

$total = count($de_total[0]) - 1; // minus header
$de_missing = count($de_empty[0]);
$es_missing = count($es_empty[0]);

echo "=== ÜBERSETZUNGSSTATUS ===\n\n";
echo "Total Strings: $total\n\n";
echo "🇩🇪 DEUTSCH:\n";
echo "   Übersetzt: " . ($total - $de_missing) . " (" . round(($total - $de_missing) / $total * 100) . "%)\n";
echo "   Fehlend:   $de_missing (" . round($de_missing / $total * 100) . "%)\n\n";
echo "🇪🇸 SPANISCH:\n";
echo "   Übersetzt: " . ($total - $es_missing) . " (" . round(($total - $es_missing) / $total * 100) . "%)\n";
echo "   Fehlend:   $es_missing (" . round($es_missing / $total * 100) . "%)\n\n";

echo "=== EMPFEHLUNG ===\n";
if ($de_missing > 100 || $es_missing > 100) {
    echo "⚠️  Viele Strings fehlen noch!\n";
    echo "    Für vollständige Übersetzung empfohlen:\n";
    echo "    - DeepL API (beste Qualität): php scripts/translate-po-deepl.php --api-key=DEIN_KEY\n";
    echo "    - Oder: Poedit mit Pre-Translate öffnen\n";
}
