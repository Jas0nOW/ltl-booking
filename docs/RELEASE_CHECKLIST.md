# Release Checklist

## Before Release
- Tag the repo: `v0.4.0` (matches `LTLB_VERSION`)
- Build ZIP: run `scripts/build-zip.sh`
- Verify checksums in `dist/SHA256SUMS.txt`
- Smoke tests:
  - Activate plugin, ensure no warnings
  - Services CRUD works
  - Frontend `[lazy_book]` booking creates appointment
  - Admin appointment list shows new booking
  - Diagnostics page shows table counts

## Deploy Steps
- Upload ZIP via WP admin Plugins > Add New > Upload
- Activate plugin
- Run migrations (auto on activation; confirm Diagnostics page status)
- Configure Settings (timezone, email From/Reply-To)
- Perform a test booking end-to-end

## Rollback
- Deactivate plugin
- Restore site files and DB from backup
- If uninstalling, ensure `delete_data_on_uninstall` is `0` unless you intend to purge data
