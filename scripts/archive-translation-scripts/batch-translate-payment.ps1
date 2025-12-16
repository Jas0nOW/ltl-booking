# Payment and Finance Terms
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Payment' = 'Zahlung'
    'Amount' = 'Betrag'
    'Subtotal' = 'Zwischensumme'
    'Tax' = 'Steuer'
    'Discount' = 'Rabatt'
    'Deposit' = 'Anzahlung'
    'Balance' = 'Restbetrag'
    'Paid' = 'Bezahlt'
    'Unpaid' = 'Unbezahlt'
    'Refund' = 'Rückerstattung'
    'Refunded' = 'Rückerstattet'
    'Payment Method' = 'Zahlungsmethode'
    'Credit Card' = 'Kreditkarte'
    'Cash' = 'Bargeld'
    'Bank Transfer' = 'Überweisung'
    'PayPal' = 'PayPal'
    'Stripe' = 'Stripe'
    'Invoice' = 'Rechnung'
    'Receipt' = 'Quittung'
    'Transaction' = 'Transaktion'
    'Transaction ID' = 'Transaktions-ID'
    'Payment Status' = 'Zahlungsstatus'
    'Paid in full' = 'Vollständig bezahlt'
    'Partially paid' = 'Teilweise bezahlt'
    'Payment failed' = 'Zahlung fehlgeschlagen'
    'Payment pending' = 'Zahlung ausstehend'
    'Pay Now' = 'Jetzt bezahlen'
    'Pay Later' = 'Später bezahlen'
    'Pay at venue' = 'Vor Ort bezahlen'
    'Free' = 'Kostenlos'
    'Currency' = 'Währung'
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
