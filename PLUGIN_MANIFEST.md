# PLUGIN_MANIFEST.md

## 1. 🌟 Allgemeine Informationen

### Plugin-Identität
* **Name:** LazyBookings (Amelia Clone)
* **Slug:** `lazy-bookings`
* **Version:** 0.1.0
* **Autor:** AI Developer & Lead User

### Kurzbeschreibung & Zweck
LazyBook ist eine High-End-Lösung für Termin- und Ressourcenmanagement in WordPress, konzipiert als vollständiger Ersatz für das Plugin "Amelia". Es bietet eine moderne React-Single-Page-Application (SPA) für das Frontend (Buchungswizard) und das Backend (Admin-Dashboard). Der Zweck ist es, dienstleistungsbasierten Unternehmen (Yoga-Studios, Hotels, Beratern) eine mächtige, provisionsfreie Buchungsplattform zu bieten, die komplexe Szenarien wie Kursbuchungen, Zimmerverwaltung, Mitarbeiter-Logins und Finanz-Splitting (Admin/Mitarbeiter) beherrscht.

### Zielgruppe & Anwendungsbereich
* **Administratoren:** Volle Kontrolle über das System, Design, Finanzen und globale Einstellungen.
* **Mitarbeiter (Trainer/Personal):** Eingeschränkter Zugriff auf eigene Termine, bereinigte Finanzen (Netto-Umsatz ohne Admin-Fee) und KI-Tagesbriefings.
* **Endkunden:** Intuitive Buchung von Dienstleistungen oder Events über einen Schritt-für-Schritt Wizard.
* **Kontext:** Wird als WordPress-Plugin installiert und via Shortcode auf Seiten eingebunden.

## 2. ⚙️ Technische Details & Funktionsweise

### Kernfunktionen (Bullet Points)
* **Dual-Template Engine:** Umschaltbare Geschäftslogik zwischen "Yoga/Service" (Slot-basiert, 60min Taktung) und "Hotel" (Nacht-basiert, Check-in/out Logik).
* **Role-Based Access Control (RBAC):** Granulare Rechteverwaltung. Mitarbeiter sehen strikt getrennte Finanzdaten (Schutz vor Einsicht in Plattform-Gebühren).
* **Intelligente KI-Tools (Google Gemini):**
    * *Room Tetris:* Optimiert Hotel-Belegungspläne zur Vermeidung von Leerstand.
    * *Smart Emails:* Generiert kontextbezogene E-Mail-Entwürfe (Storno, Reminder, Bestätigung).
    * *Briefings:* Erstellt Tageszusammenfassungen für Mitarbeiter.
* **Finanz-Engine:** Automatische Rechnungserstellung, PDF-Druck-Simulation, Status-Tracking (Offen/Bezahlt/Überfällig) und Provisionsberechnung.
* **Ressourcen-Management:** Verhindert Doppelbuchungen von physischen Räumen oder Geräten, unabhängig von der Mitarbeiterverfügbarkeit.
* **Payment Gateways:** Modulare Unterstützung für Stripe, PayPal, Klarna, Barzahlung und Firmenrechnung (B2B mit USt-ID Validierung).
* **Widget-Modus:** Ermöglicht den Start des Buchungsprozesses direkt im Kalender für spezifische Services.

### WordPress-Integrationen
Das Plugin generiert seinen eigenen PHP-Integrationscode

* **zu nutztende Hooks/Aktionen:**
    * `admin_menu`: Erstellt den Hauptmenüpunkt im WP-Dashboard für die React-Admin-App.
    * `init`: Registriert Shortcodes und initialisiert die Datenbank-Tabellen bei Aktivierung.
    * `rest_api_init`: Registriert REST-Endpoints (`/wp-json/lazy/v1/`) für CRUD-Operationen der React-App.
    * `wp_enqueue_scripts`: Lädt das kompilierte React-Bundle und CSS.

* **Admin-Seiten/Menüs:**
    * **Titel:** LazyBookings
    * **Slug:** `lazy_bookings`
    * **Platzierung:** Top-Level Menüpunkt.

### Datenhaltung
Das Plugin nutzt eine Custom-Table-Architektur für maximale Performance bei großen Datenmengen (vermeidet `wp_postmeta` Bloat).

* **Datenbank-Tabellen (Custom SQL):**
    * `wp_lazy_services`: Stammdaten für Kurse/Zimmer.
    * `wp_lazy_appointments`: Die zentralen Buchungsdaten.
    * `wp_lazy_customers`: Kundenprofile und Historie.
    * `wp_lazy_invoices`: Finanzdaten und Rechnungspositionen.
    * `wp_lazy_events`: Einmalige Veranstaltungen mit festem Start/Ende.
    * `wp_lazy_resources`: Physische Assets (Räume).
    * `wp_lazy_users`: System-Benutzer und Rechte-Matrix.

* **Optionen/Settings:**
    * `lazy_settings`: Speichert globale Konfiguration (SMTP, Payment Keys, Geschäftsdaten).
    * `lazy_design`: Speichert das Branding (Farben, Fonts).

### Front-End Interaktion
* **Enqueues:**
    * `lazy-book-style`: Tailwind CSS (kompiliert).
    * `lazy-book-script`: React App Bundle.
    * *Bedingung:* Wird nur geladen, wenn der Shortcode auf der Seite erkannt wird oder im Admin-Bereich.

* **Shortcodes:**
    * `[lazy_book]`: Lädt den Standard-Buchungswizard.
    * `[lazy_book service="123" mode="calendar"]`: Lädt das Widget für Service ID 123 und startet direkt im Kalender-Schritt.