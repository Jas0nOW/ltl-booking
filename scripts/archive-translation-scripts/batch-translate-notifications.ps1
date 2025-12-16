# Notification and Email Terms
$poFile = "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\languages\de_DE.po"

$translations = @{
    'Notification' = 'Benachrichtigung'
    'Send' = 'Senden'
    'Sent' = 'Gesendet'
    'Failed' = 'Fehlgeschlagen'
    'Retry' = 'Wiederholen'
    'Subject' = 'Betreff'
    'Message' = 'Nachricht'
    'Recipient' = 'Empfänger'
    'Sender' = 'Absender'
    'Reply To' = 'Antworten an'
    'CC' = 'CC'
    'BCC' = 'BCC'
    'Attachment' = 'Anhang'
    'Attachments' = 'Anhänge'
    'Template' = 'Vorlage'
    'Reply Templates' = 'Antwortvorlagen'
    'Email Template' = 'E-Mail-Vorlage'
    'SMS Template' = 'SMS-Vorlage'
    'Booking Confirmation' = 'Buchungsbestätigung'
    'Booking Reminder' = 'Buchungserinnerung'
    'Booking Cancelled' = 'Buchung storniert'
    'Payment Receipt' = 'Zahlungsbeleg'
    'Thank you' = 'Vielen Dank'
    'Reminder' = 'Erinnerung'
    'Follow-up' = 'Nachverfolgung'
    'Send Email' = 'E-Mail senden'
    'Send SMS' = 'SMS senden'
    'Send Notification' = 'Benachrichtigung senden'
    'Email sent successfully' = 'E-Mail erfolgreich gesendet'
    'Failed to send email' = 'E-Mail konnte nicht gesendet werden'
    'Queue' = 'Warteschlange'
    'Queued' = 'In Warteschlange'
    'Retry Count' = 'Wiederholungsanzahl'
    'Next Retry' = 'Nächster Versuch'
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
