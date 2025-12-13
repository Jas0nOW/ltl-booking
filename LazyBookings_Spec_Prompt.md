# LazyBookings – Master-Spezifikation & Agent-Prompt (v0.1)

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
- **Slug / Text Domain:** `lazy-bookings`
- **Version:** `0.1.0` (Start)
- **WP min:** 6.0
- **PHP min:** 7.4 (empfohlen 8.0+)

Repo-Name darf abweichen (z.B. `ltl-booking`), aber Plugin-Slug bleibt **lazy-bookings**.

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
- REST Controller Layer (`/wp-json/lazy/v1/...`)
- UI Layer (Admin Pages, Shortcodes, Assets)

---

## 5) Ordnerstruktur (Soll)

```
lazy-bookings/
  lazy-bookings.php
  uninstall.php
  readme.txt
  .gitignore
  /includes
    /Core
      Plugin.php
      Activator.php
      Deactivator.php
      Capabilities.php
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
  /admin
    AdminMenu.php
    Pages/
      DashboardPage.php
      ServicesPage.php
      AppointmentsPage.php
      CustomersPage.php
      SettingsPage.php
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
- `{$prefix}lazy_resources` (optional in Phase 1, sonst Phase 2)

### 6.2 Minimal-Schema (Phase 1)

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

**Wichtig:** SQL immer per `dbDelta` + `wpdb->prepare`.

---

## 7) WordPress-Integration (Hooks)

- `register_activation_hook`: Tabellen anlegen + Default-Options (`lazy_settings`, `lazy_design`)
- `admin_menu`: Top-Level Menü **LazyBookings**
- `rest_api_init`: REST Routen unter `/wp-json/lazy/v1/`
- `init`: Shortcodes registrieren
- `wp_enqueue_scripts`: Assets nur laden, wenn Shortcode auf Seite vorkommt
- `admin_enqueue_scripts`: Assets nur auf LazyBookings Admin-Seiten laden

---

## 8) REST API (Phase 1)

Namespace: `/lazy/v1`

### Endpoints
- `GET /services` (list)
- `POST /services` (create)
- `GET /services/{id}`
- `PUT /services/{id}`
- `DELETE /services/{id}` (soft delete via `is_active=0`)

- `GET /customers`
- `POST /customers`
- `GET /customers/{id}`
- `PUT /customers/{id}`

- `GET /appointments?from=YYYY-MM-DD&to=YYYY-MM-DD&service_id=...`
- `POST /appointments` (create booking)
- `PUT /appointments/{id}` (status change)

- `GET /availability?service_id=ID&date=YYYY-MM-DD`
  - returns time slots (z.B. 09:00, 10:00 …) basierend auf Default Working Hours (Phase 1)

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

Top-Level Menü: **LazyBookings** (Slug: `lazy_bookings`)

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
- Working Hours pro Staff
- Ressourcen-Blocking (Räume)
- Doppelte Buchungen verhindern
- Email-Templates + tatsächliche Mails

### Phase 3 – Payments + Invoices
- Stripe/PayPal modular
- Invoices Table + PDF Export (später)

### Phase 4 – Dual Template Engine (Hotel)
- Check-in/out Logik, Nights, Room inventory

### Phase 5 – React SPA (Optional, wenn MVP stabil)
- Admin & Wizard als React ersetzen (REST bleibt)

---

## 13) “Kickoff Prompt” für Copilot Agent / Gemini

> Du bist Senior WordPress Plugin Developer. Öffne dieses Repo und implementiere **Phase 0** komplett:
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

