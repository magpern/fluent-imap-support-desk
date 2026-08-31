# Fluent IMAP Support Desk 2.0.5

**Bulk archive fix** — ticket list bulk actions no longer white-screen.

## What changed

- **`includes/class-plugin.php`** — process ticket bulk POST on `admin_init` (before admin headers) so archive/mark-read redirects succeed.
- **`includes/class-list-table.php`** — support bulk “select all matching tickets” via `all_tickets` POST flag.
- **`includes/class-ticket-repository.php`** — add `list_ticket_ids()` for bulk actions across the full filtered result set.

No database migration. REST namespace, options, and tables unchanged.

## Upgrade

| From | Action |
|------|--------|
| **2.0.4** (GitHub Release ZIP) | Upload this ZIP or use **Plugins → Update** on production |

## Install

1. Download **`fluent-imap-support-desk-2.0.5.zip`** from this release.
2. Upload via **Plugins → Add New → Upload** or extract to `wp-content/plugins/fluent-imap-support-desk/`.

## Rollback

Restore the **2.0.4** plugin folder from backup.

Full changelog: [CHANGELOG.md](../CHANGELOG.md)
