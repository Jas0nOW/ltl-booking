# Comprehensive Translation Script - Part 1
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'No action pending.' = 'Keine Aktion ausstehend.'
    'No appointments found.' = 'Keine Termine gefunden.'
    'No bookings found.' = 'Keine Buchungen gefunden.'
    'No columns selected.' = 'Keine Spalten ausgewählt.'
    'No customers found.' = 'Keine Kunden gefunden.'
    'No data available.' = 'Keine Daten verfügbar.'
    'No default templates available.' = 'Keine Standardvorlagen verfügbar.'
    'No exceptions.' = 'Keine Ausnahmen.'
    'No guests found.' = 'Keine Gäste gefunden.'
    'No items found.' = 'Keine Einträge gefunden.'
    'No logs available.' = 'Keine Protokolle verfügbar.'
    'No pending bookings.' = 'Keine ausstehenden Buchungen.'
    'No refund' = 'Keine Rückerstattung'
    'No resources found.' = 'Keine Ressourcen gefunden.'
    'No results found.' = 'Keine Ergebnisse gefunden.'
    'No room types found.' = 'Keine Raumtypen gefunden.'
    'No rules found.' = 'Keine Regeln gefunden.'
    'No services found.' = 'Keine Dienstleistungen gefunden.'
    'No staff found.' = 'Keine Mitarbeiter gefunden.'
    'No templates found.' = 'Keine Vorlagen gefunden.'
    'No upcoming bookings.' = 'Keine bevorstehenden Buchungen.'
    'No working hours set.' = 'Keine Arbeitszeiten festgelegt.'
    'None' = 'Keine'
    'Not Paid' = 'Nicht bezahlt'
    'Not set' = 'Nicht festgelegt'
    'Note: This is a test. No actual email will be sent.' = 'Hinweis: Dies ist ein Test. Es wird keine tatsächliche E-Mail gesendet.'
    'Notes' = 'Notizen'
    'Notes (optional)' = 'Notizen (optional)'
    'Notification sent.' = 'Benachrichtigung gesendet.'
    'Number of guests' = 'Anzahl Gäste'
    'Occupancy' = 'Belegung'
    'Occupancy Rate' = 'Belegungsrate'
    'Occupied' = 'Belegt'
    'Off' = 'Aus'
    'OK' = 'OK'
    'On' = 'An'
    'One or more services are required.' = 'Eine oder mehrere Dienstleistungen sind erforderlich.'
    'Online Payment' = 'Online-Zahlung'
    'Only show active items' = 'Nur aktive Einträge anzeigen'
    'Open Calendar' = 'Kalender öffnen'
    'Operating Mode' = 'Betriebsmodus'
    'Optional' = 'Optional'
    'Options' = 'Optionen'
    'or' = 'oder'
    'Order' = 'Reihenfolge'
    'Other' = 'Andere'
    'Outbox' = 'Postausgang'
    'Overview' = 'Übersicht'
    'Page %d of %d' = 'Seite %d von %d'
    'Paid' = 'Bezahlt'
    'Paid Amount' = 'Bezahlter Betrag'
    'Partially Paid' = 'Teilweise bezahlt'
    'Password' = 'Passwort'
    'Pay at venue' = 'Vor Ort bezahlen'
    'Pay later' = 'Später bezahlen'
    'Pay online' = 'Online bezahlen'
    'Payment failed.' = 'Zahlung fehlgeschlagen.'
    'Payment Gateway' = 'Zahlungsgateway'
    'Payment not found.' = 'Zahlung nicht gefunden.'
    'Payment received.' = 'Zahlung eingegangen.'
    'Payment required' = 'Zahlung erforderlich'
    'Payments' = 'Zahlungen'
    'PayPal Client ID' = 'PayPal Client-ID'
    'PayPal Client Secret' = 'PayPal Client-Secret'
    'Pending' = 'Ausstehend'
    'Pending Approval' = 'Genehmigung ausstehend'
    'Per night' = 'Pro Nacht'
    'Percentage of total booking amount (e.g., 3.5 for 3.5%). Stored as decimal.' = 'Prozentsatz des Gesamtbuchungsbetrags (z.B. 3.5 für 3.5%). Als Dezimalzahl gespeichert.'
    'Personal: Casual & Friendly' = 'Persönlich: Locker & Freundlich'
    'Phone' = 'Telefon'
    'Phone Number' = 'Telefonnummer'
    'Please complete all required fields.' = 'Bitte füllen Sie alle Pflichtfelder aus.'
    'Please confirm you want to refund this payment.' = 'Bitte bestätigen Sie, dass Sie diese Zahlung zurückerstatten möchten.'
    'Please enter a valid email address.' = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'
    'Please enter your details' = 'Bitte geben Sie Ihre Daten ein'
    'Please enter your email address.' = 'Bitte geben Sie Ihre E-Mail-Adresse ein.'
    'Please enter your name.' = 'Bitte geben Sie Ihren Namen ein.'
    'Please review and confirm your booking' = 'Bitte überprüfen und bestätigen Sie Ihre Buchung'
    'Please select a date.' = 'Bitte wählen Sie ein Datum.'
    'Please select a service.' = 'Bitte wählen Sie eine Dienstleistung.'
    'Please select a staff member.' = 'Bitte wählen Sie einen Mitarbeiter.'
    'Please select a time.' = 'Bitte wählen Sie eine Uhrzeit.'
    'Please select an option.' = 'Bitte wählen Sie eine Option.'
    'Please try again later.' = 'Bitte versuchen Sie es später erneut.'
    'Plugin Settings' = 'Plugin-Einstellungen'
    'Plugin Version' = 'Plugin-Version'
    'Plugin Version:' = 'Plugin-Version:'
    'Port' = 'Port'
    'Port:' = 'Port:'
    'Preview' = 'Vorschau'
    'Price' = 'Preis'
    'Price (per night)' = 'Preis (pro Nacht)'
    'Primary' = 'Primär'
    'Primary Button Color' = 'Primäre Button-Farbe'
    'Priority' = 'Priorität'
    'Privacy' = 'Datenschutz'
    'Privacy Settings' = 'Datenschutzeinstellungen'
    'Process' = 'Verarbeiten'
    'Processing...' = 'Verarbeitung...'
    'Provider' = 'Anbieter'
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
