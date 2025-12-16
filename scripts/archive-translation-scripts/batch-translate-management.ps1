# Customer and Staff Management
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Could not update appointment.' = 'Termin konnte nicht aktualisiert werden.'
    'Could not update draft.' = 'Entwurf konnte nicht aktualisiert werden.'
    'Could not update status.' = 'Status konnte nicht aktualisiert werden.'
    'Create a booking page' = 'Eine Buchungsseite erstellen'
    'Create Customer' = 'Kunde erstellen'
    'Create exception' = 'Ausnahme erstellen'
    'Create First Room Type' = 'Ersten Raumtyp erstellen'
    'Create First Service' = 'Ersten Service erstellen'
    'Create Guest' = 'Gast erstellen'
    'Create new appointment' = 'Neuen Termin erstellen'
    'Create new room type' = 'Neuen Raumtyp erstellen'
    'Customer data anonymized successfully.' = 'Kundendaten erfolgreich anonymisiert.'
    'Customer Email' = 'Kunden-E-Mail'
    'Customer not found or anonymization failed.' = 'Kunde nicht gefunden oder Anonymisierung fehlgeschlagen.'
    'Customer Notifications' = 'Kundenbenachrichtigungen'
    'Customer saved.' = 'Kunde gespeichert.'
    'Customize the appearance of your booking wizard.' = 'Passen Sie das Aussehen Ihres Buchungs-Assistenten an.'
    'Data Retention Settings' = 'Datenaufbewahrungseinstellungen'
    'Database Maintenance' = 'Datenbankwartung'
    'Database Prefix' = 'Datenbankpräfix'
    'Database Statistics' = 'Datenbankstatistiken'
    'Date & Time' = 'Datum & Uhrzeit'
    'Dates' = 'Daten'
    'Day (1-28)' = 'Tag (1-28)'
    'Days ahead' = 'Tage im Voraus'
    'Days before' = 'Tage vorher'
    'DB Version' = 'DB-Version'
    'DB Version:' = 'DB-Version:'
    'Debug' = 'Debug'
    'Default Booking Status' = 'Standard-Buchungsstatus'
    'Default Currency' = 'Standardwährung'
    'Default rules added.' = 'Standardregeln hinzugefügt.'
    'Default templates added.' = 'Standardvorlagen hinzugefügt.'
    'Delete all plugin data on uninstall' = 'Alle Plugin-Daten bei Deinstallation löschen'
    'Delete Appointment' = 'Termin löschen'
    'Delete cancelled appointments after (days)' = 'Stornierte Termine löschen nach (Tage)'
    'Delete this appointment?' = 'Diesen Termin löschen?'
    'Delete this rule?' = 'Diese Regel löschen?'
    'Design saved.' = 'Design gespeichert.'
    'Design Settings' = 'Design-Einstellungen'
    'Dev Tools:' = 'Entwicklertools:'
    'Disable' = 'Deaktivieren'
    'Done.' = 'Fertig.'
    'Double Bed' = 'Doppelbett'
    'Draft Center' = 'Entwurfszentrale'
    'Draft created in Outbox.' = 'Entwurf im Postausgang erstellt.'
    'Draft not found.' = 'Entwurf nicht gefunden.'
    'Draft rejected.' = 'Entwurf abgelehnt.'
    'Draft updated.' = 'Entwurf aktualisiert.'
    'Duration (minutes)' = 'Dauer (Minuten)'
    'Edit Customer' = 'Kunde bearbeiten'
    'Edit Guest' = 'Gast bearbeiten'
    'Edit Rule' = 'Regel bearbeiten'
    'Edit Template' = 'Vorlage bearbeiten'
    'Edit working hours' = 'Arbeitszeiten bearbeiten'
    'Email Draft' = 'E-Mail-Entwurf'
    'Email from:' = 'E-Mail von:'
    'Email sent.' = 'E-Mail gesendet.'
    'Email subject/body missing.' = 'E-Mail-Betreff/Text fehlt.'
    'Email template not found.' = 'E-Mail-Vorlage nicht gefunden.'
    'Enable' = 'Aktivieren'
    'Enable AI Features' = 'KI-Funktionen aktivieren'
    'Enable Animations' = 'Animationen aktivieren'
    'Enable Gradient Background' = 'Verlaufshintergrund aktivieren'
    'Enable Logging' = 'Protokollierung aktivieren'
    'Enable Payments' = 'Zahlungen aktivieren'
    'Enable SMTP' = 'SMTP aktivieren'
    'Enabled (%s)' = 'Aktiviert (%s)'
    'Enabled Methods' = 'Aktivierte Methoden'
    'Encryption' = 'Verschlüsselung'
    'End' = 'Ende'
    'End date/time must be after start.' = 'Enddatum/-zeit muss nach dem Start liegen.'
    'End:' = 'Ende:'
    'English' = 'Englisch'
    'Error:' = 'Fehler:'
    'Estimated price:' = 'Geschätzter Preis:'
    'Every %d min' = 'Alle %d Min.'
    'Example Admin Panel' = 'Beispiel-Admin-Panel'
    'Example service description.' = 'Beispiel-Servicebeschreibung.'
    'Exception created.' = 'Ausnahme erstellt.'
    'Exception deleted.' = 'Ausnahme gelöscht.'
    'Execute' = 'Ausführen'
    'Executed.' = 'Ausgeführt.'
    'Execution failed.' = 'Ausführung fehlgeschlagen.'
    'Execution Mode' = 'Ausführungsmodus'
    'Export CSV' = 'CSV exportieren'
    'Failed to save working hours. Please check your input and try again.' = 'Arbeitszeiten konnten nicht gespeichert werden. Bitte überprüfen Sie Ihre Eingabe und versuchen Sie es erneut.'
    'Failed to send test email.' = 'Test-E-Mail konnte nicht gesendet werden.'
    'FAQs' = 'FAQs'
    'Fees' = 'Gebühren'
    'Finance (Last 30 Days)' = 'Finanzen (letzte 30 Tage)'
    'Fixed' = 'Fest'
    'Fixed Weekly Slots' = 'Feste wöchentliche Zeitfenster'
    'Fixed weekly times' = 'Feste wöchentliche Zeiten'
    'Fri' = 'Fr'
    'From' = 'Von'
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
