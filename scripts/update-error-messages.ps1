#!/usr/bin/env pwsh
# Update Error Message Translations
$ErrorActionPreference = "Stop"

$PoFile = "$PSScriptRoot\..\languages\de_DE.po"

if (-not (Test-Path $PoFile)) {
    Write-Host "ERROR: PO file not found: $PoFile" -ForegroundColor Red
    exit 1
}

# Read PO file
$content = Get-Content $PoFile -Raw -Encoding UTF8

# Define improved error messages with German translations
$translations = @{
    # Past date errors
    "The selected check-in date is in the past." = "Dieses Check-in-Datum liegt in der Vergangenheit. Bitte wählen Sie ein zukünftiges Datum."
    "This check-in date has already passed. Please select a future date." = "Dieses Check-in-Datum liegt in der Vergangenheit. Bitte wählen Sie ein zukünftiges Datum."
    "The selected time is in the past." = "Diese Uhrzeit liegt in der Vergangenheit. Bitte wählen Sie ein zukünftiges Datum und eine zukünftige Uhrzeit."
    "This time has already passed. Please choose a future date and time." = "Diese Uhrzeit liegt in der Vergangenheit. Bitte wählen Sie ein zukünftiges Datum und eine zukünftige Uhrzeit."
    
    # Lock/Conflict errors
    "Another booking is currently being processed. Please try again." = "Diese Buchung ist vorübergehend gesperrt, während eine andere Reservierung abgeschlossen wird. Bitte warten Sie einen Moment und versuchen Sie es erneut."
    "This booking is temporarily locked while another reservation is being finalized. Please wait a moment and try again." = "Diese Buchung ist vorübergehend gesperrt, während eine andere Reservierung abgeschlossen wird. Bitte warten Sie einen Moment und versuchen Sie es erneut."
    "The selected time slot is already taken." = "Dieser Zeitslot wurde gerade gebucht. Bitte wählen Sie eine andere Zeit."
    "This time slot has just been booked. Please select a different time." = "Dieser Zeitslot wurde gerade gebucht. Bitte wählen Sie eine andere Zeit."
    
    # Validation errors
    "Invalid date/time." = "Das Datum oder die Uhrzeit ist nicht gültig. Bitte überprüfen Sie Ihre Eingabe und versuchen Sie es erneut."
    "The date or time format is not valid. Please check your selection and try again." = "Das Datum oder die Uhrzeit ist nicht gültig. Bitte überprüfen Sie Ihre Eingabe und versuchen Sie es erneut."
    
    # Customer/Booking errors
    "Could not save customer." = "Wir konnten Ihre Kontaktdaten nicht speichern. Bitte überprüfen Sie Ihre Angaben und versuchen Sie es erneut."
    "We were unable to save your contact information. Please check your details and try again." = "Wir konnten Ihre Kontaktdaten nicht speichern. Bitte überprüfen Sie Ihre Angaben und versuchen Sie es erneut."
    "Could not create booking." = "Wir konnten Ihre Buchung nicht abschließen. Bitte versuchen Sie es erneut oder kontaktieren Sie uns für Unterstützung."
    "We were unable to complete your booking. Please try again or contact us for assistance." = "Wir konnten Ihre Buchung nicht abschließen. Bitte versuchen Sie es erneut oder kontaktieren Sie uns für Unterstützung."
    
    # Success messages (consistency)
    "Booking received" = "Buchung erhalten"
    "You selected payment on site. Your booking is awaiting confirmation." = "Sie haben Zahlung vor Ort gewählt. Ihre Buchung wartet auf Bestätigung."
    "You selected company invoice. We will contact you with the invoice details. Your booking is awaiting confirmation." = "Sie haben Firmenrechnung gewählt. Wir werden Sie mit den Rechnungsdetails kontaktieren. Ihre Buchung wartet auf Bestätigung."
    "Your booking has been received and is awaiting confirmation. Please check your email for details." = "Ihre Buchung wurde erhalten und wartet auf Bestätigung. Bitte prüfen Sie Ihre E-Mails für Details."
    
    # Loading/Availability messages
    "Availability could not be loaded. Please try again." = "Verfügbarkeit konnte nicht geladen werden. Bitte versuchen Sie es erneut."
    "Resources could not be loaded. Please try again." = "Ressourcen konnten nicht geladen werden. Bitte versuchen Sie es erneut."
    "Times could not be loaded" = "Zeiten konnten nicht geladen werden"
    "Times could not be loaded. Please reload the page." = "Zeiten konnten nicht geladen werden. Bitte laden Sie die Seite neu."
    "Times could not be loaded. Please try again." = "Zeiten konnten nicht geladen werden. Bitte versuchen Sie es erneut."
}

$newEntries = @()

foreach ($english in $translations.Keys) {
    $german = $translations[$english]
    
    # Check if entry exists
    if ($content -notmatch [regex]::Escape("msgid `"$english`"")) {
        Write-Host "Adding new translation: $english" -ForegroundColor Green
        $entry = @"

msgid "$english"
msgstr "$german"
"@
        $newEntries += $entry
    } else {
        Write-Host "Translation exists (skipping): $english" -ForegroundColor Gray
    }
}

if ($newEntries.Count -gt 0) {
    Write-Host "`nAdding $($newEntries.Count) new translation entries..." -ForegroundColor Yellow
    $content += "`n`n# Improved Error Messages (Auto-added)"
    $content += ($newEntries -join "`n")
    
    # Write back to file with UTF-8 encoding (no BOM)
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($PoFile, $content, $utf8NoBom)
    
    Write-Host "`nSuccessfully updated $PoFile" -ForegroundColor Green
    Write-Host "Run compile-mo.php to generate .mo file" -ForegroundColor Cyan
} else {
    Write-Host "`nNo new translations needed. All messages already exist." -ForegroundColor Cyan
}
