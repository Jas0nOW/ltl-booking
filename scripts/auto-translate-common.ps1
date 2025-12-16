# Auto-translate common English strings in de_DE.po file
# This provides baseline translations for common UI terms

$ErrorActionPreference = "Stop"

$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

if (!(Test-Path $poFile)) {
    Write-Host "ERROR: PO file not found: $poFile" -ForegroundColor Red
    exit 1
}

Write-Host "=== Auto-Translating Common Strings ===" -ForegroundColor Cyan

# Common translation dictionary (English -> German)
$translations = @{
    # Actions
    "Save" = "Speichern"
    "Save Settings" = "Einstellungen speichern"
    "Cancel" = "Abbrechen"
    "Delete" = "Löschen"
    "Edit" = "Bearbeiten"
    "Add New" = "Neu hinzufügen"
    "Create" = "Erstellen"
    "Update" = "Aktualisieren"
    "Apply" = "Anwenden"
    "Filter" = "Filtern"
    "Reset" = "Zurücksetzen"
    "Back" = "Zurück"
    "Next" = "Weiter"
    "Search" = "Suchen"
    "View" = "Ansehen"
    "Close" = "Schließen"
    
    # Common nouns
    "Name" = "Name"
    "Email" = "E-Mail"
    "Phone" = "Telefon"
    "Date" = "Datum"
    "Time" = "Zeit"
    "Start" = "Start"
    "End" = "Ende"
    "Status" = "Status"
    "Actions" = "Aktionen"
    "Settings" = "Einstellungen"
    "Customer" = "Kunde"
    "Service" = "Leistung"
    "Services" = "Leistungen"
    "Staff" = "Personal"
    "Team" = "Team"
    "Price" = "Preis"
    "Amount" = "Betrag"
    "Payment" = "Zahlung"
    "Note" = "Notiz"
    "Notes" = "Notizen"
    "Description" = "Beschreibung"
    
    # Status terms
    "Pending" = "Ausstehend"
    "Confirmed" = "Bestätigt"
    "Cancelled" = "Storniert"
    "Completed" = "Abgeschlossen"
    "Paid" = "Bezahlt"
    "Active" = "Aktiv"
    
    # Form fields
    "First name" = "Vorname"
    "Last name" = "Nachname"
    "Company name" = "Firmenname"
    "VAT / Tax ID" = "USt-IdNr."
    "required" = "erforderlich"
    
    # Hotel specific
    "Room type" = "Zimmertyp"
    "Check-in" = "Anreise"
    "Check-out" = "Abreise"
    "Guests" = "Gäste"
    "Room preference" = "Zimmerwunsch"
    "Room #" = "Zimmer #"
    
    # Appointments
    "Appointment" = "Termin"
    "Appointments" = "Termine"
    "Date & Time" = "Datum & Uhrzeit"
    "Your details" = "Ihre Angaben"
    "Confirm booking" = "Buchung bestätigen"
    
    # Messages
    "All rights reserved." = "Alle Rechte vorbehalten."
    "Are you sure?" = "Sind Sie sicher?"
    "Security check failed" = "Sicherheitsprüfung fehlgeschlagen"
    "You do not have permission to view this page." = "Sie haben keine Berechtigung, diese Seite anzuzeigen."
    "Settings saved successfully." = "Einstellungen erfolgreich gespeichert."
    "Invalid email address." = "Ungültige E-Mail-Adresse."
    
    # Weeks
    "Monday" = "Montag"
    "Tuesday" = "Dienstag"
    "Wednesday" = "Mittwoch"
    "Thursday" = "Donnerstag"
    "Friday" = "Freitag"
    "Saturday" = "Samstag"
    "Sunday" = "Sonntag"
    
    # Misc
    "Yes" = "Ja"
    "No" = "Nein"
    "None" = "Keine"
    "Any" = "Beliebig"
    "All" = "Alle"
    "From" = "Von"
    "To" = "Bis"
    "Off day" = "Freier Tag"
    "Working hours" = "Arbeitszeiten"
    "Edit working hours" = "Arbeitszeiten bearbeiten"
    "Save working hours" = "Arbeitszeiten speichern"
    "Exceptions" = "Ausnahmen"
    "Create exception" = "Ausnahme erstellen"
    "No exceptions found." = "Keine Ausnahmen gefunden."
    "No staff members found." = "Keine Mitarbeiter gefunden."
    "Add staff member" = "Mitarbeiter hinzufügen"
}

Write-Host "Loading PO file..." -ForegroundColor Yellow
$content = Get-Content $poFile -Raw -Encoding UTF8

$replacedCount = 0

foreach ($key in $translations.Keys) {
    $value = $translations[$key]
    # Escape special regex characters
    $escapedKey = [regex]::Escape($key)
    $pattern = "msgid `"$escapedKey`"`r?`nmsgstr `"`""
    $replacement = "msgid `"$key`"`nmsgstr `"$value`""
    
    if ($content -match $pattern) {
        $content = $content -replace $pattern, $replacement
        $replacedCount++
    }
}

Write-Host "Translated $replacedCount common strings" -ForegroundColor Green

Set-Content -Path $poFile -Value $content -Encoding UTF8 -NoNewline

Write-Host "PO file updated!" -ForegroundColor Green
Write-Host "Note: $($translations.Count - $replacedCount) terms were already translated or not found" -ForegroundColor Yellow
Write-Host "`nRemaining untranslated strings can be completed manually or with Poedit." -ForegroundColor Gray
