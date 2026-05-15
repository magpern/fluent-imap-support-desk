# Compatibility identifiers (internal)

Fluent IMAP Support Desk is the **product name** shown in WordPress admin, plugin headers, and user-facing copy.

Internal identifiers from the original `biopentra-contact-inbox` lineage are **unchanged** in 2.x so existing sites, workers, and backups keep working without a database migration.

## What stays the same (do not rename casually)

| Area | Identifier | Notes |
|------|------------|--------|
| PHP constants | `BIOPENTRA_INBOX_*` | Path, URL, version, capability constant |
| Options | `biopentra_inbox_*` | All settings keys (e.g. `biopentra_inbox_display_name`) |
| Database tables | `{prefix}biopentra_inbox_*` | tickets, messages, replies |
| REST API | `biopentra-support/v1` | Worker import, health, status |
| Capability | `manage_biopentra_inbox` | Granted to administrators on activation |
| Admin menu slug | `biopentra-inbox` | Bookmarks and `admin.php?page=…` URLs |
| Cron hooks | `biopentra_inbox_*` | IMAP sync, archive cleanup |
| Text domain | `biopentra-contact-inbox` | Translation file slug (legacy) |
| PHP classes | `Biopentra_Contact_Inbox_*` | Class names in source (refactor in a future major) |
| Health JSON | `"plugin":"biopentra-contact-inbox"` | Compatibility metadata for monitors |

## What changed in 2.x (visible only)

- Plugin folder: `fluent-imap-support-desk/`
- Bootstrap file: `fluent-imap-support-desk.php`
- WordPress **Plugins** list name: **Fluent IMAP Support Desk**
- Default admin labels, email template copy, and docs (no site-specific branding in defaults)

## Future major migration

A planned **3.0** (or similar) release may rename options, tables, REST namespace, capabilities, and PHP classes. That requires:

- Staged migration scripts
- Worker `API_BASE_URL` / token coordination
- Downtime or dual-read window documentation

Until then, treat any `biopentra_*` string in code as **stable contract**, not branding.

## Related docs

- [rename-migration-plan.md](rename-migration-plan.md) — folder cutover history
- [architecture-audit.md](architecture-audit.md) — tables and hooks
