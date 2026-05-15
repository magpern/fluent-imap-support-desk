# Changelog

## [Unreleased]

### Changed

- Remove visible Biopentra branding from admin labels, settings defaults, email template fallbacks, and documentation.
- Generic defaults: site name for From name, “Re: Your support inquiry”, reply footer uses `{site_name}` placeholder.
- Add [docs/compatibility-identifiers.md](docs/compatibility-identifiers.md) listing internal `biopentra_*` identifiers.

### Notes

- Preserve internal compatibility identifiers (options, tables, REST, capability, menu slug, class names).

## [2.0.0] - 2026-05-15

### Added

- Initial standalone repository `magpern/fluent-imap-support-desk`.
- Bootstrap file `fluent-imap-support-desk.php` (product name **Fluent IMAP Support Desk**).
- Compatibility: unchanged `BIOPENTRA_INBOX_*` constants, `biopentra_inbox_*` options/tables, `biopentra-support/v1` REST, `manage_biopentra_inbox` capability.
- Operational docs: architecture, security, rename migration, deployment, worker integration, rollback.
- `scripts/build-zip.sh` for private ZIP releases.

### Notes

- No behavior change vs `biopentra-custom-plugins/plugins/biopentra-contact-inbox/` at v2.0.0.
- Production folder cutover to `fluent-imap-support-desk` completed 2026-05-15 (internal IDs unchanged).
