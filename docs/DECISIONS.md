# LazyBookings Decisions (v0.4.4)

Diese Datei dokumentiert die wichtigsten technischen Entscheidungen in LazyBookings.
Für den vollständigen IST-Funktionsumfang: siehe `docs/SPEC.md`.

## Architektur

- **Custom Tables statt Post Types**: Domain-Daten liegen in eigenen Tabellen (`lazy_*`).
- **Explizite Includes**: Kein Autoloader; Klassen werden in `Includes/Core/Plugin.php` geladen.
- **Repository Layer**: DB-Zugriffe sind in `Includes/Repository/*` gekapselt.

## Rollen & Berechtigungen

- **Admin**: Admin-Seiten und Admin-REST sind für `manage_options` vorgesehen.
- **Staff Role**: Es existiert die Rolle `ltlb_staff` für Staff-Verfügbarkeiten (Working Hours/Exceptions). Admin kann alle Staff-Zeiten verwalten.

## Zeit / Timezone & Speicherung

- **Speicherung**: `start_at`/`end_at` werden als lokale `DATETIME` Strings gespeichert.
- **Format**: `Y-m-d H:i:s`.
- **Timezone-Quelle**: Standard ist die WordPress Site Timezone; optional kann `lazy_settings.timezone` die Plugin-Timezone für Berechnungen überschreiben.
- **Helper**: Parsing/Format über `LTLB_Time` (`Includes/Util/Time.php`).

## Availability / Slot-Engine

- **Intersection-Prinzip**: Verfügbarkeit = Schnittmenge aus globalen Zeiten + Staff Hours/Exceptions + Service-Regeln + bestehenden Appointments.
- **Blocking Statuses**: Standard blockt nur `confirmed`; optional blockt auch `pending` via `lazy_settings.pending_blocks`.
- **Per-Service Regeln**:
  - `available_weekdays` (CSV `0..6`, 0=Sonntag)
  - optionales Tages-Zeitfenster (`available_start_time`/`available_end_time`)
  - `availability_mode`:
    - `window`: Startzeiten innerhalb des kombinierten Fensters
    - `fixed`: nur definierte wöchentliche Startzeiten (`fixed_weekly_slots` JSON)

## Concurrency / Double-Booking-Schutz

- **Named Locks (best effort)**: Booking-Erstellung nutzt MySQL `GET_LOCK()`/`RELEASE_LOCK()` über `LTLB_LockManager`.
- **Fallback Mutex**: Falls `GET_LOCK` nicht verfügbar ist, wird ein Option-Lock als Fallback genutzt.
- **Finaler Konflikt-Check**: Vor Insert wird nochmals auf Konflikte geprüft, um Race-Conditions zu reduzieren.

## REST / Schnittstellen

- **Namespace**: `ltlb/v1`.
- **Public Read Endpoints**: sind öffentlich (Wizard benötigt keine Auth).
- **Admin Endpoints**: erfordern WP-Login + `manage_options` (REST Nonce via `X-WP-Nonce`).

## Migration Policy

- **dbDelta**: Schema wird via `dbDelta()` gepflegt.
- **DB Version**: Option `ltlb_db_version` triggert automatische Migrationen.

## Legacy Notes (historisch)

- Frühe Commits haben diese Entscheidungen eingeführt: Sanitizer (`LTLB_Sanitizer`), explizite Includes, Zeit-Helper (`LTLB_Time`), Staff-Hours/Exceptions-Tabellen, Named Locks + Fallback.
