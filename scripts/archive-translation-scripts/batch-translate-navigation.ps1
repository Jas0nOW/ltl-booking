# Filter, Sort, and Navigation Terms
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Filter' = 'Filter'
    'Filters' = 'Filter'
    'Filter by' = 'Filtern nach'
    'Sort' = 'Sortieren'
    'Sort by' = 'Sortieren nach'
    'Order' = 'Reihenfolge'
    'Ascending' = 'Aufsteigend'
    'Descending' = 'Absteigend'
    'Newest First' = 'Neueste zuerst'
    'Oldest First' = 'Älteste zuerst'
    'A-Z' = 'A-Z'
    'Z-A' = 'Z-A'
    'All' = 'Alle'
    'None' = 'Keine'
    'Select All' = 'Alle auswählen'
    'Deselect All' = 'Alle abwählen'
    'Selected' = 'Ausgewählt'
    'items' = 'Einträge'
    'Page' = 'Seite'
    'of' = 'von'
    'Go to page' = 'Gehe zu Seite'
    'First' = 'Erste'
    'Last' = 'Letzte'
    'Showing' = 'Zeige'
    'to' = 'bis'
    'entries' = 'Einträge'
    'per page' = 'pro Seite'
    'Show' = 'Zeige'
    'More' = 'Mehr'
    'Less' = 'Weniger'
    'Expand' = 'Erweitern'
    'Collapse' = 'Einklappen'
    'Expand All' = 'Alle erweitern'
    'Collapse All' = 'Alle einklappen'
    'Details' = 'Details'
    'Summary' = 'Zusammenfassung'
    'Overview' = 'Übersicht'
    'Quick View' = 'Schnellansicht'
    'Full View' = 'Vollständige Ansicht'
}

$content = Get-Content $poFile -Raw -Encoding UTF8
$translatedCount = 0

foreach ($english in $translations.Keys) {
    $german = $translations[$english]
    $pattern = "msgid `"$([regex]::Escape($english))`"`r?`nmsgstr `"`""
    $replacement = "msgid `"$english`"`nmsgstr `"$german`""
    
    if ($content -match $pattern) {
        $content = $content -replace $pattern, $replacement
        $translatedCount++
        Write-Host "Translated: $english -> $german" -ForegroundColor Green
    }
}

$content | Out-File $poFile -Encoding UTF8 -NoNewline

Write-Host ""
Write-Host "Batch Translation Complete: $translatedCount strings" -ForegroundColor Cyan
