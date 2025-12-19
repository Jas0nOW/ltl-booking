<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_I18n {
	public const TEXT_DOMAIN = 'ltl-bookings';
	public const USER_META_KEY = 'ltlb_admin_lang';

	public static function init(): void {
		add_filter( 'gettext', [ __CLASS__, 'filter_gettext' ], 10, 3 );
		add_filter( 'gettext_with_context', [ __CLASS__, 'filter_gettext_with_context' ], 10, 4 );
	}

	public static function is_ltlb_admin_page_request(): bool {
		if ( ! is_admin() ) return false;
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! is_string( $page ) || ! $page ) return false;
		return strpos( (string) $page, 'ltlb_' ) === 0;
	}

	public static function get_user_admin_locale( ?int $user_id = null ): string {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) return 'en_US';

		$locale = get_user_meta( $user_id, self::USER_META_KEY, true );
		$locale = is_string( $locale ) ? trim( $locale ) : '';
		if ( $locale !== 'de_DE' && $locale !== 'en_US' ) {
			return 'en_US';
		}
		return $locale;
	}

	public static function set_user_admin_locale( int $user_id, string $locale ): void {
		$locale = trim( $locale );
		if ( $locale !== 'de_DE' && $locale !== 'en_US' ) {
			$locale = 'en_US';
		}
		update_user_meta( $user_id, self::USER_META_KEY, $locale );
	}

	public static function filter_gettext( string $translated, string $text, string $domain ): string {
		if ( $domain !== self::TEXT_DOMAIN ) return $translated;
		if ( ! self::is_ltlb_admin_page_request() ) return $translated;

		$locale = self::get_user_admin_locale();
		if ( $locale !== 'de_DE' ) return $translated;

		$dict = self::get_de_dictionary();
		return isset( $dict[ $text ] ) ? $dict[ $text ] : $translated;
	}

	public static function filter_gettext_with_context( string $translated, string $text, string $context, string $domain ): string {
		if ( $domain !== self::TEXT_DOMAIN ) return $translated;
		if ( ! self::is_ltlb_admin_page_request() ) return $translated;

		$locale = self::get_user_admin_locale();
		if ( $locale !== 'de_DE' ) return $translated;

		$dict = self::get_de_dictionary();
		return isset( $dict[ $text ] ) ? $dict[ $text ] : $translated;
	}

	private static function get_de_dictionary(): array {
		static $dict = null;
		if ( is_array( $dict ) ) return $dict;

		// Load translations from .po file with simple regex parsing
		$po_file = dirname( __DIR__, 2 ) . '/languages/de_DE.po';
		if ( file_exists( $po_file ) ) {
			$dict = self::parse_po_file( $po_file );
			if ( ! empty( $dict ) ) {
				return $dict;
			}
		}

		// Fallback to hardcoded dictionary
		$dict = [
				'Dashboard' => 'Dashboard',
				'Appointments' => 'Termine',
			'Calendar' => 'Kalender',
			'Customers' => 'Kunden',
			'Services' => 'Services',
			'Room Types' => 'Zimmertypen',
			'Room' => 'Zimmer',
			'Resources' => 'Ressourcen',
			'Rooms' => 'Zimmer',
			'Unassigned' => 'Nicht zugewiesen',
			'Other' => 'Sonstiges',
			'Staff' => 'Mitarbeiter',
			'Settings' => 'Einstellungen',
			'Design' => 'Design',
			'Diagnostics' => 'Diagnostik',
			'Privacy' => 'Datenschutz',
			'Privacy & GDPR' => 'Datenschutz & DSGVO',
			'LazyBookings' => 'LazyBookings',
			'Version %s' => 'Version %s',
			'LazyBookings Navigation' => 'LazyBookings Navigation',
			'Language' => 'Sprache',
			'English' => 'Englisch',
			'German' => 'Deutsch',
			'Update' => 'Speichern',

			// Common actions
			'Add New' => 'Neu hinzufügen',
			'Edit' => 'Bearbeiten',
			'Cancel' => 'Abbrechen',
			'Delete' => 'Löschen',
			'Confirm' => 'Bestätigen',
			'Create' => 'Erstellen',
			'Save' => 'Speichern',
			'Yes' => 'Ja',
			'No' => 'Nein',
			'Active' => 'Aktiv',
			'Inactive' => 'Inaktiv',
			'Filter' => 'Filtern',
			'Reset' => 'Zurücksetzen',

			// Generic labels
			'Name' => 'Name',
			'Description' => 'Beschreibung',
			'Capacity' => 'Kapazität',
			'Email' => 'E-Mail',
			'First Name' => 'Vorname',
			'Last Name' => 'Nachname',
			'Phone' => 'Telefon',
			'Notes' => 'Notizen',
			'Date' => 'Datum',
			'From' => 'Von',
			'To' => 'Bis',
			'Start' => 'Start',
			'End' => 'Ende',
			'Legend' => 'Legende',
			'Status' => 'Status',
			'Actions' => 'Aktionen',
			'Tip: Click an appointment to view and edit details.' => 'Tipp: Klicken Sie auf einen Termin, um Details anzusehen und zu bearbeiten.',
			'Today' => 'Heute',
			'Week' => 'Woche',
			'Month' => 'Monat',
			'Day' => 'Tag',
			'Mon' => 'Mo',
			'Tue' => 'Di',
			'Wed' => 'Mi',
			'Thu' => 'Do',
			'Fri' => 'Fr',
			'Sat' => 'Sa',
			'Sun' => 'So',

			// Status labels
			'Confirmed' => 'Bestätigt',
			'Pending' => 'Ausstehend',
			'Cancelled' => 'Storniert',
			'All Statuses' => 'Alle Status',
				'Cleanup completed. Deleted appointments: %d. Anonymized customers: %d.' => 'Bereinigung abgeschlossen. Gelöschte Termine: %d. Anonymisierte Kunden: %d.',
			'All Services' => 'Alle Services',
			'Cancel this appointment?' => 'Diesen Termin stornieren?',
			'Permanently delete?' => 'Dauerhaft löschen?',

			// Permissions/security
			'No access' => 'Kein Zugriff',
			'Insufficient permissions' => 'Unzureichende Berechtigungen',
			'No permission' => 'Keine Berechtigung',
			'Nonce verification failed' => 'Nonce-Prüfung fehlgeschlagen',
			'Security check failed' => 'Sicherheitsprüfung fehlgeschlagen',
			'Security check failed. Please reload the page and try again.' => 'Sicherheitsprüfung fehlgeschlagen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
			'Are you sure?' => 'Sind Sie sicher?',

			// Common notices/errors
			'Could not create outbox draft.' => 'Entwurf konnte nicht erstellt werden.',
			'Could not save service. Please try again.' => 'Service konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.',
			'Could not save customer. Please try again.' => 'Kunde konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.',
			'Could not save guest. Please try again.' => 'Gast konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.',
			'Could not save %s. Please try again.' => '%s konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.',

			// Dashboard
			'LazyBookings Dashboard' => 'LazyBookings Übersicht',
			'Latest Appointments' => 'Neueste Termine',
			'No appointments found.' => 'Keine Termine gefunden.',
			'Saved.' => 'Gespeichert.',
			'No staff members found.' => 'Noch keine Mitarbeitenden vorhanden.',
			'Edit %s' => '%s bearbeiten',
			'Create %s' => '%s anlegen',

			// Diagnostics
			'Migrations ran successfully.' => 'Migrationen erfolgreich ausgeführt.',
			'System Information' => 'Systeminformationen',
			'WordPress Version' => 'WordPress-Version',
			'PHP Version' => 'PHP-Version',
			'Database Prefix' => 'Datenbank-Präfix',
			'Booking Mode' => 'Buchungsmodus',
			'DB Version' => 'DB-Version',
			'Plugin Version' => 'Plugin-Version',
			'Database Statistics' => 'Datenbank-Statistik',
			'Database Maintenance' => 'Datenbank-Wartung',
			'Run migrations' => 'Migrationen ausführen',
			'Runs database migrations again. Can be run multiple times (uses dbDelta).' => 'Führt Datenbank-Migrationen erneut aus. Kann mehrfach ausgeführt werden (nutzt dbDelta).',
			'Run system check' => 'Systemcheck ausführen',
			'Run system diagnostics (read-only).' => 'Systemdiagnose ausführen (nur lesend).',
			'Table Status' => 'Tabellenstatus',
			'Table name' => 'Tabellenname',
			'Rows' => 'Zeilen',
			'✓ Present' => '✓ Vorhanden',
			'✗ Missing' => '✗ Fehlt',
			'N/A' => 'k. A.',
			'System Check Results' => 'Systemdiagnose-Ergebnisse',
			'unknown' => 'unbekannt',
			'Plugin Version:' => 'Plugin-Version:',
			'DB Version:' => 'DB-Version:',
			'⚠ DB version is behind plugin version.' => '⚠ DB-Version ist hinter der Plugin-Version.',
			'Please run migrations.' => 'Bitte Migrationen ausführen.',
			'✓ DB version matches plugin version.' => '✓ DB-Version entspricht der Plugin-Version',
			'Booking Mode:' => 'Buchungsmodus:',
			'Supported ✓' => 'Unterstützt ✓',
			'Not supported (race condition protection disabled)' => 'Nicht unterstützt (Schutz vor Race Conditions deaktiviert)',
			'Email from:' => 'E-Mail von:',
			'Enabled (%s)' => 'Aktiv (%s)',
			'Enabled' => 'Aktiv',
			'Disabled' => 'Deaktiviert',
			'Logging:' => 'Protokollierung:',
			'Dev Tools:' => 'Dev-Tools:',
			'Last Migration:' => 'Letzte Migration:',
			'No customer data.' => 'Keine Kundendaten.',

			// Appointments list
			'View Calendar' => 'Kalender anzeigen',
			'Export CSV' => 'CSV exportieren',
			'You do not have permission to view this page.' => 'Sie haben keine Berechtigung, diese Seite zu sehen.',
			'From Date' => 'Von Datum',
			'To Date' => 'Bis Datum',
			'Filter from date' => 'Nach Startdatum filtern',
			'Filter to date' => 'Nach Enddatum filtern',
			'Filter by status' => 'Nach Status filtern',
			'Date & Time' => 'Datum & Uhrzeit',
			'Customer' => 'Kunde',
			'Service' => 'Service',
			'Resource' => 'Ressource',
			'No appointments found for the current filters.' => 'Keine Termine für die aktuellen Filter gefunden.',
			'No appointments yet. Once someone books via the booking form, they will appear here.' => 'Noch keine Termine vorhanden. Sobald jemand über das Buchungsformular bucht, erscheinen sie hier.',
			'Team' => 'Team',
			'Appointment deleted.' => 'Termin gelöscht.',
			'Status updated.' => 'Status aktualisiert.',
			'Appointment updated.' => 'Termin aktualisiert.',
			'Appointment details loaded.' => 'Termindetails geladen.',
			'Loading appointment details…' => 'Termindetails werden geladen…',

			// Calendar legend: editable colors
			'Change color' => 'Farbe ändern',
			'Change color for Confirmed' => 'Farbe für Bestätigt ändern',
			'Change color for Pending' => 'Farbe für Ausstehend ändern',
			'Change color for Cancelled' => 'Farbe für Storniert ändern',
			'Color saved.' => 'Farbe gespeichert.',
			'Could not save color.' => 'Farbe konnte nicht gespeichert werden.',
			'No rooms found.' => 'Keine Zimmer gefunden.',
			'Could not load rooms.' => 'Zimmer konnten nicht geladen werden.',

			// CSV export headings
			'Customer Name' => 'Kundenname',
			'Customer Email' => 'Kunden-E-Mail',
			'Customer Phone' => 'Kundentelefon',

			// Customers
			'Manage customer information. Customers are created automatically from bookings.' => 'Kundendaten verwalten. Kunden werden automatisch aus Buchungen erstellt.',
			'Edit Customer' => 'Kunde bearbeiten',
			'Add New Customer' => 'Neuen Kunden hinzufügen',
			'Update Customer' => 'Kunde aktualisieren',
			'Create Customer' => 'Kunde erstellen',
			'No customers found.' => 'Keine Kunden gefunden.',

			// Design (Admin preview)
			'Backend' => 'Backend',
			'Frontend' => 'Frontend',
			'Backend Preview' => 'Backend-Vorschau',
			'Example Admin Panel' => 'Beispiel Admin-Panel',
			'Preview navigation' => 'Vorschau-Navigation',
			'Primary Button' => 'Primärer Button',
			'Accent preview' => 'Akzent-Vorschau',
			'Secondary' => 'Sekundär',
			'Search…' => 'Suchen…',
			'This is a live preview of your backend color palette.' => 'Dies ist eine Live-Vorschau deiner Backend-Farbpalette.',
			'Backend tab controls the color palette used inside WP Admin (LazyBookings pages).' => 'Der Backend-Tab steuert die Farbpalette innerhalb des WP-Admins (LazyBookings-Seiten).',
			'Frontend tab controls the color palette used on the booking widget.' => 'Der Frontend-Tab steuert die Farbpalette im Buchungs-Widget.',
			'Jane Doe' => 'Max Mustermann',
			'Yoga Session' => 'Yoga-Kurs',

			// Services
			'Service' => 'Service',
			'Room Type' => 'Zimmertyp',
			'Edit %s' => '%s bearbeiten',
			'Add New %s' => 'Neuen %s hinzufügen',
			'Service Name' => 'Service-Name',
			'Duration (minutes)' => 'Dauer (Minuten)',
			'Service duration in minutes' => 'Service-Dauer in Minuten',
			'Price' => 'Preis',
			'Buffer Time' => 'Pufferzeit',
			'Before (min)' => 'Vorher (Min)',
			'After (min)' => 'Nachher (Min)',
			'No resources found. Please add resources first.' => 'Keine Ressourcen gefunden. Bitte zuerst Ressourcen anlegen.',
			'Select resources that can perform this service.' => 'Wähle Ressourcen aus, die diesen Service durchführen können.',
			'Availability (optional)' => 'Verfügbarkeit (optional)',
			'Window (any start time within)' => 'Zeitfenster (beliebiger Start innerhalb)',
			'Fixed weekly start times' => 'Feste wöchentliche Startzeiten',
			'Fixed weekly times' => 'Feste wöchentliche Zeiten',
			'Add time' => 'Zeit hinzufügen',
			'Limit this class to specific days/times. Choose a window (any time inside) or fixed weekly start times (e.g. Fri 18:00). If left empty, global working hours apply.' => 'Begrenze diesen Kurs auf bestimmte Tage/Zeiten. Wähle ein Zeitfenster (beliebige Startzeit innerhalb) oder feste wöchentliche Startzeiten (z.B. Fr 18:00). Wenn leer, gelten die globalen Arbeitszeiten.',
			'Add one or more weekly start times. Example: Fri 18:00. The customer will only see these times (still respecting staff/global hours and existing bookings).' => 'Füge eine oder mehrere wöchentliche Startzeiten hinzu. Beispiel: Fr 18:00. Kunden sehen nur diese Zeiten (unter Berücksichtigung von Mitarbeiter-/globalen Zeiten und bestehenden Buchungen).',
			'Save Service' => 'Service speichern',

			// Resources
			'Manage %s' => '%s verwalten',
			'Rooms are the bookable units (e.g. Room 101, Room 102). Link them to room types to control availability.' => 'Zimmer sind die buchbaren Einheiten (z. B. Zimmer 101, Zimmer 102). Verknüpfe sie mit Zimmertypen, um die Verfügbarkeit zu steuern.',
			'Resources are rooms, equipment, or capacities. Link them to services to control availability.' => 'Ressourcen sind z. B. Räume, Equipment oder Kapazitäten. Verknüpfe sie mit Leistungen, um die Verfügbarkeit zu steuern.',
			'How many concurrent bookings can this resource cover?' => 'Wie viele gleichzeitige Buchungen kann diese Ressource abdecken?',
			'No rooms found.' => 'Noch keine Zimmer vorhanden.',
			'No resources found.' => 'Noch keine Ressourcen vorhanden.',

			// Staff
			'Working hours saved.' => 'Arbeitszeiten gespeichert.',
			'An error occurred while saving.' => 'Beim Speichern ist ein Fehler aufgetreten.',
			'Exception created.' => 'Ausnahme angelegt.',
			'Could not create exception.' => 'Ausnahme konnte nicht angelegt werden.',
			'Exception deleted.' => 'Ausnahme gelöscht.',
			'Could not delete exception.' => 'Ausnahme konnte nicht gelöscht werden.',
			'Add staff member' => 'Mitarbeitende hinzufügen',
			'Edit working hours' => 'Arbeitszeiten bearbeiten',
			'Save working hours' => 'Arbeitszeiten speichern',
			'Exceptions' => 'Ausnahmen',
			'Off day' => 'Freier Tag',
			'Note' => 'Notiz',
			'No exceptions found.' => 'Noch keine Ausnahmen vorhanden.',
			'Create exception' => 'Ausnahme anlegen',
			'Yes, off' => 'Ja, frei',
			'Leave empty if off' => 'Leer lassen, wenn frei',
			'End time' => 'Endzeit',
			'No services defined yet.' => 'Noch keine Services angelegt.',
			'Create Your First Service' => 'Ersten Service erstellen',
			'Duration' => 'Dauer',
			'Weekday' => 'Wochentag',
			'Start time' => 'Startzeit',
			'Remove' => 'Entfernen',
			'Mon' => 'Mo',
			'Tue' => 'Di',
			'Wed' => 'Mi',
			'Thu' => 'Do',
			'Fri' => 'Fr',
			'Sat' => 'Sa',
			'Sun' => 'So',
			'Service saved.' => 'Service gespeichert.',
			'Resource saved.' => 'Ressource gespeichert.',
			'An error occurred.' => 'Ein Fehler ist aufgetreten.',
			'An error occurred while saving.' => 'Beim Speichern ist ein Fehler aufgetreten.',

			// Resources
			'How many simultaneous bookings can this resource handle?' => 'Wie viele gleichzeitige Buchungen kann diese Ressource abdecken?',
			'Update Resource' => 'Ressource aktualisieren',
			'Create Resource' => 'Ressource erstellen',
			'Manage %s' => '%s verwalten',
			'Rooms are the bookable units (e.g., Room 101, Room 102). Link them to room types to manage availability.' => 'Zimmer sind die buchbaren Einheiten (z.B. Zimmer 101, Zimmer 102). Verknüpfe sie mit Zimmertypen, um Verfügbarkeit zu steuern.',
			'Resources are rooms, equipment, or staff capacity. Link them to services to manage availability.' => 'Ressourcen sind Räume, Equipment oder Mitarbeiter-Kapazität. Verknüpfe sie mit Services, um Verfügbarkeit zu steuern.',
			'No resources found.' => 'Keine Ressourcen gefunden.',

			// Staff
			'Staff Members' => 'Mitarbeiter',
			'No staff users found.' => 'Keine Mitarbeiter gefunden.',
			'Edit hours' => 'Zeiten bearbeiten',
			'Edit Working Hours' => 'Arbeitszeiten bearbeiten',
			'Save Working Hours' => 'Arbeitszeiten speichern',
			'Working hours saved.' => 'Arbeitszeiten gespeichert.',
			'Exceptions' => 'Ausnahmen',
			'No exceptions found.' => 'Keine Ausnahmen gefunden.',
			'Off day' => 'Frei',
			'Create Exception' => 'Ausnahme erstellen',
			'Yes, staff is off' => 'Ja, Mitarbeiter ist frei',
			'Leave empty if off day' => 'Leer lassen, wenn frei',
			'Exception created.' => 'Ausnahme erstellt.',
			'Failed to create exception.' => 'Ausnahme konnte nicht erstellt werden.',
			'Exception deleted.' => 'Ausnahme gelöscht.',
			'Failed to delete exception.' => 'Ausnahme konnte nicht gelöscht werden.',

			// Settings page
			'Settings saved.' => 'Einstellungen gespeichert.',
			'Save Settings' => 'Einstellungen speichern',
			'General Settings' => 'Allgemeine Einstellungen',
			'Working Hours' => 'Arbeitszeiten',
			'Start:' => 'Start:',
			'End:' => 'Ende:',
			'Global working hours (0-23). Individual staff hours can override this.' => 'Globale Arbeitszeiten (0-23). Individuelle Mitarbeiterzeiten können diese überschreiben.',
			'Slot Size (minutes)' => 'Slot-Größe (Minuten)',
			'Controls the time grid used to compute available times.' => 'Bestimmt das Zeitraster, in dem verfügbare Zeiten berechnet werden.',
			'Base time slot interval for calendar generation.' => 'Basis-Zeitintervall für die Slot-Generierung.',
			'Timezone' => 'Zeitzone',
			'WordPress Default' => 'WordPress-Standard',
			'Default Booking Status' => 'Standard-Buchungsstatus',
			'Status assigned to new bookings.' => 'Status, der neuen Buchungen zugewiesen wird.',
			'Pending Blocks Availability' => 'Ausstehend blockiert Verfügbarkeit',
			'If enabled, pending bookings are treated as occupied.' => 'Wenn aktiv, werden ausstehende Buchungen wie belegt behandelt.',
			'Yes, pending bookings block the time slot' => 'Ja, ausstehende Buchungen blockieren den Zeitraum',
			'Useful to avoid double bookings before you confirm.' => 'Praktisch, um doppelte Buchungen zu vermeiden, bevor Sie bestätigen.',
			'Booking Template Mode' => 'Buchungsmodus (Template)',
			'Service Booking (Appointments)' => 'Service-Buchung (Termine)',
			'Hotel Booking (Check-in/Check-out)' => 'Hotel-Buchung (Check-in/Check-out)',
			'Controls whether services (appointments) or hotel date ranges are bookable.' => 'Steuert, ob Termine (Services) oder Datumsbereiche (Hotel) gebucht werden.',
			'Switch between appointment-based booking (services) and date-range booking (hotel/rooms).' => 'Wechseln Sie zwischen terminbasierten Buchungen (Services) und Datumsbereich-Buchungen (Hotel/Zimmer).',
			'Email Settings' => 'E-Mail-Einstellungen',
			'SMTP (optional)' => 'SMTP (optional)',
			'Enable SMTP' => 'SMTP aktivieren',
			'Send emails via SMTP instead of the server mail function' => 'E-Mails über SMTP senden statt über die Server-Mailfunktion',
			'By default this configures WordPress wp_mail() globally while enabled.' => 'Standardmäßig konfiguriert dies WordPress wp_mail() global, solange es aktiviert ist.',
			'Only apply SMTP to LazyBookings emails' => 'SMTP nur auf LazyBookings-E-Mails anwenden',
			'Enable this to avoid affecting other plugins that also send emails.' => 'Aktivieren Sie dies, um andere Plugins, die ebenfalls E-Mails senden, nicht zu beeinflussen.',
			'SMTP Host' => 'SMTP-Host',
			'Examples: smtp.gmail.com, smtp.hostinger.com, smtp.strato.de' => 'Beispiele: smtp.gmail.com, smtp.hostinger.com, smtp.strato.de',
			'SMTP Port' => 'SMTP-Port',
			'Common ports: 587 (TLS), 465 (SSL).' => 'Häufige Ports: 587 (TLS), 465 (SSL).',
			'Encryption' => 'Verschlüsselung',
			'Authentication' => 'Authentifizierung',
			'Use SMTP authentication' => 'SMTP-Authentifizierung verwenden',
			'SMTP Username' => 'SMTP-Benutzername',
			'Usually your full email address for Gmail/Hostinger/Strato.' => 'Meist Ihre vollständige E-Mail-Adresse (z. B. für Gmail/Hostinger/Strato).',
			'SMTP Password' => 'SMTP-Passwort',
			'A password is stored. Leave blank to keep the existing password.' => 'Ein Passwort ist gespeichert. Lassen Sie das Feld leer, um das bestehende Passwort zu behalten.',
			'For Gmail you must use an App Password (not your normal login password).' => 'Für Gmail müssen Sie ein App-Passwort verwenden (nicht Ihr normales Login-Passwort).',
			'None' => 'Keine',
			'Sender Info' => 'Absender',
			'From Name:' => 'Von-Name:',
			'From Email:' => 'Von-E-Mail:',
			'Reply-To:' => 'Antwort-an:',
			'SMTP:' => 'SMTP:',
			'SMTP server:' => 'SMTP-Server:',
			'SMTP scope:' => 'SMTP-Geltungsbereich:',
			'Admin Notifications' => 'Admin-Benachrichtigungen',
			'Send email to admin on new booking' => 'E-Mail an Admin bei neuer Buchung senden',
			'Subject:' => 'Betreff:',
			'Body:' => 'Inhalt:',
			'You can use tags that will be replaced automatically.' => 'Sie können Platzhalter verwenden, die automatisch ersetzt werden.',
			'Available tags: {customer_name}, {service_name}, {start_time}, {end_time}, {status}' => 'Verfügbare Platzhalter: {customer_name}, {service_name}, {start_time}, {end_time}, {status}',
			'Customer Notifications' => 'Kunden-Benachrichtigungen',
			'Send confirmation email to customer' => 'Bestätigungs-E-Mail an Kunde senden',
			'Logging' => 'Logging',
			'Enable Logging' => 'Logging aktivieren',
			'Writes events/errors to a log file for diagnostics.' => 'Schreibt Ereignisse/Fehler in eine Log-Datei zur Diagnose.',
			'Log errors and events to file' => 'Fehler und Ereignisse in Datei protokollieren',
			'Log Level' => 'Log-Level',
			'Error' => 'Fehler',
			'Warning' => 'Warnung',
			'Info' => 'Info',
			'Debug' => 'Debug',
			'Enable this only when needed (e.g., for debugging).' => 'Aktiviere dies nur bei Bedarf (z. B. zur Fehlersuche).',
			'Controls how verbose logging is.' => 'Steuert, wie ausführlich protokolliert wird.',
			'For normal use, "Error" is usually enough. Use "Debug" only temporarily.' => 'Für normale Nutzung reicht meist „Fehler“. „Debug“ nur kurzfristig verwenden.',
			'Test Email Configuration' => 'Test-E-Mail-Konfiguration',
			'Send test email to:' => 'Test-E-Mail senden an:',
			'Send Test Email' => 'Test-E-Mail senden',
			'Test email sent successfully to ' => 'Test-E-Mail erfolgreich gesendet an ',
			'Failed to send test email.' => 'Test-E-Mail konnte nicht gesendet werden.',
			'Invalid email address.' => 'Ungültige E-Mail-Adresse.',
			'LazyBookings Test Email' => 'LazyBookings Test-E-Mail',
			'This is a test email from LazyBookings plugin.' => 'Dies ist eine Test-E-Mail vom LazyBookings-Plugin.',
			'From:' => 'Von:',
			'Sent at:' => 'Gesendet am:',

			// Design page
			'Design saved.' => 'Design gespeichert.',
			'Design Settings' => 'Design-Einstellungen',
			'Customize the appearance of your booking wizard.' => 'Passe das Erscheinungsbild des Buchungsassistenten an.',
			'Save Design' => 'Design speichern',
			'Colors' => 'Farben',
			'Background Color' => 'Hintergrundfarbe',
			'Main background color for booking form' => 'Haupt-Hintergrundfarbe für das Buchungsformular',
			'Text Color' => 'Textfarbe',
			'Main text color' => 'Haupt-Textfarbe',
			'Accent Color' => 'Akzentfarbe',
			'Small highlights (required *), and the gradient end color when Gradient is enabled.' => 'Kleine Hervorhebungen (Pflichtfeld *), und Endfarbe des Verlaufs, wenn Verlauf aktiv ist.',
			'Border Color' => 'Rahmenfarbe',
			'Color for input and card borders' => 'Farbe für Eingabe- und Karten-Rahmen',
			'Panel Background' => 'Panel-Hintergrund',
			'Background for inner panels (fieldsets/cards)' => 'Hintergrund für innere Panels (Fieldsets/Karten)',
			'Buttons' => 'Buttons',
			'Primary Color' => 'Primärfarbe',
			'Primary button background.' => 'Hintergrund des primären Buttons.',
			'Primary Hover Color' => 'Primärfarbe (Hover)',
			'Primary button hover background and border.' => 'Hover-Hintergrund und Rahmen des primären Buttons.',
			'Secondary Color' => 'Sekundärfarbe',
			'Secondary button border and text (outline).' => 'Rahmen und Text des sekundären Buttons (Outline).',
			'Secondary Hover Color' => 'Sekundärfarbe (Hover)',
			'Secondary button hover fill background and border.' => 'Hover-Füllung und Rahmen des sekundären Buttons.',
			'Auto Button Text Color' => 'Auto Button-Textfarbe',
			'Automatically choose readable text color for the primary button (black/white).' => 'Automatisch gut lesbare Textfarbe für den primären Button wählen (schwarz/weiß).',
			'Manual Button Text Color' => 'Manuelle Button-Textfarbe',
			'Used only if Auto Button Text Color is disabled.' => 'Nur verwendet, wenn Auto Button-Textfarbe deaktiviert ist.',
			'Spacing & Shapes' => 'Abstände & Formen',
			'Border Radius (px)' => 'Eckenradius (px)',
			'Roundness of buttons and inputs' => 'Rundung von Buttons und Eingabefeldern',
			'Border Width (px)' => 'Rahmenbreite (px)',
			'Thickness of input and card borders' => 'Stärke der Rahmen von Eingaben und Karten',
			'Shadow & Effects' => 'Schatten & Effekte',
			'Control which elements should have shadows. Uncheck all for a flat design.' => 'Steuere, welche Elemente Schatten haben sollen. Alle deaktivieren für ein flaches Design.',
			'Container Shadow' => 'Container-Schatten',
			'Add shadow to the main booking form container' => 'Schatten für den Haupt-Container des Buchungsformulars hinzufügen',
			'Button Shadow' => 'Button-Schatten',
			'Add shadow to buttons (submit, primary, etc.)' => 'Schatten für Buttons hinzufügen (Submit/Primary etc.)',
			'Input Shadow' => 'Input-Schatten',
			'Add shadow to input fields (text, select, etc.)' => 'Schatten für Eingabefelder hinzufügen (Text, Select etc.)',
			'Card Shadow' => 'Karten-Schatten',
			'Add shadow to service/room cards' => 'Schatten für Service-/Zimmer-Karten hinzufügen',
			'Shadow Blur (px)' => 'Schatten-Weichzeichnung (px)',
			'Softness of the shadow effect' => 'Weichheit des Schatteneffekts',
			'Shadow Spread (px)' => 'Schatten-Ausbreitung (px)',
			'How far the shadow spreads' => 'Wie weit sich der Schatten ausbreitet',
			'Enable Gradient Background' => 'Verlaufshintergrund aktivieren',
			'Use gradient from Primary to Accent color' => 'Verlauf von Primär- zu Akzentfarbe verwenden',
			'Animation Duration (ms)' => 'Animationsdauer (ms)',
			'Speed of hover animations' => 'Geschwindigkeit der Hover-Animationen',
			'Enable Animations' => 'Animationen aktivieren',
			'Disable to remove all hover and focus transitions.' => 'Deaktivieren, um alle Hover- und Fokus-Übergänge zu entfernen.',
			'Custom CSS' => 'Custom CSS',
			'Custom CSS Rules' => 'Custom CSS Regeln',
			'Add custom CSS for advanced styling.' => 'Füge Custom CSS für erweiterte Gestaltung hinzu.',
			'Live Preview' => 'Live-Vorschau',
			'Booking Wizard' => 'Buchungsassistent',
			'This is a live preview of your design settings.' => 'Dies ist eine Live-Vorschau deiner Design-Einstellungen.',
			'Service Card' => 'Service-Karte',
			'Example service description.' => 'Beispiel-Servicebeschreibung.',
			'Changes update automatically.' => 'Änderungen werden automatisch aktualisiert.',

			// Diagnostics
			'Migrations executed successfully.' => 'Migrationen erfolgreich ausgeführt.',
			'System Information' => 'Systeminformationen',
			'Database Statistics' => 'Datenbank-Statistiken',
			'Table Status' => 'Tabellenstatus',
			'WordPress Version' => 'WordPress-Version',
			'PHP Version' => 'PHP-Version',
			'Database Prefix' => 'Datenbank-Präfix',
			'Template Mode' => 'Template-Modus',
			'DB Version' => 'DB-Version',
			'Plugin Version' => 'Plugin-Version',
			'Database Maintenance' => 'Datenbank-Wartung',
			'Run Migrations' => 'Migrationen ausführen',
			'Re-runs database migrations. Safe to execute multiple times (uses dbDelta).' => 'Führt Datenbank-Migrationen erneut aus. Kann sicher mehrfach ausgeführt werden (nutzt dbDelta).',
			'Run Doctor' => 'Doctor ausführen',
			'Run system diagnostics (read-only).' => 'Systemdiagnose ausführen (nur lesend).',
			'Table Name' => 'Tabellenname',
			'Rows' => 'Zeilen',
			'✓ Exists' => '✓ Vorhanden',
			'✗ Missing' => '✗ Fehlt',
			'N/A' => 'k.A.',
			'System Diagnostics Results' => 'Systemdiagnose-Ergebnisse',
			'unknown' => 'unbekannt',
			'Plugin Version:' => 'Plugin-Version:',
			'DB Version:' => 'DB-Version:',
			'⚠ DB version is behind plugin version.' => '⚠ DB-Version ist hinter der Plugin-Version.',
			'Consider running migrations.' => 'Bitte Migrationen ausführen.',
			'✓ DB version matches plugin version' => '✓ DB-Version entspricht der Plugin-Version',
			'Template Mode:' => 'Template-Modus:',
			'MySQL Named Locks:' => 'MySQL Named Locks:',
			'Supported ✓' => 'Unterstützt ✓',
			'Not supported (race condition protection disabled)' => 'Nicht unterstützt (Schutz vor Race Conditions deaktiviert)',
			'Email From:' => 'E-Mail von:',
			'Logging:' => 'Logging:',
			'Enabled' => 'Aktiviert',
			'Disabled' => 'Deaktiviert',
			'Enabled (%s)' => 'Aktiviert (%s)',
			'Dev Tools:' => 'Dev-Tools:',
			'Last Migration:' => 'Letzte Migration:',

			// Privacy
			'Retention settings saved.' => 'Aufbewahrungseinstellungen gespeichert.',
			'Data Retention Settings' => 'Datenaufbewahrung',
			'Delete canceled appointments after (days)' => 'Stornierte Termine löschen nach (Tagen)',
			'Delete cancelled appointments after (days)' => 'Stornierte Termine löschen nach (Tagen)',
			'Set to 0 to disable automatic deletion. Appointments with status "canceled" older than this will be permanently deleted.' => 'Auf 0 setzen, um automatisches Löschen zu deaktivieren. Termine mit Status "canceled" älter als dieser Wert werden dauerhaft gelöscht.',
			'Set to 0 to disable automatic deletion. Appointments with status "cancelled" older than this will be permanently deleted.' => 'Auf 0 setzen, um automatisches Löschen zu deaktivieren. Termine mit Status "cancelled" älter als dieser Wert werden dauerhaft gelöscht.',
			'Anonymize customer data after (days)' => 'Kundendaten anonymisieren nach (Tagen)',
			'Set to 0 to disable automatic anonymization. Appointments older than this will have customer data anonymized (email, name, phone replaced).' => 'Auf 0 setzen, um automatische Anonymisierung zu deaktivieren. Termine älter als dieser Wert werden anonymisiert (E-Mail, Name, Telefon ersetzt).',
			'Save Retention Settings' => 'Aufbewahrung speichern',
			'Manual Anonymization' => 'Manuelle Anonymisierung',
			'Customer Email' => 'Kunden-E-Mail',
			'Anonymizes customer data (email, first name, last name, phone) by replacing with anonymized values. This action cannot be undone.' => 'Anonymisiert Kundendaten (E-Mail, Vorname, Nachname, Telefon) durch Ersetzen mit anonymisierten Werten. Diese Aktion kann nicht rückgängig gemacht werden.',
			'Are you sure you want to anonymize this customer? This cannot be undone.' => 'Möchten Sie diesen Kunden wirklich anonymisieren? Dies kann nicht rückgängig gemacht werden.',
			'Anonymize Customer' => 'Kunden anonymisieren',
			'Customer data anonymized successfully.' => 'Kundendaten erfolgreich anonymisiert.',
			'Customer not found or anonymization failed.' => 'Kunde nicht gefunden oder Anonymisierung fehlgeschlagen.',
			'Run Retention Cleanup' => 'Datenbereinigung ausführen',
			'Retention policies are automatically applied via scheduled tasks. You can manually trigger cleanup here:' => 'Aufbewahrungsregeln werden automatisch über geplante Aufgaben angewendet. Sie können die Bereinigung hier manuell auslösen:',
			'Run retention cleanup now?' => 'Datenbereinigung jetzt ausführen?',
			'Run Cleanup Now' => 'Bereinigung jetzt ausführen',

			'You do not have permission to view this page.' => 'Sie haben keine Berechtigung, diese Seite anzusehen.',
			'First name' => 'Vorname',
			'Last name' => 'Nachname',
			'Save Customer' => 'Kunde speichern',
			'Delete Appointment' => 'Termin löschen',
			'Open Appointments List' => 'Terminliste öffnen',
			'Delete this appointment?' => 'Diesen Termin löschen?',
			'Customer saved.' => 'Kunde gespeichert.',
			'Could not load appointment details.' => 'Termindetails konnten nicht geladen werden.',
			'Could not update appointment.' => 'Termin konnte nicht aktualisiert werden.',
			'Could not update status.' => 'Status konnte nicht aktualisiert werden.',
			'Could not delete appointment.' => 'Termin konnte nicht gelöscht werden.',
			'Could not save customer.' => 'Kunde konnte nicht gespeichert werden.',
			'This time slot conflicts with an existing booking.' => 'Dieser Zeitraum überschneidet sich mit einer bestehenden Buchung.',
			'Appointment #%d' => 'Termin #%d',

			// AI / Outbox / Automations
			'AI & Automations' => 'KI & Automationen',
			'Outbox' => 'Outbox',
			'Outbox Item' => 'Outbox-Eintrag',
			'Back to Outbox' => 'Zurück zur Outbox',
			'Draft Center' => 'Entwürfe',
			'Draft created in Outbox.' => 'Entwurf in der Outbox erstellt.',
			'Report draft created in Outbox.' => 'Report-Entwurf in der Outbox erstellt.',
			'Outbox not available.' => 'Outbox ist nicht verfügbar.',
			'AI Outbox not available.' => 'KI-Outbox ist nicht verfügbar.',
			'Smart Room Assistant' => 'Smarter Zimmerassistent',
			'Room Assistant' => 'Zimmerassistent',
			'Automations' => 'Automationen',
			'Reply Templates' => 'Antwortvorlagen',
			'Propose via Outbox' => 'Über Outbox vorschlagen',
			'Room proposal sent to Outbox.' => 'Zimmer-Vorschlag an die Outbox gesendet.',

			'ID' => 'ID',

			// Frontend/Public booking wizard
			'Step %s of %s' => 'Schritt %s von %s',
			'night' => 'Nacht',
			'nights' => 'Nächte',
			'Any' => 'Beliebig',
			'Room #' => 'Zimmer #',
			'Resource #' => 'Ressource #',
			'Optional: select a room.' => 'Optional: Wählen Sie ein Zimmer aus.',
			'Optional: select a resource.' => 'Optional: Wählen Sie eine Ressource aus.',
			'Availability could not be loaded. Please try again.' => 'Verfügbarkeit konnte nicht geladen werden. Bitte versuchen Sie es erneut.',
			'Resources could not be loaded. Please try again.' => 'Ressourcen konnten nicht geladen werden. Bitte versuchen Sie es erneut.',

			// Design & AI placeholders
			'.ltlb-booking .service-card { /* your styles */ }' => '.ltlb-booking .service-card { /* Ihre Styles */ }',
			'mail@example.com, +1-555-0000' => 'mail@beispiel.de, +49-123-456789',
		];

		return $dict;
	}

	/**
	 * Parse .po file with simple text processing
	 * @param string $po_file Path to .po file
	 * @return array Dictionary of translations
	 */
	private static function parse_po_file( string $po_file ): array {
		$content = @file_get_contents( $po_file );
		if ( ! is_string( $content ) || empty( $content ) ) {
			return [];
		}

		// Fix encoding issues - convert from UTF-8 to UTF-8 (fixes double-encoding)
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$content = mb_convert_encoding( (string) $content, 'UTF-8', 'UTF-8' );
		}

		// Fix common broken UTF-8 sequences
		$content = str_replace( 'Ã¤', 'ä', (string) $content );
		$content = str_replace( 'Ã¶', 'ö', (string) $content );
		$content = str_replace( 'Ã¼', 'ü', (string) $content );
		$content = str_replace( 'Ã„', 'Ä', (string) $content );
		$content = str_replace( 'Ã–', 'Ö', (string) $content );
		$content = str_replace( 'Ãœ', 'Ü', (string) $content );
		$content = str_replace( 'ÃŸ', 'ß', (string) $content );
		$content = str_replace( 'ÃƒÂ¤', 'ä', (string) $content );
		$content = str_replace( 'ÃƒÂ¶', 'ö', (string) $content );
		$content = str_replace( 'ÃƒÂ¼', 'ü', (string) $content );
		$content = str_replace( 'ÃƒÂ„', 'Ä', (string) $content );
		$content = str_replace( 'ÃƒÂ–', 'Ö', (string) $content );
		$content = str_replace( 'ÃƒÅ"', 'Ü', (string) $content );
		$content = str_replace( 'ÃƒÅ¸', 'ß', (string) $content );
		$content = str_replace( 'Ã©', 'é', (string) $content );
		$content = str_replace( 'Ã¨', 'è', (string) $content );
		$content = str_replace( 'Ã ', 'à', (string) $content );

		$translations = [];
		$lines = explode( "\n", (string) $content );
		$msgid = '';
		$msgstr = '';
		$in_msgid = false;
		$in_msgstr = false;

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );

			// Start of msgid
			if ( strpos( (string) $line, 'msgid "' ) === 0 ) {
				// Save previous entry
				if ( $msgid !== '' && $msgstr !== '' ) {
					$translations[ $msgid ] = $msgstr;
				}
				$msgid = substr( (string) $line, 7, -1 );
				$msgstr = '';
				$in_msgid = true;
				$in_msgstr = false;
			}
			// Start of msgstr
			elseif ( strpos( (string) $line, 'msgstr "' ) === 0 ) {
				$msgstr = substr( (string) $line, 8, -1 );
				$in_msgid = false;
				$in_msgstr = true;
			}
			// Continuation line
			elseif ( strpos( (string) $line, '"' ) === 0 && strlen( (string) $line ) > 1 ) {
				$value = substr( (string) $line, 1, -1 );
				if ( $in_msgid ) {
					$msgid .= $value;
				} elseif ( $in_msgstr ) {
					$msgstr .= $value;
				}
			}
			// Empty line or comment - reset
			elseif ( $line === '' || strpos( (string) $line, '#' ) === 0 ) {
				if ( $msgid !== '' && $msgstr !== '' ) {
					$translations[ $msgid ] = $msgstr;
				}
				$msgid = '';
				$msgstr = '';
				$in_msgid = false;
				$in_msgstr = false;
			}
		}

		// Save last entry
		if ( $msgid !== '' && $msgstr !== '' ) {
			$translations[ $msgid ] = $msgstr;
		}

		// Remove escaped characters
		foreach ( $translations as $key => $value ) {
			$translations[ $key ] = stripcslashes( $value );
		}

		return $translations;
	}
}
