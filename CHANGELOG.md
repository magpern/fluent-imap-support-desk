# Changelog

## [2.0.0] - 2026-05-15

### Added

- Initial standalone repository `magpern/fluent-imap-support-desk`.
- Bootstrap file `fluent-imap-support-desk.php` (product name **Fluent IMAP Support Desk**).
- Compatibility: unchanged `BIOPENTRA_INBOX_*` constants, `biopentra_inbox_*` options/tables, `biopentra-support/v1` REST, `manage_biopentra_inbox` capability.
- Operational docs: architecture, security, rename migration, deployment, worker integration, rollback.
- `scripts/build-zip.sh` for private ZIP releases.

### Notes

- No behavior change vs `biopentra-custom-plugins/plugins/biopentra-contact-inbox/` at v2.0.0.
- Production Biopentra site remains on `biopentra-contact-inbox` slug until migration release.
