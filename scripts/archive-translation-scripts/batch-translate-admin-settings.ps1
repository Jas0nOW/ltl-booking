# Admin and Settings Terms
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Plugin Settings' = 'Plugin-Einstellungen'
    'General Settings' = 'Allgemeine Einstellungen'
    'Advanced Settings' = 'Erweiterte Einstellungen'
    'Booking Settings' = 'Buchungseinstellungen'
    'Calendar Settings' = 'Kalendereinstellungen'
    'Notification Settings' = 'Benachrichtigungseinstellungen'
    'Email Settings' = 'E-Mail-Einstellungen'
    'Payment Settings' = 'Zahlungseinstellungen'
    'Privacy Settings' = 'Datenschutzeinstellungen'
    'Role' = 'Rolle'
    'Roles' = 'Rollen'
    'Permission' = 'Berechtigung'
    'Permissions' = 'Berechtigungen'
    'Access' = 'Zugriff'
    'Read Only' = 'Nur lesen'
    'Full Access' = 'Vollzugriff'
    'Administrator' = 'Administrator'
    'Manager' = 'Manager'
    'User' = 'Benutzer'
    'Guest' = 'Gast'
    'Diagnostic Tools' = 'Diagnosewerkzeuge'
    'System Info' = 'Systeminformationen'
    'Debug Mode' = 'Debug-Modus'
    'Clear Cache' = 'Cache leeren'
    'Reset Settings' = 'Einstellungen zurücksetzen'
    'Export' = 'Exportieren'
    'Import' = 'Importieren'
    'Backup' = 'Sicherung'
    'Restore' = 'Wiederherstellen'
    'Database' = 'Datenbank'
    'Version' = 'Version'
    'License' = 'Lizenz'
    'Documentation' = 'Dokumentation'
    'Support' = 'Support'
    'Help' = 'Hilfe'
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
