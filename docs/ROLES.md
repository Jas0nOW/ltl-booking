# LazyBookings – Role Profiles & Capabilities (v1.1.0)

**Ziel:** Klare Rollen-Hierarchie mit Capability-basierter Access Control.

---

## Rollen-Modell

### 1) SuperAdmin (Owner / System Admin)
**Caps (Auszug):**
- `manage_options` (WP-Standard – Plugin-Konfiguration)
- `manage_ai_settings`, `manage_ai_secrets`
- `view_ai_reports`, `approve_ai_drafts`
- `manage_staff_roles`
- `manage_bookings`, `manage_customers`, `view_payments`, `view_reports`

**Sicht:**
- Alle Settings (E-Mail, AI, Automation, Security)
- Alle Reports & Analytics
- Audit Logs (voll), Secrets Reveal Button

---

### 2) CEO (Manager / Business Owner)
**Caps (Auszug):**
- `view_ai_reports`
- `approve_ai_drafts`
- `view_bookings` / Reports-Read-Caps

**Sicht:**
- Dashboard + AI Insights
- Outbox (Approve only)
- Reports: Revenue, Occupancy, KPIs
- **Nicht:** Settings, Keys, Secrets, Staff-Rollen

---

### 3) Mitarbeiter (Staff / Team Member)
**Caps (Auszug):**
- `view_bookings`, `manage_own_bookings`
- `view_customers`
- `manage_own_availability`

**Sicht:**
- Persönliches Dashboard (eigene Termine)
- "My Appointments" / Buchungen nach zugewiesenem Staff gefiltert
- **Nicht:** globale Settings, AI, Gesamt-Reports

---

### 4) Gast (Customer / Booking Visitor)
**Caps:** None (nur öffentliches Buchungs-Formular)

**Sicht:**
- Booking Wizard (Frontend)

---

## Implementierung

- Rollen werden über `LTLB_RoleManager` registriert/synchronisiert.
- Cap-Checks erfolgen konsistent über `current_user_can()` + Nonce-Validierung in Admin-Actions und REST-Endpunkten.
