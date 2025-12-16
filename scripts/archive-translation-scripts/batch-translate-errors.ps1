# Error and Status Messages
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Could not create booking.' = 'Buchung konnte nicht erstellt werden.'
    'Could not create exception.' = 'Ausnahme konnte nicht erstellt werden.'
    'Could not create outbox draft.' = 'Postausgang-Entwurf konnte nicht erstellt werden.'
    'Could not create report draft.' = 'Bericht-Entwurf konnte nicht erstellt werden.'
    'Could not delete appointment.' = 'Termin konnte nicht gelöscht werden.'
    'Could not delete appointments. Please try again.' = 'Termine konnten nicht gelöscht werden. Bitte versuchen Sie es erneut.'
    'Could not delete exception.' = 'Ausnahme konnte nicht gelöscht werden.'
    'Could not generate report.' = 'Bericht konnte nicht generiert werden.'
    'Could not load appointment details.' = 'Termindetails konnten nicht geladen werden.'
    'Could not load room suggestions.' = 'Raumvorschläge konnten nicht geladen werden.'
    'Could not load rooms.' = 'Räume konnten nicht geladen werden.'
    'Could not propose room.' = 'Raum konnte nicht vorgeschlagen werden.'
    'Could not queue room assignment.' = 'Raumzuweisung konnte nicht in Warteschlange gestellt werden.'
    'Could not reject draft.' = 'Entwurf konnte nicht abgelehnt werden.'
    'Could not save %s. Please try again.' = '%s konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.'
    'Could not save color.' = 'Farbe konnte nicht gespeichert werden.'
    'Could not save customer. Please try again.' = 'Kunde konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.'
    'Could not save guest. Please try again.' = 'Gast konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.'
    'Could not save service. Please try again.' = 'Service konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.'
    'Could not save the appointment to the database.' = 'Termin konnte nicht in der Datenbank gespeichert werden.'
    'Could not assign room.' = 'Raum konnte nicht zugewiesen werden.'
    'Cleanup completed. Deleted appointments: %d. Anonymized customers: %d.' = 'Bereinigung abgeschlossen. Gelöschte Termine: %d. Anonymisierte Kunden: %d.'
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
