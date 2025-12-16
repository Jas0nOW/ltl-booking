# Automation and AI Terms
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Automation' = 'Automatisierung'
    'Rule' = 'Regel'
    'Rules' = 'Regeln'
    'Trigger' = 'Auslöser'
    'Triggers' = 'Auslöser'
    'Action' = 'Aktion'
    'Condition' = 'Bedingung'
    'Conditions' = 'Bedingungen'
    'When' = 'Wenn'
    'Then' = 'Dann'
    'If' = 'Falls'
    'And' = 'Und'
    'Or' = 'Oder'
    'AI Response' = 'KI-Antwort'
    'Generate' = 'Generieren'
    'Generate Response' = 'Antwort generieren'
    'AI Provider' = 'KI-Anbieter'
    'API Key' = 'API-Schlüssel'
    'Model' = 'Modell'
    'Prompt' = 'Eingabeaufforderung'
    'Response' = 'Antwort'
    'Suggestion' = 'Vorschlag'
    'Suggestions' = 'Vorschläge'
    'Smart Reply' = 'Intelligente Antwort'
    'Auto Reply' = 'Automatische Antwort'
    'Send Automatically' = 'Automatisch senden'
    'Review Before Sending' = 'Vor dem Senden überprüfen'
    'Approve' = 'Genehmigen'
    'Reject' = 'Ablehnen'
    'Draft Reply' = 'Antwortentwurf'
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
