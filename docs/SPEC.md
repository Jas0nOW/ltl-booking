# LazyBookings – Projekt-Spezifikation (IST-Stand) & AI-Briefing (v0.4.4)

Letztes Update: 2025-12-13

Diese Datei ist die **Source of Truth** für den aktuellen Funktionsumfang von **LazyBookings**.
Ziel: Du kannst diese Datei einer externen KI geben, ohne Code zu teilen, und sie versteht zuverlässig:
- Was das Plugin bereits kann (inkl. Grenzen)
- Wie Datenmodell, Admin-UI, Frontend und API zusammenhängen
- Was als nächstes noch fehlt / typische Erweiterungen

---

## 0) Projektprofil

- **Produkt:** LazyBookings (Amelia-Alternative)
- **WordPress Plugin Slug/Text Domain:** `ltl-bookings`
- **Aktuelle Plugin-Version:** 0.4.4
- **DB-Version (Option `ltlb_db_version`):** 0.4.4
- **Minimum:** WordPress 6.0+, PHP 7.4+ (empfohlen 8.x)

---

## 1) Kern-Idee & Zielgruppe

LazyBookings ist ein Buchungs-Plugin für:
- **Service/Kurs-Modus**: Zeit-Slots an einem Datum (z.B. Yoga-Kurse, Termine)
- **Hotel/Room-Modus**: ist im Projekt als Konzept/Engine vorhanden; das öffentliche REST-Interface ist aktuell primär für Service/Kurs-Slots ausgelegt

Fokus liegt auf:
- Performance (Custom Tables statt `wp_posts`)
- “Agency-grade” UX im Frontend (Wizard als modernes Stepper-Fenster)
- Flexible Verfügbarkeit (global, staff-basiert, service-basiert, fixe Kurszeiten)

---

## 2) Begriffe (Glossar)

- **Service** = buchbares Angebot (Kurs/Termin oder Room Type)
- **Resource** = buchbare Ressource (Raum/Studio/Equipment/Hotelzimmer) mit Kapazität
- **Appointment** = Buchung (Zeitspanne) eines Services, belegt Ressourcen
- **Customer** = Kunde (wird bei Buchung automatisch angelegt/aktualisiert)
- **Staff** = WP-User, optional einem Service zugeordnet; kann eigene Arbeitszeiten haben

---

## 3) Was ist umgesetzt? (Feature-Matrix)

### 3.1 Admin (WP-Backend)

✅ Admin-Menü **LazyBookings** mit Seiten:
- Dashboard
- Services (CRUD)
- Customers (Liste)
- Appointments (Liste, Filter, CSV Export)
- Calendar (Kalenderansicht mit Drag & Drop)
- Staff (Arbeitszeiten + Ausnahmen)
- Resources (CRUD)
- Settings (u.a. Working Hours, Template Mode, Mail, Logging)
- Design (Design Tokens / CSS Variablen)
- Diagnostics (DB Status, Migrationen)
- Privacy (Basisfunktionen)

✅ Einheitlicher Admin-Header / Navigation ("Plugin-like" UX)
- Alle LazyBookings Admin-Seiten sind in einen gemeinsamen Header mit Tabs integriert
- Styling nutzt die konfigurierten Design-Variablen (scoped auf `.ltlb-admin`)

✅ Kalender-Management (Admin)
- Kalenderansicht auf der Seite **Calendar** (FullCalendar)
- Termine als Events (Titel = Service + Kunde)
- Drag & Drop / Resize: Terminzeit wird serverseitig gespeichert
- Klick auf Event öffnet Detail-Panel mit:
  - Status ändern (pending/confirmed/cancelled)
  - Kundendaten bearbeiten (Name/Email/Phone/Notes)
  - Termin löschen

✅ Per-User Sprachauswahl (Admin)
- Sprachauswahl in der Admin-Header-Leiste (English/Deutsch)
- Speicherung pro Benutzer (user meta), wirkt nur auf LazyBookings Admin-Seiten

✅ Services: pro Service konfigurierbar
- Dauer (Minuten)
- Preis/Currency
- Optional: “Group/Capacity”-Grundlage (`is_group`, `max_seats_per_booking`)
- Optional: **Verfügbarkeit pro Service** (Details siehe Kapitel 5)

✅ Resources:
- Kapazität pro Resource (für Kursplätze / Hotelgäste)
- Service ↔ Resource Mapping (wenn leer: Service kann alle Resources nutzen)

✅ Staff:
- Wochen-Arbeitszeiten pro User (Wochentag, Start/End, aktiv)
- Exceptions pro Datum (off day oder Sonder-Zeiten)

---

### 3.2 Frontend (UX)

✅ Shortcodes:
- `[lazy_book]` → Standard Wizard
- `[lazy_book_calendar]` → startet im Kalender-Schritt
- `[lazy_book service="123" mode="calendar"]` → vorbefüllter Service + Kalender-Start

✅ Wizard als Stepper-„Fenster“:
- einzelne Schritte als Panels, mit Next/Back
- “Smart middle”: Auto-Advance bei klaren Entscheidungen (z.B. Datum/Slot), aber Back immer möglich
- animierte Übergänge + “smart resizing” (Höhe passt sich an Step-Inhalt an)

✅ Slot-Loading (Service/Kurs-Modus):
- Datum wählen → Slots werden via REST geladen
- Slot wählen → (optional) Ressourcen/Details werden via REST geladen

---

### 3.3 Design System (Frontend + Admin Preview)

✅ Design Settings in `lazy_design`, als **CSS Variablen scoped** auf `.ltlb-booking` (nicht global).
Enthält u.a.:
- Primary/Secondary getrennt (inkl. Hover)
- Border Farbe/Breite/Radius
- Schatten getrennt für Container/Button/Input/Card
- Animationen ein/aus + Dauer
- Optional Gradient
- Button-Text-Farbe: manuell oder Auto-Kontrast

---

### 3.4 REST API (öffentliche Read-Endpunkte)

Namespace: `/wp-json/ltlb/v1`

✅ `GET /availability?service_id=...&date=YYYY-MM-DD`
- Default: gibt ein Objekt zurück `{ "slots": [...] }`
- Mit `slots=1`: gibt direkt die Slot-Liste zurück
- Optional: `slot_step` (Minuten) für Slot-Raster (Default 15)

✅ `GET /time-slots?service_id=...&date=YYYY-MM-DD`
- gibt Slot-Liste zurück (derzeit ohne `slot_step` Parameter; intern Default 15)

✅ `GET /slot-resources?service_id=...&start=YYYY-MM-DD HH:MM:SS`
- liefert Ressourcen-Auslastung für einen konkreten Startzeitpunkt

Hinweis: `start` akzeptiert auch ISO 8601 (z.B. `2025-12-13T09:00:00+01:00`).

⚠️ Es gibt aktuell **keinen separaten öffentlichen Hotel-Availability Endpoint** im REST.

Optional (disabled by default): Lightweight Rate-Limiting für öffentliche Read-Endpunkte via `lazy_settings.rate_limit_enabled`.

---

### 3.5 REST API (Admin, authentifiziert)

Namespace: `/wp-json/ltlb/v1`

Diese Endpunkte sind **nur für Admin-Nutzer** gedacht und benötigen Auth (WP Admin Session + REST Nonce `wp_rest`).

✅ Kalender-Events laden
- `GET /admin/calendar/events?start=...&end=...`

✅ Termin Details laden
- `GET /admin/appointments/{id}`

✅ Termin verschieben/resize (Drag & Drop)
- `POST /admin/appointments/{id}/move` mit `start`, `end`

✅ Termin Status ändern
- `POST /admin/appointments/{id}/status` mit `status` (pending/confirmed/cancelled)

✅ Termin löschen
- `DELETE /admin/appointments/{id}`

✅ Kunde aktualisieren
- `POST /admin/customers/{id}` mit Feldern `email`, `first_name`, `last_name`, `phone`, `notes`

---

## 4) Datenmodell (DB – Custom Tables)

Alle Tabellen haben Prefix: `$wpdb->prefix . 'lazy_' . name`.

### 4.1 Tabellen (IST)

- `lazy_services`
- `lazy_customers`
- `lazy_appointments`
- `lazy_resources`
- `lazy_service_resources` (junction)
- `lazy_appointment_resources` (junction)
- `lazy_staff_hours`
- `lazy_staff_exceptions`

Für exakte Spalten siehe `docs/DB_SCHEMA.md`.

---

## 5) Verfügbarkeit / Buchungsregeln (entscheidend)

Die Slot-Berechnung (Service/Kurs-Modus) berücksichtigt:

### 5.1 Globales Zeitfenster (Settings)
- `lazy_settings.working_hours_start` (Stunde, Default 9)
- `lazy_settings.working_hours_end` (Stunde, Default 17)

### 5.2 Staff-Zeiten (wenn Service einem Staff-User zugeordnet ist)
- Weekly Hours (aktiv) überschreiben das globale Zeitfenster für den Tag
- Exceptions können:
  - “Off day” setzen (keine Slots)
  - oder Sonderzeiten definieren (Start/End)

### 5.3 Service-spezifische Limits (neu)

Service hat:
- `available_weekdays` (0=Sun..6=Sat) – optional
- `available_start_time` / `available_end_time` – optional
- `availability_mode`:
  - `window` (Default): Slots sind innerhalb des effektiven Fensters erlaubt
  - `fixed`: Slots sind **nur** exakt die definierten Wochenzeiten
- `fixed_weekly_slots`: JSON Liste wie `[{"weekday":5,"time":"18:00"}, ...]`

**Wichtig:**
- Effektives Zeitfenster = Schnittmenge aus Global + Staff (falls gesetzt) + Service-Window (falls gesetzt)
- Im `fixed`-Modus werden nur Zeiten berücksichtigt, die innerhalb dieses effektiven Fensters liegen

### 5.4 Bestehende Buchungen / Blocking
- Slots werden pro Resource gegen vorhandene Appointments geprüft
- Standard: nur `confirmed` blockt; optional kann `pending` blocken (Setting)

---

## 6) Security & Privacy (IST)

- Admin-Formulare sind nonce-geschützt
- REST Read-Endpunkte sind aktuell public (keine Auth), da fürs Frontend benötigt
- Logging ist privacy-safe (PII wird reduziert/gehäschte Ausgabe), standardmäßig nicht aggressiv
- Privacy/GDPR Seite ist vorhanden inkl. Retention-Settings (Delete cancelled / Anonymize), scheduled Cleanup (Cron) und manuellen Tools (Cleanup now + Anonymize by email)

---

## 7) Bekannte Grenzen / Was fehlt (Roadmap Hinweise)

Nicht (voll) umgesetzt:
- Public REST CRUD für Services/Customers/Appointments
- Payments (Stripe/PayPal)
- Wiederkehrende Kurse/Recurrence Rules (iCal/ICS)
- Warteliste / Coupons / Rabatte
- Vollständige Hotel REST API (Check-in/out Availability)
- Vollständige “Seats” UX (Mehrere Plätze pro Buchung) ist nur teilweise vorbereitet

---

## 8) Wie eine externe KI “Gaps” findet

Wenn du einer KI diese SPEC gibst, soll sie insbesondere prüfen:
- Gibt es für jede UI-Funktion passende Validierung + Sanitizing?
- Sind alle Tabellen/Spalten konsistent dokumentiert (DB_SCHEMA) und migriert?
- Sind REST Endpunkte klar und stabil dokumentiert (API.md)?
- Welche Features sind nur “vorbereitet”, aber nicht end-to-end nutzbar?

---

## 9) Referenz-Dokumente

- `docs/API.md` – Endpunkte + Payloads
- `docs/DB_SCHEMA.md` – Tabellen & Spalten
- `docs/QA_CHECKLIST.md` – manuelle Tests
- `docs/DECISIONS.md` – Architektur-/Produkt-Entscheidungen (historisch)