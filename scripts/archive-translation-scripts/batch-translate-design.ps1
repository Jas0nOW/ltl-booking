# Design and UI Components
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Border Radius (px)' = 'Rahmenradius (px)'
    'Border Width (px)' = 'Rahmenbreite (px)'
    'Brand Name' = 'Markenname'
    'Brand Voice' = 'Markenstimme'
    'Breadcrumbs' = 'Brotkrumen'
    'Bulk Actions' = 'Massenaktionen'
    'Bulk actions toolbar' = 'Massenaktionen-Symbolleiste'
    'Bunk Beds' = 'Etagenbetten'
    'Business Context' = 'Geschäftskontext'
    'Business: Friendly Concierge' = 'Geschäftlich: Freundlicher Concierge'
    'Business: Short & Professional' = 'Geschäftlich: Kurz & Professionell'
    'Button Shadow' = 'Button-Schatten'
    'Buttons' = 'Buttons'
    'Calendar Feed (iCal)' = 'Kalender-Feed (iCal)'
    'Cancellation, refund, and booking policies.' = 'Storno-, Rückerstattungs- und Buchungsrichtlinien.'
    'Card (POS / on site)' = 'Karte (POS / vor Ort)'
    'Card (POS)' = 'Karte (POS)'
    'Card (Stripe)' = 'Karte (Stripe)'
    'Card Details' = 'Kartendetails'
    'Card Shadow' = 'Karten-Schatten'
    'Cash (on site)' = 'Bargeld (vor Ort)'
    'Change color' = 'Farbe ändern'
    'Change color for Cancelled' = 'Farbe für Storniert ändern'
    'Change color for Confirmed' = 'Farbe für Bestätigt ändern'
    'Change color for Pending' = 'Farbe für Ausstehend ändern'
    'Change status to cancelled' = 'Status zu Storniert ändern'
    'Change status to confirmed' = 'Status zu Bestätigt ändern'
    'Change status to pending' = 'Status zu Ausstehend ändern'
    'Changes update automatically.' = 'Änderungen werden automatisch aktualisiert.'
    'Check availability' = 'Verfügbarkeit prüfen'
    'Check-in' = 'Anreise'
    'Check-ins Today' = 'Anreisen heute'
    'Check-out' = 'Abreise'
    'Check-outs Today' = 'Abreisen heute'
    'Checkout (redirect)' = 'Checkout (Weiterleitung)'
    'Children' = 'Kinder'
    'Choose action to apply to selected appointments' = 'Wählen Sie die Aktion für ausgewählte Termine'
    'Choose room' = 'Raum wählen'
    'Clear availability' = 'Verfügbarkeit löschen'
    'Clear Filters' = 'Filter löschen'
    'Color for input and card borders' = 'Farbe für Eingabe- und Kartenränder'
    'Color saved.' = 'Farbe gespeichert.'
    'Colors' = 'Farben'
    'Columns' = 'Spalten'
    'Common questions and answers.' = 'Häufige Fragen und Antworten.'
    'Company invoice' = 'Firmenrechnung'
    'Company name' = 'Firmenname'
    'Container Shadow' = 'Container-Schatten'
    'Contact Info' = 'Kontaktinformationen'
    'Conflict Check' = 'Konfliktprüfung'
    'Confirm Refund' = 'Rückerstattung bestätigen'
    'Connection failed.' = 'Verbindung fehlgeschlagen.'
    'Connection failed: %s' = 'Verbindung fehlgeschlagen: %s'
    'Connection OK' = 'Verbindung OK'
    'Connection OK.' = 'Verbindung OK.'
    'Cost / Night' = 'Kosten / Nacht'
    'Cost per Night' = 'Kosten pro Nacht'
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
