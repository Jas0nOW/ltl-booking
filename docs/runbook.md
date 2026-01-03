# Runbook (Operations & Maintenance)

**Scope:** Deployment, backup, restore, and routine maintenance tasks.  
**Non-Scope:** Initial setup (see Quickstart) or code development.

## Who should read this?
- DevOps engineers.
- Site administrators.

---

## 1. Deployment

### Manual Deployment
1. Create a zip of the `ltl-bookings` folder (excluding `node_modules`, `.git`, and `tests`).
2. Upload and replace the plugin on the production site.
3. Navigate to the **LazyBookings > Diagnostics** page to trigger any pending database migrations.

### Automated Deployment (CI/CD)
- Ensure `npm run build` is executed before packaging.
- Use the `scripts/build-zip.ps1` (or `.sh`) to generate a production-ready archive.

---

## 2. Backup & Restore

### Database
LazyBookings stores data in custom tables prefixed with `wp_ltlb_`.
- **Backup**: Include all `wp_ltlb_*` tables in your standard database backup.
- **Restore**: Restore the tables and ensure the `ltlb_db_version` option in `wp_options` matches the plugin version.

### Files
- Backup the `wp-content/uploads/ltlb-logs/` directory if you need to preserve historical logs.

---

## 3. Maintenance Tasks

### Data Retention
- Configure automatic cleanup in **LazyBookings > Settings > Privacy**.
- Recommended: Delete cancelled appointments after 30 days and anonymize completed ones after 2 years.

### Performance Optimization
- Periodically check the **Diagnostics** page for index health.
- If the `wp_ltlb_appointments` table grows very large, consider archiving old records.

---

## 4. Monitoring

- **Health Check**: Monitor the `/wp-json/ltlb/v1/health` endpoint (if implemented) or check the Diagnostics page.
- **Error Rates**: Monitor the PHP error log for `LTLB_Logger` entries.

---

## Next Steps
- [Troubleshooting](troubleshooting.md)
- [Security Policy](security.md)
