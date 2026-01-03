# Troubleshooting Guide

**Scope:** Common issues, debugging tools, and resolution steps.  
**Non-Scope:** Fixing core WordPress issues or server-side configuration (PHP/MySQL).

## Who should read this?
- Administrators managing the plugin.
- Developers debugging issues.

---

## 1. Common Issues

### Bookings not appearing in Calendar
- **Check**: Ensure the appointment status is not "Draft" or "Cancelled".
- **Check**: Verify that the staff member assigned to the service has their hours configured correctly.
- **Fix**: Go to **LazyBookings > Diagnostics** and run a "Calendar Sync" if available.

### Emails not being sent
- **Check**: Verify your SMTP settings in **LazyBookings > Settings > Email**.
- **Check**: Use the "Send Test Email" button in the settings page.
- **Fix**: Install a plugin like "WP Mail SMTP" to ensure reliable delivery.

### AI Assistant not responding
- **Check**: Ensure your API Key is valid in **LazyBookings > Settings > AI**.
- **Check**: Check the **Outbox** for any failed AI drafts.
- **Fix**: Review the logs in `wp-content/uploads/ltlb-logs/` for specific API errors.

---

## 2. Debugging Tools

### Diagnostics Dashboard
Located at **LazyBookings > Diagnostics**. This page provides:
- **System Health**: Checks for database table existence, PHP version, and required extensions.
- **Cron Status**: Verifies if the cleanup and notification tasks are running.
- **Log Viewer**: Access to the latest plugin logs.

### Logging
LazyBookings uses a custom logger. Logs are stored in:
`wp-content/uploads/ltlb-logs/` (if directory is writable) or the standard PHP error log.

To enable verbose logging:
1. Go to **LazyBookings > Settings > Advanced**.
2. Set **Log Level** to `Debug`.

---

## 3. Error Codes

| Code | Meaning | Resolution |
| :--- | :--- | :--- |
| `missing_email` | Customer email is missing. | Ensure the email field is required in the wizard. |
| `lock_timeout` | Database lock could not be acquired. | High traffic detected. Try again in a few seconds. |
| `db_error` | A database query failed. | Check the Diagnostics page for table errors. |

---

## Next Steps
- [Error Handling Strategy](explanation/error-handling.md)
- [System Check](archive/audit/SYSTEM_CHECK.md)
- [Testing Guide](how-to/testing.md)
