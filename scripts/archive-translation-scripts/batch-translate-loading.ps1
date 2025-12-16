# Loading and Status Messages
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Loading appointment details...' = 'Lade Termindetails...'
    'Loading availability...' = 'Lade Verfügbarkeit...'
    'Loading available times...' = 'Lade verfügbare Zeiten...'
    'Loading calendar... This may take a few seconds.' = 'Lade Kalender... Dies kann einige Sekunden dauern.'
    'Loading resources...' = 'Lade Ressourcen...'
    'Loading room suggestions...' = 'Lade Raumvorschläge...'
    'Maximum number of simultaneous bookings this resource can handle (e.g., 1 for exclusive use, 10 for a meeting room).' = 'Maximale Anzahl gleichzeitiger Buchungen, die diese Ressource bewältigen kann (z.B. 1 für exklusive Nutzung, 10 für einen Besprechungsraum).'
    'Migrations ran successfully.' = 'Migrationen erfolgreich ausgeführt.'
    'Minutely (every N minutes)' = 'Minütlich (alle N Minuten)'
    'Missing appointment_id/resource_id.' = 'Fehlende appointment_id/resource_id.'
    'Mobile-first design' = 'Mobile-First-Design'
    'Mode' = 'Modus'
    'Mon' = 'Mo'
    'Monthly (%d) @ %s' = 'Monatlich (%d) @ %s'
    'Multiple resources are available for this time' = 'Mehrere Ressourcen sind für diese Zeit verfügbar'
    'Multiple rooms are available for your dates' = 'Mehrere Zimmer sind für Ihre Daten verfügbar'
    'MySQL Named Locks:' = 'MySQL Named Locks:'
    'N/A' = 'N/V'
    'Name (optional)' = 'Name (optional)'
    'Name is required.' = 'Name ist erforderlich.'
    'Network error' = 'Netzwerkfehler'
    'Never share this key.' = 'Teilen Sie diesen Schlüssel niemals.'
    'New Appointment' = 'Neuer Termin'
    'New Booking Received' = 'Neue Buchung eingegangen'
    'New Booking Received - %s' = 'Neue Buchung eingegangen - %s'
    'New Room Type' = 'Neuer Raumtyp'
    'Next &raquo;' = 'Weiter &raquo;'
    'Next 7 Days' = 'Nächste 7 Tage'
    'Next Run' = 'Nächster Lauf'
    'night' = 'Nacht'
    'nights' = 'Nächte'
    'No' = 'Nein'
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
