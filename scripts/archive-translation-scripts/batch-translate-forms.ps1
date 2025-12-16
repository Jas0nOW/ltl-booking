# Form Fields and Labels
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'First Name' = 'Vorname'
    'Last Name' = 'Nachname'
    'Full Name' = 'Vollständiger Name'
    'Email Address' = 'E-Mail-Adresse'
    'Phone Number' = 'Telefonnummer'
    'Address' = 'Adresse'
    'City' = 'Stadt'
    'State' = 'Bundesland'
    'Zip Code' = 'Postleitzahl'
    'Country' = 'Land'
    'Company' = 'Firma'
    'Website' = 'Webseite'
    'Description' = 'Beschreibung'
    'Title' = 'Titel'
    'Required' = 'Erforderlich'
    'Optional' = 'Optional'
    'Submit' = 'Absenden'
    'Reset' = 'Zurücksetzen'
    'Update' = 'Aktualisieren'
    'Create' = 'Erstellen'
    'Upload' = 'Hochladen'
    'Download' = 'Herunterladen'
    'Choose File' = 'Datei auswählen'
    'No file chosen' = 'Keine Datei ausgewählt'
    'Image' = 'Bild'
    'Icon' = 'Symbol'
    'Color' = 'Farbe'
    'Enabled' = 'Aktiviert'
    'Disabled' = 'Deaktiviert'
    'Active' = 'Aktiv'
    'Inactive' = 'Inaktiv'
    'Published' = 'Veröffentlicht'
    'Draft' = 'Entwurf'
    'Display Order' = 'Anzeigereihenfolge'
    'Sort Order' = 'Sortierreihenfolge'
    'Visible' = 'Sichtbar'
    'Hidden' = 'Versteckt'
    'Show' = 'Anzeigen'
    'Hide' = 'Verbergen'
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
