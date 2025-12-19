<?php
/**
 * Accurate translation count + add missing Dashboard translations
 */

$po_file = __DIR__ . '/../languages/de_DE.po';
$content = file_get_contents($po_file);

// Count actual translated vs empty
preg_match_all('/^msgstr ""$/m', $content, $empty);
preg_match_all('/^msgstr ".+"$/m', $content, $filled);

$total = count($empty[0]) + count($filled[0]);
$translated = count($filled[0]);
$empty_count = count($empty[0]);

echo "=== ECHTER ÜBERSETZUNGSSTATUS ===\n\n";
echo "Total Strings: $total\n";
echo "Übersetzt (msgstr nicht leer): $translated (" . round($translated/$total*100) . "%)\n";
echo "Leer (msgstr=\"\"): $empty_count (" . round($empty_count/$total*100) . "%)\n\n";

// Dashboard-specific translations that are missing
$dashboard_translations = [
    // Dashboard page
    'Your business at a glance' => 'Ihr Geschäft auf einen Blick',
    'Recent Appointments' => 'Aktuelle Termine',
    'Recent Bookings' => 'Aktuelle Buchungen',
    'Today\'s Schedule' => 'Heutiger Zeitplan',
    'Upcoming Appointments' => 'Bevorstehende Termine',
    'Quick Stats' => 'Schnellstatistiken',
    'Revenue Overview' => 'Umsatzübersicht',
    'Booking Trends' => 'Buchungstrends',
    'Customer Activity' => 'Kundenaktivität',
    'Staff Performance' => 'Mitarbeiterleistung',
    'Popular Services' => 'Beliebte Dienstleistungen',
    'Occupancy Rate' => 'Auslastungsrate',
    'Average Booking Value' => 'Durchschnittlicher Buchungswert',
    'This Week' => 'Diese Woche',
    'This Month' => 'Dieser Monat',
    'Last 7 Days' => 'Letzte 7 Tage',
    'Last 30 Days' => 'Letzte 30 Tage',
    'View All' => 'Alle anzeigen',
    'View Details' => 'Details anzeigen',
    'No appointments today' => 'Keine Termine heute',
    'No recent appointments' => 'Keine aktuellen Termine',
    'No recent bookings' => 'Keine aktuellen Buchungen',
    'No data available' => 'Keine Daten verfügbar',
    'No data' => 'Keine Daten',
    'Loading dashboard...' => 'Dashboard wird geladen...',
    
    // Navigation
    'Primary Navigation' => 'Hauptnavigation',
    'Quick Add' => 'Schnell hinzufügen',
    'Add New' => 'Neu hinzufügen',
    
    // Common UI
    'Search...' => 'Suchen...',
    'Select...' => 'Auswählen...',
    'No results' => 'Keine Ergebnisse',
    'No items' => 'Keine Einträge',
    'per page' => 'pro Seite',
    'items' => 'Einträge',
    'of' => 'von',
    'Showing' => 'Zeige',
    'entries' => 'Einträge',
    
    // Messages
    'A new booking has been received and requires your attention.' => 'Eine neue Buchung ist eingegangen und erfordert Ihre Aufmerksamkeit.',
    'A password is stored. Leave blank to keep the existing password.' => 'Ein Passwort ist gespeichert. Leer lassen, um das bestehende Passwort beizubehalten.',
    'A key is stored. Leave blank to keep the existing key.' => 'Ein Schlüssel ist gespeichert. Leer lassen, um den bestehenden Schlüssel beizubehalten.',
    'A webhook secret is stored. Leave blank to keep the existing secret.' => 'Ein Webhook-Secret ist gespeichert. Leer lassen, um das bestehende Secret beizubehalten.',
    'A guided booking process that prevents mistakes and keeps everything consistent.' => 'Ein geführter Buchungsprozess, der Fehler verhindert und alles konsistent hält.',
    
    // Service/Room related
    'Add booking form to your website' => 'Buchungsformular zu Ihrer Website hinzufügen',
    'Add custom CSS for advanced styling.' => 'Benutzerdefiniertes CSS für erweiterte Gestaltung hinzufügen.',
    'Add custom CSS to override or extend the design. Use .ltlb-booking as the wrapper class.' => 'Benutzerdefiniertes CSS hinzufügen, um das Design zu überschreiben oder zu erweitern. Verwenden Sie .ltlb-booking als Wrapper-Klasse.',
    'Add detailed descriptions for customers' => 'Detaillierte Beschreibungen für Kunden hinzufügen',
    'Activate AI-powered automations' => 'KI-gestützte Automatisierungen aktivieren',
    
    // Package related
    '10er Card' => '10er-Karte',
    '5er Card' => '5er-Karte',
    '%d of %d credits remaining' => '%d von %d Guthaben verbleibend',
    '%d rooms found in service mode (should be in hotel mode)' => '%d Zimmer im Service-Modus gefunden (sollte im Hotel-Modus sein)',
    '%d services are missing room configuration (beds_type)' => '%d Dienstleistungen fehlt die Zimmerkonfiguration (beds_type)',
    
    // Status/misc
    'Action not found.' => 'Aktion nicht gefunden.',
    'Accept offer: %s' => 'Angebot annehmen: %s',
    'Recent Deliveries' => 'Aktuelle Zustellungen',
    'Your booking: {service_name}' => 'Ihre Buchung: {service_name}',
    'Your business name.' => 'Ihr Firmenname.',
    'Your details' => 'Ihre Daten',
    '{service_name} â€" booking confirmation' => '{service_name} – Buchungsbestätigung',
    '– Select –' => '– Auswählen –',
    '—' => '—',
    '#' => '#',
    
    // Info messages
    'â„¹ No scheduled cron jobs yet' => 'ℹ Noch keine geplanten Cron-Jobs',
];

echo "=== FÜGE FEHLENDE ÜBERSETZUNGEN HINZU ===\n\n";

$added = 0;
foreach ($dashboard_translations as $en => $de) {
    // Find the msgid and check if msgstr is empty
    $pattern = '/msgid "' . preg_quote($en, '/') . '"\s*\nmsgstr ""/';
    if (preg_match($pattern, $content)) {
        $replacement = 'msgid "' . $en . '"' . "\n" . 'msgstr "' . addcslashes($de, '"\\') . '"';
        $content = preg_replace($pattern, $replacement, $content, 1);
        $added++;
        echo "✅ $en\n";
    }
}

file_put_contents($po_file, $content);

echo "\n✅ $added neue Übersetzungen hinzugefügt\n";

// Recount
preg_match_all('/^msgstr ""$/m', $content, $empty);
preg_match_all('/^msgstr ".+"$/m', $content, $filled);
$new_translated = count($filled[0]);
$new_empty = count($empty[0]);

echo "\n=== NEUER STATUS ===\n";
echo "Übersetzt: $new_translated (" . round($new_translated/$total*100) . "%)\n";
echo "Leer: $new_empty (" . round($new_empty/$total*100) . "%)\n";
