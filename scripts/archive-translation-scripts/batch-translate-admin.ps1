# Batch-Übersetzung häufiger Admin-Strings
# Übersetzt systematisch die am häufigsten verwendeten UI-Begriffe

$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"
$content = Get-Content $poFile -Raw -Encoding UTF8

# Übersetzungs-Dictionary für häufige Patterns
$translations = @{
    # Actions
    'Add New' = 'Neu hinzufügen'
    'Add New %s' = 'Neue(n) %s hinzufügen'
    'Add First' = 'Erste(n) hinzufügen'
    'Add Default Rules' = 'Standardregeln hinzufügen'
    'Add Rule' = 'Regel hinzufügen'
    'Add Template' = 'Vorlage hinzufügen'
    'Add Slot' = 'Zeitslot hinzufügen'
    'Add staff member' = 'Mitarbeiter hinzufügen'
    'Add time' = 'Uhrzeit hinzufügen'
    
    # Admin terms
    'Admin Notifications' = 'Admin-Benachrichtigungen'
    'Active' = 'Aktiv'
    'Actions' = 'Aktionen'
    'Action' = 'Aktion'
    'Action not found.' = 'Aktion nicht gefunden.'
    
    # AI terms
    'AI' = 'KI'
    'AI & Automations' = 'KI & Automationen'
    'AI Enabled' = 'KI aktiviert'
    'AI executes actions automatically' = 'KI führt Aktionen automatisch aus'
    'AI drafts appear in Outbox for approval' = 'KI-Entwürfe erscheinen zur Genehmigung im Postausgang'
    'AI Input' = 'KI-Eingabe'
    'AI Input / Output' = 'KI-Eingabe / Ausgabe'
    'AI Insights' = 'KI-Einblicke'
    'Activate AI-powered automations' = 'KI-gestützte Automationen aktivieren'
    
    # Design terms
    'Accent Color' = 'Akzentfarbe'
    'Accent preview' = 'Akzent-Vorschau'
    'Add custom CSS for advanced styling.' = 'Fügen Sie benutzerdefiniertes CSS für erweiterte Gestaltung hinzu.'
    'Add shadow to buttons (submit, primary, etc.)' = 'Schatten zu Buttons hinzufügen (Senden, Primär, etc.)'
    'Add shadow to input fields (text, select, etc.)' = 'Schatten zu Eingabefeldern hinzufügen (Text, Auswahl, etc.)'
    'Add shadow to service/room cards' = 'Schatten zu Dienstleistungs-/Zimmerkarten hinzufügen'
    'Add shadow to the main booking form container' = 'Schatten zum Hauptformular-Container hinzufügen'
    
    # Timing
    'After (min)' = 'Danach (Min.)'
    'Before (min)' = 'Davor (Min.)'
    'Adults' = 'Erwachsene'
    
    # Common verbs/actions
    'saved successfully' = 'erfolgreich gespeichert'
    'deleted successfully' = 'erfolgreich gelöscht'
    'created successfully' = 'erfolgreich erstellt'
    'updated successfully' = 'erfolgreich aktualisiert'
    
    # Settings
    'A key is stored. Leave blank to keep the existing key.' = 'Ein Schlüssel ist gespeichert. Leer lassen, um den vorhandenen Schlüssel beizubehalten.'
    'A password is stored. Leave blank to keep the existing password.' = 'Ein Passwort ist gespeichert. Leer lassen, um das vorhandene Passwort beizubehalten.'
    'A webhook secret is stored. Leave blank to keep the existing secret.' = 'Ein Webhook-Secret ist gespeichert. Leer lassen, um das vorhandene Secret beizubehalten.'
    
    # Appointments/Bookings
    'Add New Appointment' = 'Neuen Termin hinzufügen'
    'Add New Booking' = 'Neue Buchung hinzufügen'
    'Add New Customer' = 'Neuen Kunden hinzufügen'
    'Add New Guest' = 'Neuen Gast hinzufügen'
    
    # Pagination
    '&laquo; Prev' = '&laquo; Zurück'
}

$translatedCount = 0

# Ersetze jeden Eintrag
foreach ($english in $translations.Keys) {
    $german = $translations[$english]
    $escaped_english = [regex]::Escape($english)
    $pattern = "msgid `"$escaped_english`"\s+msgstr `"`""
    $replacement = "msgid `"$english`"`nmsgstr `"$german`""
    
    if ($content -match $pattern) {
        $content = $content -replace $pattern, $replacement
        $translatedCount++
        Write-Host "✓ Translated: $english -> $german" -ForegroundColor Green
    }
}

# Speichere aktualisierte Datei
$content | Out-File $poFile -Encoding UTF8 -NoNewline

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Batch Translation Complete!" -ForegroundColor Green
Write-Host "Translated: $translatedCount strings" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
