# LazyBookings – AI Integration & Automations

**Ziel:** KI‑gestützte Automationen für Hotel/Appointments, human‑in‑the‑loop, sicherer Secrets‑Handling.

---

## 1) Datenstruktur (WP Options)

### AI Config
- **Key:** `lazy_ai_config`
- **Wert:** Array mit:
  - `provider` — `"gemini"` (default) oder zukünftig `"claude"` etc.
  - `model` — `"gemini-2.5-flash"` oder `"gemini-3.0-pro-preview"`
  - `operating_mode` — `"autonomous"` oder `"human-in-the-loop"` (global)
  - `enabled` — `1/0` (Master-Switch)

### API Keys (Secrets)
- **Key:** `lazy_api_keys` (autoload: false, encrypted optional)
- **Wert:** Array mit:
  - `gemini` — API‑Key (niemals im HTML/JS sichtbar)
  - `[provider]` — weitere Provider‑Keys

### Business Context
- **Key:** `lazy_business_context`
- **Wert:** Array mit:
  - `brand_name`, `brand_voice`, `contact_info`
  - `faq`, `policies`, `invoice_terms`
  - send‑controls pro Feld (boolean)

### Role Capabilities
- **Custom Caps:** `manage_ai_settings`, `manage_ai_secrets`, `view_ai_reports`, `approve_ai_drafts`
- **Roles:**
  - SuperAdmin: alle Caps
  - CEO: `view_ai_reports`, `approve_ai_drafts`
  - Mitarbeiter: `view_ai_reports`

---

## 2) Settings UI (SettingsPage erweitern)

### Tabs (bilingual EN+DE)
1. **Email** (existiert, refresh)
2. **AI** (neu) – Keys, Provider, Model, Mode, Business Context
3. **Automation** (neu, optional jetzt) – globale Regel‑Settings
4. **Security** (neu, optional jetzt) – Role Overrides

### E‑Mail Tab (refresh)
- Sender Info, Admin Notifications, Customer Notifications
- i18n: alle Strings via `__('…', 'ltl-bookings')`

### AI Tab (neu)
```
[AI Settings]
☐ Enable AI Automations [toggle]

[Provider & Model]
○ Gemini [radio]
  - Model: ◉ 2.5 Flash ◎ 3.0 Pro Preview [radio]
  - API Key: [password input] [Test Connection button]

[Operating Mode]
○ Autonomous [radio]
  → Actions ausführen ohne Bestätigung
○ Human-in-the-Loop [radio]
  → Actions in Outbox (Approve/Reject)

[Business Context]
Brand Name: [text]
Brand Voice: [textarea] [toggle "send to AI?"]
FAQs: [textarea] [toggle]
Policies: [textarea] [toggle]
Invoice Terms: [textarea] [toggle]
Contact Info: [text] [toggle]
```

---

## 3) Secrets Security

### Storage
- `lazy_api_keys` mit `autoload: false` (nicht in Cache)
- Optional: at‑rest encryption (AES‑GCM mit WP salts)
- Keys niemals in Logs / HTML / JS

### Access Control
- GET Keys: `current_user_can('manage_ai_secrets')` (SuperAdmin only)
- Reveal Button: nur SuperAdmin, Audit Log
- Test Connection: sendet nur Model/Provider, nicht Keys im Response

### Sanitization
- Keys: `sanitize_text_field` + regex `/^[a-zA-Z0-9_-]+$/`
- Fallback: leerer String bei Invalid

---

## 4) Test Connection (Gemini v1)

```
POST /wp-admin/admin-ajax.php
- action: `ltlb_test_ai_connection`
- provider: `"gemini"`
- api_key: (vom Form)
- model: (vom Form)

Response:
{
  "success": true|false,
  "message": "Connection OK" | "Auth Failed" | "Rate Limited" | "Server Error",
  "timestamp": "2025-12-14 10:30:00"
}
```

---

## 5) Role Profiles (Default Caps)

| Rolle | manage_ai_settings | manage_ai_secrets | view_ai_reports | approve_ai_drafts |
|-------|---|---|---|---|
| SuperAdmin | ✓ | ✓ | ✓ | ✓ |
| CEO | ✗ | ✗ | ✓ | ✓ |
| Mitarbeiter | ✗ | ✗ | ✓ | ✗ |
| Gast | ✗ | ✗ | ✗ | ✗ |

**Setup:** Caps via `register_cap()` in Activator; rollen mappings in Plugin.php.

---

## 6) Outbox (Draft Center)

Später (P1 Task 7):
- Liste "Pending AI Actions": Emails, Reports, Room Assignments
- Approve → Execute, Reject → Discard
- Audit Log pro Action

---

## 7) Provider Layer (später P1)

```php
interface LTLB_AI_Provider {
  public function test_connection(): array; // {success, message}
  public function generate_text(string $prompt, array $context): string;
  public function get_model_info(): array;
}

class LTLB_AI_Gemini implements LTLB_AI_Provider {
  // Gemini v1.5 / v2.0 / v3.0 Preview
}

class LTLB_AI_Factory {
  public static function get_provider(string $name): LTLB_AI_Provider;
}
```

---

## 8) Prompt Templates (später P3)

2 Basis Vorlagen pro Usecase:
- **Professional:** Formal, structure, facts first
- **Friendly:** Conversational, personality, warm

---

## Audit Log Schema (später)

```
Table: lazy_ai_actions
- id (PK)
- timestamp
- user_id
- action_type (email, report, assignment)
- status (draft, approved, executed, rejected)
- ai_input (trimmed, no secrets)
- ai_output (generated text)
- final_state (if executed)
- notes (admin comment)
```

