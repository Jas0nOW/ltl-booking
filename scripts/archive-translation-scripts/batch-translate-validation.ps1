# Validation and Error Messages
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Error' = 'Fehler'
    'Warning' = 'Warnung'
    'Success' = 'Erfolg'
    'Info' = 'Info'
    'Invalid' = 'Ungültig'
    'Required field' = 'Pflichtfeld'
    'Invalid email address' = 'Ungültige E-Mail-Adresse'
    'Invalid phone number' = 'Ungültige Telefonnummer'
    'Field is required' = 'Feld ist erforderlich'
    'Please enter a valid email' = 'Bitte geben Sie eine gültige E-Mail ein'
    'Please enter a valid phone number' = 'Bitte geben Sie eine gültige Telefonnummer ein'
    'Please fill in all required fields' = 'Bitte füllen Sie alle Pflichtfelder aus'
    'Something went wrong' = 'Etwas ist schiefgelaufen'
    'Please try again' = 'Bitte versuchen Sie es erneut'
    'Are you sure?' = 'Sind Sie sicher?'
    'This action cannot be undone' = 'Diese Aktion kann nicht rückgängig gemacht werden'
    'Confirm deletion' = 'Löschung bestätigen'
    'Successfully saved' = 'Erfolgreich gespeichert'
    'Successfully deleted' = 'Erfolgreich gelöscht'
    'Successfully updated' = 'Erfolgreich aktualisiert'
    'Successfully created' = 'Erfolgreich erstellt'
    'Failed to save' = 'Speichern fehlgeschlagen'
    'Failed to delete' = 'Löschen fehlgeschlagen'
    'Failed to update' = 'Aktualisierung fehlgeschlagen'
    'Failed to create' = 'Erstellung fehlgeschlagen'
    'No results found' = 'Keine Ergebnisse gefunden'
    'Loading' = 'Laden'
    'Processing' = 'Verarbeitung'
    'Saving' = 'Speichern'
    'Deleting' = 'Löschen'
    'Updating' = 'Aktualisieren'
    'Please wait' = 'Bitte warten'
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
