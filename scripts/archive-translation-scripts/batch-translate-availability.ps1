# Availability and Schedule Terms
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Availability' = 'Verfügbarkeit'
    'Available' = 'Verfügbar'
    'Unavailable' = 'Nicht verfügbar'
    'Available Slots' = 'Verfügbare Zeitfenster'
    'Working Hours' = 'Arbeitszeiten'
    'Business Hours' = 'Geschäftszeiten'
    'Opening Hours' = 'Öffnungszeiten'
    'Closed' = 'Geschlossen'
    'Open' = 'Geöffnet'
    'Break' = 'Pause'
    'Breaks' = 'Pausen'
    'Holiday' = 'Feiertag'
    'Holidays' = 'Feiertage'
    'Exception' = 'Ausnahme'
    'Exceptions' = 'Ausnahmen'
    'Schedule' = 'Zeitplan'
    'Time Slot' = 'Zeitfenster'
    'Time Slots' = 'Zeitfenster'
    'Slot Duration' = 'Zeitfensterdauer'
    'Buffer Time' = 'Pufferzeit'
    'Lead Time' = 'Vorlaufzeit'
    'Max Advance Booking' = 'Max. Buchungsvorlauf'
    'Min Advance Booking' = 'Min. Buchungsvorlauf'
    'Same Day Booking' = 'Buchung am selben Tag'
    'Capacity' = 'Kapazität'
    'Max Capacity' = 'Max. Kapazität'
    'Remaining Capacity' = 'Verbleibende Kapazität'
    'Fully Booked' = 'Ausgebucht'
    'Overbooked' = 'Überbucht'
    'Block Time' = 'Zeit blockieren'
    'Blocked' = 'Blockiert'
    'Recurring' = 'Wiederkehrend'
    'One-time' = 'Einmalig'
    'Repeat' = 'Wiederholen'
    'Every' = 'Jeden'
    'Weekly' = 'Wöchentlich'
    'Daily' = 'Täglich'
    'Monthly' = 'Monatlich'
    'Custom' = 'Benutzerdefiniert'
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
