# Security Policy

**Scope:** Authentication, authorization, data sanitization, and threat mitigation.  
**Non-Scope:** Server-level security (firewalls, SSL) or general WordPress security best practices.

## Who should read this?
- Developers implementing new features.
- Security auditors.

---

## 1. Authentication & Authorization

### Admin Access
All admin-facing REST endpoints and AJAX actions are protected by:
- **Capabilities**: We use custom capabilities (e.g., `manage_bookings`, `manage_ai_settings`) to restrict access based on user roles.
- **Nonces**: Every state-changing request must include a valid WordPress nonce (`ltlb_admin_nonce`).

### Public Access
Public endpoints (e.g., `/time-slots`, `/process-payment`) are accessible without authentication but are protected by:
- **Rate Limiting**: IP-based rate limiting to prevent brute-force or denial-of-service attacks.
- **Input Validation**: Strict validation of all incoming parameters.

---

## 2. Data Security

### Sanitization & Validation
- **Inputs**: All data from `$_GET`, `$_POST`, and REST requests is sanitized using `sanitize_text_field`, `absint`, or custom validation logic before use.
- **Database**: We use `$wpdb->prepare()` for all database queries to prevent SQL injection.
- **Outputs**: All data rendered in templates is escaped using `esc_html`, `esc_attr`, or `esc_url`.

### PII Protection (GDPR)
- **Anonymization**: Tools are provided to anonymize customer data upon request.
- **Retention**: Configurable data retention policies automatically clean up old appointments and logs.
- **Logging**: Sensitive information (PII) is filtered out of debug logs unless explicitly enabled for troubleshooting.

---

## 3. Threat Mitigation

### Race Conditions
- **MySQL Named Locks**: We use `GET_LOCK()` and `RELEASE_LOCK()` to prevent double-bookings during high-concurrency scenarios.

### CSRF Protection
- All state-changing actions (POST/PUT/DELETE) require a valid nonce check.

---

## Next Steps
- [Roles & Capabilities](reference/roles-capabilities.md)
- [Error Handling](explanation/error-handling.md)
- [Testing Guide](how-to/testing.md)
