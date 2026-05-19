# Changelog

## [2.0.2] - 2026-05-19

**Packaging and release automation** — aligns version numbers with code already on `main`. **No intentional support-desk behavior changes** beyond what landed between `v2.0.0` and today (branding copy).

### Added

- `readme.txt`, `LICENSE` for distribution.
- `scripts/lib/verify-release-zip.py` — production ZIP verification.
- `scripts/release-audit.sh` — repository vs artifact checks.
- `.github/workflows/ci.yml` and `.github/workflows/release.yml` — PHP lint, build ZIP, GitHub Release on `v*` tags.
- `docs/GITHUB_RELEASE_NOTES_2.0.2.md`.

### Changed

- **Production release ZIP** — `scripts/build-zip.sh` uses explicit excludes; ships `includes/`, `assets/`, main plugin file, `uninstall.php`, `readme.txt`, `LICENSE` only.
- **Version** — header and `BIOPENTRA_INBOX_VERSION` set to **2.0.2**.

### Notes

- Git tag **`v2.0.1`** (2026-05-15) pointed at branding/menu-label commits but left the plugin header at **2.0.0**. That tag is historical; **do not use it for deployments**. Use **`v2.0.2`** instead.
- Internal compatibility identifiers (`biopentra_*` options, tables, REST, capability) remain unchanged.

## [Unreleased]

_No pending changes._

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
