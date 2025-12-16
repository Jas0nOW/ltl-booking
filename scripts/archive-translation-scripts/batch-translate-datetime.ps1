# Date/Time/Calendar Terms
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Monday' = 'Montag'
    'Tuesday' = 'Dienstag'
    'Wednesday' = 'Mittwoch'
    'Thursday' = 'Donnerstag'
    'Friday' = 'Freitag'
    'Saturday' = 'Samstag'
    'Sunday' = 'Sonntag'
    'January' = 'Januar'
    'February' = 'Februar'
    'March' = 'März'
    'April' = 'April'
    'May' = 'Mai'
    'June' = 'Juni'
    'July' = 'Juli'
    'August' = 'August'
    'September' = 'September'
    'October' = 'Oktober'
    'November' = 'November'
    'December' = 'Dezember'
    'Today' = 'Heute'
    'Tomorrow' = 'Morgen'
    'Yesterday' = 'Gestern'
    'Week' = 'Woche'
    'Month' = 'Monat'
    'Year' = 'Jahr'
    'Day' = 'Tag'
    'Hour' = 'Stunde'
    'Minute' = 'Minute'
    'Hours' = 'Stunden'
    'Minutes' = 'Minuten'
    'All Day' = 'Ganztägig'
    'Morning' = 'Vormittag'
    'Afternoon' = 'Nachmittag'
    'Evening' = 'Abend'
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
