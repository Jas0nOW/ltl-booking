# LazyBookings – Role Profiles & Capabilities

**Ziel:** Klare Rollen‑Hierarchie mit Capabilities‑basierter AC.

---

## Rollen‑Modell

### 1) SuperAdmin (Owner / System Admin)
**Caps:**
- `manage_options` (WP standard – plugin config)
- `manage_ai_settings` (AI Provider, Keys, Mode)
- `manage_ai_secrets` (API Keys anzeigen/editieren)
- `view_ai_reports` (AI Insights Dashboard)
- `approve_ai_drafts` (Outbox Approve/Reject)
- `manage_staff_roles` (Rollen zuweisen)

**Sicht:**
- Alle Settings (Email, AI, Automation, Security)
- All Reports & Analytics
- Audit Logs (voll)
- Secrets Reveal Button

---

### 2) CEO (Manager / Business Owner)
**Caps:**
- `view_ai_reports` (AI Insights Dashboard, Daily/Overall)
- `approve_ai_drafts` (Outbox Actions)
- `read_appointments` / `read_bookings` (Dashboard data)

**Sicht:**
- Dashboard + AI Insights
- Outbox (Approve only, no Reject)
- Reports: Revenue, Occupancy, KPIs
- **Nicht:** Settings, Keys, Secrets, Staff Roles

---

### 3) Mitarbeiter (Staff / Team Member)
**Caps:**
- `view_ai_reports` (Personal Reports only)
- `read_own_appointments` (only own assignments)

**Sicht:**
- Dashboard (personalized: own tasks, own revenue)
- My Appointments / Bookings (filter by assigned staff)
- **Nicht:** Settings, AI, Reports (only own), Outbox

---

### 4) Gast (Customer / Booking Visitor)
**Caps:** None (public form only)

**Sicht:** Booking widget (frontend)

---

## Capabilities (WP Custom)

### AI-Related
- `manage_ai_settings` — Edit AI Provider/Model/Mode
- `manage_ai_secrets` — View/Edit API Keys
- `view_ai_reports` — Access AI Insights Dashboard
- `approve_ai_drafts` — Approve/Reject Outbox Actions

### Business-Related
- `manage_staff_roles` — Assign roles to team members
- `view_financial_reports` — Revenue/Profit dashboard

### Data-Related
- `read_appointments` — View all appointments
- `edit_appointments` — Modify appointments
- `read_own_appointments` — View only own assignments
- `read_bookings` — View all hotel bookings
- `edit_bookings` — Modify bookings

---

## Registration (in Activator / Plugin.php)

```php
register_cap('manage_ai_settings', ['superadmin']);
register_cap('manage_ai_secrets', ['superadmin']);
register_cap('view_ai_reports', ['superadmin', 'ceo', 'mitarbeiter']);
register_cap('approve_ai_drafts', ['superadmin', 'ceo']);
register_cap('manage_staff_roles', ['superadmin']);
```

---

## Implementation Strategy

1. **Aktivator:** Caps registrieren beim Plugin activate
2. **Role Mapper:** `LTLB_Role_Manager` Klasse für Cap‑Checks
3. **Settings Page:** Tabs sichtbar nur mit Cap
4. **Dashboard:** Widget filtering nach Cap
5. **Outbox:** approve/reject buttons basierend auf Cap

