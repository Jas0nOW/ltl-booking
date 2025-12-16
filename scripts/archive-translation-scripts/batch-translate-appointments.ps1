# Appointment and Booking Messages
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Appointment created.' = 'Termin erstellt.'
    'Appointment deleted.' = 'Termin gelöscht.'
    'Appointment details loaded.' = 'Termindetails geladen.'
    'Appointment not found.' = 'Termin nicht gefunden.'
    'Appointment updated.' = 'Termin aktualisiert.'
    'appointments updated.' = 'Termine aktualisiert.'
    'Appointments Dashboard' = 'Termine Dashboard'
    'Appointments This Week' = 'Termine diese Woche'
    'Appointment #%d' = 'Termin #%d'
    'Apply bulk action to selected appointments' = 'Massenaktion auf ausgewählte Termine anwenden'
    'API Keys' = 'API-Schlüssel'
    'Approve & Execute' = 'Genehmigen & Ausführen'
    'Approve AI Actions' = 'KI-Aktionen genehmigen'
    'Assign room' = 'Raum zuweisen'
    'Assigned room' = 'Zugewiesener Raum'
    'Assigned Resources' = 'Zugewiesene Ressourcen'
    'Authentication' = 'Authentifizierung'
    'Authentication failed (invalid API key)' = 'Authentifizierung fehlgeschlagen (ungültiger API-Schlüssel)'
    'Auto Button Text Color' = 'Automatische Button-Textfarbe'
    'Autonomous' = 'Autonom'
    'Automation Rules' = 'Automatisierungsregeln'
    'Availability Mode' = 'Verfügbarkeitsmodus'
    'Availability (optional)' = 'Verfügbarkeit (optional)'
    'Available Days & Times' = 'Verfügbare Tage & Zeiten'
    'Back' = 'Zurück'
    'Back to Appointments' = 'Zurück zu Terminen'
    'Back to Outbox' = 'Zurück zum Postausgang'
    'Backend' = 'Backend'
    'Backend Preview' = 'Backend-Vorschau'
    'Bed Type' = 'Betttyp'
    'Before (min)' = 'Vorher (Min)'
    'After (min)' = 'Nachher (Min)'
    'Body' = 'Text'
    'Book a room' = 'Ein Zimmer buchen'
    'Book a service' = 'Einen Service buchen'
    'Booking form' = 'Buchungsformular'
    'Booking ID' = 'Buchungs-ID'
    'Booking Mode' = 'Buchungsmodus'
    'Booking Mode:' = 'Buchungsmodus:'
    'Booking received' = 'Buchung eingegangen'
    'Booking Status Update' = 'Buchungsstatus-Update'
    'Booking Status Update: %s - %s' = 'Buchungsstatus-Update: %s - %s'
    'Booking Template Mode' = 'Buchungsvorlagenmodus'
    'Booking Wizard' = 'Buchungs-Assistent'
    'Booking Confirmation - %s' = 'Buchungsbestätigung - %s'
    'Based on confirmed bookings with assigned rooms.' = 'Basierend auf bestätigten Buchungen mit zugewiesenen Zimmern.'
    'Automatically choose readable text color for the primary button (black/white).' = 'Automatisch lesbare Textfarbe für den primären Button wählen (schwarz/weiß).'
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
