# PowerShell Script: Translate final 11 strings directly in UTF-8 .po file
$poFile = Join-Path (Split-Path $PSScriptRoot -Parent) "languages\de_DE.po"

Write-Host "Reading $poFile with UTF-8 encoding..."
$content = [System.IO.File]::ReadAllText($poFile, [System.Text.Encoding]::UTF8)

# Track replacements
$count = 0

# 1. {service_name} â€" booking confirmation
if ($content -match 'msgid "\{service_name\} .{1,5} booking confirmation"\s*msgstr ""') {
    $content = $content -replace '(msgid "\{service_name\} .{1,5} booking confirmation"\s*)msgstr ""', '$1msgstr "{service_name} – Buchungsbestätigung"'
    $count++
    Write-Host "1. Translated: {service_name} – booking confirmation"
}

# 2. â€" (dash only)
if ($content -match 'msgid ".{1,5}"\s*msgstr ""\s*\n\s*#: admin/Pages/ServicesPage\.php') {
    $content = $content -replace '(#: admin/Pages/AppointmentsPage\.php\s*msgid ".{1,5}"\s*)msgstr ""', '$1msgstr "–"'
    $count++
    Write-Host "2. Translated: – (dash)"
}

# 3. â€" Select â€"
if ($content -match 'msgid ".{1,5} Select .{1,5}"\s*msgstr ""\s*\n\s*#: admin/Pages/DesignPage\.php') {
    $content = $content -replace '(#: admin/Pages/ServicesPage\.php\s*msgid ".{1,5} Select .{1,5}"\s*)msgstr ""', '$1msgstr "– Auswählen –"'
    $count++
    Write-Host "3. Translated: – Select –"
}

# 4. AI settings managed under
if ($content -match 'msgid "AI settings \(provider, model, business context, and operating mode\) are managed under .{1,10}AI & Automations.{1,10}"\s*msgstr ""\s*\n\s*#: admin/Pages/AIPage\.php') {
    $content = $content -replace '(#: admin/Pages/SettingsPage\.php\s*msgid "AI settings \(provider, model, business context, and operating mode\) are managed under .{1,10}AI & Automations.{1,10}"\s*)msgstr ""', '$1msgstr "KI-Einstellungen (Anbieter, Modell, Geschäftskontext und Betriebsmodus) werden unter „KI & Automatisierungen" verwaltet."'
    $count++
    Write-Host "4. Translated: AI settings managed under..."
}

# 5. ✓ DB version matches
if ($content -match 'msgid ".{1,5} DB version matches plugin version\."\s*msgstr ""\s*\n\s*#: admin/Pages/DiagnosticsPage\.php\s*msgid ".{1,5} Present"') {
    $content = $content -replace '(#: admin/Pages/DiagnosticsPage\.php\s*msgid ".{1,5} DB version matches plugin version\."\s*)msgstr ""', '$1msgstr "✓ DB-Version entspricht Plugin-Version."'
    $count++
    Write-Host "5. Translated: ✓ DB version matches"
}

# 6. ✓ Present
if ($content -match 'msgid ".{1,5} Present"\s*msgstr ""\s*\n\s*#: admin/Pages/DesignPage\.php') {
    $content = $content -replace '(#: admin/Pages/DiagnosticsPage\.php\s*msgid ".{1,5} Present"\s*)msgstr ""\s*\n\s*#: admin/Pages/DesignPage\.php', '$1msgstr "✓ Vorhanden"' + "`n`n#: admin/Pages/DesignPage.php"
    $count++
    Write-Host "6. Translated: ✓ Present"
}

# 7. ⚠ DB version is behind
if ($content -match 'msgid ".{1,5} DB version is behind plugin version\."\s*msgstr ""') {
    $content = $content -replace '(msgid ".{1,5} DB version is behind plugin version\."\s*)msgstr ""', '$1msgstr "⚠ DB-Version liegt hinter Plugin-Version."'
    $count++
    Write-Host "7. Translated: ⚠ DB version is behind"
}

# 8. Deterministic formula
if ($content -match 'msgid "Deterministic: gross profit = revenue .{1,5} fees .{1,5} room costs \(from assigned rooms .{1,5} nights\)\."\s*msgstr ""') {
    $content = $content -replace '(msgid "Deterministic: gross profit = revenue .{1,5} fees .{1,5} room costs \(from assigned rooms .{1,5} nights\)\."\s*)msgstr ""', '$1msgstr "Deterministisch: Bruttogewinn = Umsatz − Gebühren − Zimmerkosten (aus zugewiesenen Zimmern × Nächte)."'
    $count++
    Write-Host "8. Translated: Deterministic formula"
}

# 9. No rules yet
if ($content -match 'msgid "No rules yet\. Click .{1,10}Add Default Rules.{1,10} to get started\."\s*msgstr ""') {
    $content = $content -replace '(msgid "No rules yet\. Click .{1,10}Add Default Rules.{1,10} to get started\."\s*)msgstr ""', '$1msgstr "Noch keine Regeln. Klicken Sie auf „Standardregeln hinzufügen", um zu beginnen."'
    $count++
    Write-Host "9. Translated: No rules yet..."
}

# 10. Optimized for phone
if ($content -match 'msgid "Optimized for phone, tablet, and desktop .{1,5} without breaking your theme\."\s*msgstr ""') {
    $content = $content -replace '(msgid "Optimized for phone, tablet, and desktop .{1,5} without breaking your theme\."\s*)msgstr ""', '$1msgstr "Optimiert für Handy, Tablet und Desktop – ohne Ihr Theme zu beeinträchtigen."'
    $count++
    Write-Host "10. Translated: Optimized for phone..."
}

# 11. Supported ✓
if ($content -match 'msgid "Supported .{1,5}"\s*msgstr ""') {
    $content = $content -replace '(msgid "Supported .{1,5}"\s*)msgstr ""', '$1msgstr "Unterstützt ✓"'
    $count++
    Write-Host "11. Translated: Supported ✓"
}

# Write back with UTF-8 BOM (WordPress standard)
Write-Host "`nWriting changes back to file..."
$utf8BOM = New-Object System.Text.UTF8Encoding $true
[System.IO.File]::WriteAllText($poFile, $content, $utf8BOM)

Write-Host "`n===== FINAL TRANSLATION COMPLETE ====="
Write-Host "Successfully translated: $count strings"
Write-Host "======================================="
