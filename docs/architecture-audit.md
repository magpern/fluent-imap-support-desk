# Architecture audit — Fluent IMAP Support Desk 2.0.0

**Date:** 2026-05-15  
**Bootstrap:** `fluent-imap-support-desk.php`  
**Compatibility lineage:** `biopentra-contact-inbox` (unchanged internals). See [compatibility-identifiers.md](compatibility-identifiers.md). PHP class names remain `Biopentra_Contact_Inbox_*` until a future major release.

---

## Bootstrap

| Item | Value |
|------|--------|
| Product name | Fluent IMAP Support Desk |
| Folder slug (this repo / ZIP) | `fluent-imap-support-desk` |
| Constants | `BIOPENTRA_INBOX_VERSION`, `PATH`, `URL`, `CAP` |
| Capability | `manage_biopentra_inbox` |
| Text domain | `biopentra-contact-inbox` (unchanged) |
| REST namespace | `biopentra-support/v1` |

`plugins_loaded` (prio 20) loads admin/runtime; `rest_api_init` (prio 5) registers worker routes.

---

## Main classes

| Class | Role |
|-------|------|
| `Biopentra_Contact_Inbox_Plugin` | Admin menu, screens, `admin_post_*` handlers |
| `Biopentra_Contact_Inbox_Settings` | Tabbed settings API |
| `Biopentra_Contact_Inbox_Rest_Worker` | Worker REST import/status/health |
| `Biopentra_Contact_Inbox_Inbound_Import` | Message import pipeline |
| `Biopentra_Contact_Inbox_Mailer` / `Bridge_Smtp` | Outbound mail |
| `Biopentra_Contact_Inbox_Fluent_Ticket_Bridge` | Fluent Forms hooks |
| `Biopentra_Contact_Inbox_Ticket_Repository` / `Message_Repository` | Data layer |
| `Biopentra_Contact_Inbox_Imap_Sync` | Legacy in-PHP IMAP (optional) |
| `Biopentra_Contact_Inbox_Cron` | Scheduled sync / cleanup |

---

## Database tables

| Table | Purpose |
|-------|---------|
| `{prefix}biopentra_inbox_tickets` | Ticket headers |
| `{prefix}biopentra_inbox_messages` | Thread messages |
| `{prefix}biopentra_inbox_replies` | Legacy reply log |

Created/upgraded via `Biopentra_Contact_Inbox_Activator` (`biopentra_inbox_db_version`).

---

## Options (prefix `biopentra_inbox_`)

Includes IMAP/SMTP hosts, encrypted/plain passwords (options), worker token **hash**, import driver, Fluent migration cursor, reply template settings, archive retention, uninstall flag.

Constants `BIOPENTRA_INBOX_IMAP_PASS` / `BIOPENTRA_INBOX_SMTP_PASS` can override DB-stored passwords when defined in `wp-config.php`.

---

## Admin

- Menu slug: `biopentra-inbox` (legacy)
- Submenus: Tickets, Settings
- Many `admin_post_biopentra_inbox_*` actions (reply, IMAP sync, Fluent migrate, worker token, danger reset, etc.)

---

## REST (worker)

| Route | Auth | Purpose |
|-------|------|---------|
| `GET .../health` | Public | Liveness + token configured flag |
| `POST .../messages/import` | Bearer token | Import message payload |
| `POST .../worker/status` | Bearer token | Worker heartbeat |

---

## Cron

- `biopentra_inbox_imap_sync`
- `biopentra_inbox_archived_cleanup`

---

## External integration

- **Fluent Forms** — submission → ticket
- **proton-bridge** (Docker) — IMAP/SMTP on `bridge-net`
- **biopentra-mail-worker** — polls Bridge, calls WordPress REST

See `docs/worker-integration.md`.

---

## Rename deferred (future release)

Planned migration may rename: folder slug, main PHP file, option keys, table names, REST namespace, capability, text domain. **Not in 2.0.0 standalone repo.** See `docs/rename-migration-plan.md`.

---

## Risks

- Large `class-plugin.php` surface (many `admin_post` handlers).
- Public `/health` exposes configuration hints (mitigate at edge if needed).
- Dual-plugin risk if both `biopentra-contact-inbox` and `fluent-imap-support-desk` folders are active.
