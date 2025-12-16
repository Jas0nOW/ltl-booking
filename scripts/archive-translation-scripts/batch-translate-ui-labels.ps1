# UI Labels and Terms
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Add New Appointment' = 'Neuen Termin hinzufügen'
    'Add New Booking' = 'Neue Buchung hinzufügen'
    'Add New Customer' = 'Neuen Kunden hinzufügen'
    'Add New Guest' = 'Neuen Gast hinzufügen'
    'Add First Customer' = 'Ersten Kunden hinzufügen'
    'Add First Guest' = 'Ersten Gast hinzufügen'
    'Add staff member' = 'Mitarbeiter hinzufügen'
    'Add Template' = 'Vorlage hinzufügen'
    'Add Slot' = 'Zeitfenster hinzufügen'
    'Add time' = 'Zeit hinzufügen'
    'Add Default Rules' = 'Standardregeln hinzufügen'
    'All Statuses' = 'Alle Status'
    'Any' = 'Beliebig'
    'Adults' = 'Erwachsene'
    'Amenities' = 'Ausstattung'
    'All Set' = 'Alles erledigt'
    'All rights reserved.' = 'Alle Rechte vorbehalten.'
    'Admin Notifications' = 'Admin-Benachrichtigungen'
    'Accent Color' = 'Akzentfarbe'
    'Accent preview' = 'Akzentvorschau'
    'AI Settings saved.' = 'KI-Einstellungen gespeichert.'
    'Activate AI-powered automations' = 'KI-gesteuerte Automatisierungen aktivieren'
    'AI Enabled' = 'KI aktiviert'
    'AI drafts appear in Outbox for approval' = 'KI-Entwürfe erscheinen im Postausgang zur Genehmigung'
    'AI executes actions automatically' = 'KI führt Aktionen automatisch aus'
    'AI Input' = 'KI-Eingabe'
    'AI Output' = 'KI-Ausgabe'
    'AI Input / Output' = 'KI-Eingabe / Ausgabe'
    'AI Insights' = 'KI-Erkenntnisse'
    'AI service provider for content generation.' = 'KI-Dienstleister für die Inhaltserstellung.'
    'AI Settings' = 'KI-Einstellungen'
    'Animation Duration (ms)' = 'Animationsdauer (ms)'
    'Anonymize Customer' = 'Kunde anonymisieren'
    'Anonymize customer data after (days)' = 'Kundendaten anonymisieren nach (Tage)'
    'Action not found.' = 'Aktion nicht gefunden.'
    'Analytics (Last 30 Days)' = 'Statistik (letzte 30 Tage)'
    'Add custom CSS for advanced styling.' = 'Fügen Sie benutzerdefiniertes CSS für erweiterte Gestaltung hinzu.'
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
