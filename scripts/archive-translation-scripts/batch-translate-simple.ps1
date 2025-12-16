# Simple Batch Translation Script
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Add New' = 'Neu hinzufügen'
    'Add Rule' = 'Regel hinzufügen'
    'Add Service' = 'Service hinzufügen'
    'Add Staff' = 'Mitarbeiter hinzufügen'
    'Add Resource' = 'Ressource hinzufügen'
    'Actions' = 'Aktionen'
    'AI Assistant' = 'KI-Assistent'
    'AI Suggestions' = 'KI-Vorschläge'
    'All Services' = 'Alle Services'
    'All Staff' = 'Alle Mitarbeiter'
    'Analytics' = 'Statistik'
    'Apply' = 'Anwenden'
    'Automations' = 'Automatisierungen'
    'Background Color' = 'Hintergrundfarbe'
    'Border Color' = 'Rahmenfarbe'
    'Border Radius' = 'Rahmenradius'
    'Brand Color' = 'Markenfarbe'
    'Button Color' = 'Button-Farbe'
    'Calendar' = 'Kalender'
    'Cancel' = 'Abbrechen'
    'Close' = 'Schließen'
    'Customers' = 'Kunden'
    'Dashboard' = 'Dashboard'
    'Delete' = 'Löschen'
    'Design' = 'Design'
    'Diagnostics' = 'Diagnose'
    'Edit' = 'Bearbeiten'
    'Email' = 'E-Mail'
    'General' = 'Allgemein'
    'Name' = 'Name'
    'Next' = 'Weiter'
    'Notifications' = 'Benachrichtigungen'
    'Outbox' = 'Postausgang'
    'Payments' = 'Zahlungen'
    'Phone' = 'Telefon'
    'Previous' = 'Zurück'
    'Privacy' = 'Datenschutz'
    'Resources' = 'Ressourcen'
    'Save' = 'Speichern'
    'Save Changes' = 'Änderungen speichern'
    'Search' = 'Suchen'
    'Services' = 'Dienstleistungen'
    'Settings' = 'Einstellungen'
    'Staff' = 'Mitarbeiter'
    'Status' = 'Status'
    'Templates' = 'Vorlagen'
    'Text Color' = 'Textfarbe'
    'View' = 'Ansehen'
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
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Batch Translation Complete!" -ForegroundColor Green
Write-Host "Translated: $translatedCount strings" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
