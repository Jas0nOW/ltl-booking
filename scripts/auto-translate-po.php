<?php
/**
 * Auto-translate all remaining empty msgstr entries by requesting
 * Google Translate (public endpoint) and caching the results.
 *
 * Usage: php scripts/auto-translate-po.php
 */

declare(strict_types=1);

$targets = [
    'de_DE' => 'de',
    'es_ES' => 'es',
];

$cacheDir = __DIR__ . '/../translations-cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

foreach ($targets as $locale => $langCode) {
    $poPath = __DIR__ . "/../languages/{$locale}.po";
    if (!file_exists($poPath)) {
        echo "Skipping missing {$poPath}\n";
        continue;
    }

    echo "Translating {$locale} via translate.googleapis.com\n";
    $cacheFile = "$cacheDir/{$locale}.json";
    $cache = [];
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            $cache = $cached;
        }
    }

    $lines = file($poPath, FILE_IGNORE_NEW_LINES);
    $entries = parsePoEntries($lines);
    $needsUpdate = 0;

    foreach ($entries as &$entry) {
        if ($entry['msgstr_text'] !== '' || trim($entry['msgid_text']) === '') {
            continue;
        }

        $msgid = $entry['msgid_text'];
        if (isset($cache[$msgid])) {
            $entry['translation'] = $cache[$msgid];
            continue;
        }

        $placeholders = [];
        $masked = maskPlaceholders($msgid, $placeholders);
        $translated = fetchTranslation($masked, $langCode);
        if ($translated === '') {
            echo "  ✗ failed: {$msgid}\n";
            continue;
        }
        $final = restorePlaceholders($translated, $placeholders);
        $cache[$msgid] = $final;
        $entry['translation'] = $final;
        file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $needsUpdate++;
        usleep(150000);
    }
    unset($entry);

    if ($needsUpdate === 0) {
        echo "  nothing new to translate for {$locale}\n";
        continue;
    }

    $newLines = rebuildLines($lines, $entries);
    file_put_contents($poPath, implode("\n", $newLines));
    echo "  applied {$needsUpdate} auto-translations to {$locale}.po\n";
}

function parsePoEntries(array $lines): array
{
    $entries = [];
    $current = null;

    foreach ($lines as $index => $line) {
        $trim = trim($line);

        if (str_starts_with($trim, 'msgid ')) {
            if ($current !== null) {
                $entries[] = finalizeEntry($current);
            }
            $current = startEntry($line, $index);
            continue;
        }

        if ($current === null) {
            continue;
        }

        if ($current['state'] === 'msgid' && preg_match('/^"(.*)"$/', $trim, $matches)) {
            $current['msgid_text'] .= stripcslashes($matches[1]);
            $current['msgid_lines'][] = $index;
            continue;
        }

        if (str_starts_with($trim, 'msgstr ')) {
            $current['state'] = 'msgstr';
            $current['msgstr_start'] = $index;
            $current['msgstr_lines'][] = $index;
            if (preg_match('/^msgstr "(.*)"$/', $trim, $matches)) {
                $current['msgstr_text'] .= stripcslashes($matches[1]);
            }
            $current['msgstr_end'] = $index;
            continue;
        }

        if ($current['state'] === 'msgstr' && preg_match('/^"(.*)"$/', $trim, $matches)) {
            $current['msgstr_text'] .= stripcslashes($matches[1]);
            $current['msgstr_lines'][] = $index;
            $current['msgstr_end'] = $index;
        }
    }

    if ($current !== null) {
        $entries[] = finalizeEntry($current);
    }

    return $entries;
}

function startEntry(string $line, int $index): array
{
    $text = '';
    if (preg_match('/^msgid "(.*)"$/', trim($line), $matches)) {
        $text = stripcslashes($matches[1]);
    }

    return [
        'state' => 'msgid',
        'msgid_text' => $text,
        'msgstr_text' => '',
        'msgstr_start' => -1,
        'msgstr_end' => -1,
        'msgstr_lines' => [],
        'msgid_lines' => [$index],
    ];
}

function finalizeEntry(array $entry): array
{
    if ($entry['msgstr_start'] === -1) {
        $entry['msgstr_start'] = $entry['msgid_lines'][count($entry['msgid_lines']) - 1] + 1;
        $entry['msgstr_end'] = $entry['msgstr_start'];
    }
    return $entry;
}

function rebuildLines(array $lines, array $entries): array
{
    $lookup = [];
    foreach ($entries as $entry) {
        if (isset($entry['translation']) && $entry['msgstr_start'] >= 0) {
            $lookup[$entry['msgstr_start']] = $entry;
        }
    }

    $result = [];
    $lineCount = count($lines);

    for ($i = 0; $i < $lineCount; $i++) {
        if (isset($lookup[$i])) {
            $entry = $lookup[$i];
            $result[] = 'msgstr "' . escapePoString($entry['translation']) . '"';
            $i = $entry['msgstr_end'];
            continue;
        }
        $result[] = $lines[$i];
    }

    return $result;
}

function maskPlaceholders(string $text, array &$placeholders): string
{
    $placeholders = [];
    return preg_replace_callback('/%(?:\d+\$)?[sd%]/', function ($matches) use (&$placeholders) {
        $placeholders[] = $matches[0];
        return '__PH_' . (count($placeholders) - 1) . '__';
    }, $text);
}

function restorePlaceholders(string $text, array $placeholders): string
{
    foreach ($placeholders as $index => $value) {
        $text = str_replace('__PH_' . $index . '__', $value, $text);
    }
    return $text;
}

function escapePoString(string $value): string
{
    return addcslashes($value, "\\\"");
}

function fetchTranslation(string $text, string $targetLang): string
{
    $encoded = rawurlencode($text);
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl={$targetLang}&dt=t&q={$encoded}";

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
        $response = curl_exec($curl);
        curl_close($curl);
    } else {
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: Mozilla/5.0 (compatible)\r\n",
                'timeout' => 10,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    }

    if ($response === false || $response === '') {
        return '';
    }

    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        return '';
    }

    return $payload[0][0][0] ?? '';
}
