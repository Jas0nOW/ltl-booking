# Comprehensive Translation Script - Part 6 (Early entries A-B)
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    '.ltlb-booking .service-card { /* your styles */ }' = '.ltlb-booking .service-card { /* Ihre Styles */ }'
    'A key is stored. Leave blank to keep the existing key.' = 'Ein Schlüssel ist gespeichert. Leer lassen, um den vorhandenen Schlüssel zu behalten.'
    'A password is stored. Leave blank to keep the existing password.' = 'Ein Passwort ist gespeichert. Leer lassen, um das vorhandene Passwort zu behalten.'
    'A webhook secret is stored. Leave blank to keep the existing secret.' = 'Ein Webhook-Secret ist gespeichert. Leer lassen, um das vorhandene Secret zu behalten.'
    'Add New %s' = 'Neue %s hinzufügen'
    'Add one or more weekly start times. Example: Fri 18:00. The customer will only see these times (still respecting staff/global hours and existing bookings).' = 'Fügen Sie einen oder mehrere wöchentliche Startzeiten hinzu. Beispiel: Fr 18:00. Der Kunde sieht nur diese Zeiten (unter Berücksichtigung von Personal-/Globalen Zeiten und bestehenden Buchungen).'
    'Add shadow to buttons (submit, primary, etc.)' = 'Schatten zu Buttons hinzufügen (Absenden, Primär, etc.)'
    'Add shadow to input fields (text, select, etc.)' = 'Schatten zu Eingabefeldern hinzufügen (Text, Auswahl, etc.)'
    'Add shadow to service/room cards' = 'Schatten zu Service-/Zimmerkarten hinzufügen'
    'Add shadow to the main booking form container' = 'Schatten zum Hauptbuchungsformular-Container hinzufügen'
    'AI' = 'KI'
    'AI & Automations' = 'KI & Automatisierungen'
    'AI settings (provider, model, business context, and operating mode) are managed under â€œAI & Automationsâ€.' = 'KI-Einstellungen (Anbieter, Modell, Geschäftskontext und Betriebsmodus) werden unter „KI & Automatisierungen" verwaltet.'
    'Allow customers to pay online via Stripe or PayPal.' = 'Erlauben Sie Kunden, online per Stripe oder PayPal zu zahlen.'
    'Another booking is in progress. Please try again.' = 'Eine andere Buchung wird verarbeitet. Bitte versuchen Sie es erneut.'
    'âœ— Missing' = 'âœ— Fehlt'
    'Are you sure you want to anonymize this customer? This cannot be undone.' = 'Sind Sie sicher, dass Sie diesen Kunden anonymisieren möchten? Dies kann nicht rückgängig gemacht werden.'
    'Are you sure you want to delete the selected services?' = 'Sind Sie sicher, dass Sie die ausgewählten Dienstleistungen löschen möchten?'
    'Are you sure you want to delete this item?' = 'Sind Sie sicher, dass Sie dieses Element löschen möchten?'
    'âš  DB version is behind plugin version.' = 'âš  DB-Version liegt hinter Plugin-Version.'
    'Autonomous (approve + execute)' = 'Autonom (genehmigen + ausführen)'
    'Available any time within the allowed hours.' = 'Verfügbar zu jeder Zeit innerhalb der erlaubten Stunden.'
    'Available tags: {customer_name}, {service_name}, {start_time}, {end_time}, {status}' = 'Verfügbare Tags: {customer_name}, {service_name}, {start_time}, {end_time}, {status}'
    'Available times are still loadingâ€¦' = 'Verfügbare Zeiten werden noch geladen…'
    'Available times load after you select a date' = 'Verfügbare Zeiten werden nach Datumsauswahl geladen'
    'Backend tab controls the color palette used inside WP Admin (LazyBookings pages).' = 'Backend-Tab steuert die Farbpalette, die im WP-Admin (LazyBookings-Seiten) verwendet wird.'
    'Background for inner panels (fieldsets/cards)' = 'Hintergrund für innere Panels (Fieldsets/Karten)'
    'Base time slot ists, %d available, %d leftover.' = 'Beste Auswahl: %d Gäste, %d verfügbar, %d übrig.'
    'Body:' = 'Text:'
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
