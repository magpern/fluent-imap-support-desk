# Fluent IMAP Support Desk 2.0.2

**Version alignment and production release automation** — not a feature release.

## What changed

- Plugin version **2.0.2** aligns the header and `BIOPENTRA_INBOX_VERSION` with shipped code (including branding updates that were previously tagged as `v2.0.1` without a version bump).
- **Production ZIP** excludes `scripts/`, `docs/`, `.github/`, and other dev-only paths; includes runtime code, `readme.txt`, and `LICENSE`.
- **GitHub Actions** CI (PHP lint + zip build) and Release workflow on `v*` tags.

## Upgrade

| From | Action |
|------|--------|
| **2.0.0** or live deploy at **2.0.0** header | Replace plugin folder with this ZIP or rsync from tag `v2.0.2` |
| **v2.0.1 tag only** | No separate 2.0.1 package; use **2.0.2** |

Database tables, options, REST namespace (`biopentra-support/v1`), and cron hooks are **unchanged**.

## Install

1. Download **`fluent-imap-support-desk-2.0.2.zip`** from this release.
2. Upload via **Plugins → Add New → Upload** or extract to `wp-content/plugins/fluent-imap-support-desk/`.
3. Activate if needed; verify **Support Desk** admin loads.

## Rollback

Deactivate and restore the previous plugin folder from backup.

Full changelog: [CHANGELOG.md](../CHANGELOG.md)
