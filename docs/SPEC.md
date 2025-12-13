# LazyBookings – Master-Spezifikation & Agent-Prompt (v0.4.0)

Diese Datei ist die **Source of Truth** für das WordPress-Plugin **LazyBookings** (Amelia-Alternative).
Nutze sie 1:1 als Prompt/Briefing für Copilot Agent, Gemini (Continue/Aider) oder andere Coding-Agents.

---

## 0) Arbeitsvertrag für die Coding-KI (bitte strikt befolgen)

1. **Arbeite in kleinen, überprüfbaren Schritten** (jede Stufe: implementieren → aktivieren → testen → commit).
2. **Keine “magischen” Annahmen**: Wenn etwas unklar ist, wähle eine sinnvolle Default-Entscheidung und dokumentiere sie in `docs/DECISIONS.md`.
3. **WordPress-Standards**: Capabilities, Nonces, Sanitizing/Escaping, Prepared Statements (wpdb), i18n-ready.
4. **Kein Feature-Bloat im ersten Durchlauf**: Erst Phase 1 sauber und stabil. Dann erweitern.
5. **Kein externer Lizenzcode** (Amelia o.ä.) – alles neu implementieren.

---

## 1) Produkt-Ziel

**LazyBookings** ist eine **High-End-Lösung für Termin- und Ressourcenmanagement** in WordPress als Ersatz für Amelia.
Es soll **für service-basierte Unternehmen** (z.B. Yoga, Beratung) funktionieren und perspektivisch auch **Hotel/Room-Logik** unterstützen (Dual-Template Engine).

---

## 2) Plugin-Identität & Kompatibilität

- **Name:** LazyBookings (Amelia Clone)
- **Slug / Text Domain:** `ltl-bookings`
- **Doc Stand:** `SPEC v0.4.0`
- **Current Plugin Version:** `0.4.0`
- **Current DB Version:** `0.4.0`
  
Hinweis: Frühere Phasen/Bezeichnungen wurden harmonisiert; diese SPEC spiegelt den aktuellen Stand wider.
- **WP min:** 6.0
- **PHP min:** 7.4 (empfohlen 8.0+)

Repo-Name darf abweichen (z.B. `ltl-bookings`), aber Plugin-Slug bleibt **ltl-bookings**.

---

## 3) UX/Design-Vorgaben (Global Colors)

- **Background:** `#FDFCF8` (creme)
- **Primary:** `#A67B5B` (Terrakotta)
- **Secondary/Text:** `#3D3D3D` (Dunkelgrau)
- **Accent:** `#8DA399` (Salbei; Hover/Success)
- **Teacher-card background:** `#FDFCF8`

Diese Farben werden in `lazy_design` gespeichert und als CSS Variables ausgegeben.

---

## 4) Architektur-Entscheidung (realistisch & erweiterbar)

### 4.1 MVP-Strategie
Auch wenn langfristig eine React-SPA geplant ist, wird **Phase 1** als **PHP-first** MVP umgesetzt:
- Admin: zunächst WordPress-native Admin Pages (Settings/Listen/Details)
- Frontend: Shortcode + minimaler Wizard (PHP/JS), später austauschbar gegen React

### 4.2 Modularer Aufbau (damit später React/Payments/Hotel reinpasst)
- Domain/Entities (Service, Customer, Appointment, Resource, Invoice)
- Repositories (DB-Zugriff pro Tabelle)
- REST Controller Layer (`/wp-json/ltlb/v1/...`)
- UI Layer (Admin Pages, Shortcodes, Assets)

---

## 5) Ordnerstruktur (Soll)

```
ltl-bookings/
  ltl-bookings.php
  uninstall.php
  readme.txt
  .gitignore
  /includes
    /Core
      Plugin.php
      Activator.php
      Deactivator.php
      Capabilities.php
    /Admin
      StaffProfile.php
    /DB
      Schema.php
      Migrator.php
    /Domain
      Service.php
      Customer.php
      Appointment.php
      Resource.php
    /Repository
      ServiceRepository.php
      CustomerRepository.php
      AppointmentRepository.php
      ResourceRepository.php
      StaffHoursRepository.php
      StaffExceptionsRepository.php
    /Rest
      Routes.php
      ServicesController.php
      CustomersController.php
      AppointmentsController.php
      AvailabilityController.php
    /Util
      Sanitizer.php
      Validator.php
      Time.php
      Availability.php
  /admin
    AdminMenu.php
    Pages/
      DashboardPage.php
      ServicesPage.php
      AppointmentsPage.php
      CustomersPage.php
      SettingsPage.php
      StaffPage.php
  /public
    Shortcodes.php
    Templates/
      wizard.php
      calendar.php
  /assets
    /css
    /js
  /docs
    SPEC.md
    DECISIONS.md
    DB_SCHEMA.md
    API.md
```

---

## 6) Datenhaltung: Custom Tables (Performance)

### 6.1 Tabellen (Start in Phase 1)
**Prefix-Regel:** nutze `$wpdb->prefix . 'lazy_' . <name>`

- `{$prefix}lazy_services`
- `{$prefix}lazy_customers`
- `{$prefix}lazy_appointments`
- `{$prefix}lazy_staff_hours`
- `{$prefix}lazy_staff_exceptions`
- `{$prefix}lazy_resources` (optional in Phase 1, sonst Phase 2)

### 6.2 Minimal-Schema (Phase 1 & 2)

#### lazy_services
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(190) NOT NULL
- `description` LONGTEXT NULL
- `duration_min` SMALLINT UNSIGNED NOT NULL DEFAULT 60
- `buffer_before_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0
- `buffer_after_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0
- `price_cents` INT UNSIGNED NOT NULL DEFAULT 0
- `currency` CHAR(3) NOT NULL DEFAULT 'EUR'
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL
INDEX: `is_active`

#### lazy_customers
- `id` BIGINT UNSIGNED PK AI
- `email` VARCHAR(190) NOT NULL UNIQUE
- `first_name` VARCHAR(100) NULL
- `last_name` VARCHAR(100) NULL
- `phone` VARCHAR(50) NULL
- `notes` LONGTEXT NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

#### lazy_appointments
- `id` BIGINT UNSIGNED PK AI
- `service_id` BIGINT UNSIGNED NOT NULL (FK logisch)
- `customer_id` BIGINT UNSIGNED NOT NULL (FK logisch)
- `staff_user_id` BIGINT UNSIGNED NULL (WP user id, optional in Phase 1)
- `start_at` DATETIME NOT NULL
- `end_at` DATETIME NOT NULL
- `status` VARCHAR(20) NOT NULL DEFAULT 'pending'  (pending|confirmed|canceled)
- `timezone` VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin'
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL
INDEX: `service_id`, `customer_id`, `start_at`, `status`

#### lazy_staff_hours
- `id` BIGINT UNSIGNED PK AI
- `user_id` BIGINT UNSIGNED NOT NULL
- `weekday` TINYINT NOT NULL
- `start_time` TIME NOT NULL
- `end_time` TIME NOT NULL
- `is_active` TINYINT(1) NOT NULL
- `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

#### lazy_staff_exceptions
- `id` BIGINT UNSIGNED PK AI
- `user_id` BIGINT UNSIGNED NOT NULL
- `date` DATE NOT NULL
- `is_off_day` TINYINT(1) NOT NULL
- `start_time` TIME NULL
- `end_time` TIME NULL
- `note` TEXT NULL
- `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```
**Wichtig:** SQL immer per `dbDelta` + `wpdb->prepare`.

---

## 7) WordPress-Integration (Hooks)

- `register_activation_hook`: Tabellen anlegen + Default-Options (`lazy_settings`, `lazy_design`)
- `admin_menu`: Top-Level Menü **LazyBookings**
- `rest_api_init`: REST Routen unter `/wp-json/ltlb/v1/`
- `init`: Shortcodes registrieren
- `wp_enqueue_scripts`: Assets nur laden, wenn Shortcode auf Seite vorkommt
- `admin_enqueue_scripts`: Assets nur auf LazyBookings Admin-Seiten laden

---

## 8) REST API

Namespace: `/ltlb/v1`

### Implemented (current)
- `GET /availability` — service_id, date; optional slots, slot_step
- `GET /time-slots` — service_id, date; optional slot_step
- `GET /slot-resources` — service_id, start (YYYY-MM-DD HH:MM:SS)
- `GET /hotel-availability` — service_id, checkin, checkout, guests

Booking creation: via Shortcode form (no public REST create in current phase)

### Planned (future phases)
- `GET /services`, `GET /services/{id}`, `POST /services`, `PUT /services/{id}`, `DELETE /services/{id}` (soft delete)
- `GET /customers`, `GET /customers/{id}`, `POST /customers`, `PUT /customers/{id}`
- `GET /appointments`, `PUT /appointments/{id}` (status change)

### Auth & Rechte
- Admin endpoints: `current_user_can('manage_options')`
- Public booking endpoint (falls unauthed erlaubt): **nonce + rate limiting** (Phase 2). In Phase 1: nur eingeloggte Admins testen.

---

## 9) Frontend (Shortcodes)

### Shortcodes
- `[lazy_book]` → Standard Wizard
- `[lazy_book service="123" mode="calendar"]` → startet im Kalender-Schritt

### Phase-1 Wizard (minimal)
1. Service wählen
2. Datum/Zeit Slot wählen (aus `/availability`)
3. Kundendaten (email, name, phone)
4. Bestätigen → Appointment anlegen (status `pending`)

---

## 10) Admin UI (Phase 1)

Top-Level Menü: **LazyBookings**

Finaler Admin-Menü-Slug: `ltlb_dashboard` (entspricht der Code-Implementierung in Plugin.php)

Seiten:
- Dashboard (KPIs später)
- Services (CRUD)
- Appointments (Liste + Status ändern)
- Customers (Liste + Details)
- Settings (Default Working Hours, Timezone, Email Templates minimal)
- Design (Farben editieren → speichert `lazy_design`)

---

## 11) Security-Checkliste (Pflicht)

- **Capabilities:** Jede Admin-Aktion mit `current_user_can()`
- **Nonces:** Jede Form/AJAX/REST Mutation
- **Sanitize/Validate:** Eingaben serverseitig
- **Escape:** Ausgaben im Admin/Frontend
- **wpdb prepare:** Keine SQL-Strings ohne `prepare`
- **XSS/CSRF:** konsequent verhindern
- **Logs:** keine sensiblen Daten im Klartext loggen

---

## 12) Phasenplan (damit das Projekt “perfekt” wird, ohne zu explodieren)

### Phase 0 – Repo Hygiene (1–2h)
- Struktur wie oben anlegen
- `docs/` Dateien anlegen
- Plugin muss aktivierbar sein ohne Errors

### Phase 1 – MVP Booking (funktional)
- DB Schema + Migrator
- Services CRUD (Admin)
- Customers CRUD (Admin)
- Appointments: Anlegen + Liste + Status
- Shortcode Wizard (minimal)
- REST API für obige Module

**Akzeptanzkriterien Phase 1**
- Plugin aktiviert, Tabellen existieren
- Ich kann Service anlegen
- Ich kann im Frontend buchen und Appointment erscheint im Admin

### Phase 2 – Ressourcen/Staff/Verfügbarkeit “richtig”
- Working Hours pro Staff (erledigt)
- Staff Exceptions (erledigt)
- Availability Engine (erledigt)
- E-Mail-Benachrichtigungen (erledigt)
- Doppelte Buchungen verhindern (erledigt)
- Ressourcen-Blocking (Räume)
- Email-Templates + tatsächliche Mails

### Phase 3 – Payments + Invoices
- Stripe/PayPal modular
- Invoices Table + PDF Export (später)

### Phase 4 – Hotel Mode MVP (Dual Template Engine)
- Template mode: service (time-slot booking) vs hotel (date-range booking)
- Hotel bookings: check-in/check-out dates, nights calculation, room assignment
- Room types (services) and rooms (resources) with capacity management
- Implemented in 9 commits (see DECISIONS.md Phase 4)

### Phase 4.1 – Production Readiness (9 Commits)
**Goal**: Harden plugin for production deployment
- **Commit 1**: Health/Diagnostics page (system info, DB stats, manual migrations)
- **Commit 2**: Named Lock Protection (MySQL GET_LOCK to prevent race conditions)
- **Commit 3**: Indexes & Query Performance (composite indexes for staff queries)
- **Commit 4**: Admin UX Upgrade (filters by service/customer, CSV export)
- **Commit 5**: Email Deliverability (Reply-To field, test email button, validation)
- **Commit 6**: GDPR Basics (retention settings, manual customer anonymization)
- **Commit 7**: Logging System (privacy-safe logging with levels, PII hashing)
- **Commit 8**: QA Automation (smoke test checklist, upgrade test procedures)
- **Commit 9**: Documentation Sweep (updated DECISIONS, SPEC, QA_CHECKLIST)

**Current Version**: 0.4.0 (DB version: 0.4.0)

### Phase 5 – React SPA (Optional, wenn MVP stabil)
- Admin & Wizard als React ersetzen (REST bleibt)

---

## 13) “Kickoff Prompt” für Copilot Agent / Gemini

> Du bist Senior WordPress Plugin Developer. Öffne dieses Repo und implementiere **Phase 1** komplett:
> - Lege die Ordnerstruktur an
> - Baue eine Plugin-Bootstrap-Klasse
> - Implementiere Activator mit dbDelta für Phase-1 Tabellen
> - Lege `docs/DB_SCHEMA.md`, `docs/API.md`, `docs/DECISIONS.md` an (kurz)
> - Stelle sicher: Plugin aktiviert ohne Warnungen
> Arbeite in kleinen Commits, nach jedem Schritt kurz testen.

---

## 14) Notizen (für später)

- KI-Features (Gemini): erst ab Phase 3/4, wenn die Datenmodelle stabil sind.
- DSGVO: Datenminimierung + Löschkonzept (uninstall/retention) später.