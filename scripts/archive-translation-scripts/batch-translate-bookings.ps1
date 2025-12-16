# Booking/Appointment Terms
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Appointment' = 'Termin'
    'Appointments' = 'Termine'
    'Booking' = 'Buchung'
    'Bookings' = 'Buchungen'
    'Book Now' = 'Jetzt buchen'
    'Booked' = 'Gebucht'
    'Pending' = 'Ausstehend'
    'Confirmed' = 'Bestätigt'
    'Completed' = 'Abgeschlossen'
    'Cancelled' = 'Storniert'
    'No-show' = 'Nicht erschienen'
    'Customer' = 'Kunde'
    'Date' = 'Datum'
    'Time' = 'Uhrzeit'
    'Duration' = 'Dauer'
    'Price' = 'Preis'
    'Total' = 'Gesamt'
    'Service' = 'Service'
    'Staff Member' = 'Mitarbeiter'
    'Resource' = 'Ressource'
    'Notes' = 'Notizen'
    'Internal Notes' = 'Interne Notizen'
    'Customer Notes' = 'Kundennotizen'
    'Start Date' = 'Startdatum'
    'End Date' = 'Enddatum'
    'Start Time' = 'Startzeit'
    'End Time' = 'Endzeit'
    'Select Service' = 'Service auswählen'
    'Select Staff' = 'Mitarbeiter auswählen'
    'Select Date' = 'Datum auswählen'
    'Select Time' = 'Uhrzeit auswählen'
    'Available Times' = 'Verfügbare Zeiten'
    'No available times' = 'Keine verfügbaren Zeiten'
    'Book Appointment' = 'Termin buchen'
    'Reschedule' = 'Umbuchen'
    'Confirm Booking' = 'Buchung bestätigen'
    'Cancel Booking' = 'Buchung stornieren'
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
