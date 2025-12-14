# LazyBookings – Automations & Outbox

**Ziel:** Zentrale Verwaltung AI‑generierter Aktionen mit Approval‑Workflow.

---

## 1) Outbox Architektur

### DB Table: lazy_ai_actions
```
id (PK, auto)
timestamp (created_at)
user_id (creator/approver)
action_type (email, report, room_assignment, reminder)
status (draft, approved, rejected, executed, failed)
ai_input (prompt sent to AI, truncated)
ai_output (generated content)
final_state (JSON: if executed)
metadata (JSON: extra context)
notes (admin notes)
```

### Status Flow
```
                     ┌─→ approved ─→ executed ──┐
draft ──→ (review) ──┤                            └─→ archive
                     └─→ rejected  ────────────────┘
```

### Draft Creation (P1 Task 7)
- AI erzeugt Draft
- Mode = HITL → speichert in `draft` Status
- Mode = Autonomous → Approve direkt und Execute

---

## 2) Outbox UI (OutboxPage.php)

### List View
- Tabelle: action_type | ai_output (preview) | status | created_at | actions
- Filter: Type, Status, User, Date Range
- Bulk: Approve Selected, Reject Selected

### Action Detail
- Full ai_input (prompt)
- Full ai_output
- Metadata (context)
- Approve/Reject/Edit buttons
- Admin notes (textarea)

### Execute Behavior (per action_type)
- **email:** Via Mailer / wp_mail
- **report:** Download PDF OR Email to user
- **room_assignment:** Update DB + Notification
- **reminder:** Schedule Event OR Send Immediately

---

## 3) Gemini Integration (P1 Task 4)

### LTLB_AI_Gemini Class
```php
class LTLB_AI_Gemini implements LTLB_AI_Provider {
  private string $api_key;
  private string $model; // 2.5-flash or 3.0-pro-preview
  
  public function test_connection(): array {
    // POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
    // minimal prompt, check 200 OK
  }
  
  public function generate_text(string $prompt, array $context): string {
    // POST request, return content[0].text
    // Handle rate limits, auth errors, etc.
  }
}
```

### Factory Pattern
```php
LTLB_AI_Factory::get_provider('gemini') → LTLB_AI_Gemini instance
```

---

## 4) Automation Rules (P2 Task 8)

Later: Cron‑based Rules Engine
```
Trigger: appointment_confirmed, booking_overdue, daily_report
Condition: mode === hotel, status = pending
Action: send_email, generate_report, assign_room
Schedule: immediately, daily, weekly
```

---

## 5) AI Usecases (P2)

### Hotel: Smart Room Assistant
- **Trigger:** Room assignment needed (new booking)
- **Input:** Guests, room types, availability, constraints
- **AI:** Suggest room assignment (HITL only)
- **Action:** Approve → update DB

### Invoices + Overdue Reminders
- **Trigger:** Invoice due date approaching (daily check)
- **Input:** Invoice, customer, policy
- **AI:** Generate friendly reminder email
- **Action:** Approve → send via wp_mail

### Dashboard Reports
- **Trigger:** Manual button click OR scheduled daily
- **Input:** Last 7/30 days data, KPIs
- **AI:** Summarize insights, highlights, recommendations
- **Action:** View draft → Approve → Email/Download

---

## 6) Logging & Audit (Security)

Every action_type logged in lazy_ai_actions table:
- What was sent to AI (trimmed, no secrets)
- What AI generated
- Who approved/rejected
- Final execution state
- Timestamps

Retention: Keep 90 days (configurable)

