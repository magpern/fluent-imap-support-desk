# Fluent IMAP Support Desk

WordPress plugin that bridges **Fluent Forms** contact submissions into a threaded **support desk**, with **IMAP inbound** and **SMTP outbound** mail (including **Proton Mail Bridge** and an optional **proton-mail-worker** container).

**Current compatibility lineage:** evolved from `biopentra-contact-inbox` (v2.0.0). Database tables, options, cron hooks, capabilities, and REST namespace are **unchanged** in this standalone repo until a staged rename migration.

## Features

- Fluent Forms → ticket creation and threading
- Admin **Support Desk** (tickets, replies, settings)
- IMAP sync and external **mail worker** import (`POST /wp-json/biopentra-support/v1/messages/import`)
- Proton Bridge–friendly Docker layout (internal IMAP/SMTP, no public mail ports)
- Branded HTML reply templates, archive retention, Fluent migration tools

## Requirements

| Component | Version |
|-----------|---------|
| WordPress | 6.0+ |
| PHP | 7.4+ |
| WooCommerce | Recommended (site context; not a hard dependency for all features) |
| Fluent Forms | Required for form→ticket bridge |
| MariaDB/MySQL | Custom tables on activation |

Optional: **Docker Compose** stack with `proton-bridge` and `biopentra-mail-worker` (see `docs/worker-integration.md`).

## Private deployment (ZIP)

This plugin is deployed as a **private ZIP**, not from WordPress.org.

```bash
./scripts/build-zip.sh
# → builds/fluent-imap-support-desk-2.0.0.zip
```

Upload via **Plugins → Add New → Upload**, or rsync the `fluent-imap-support-desk/` folder into `wp-content/plugins/`.

## Production status (Biopentra)

**Folder cutover completed 2026-05-15:** production at `https://www.biopentra.eu` runs **`fluent-imap-support-desk`** v2.0.0. Legacy `biopentra-contact-inbox` is inactive (backup folder aside only).

Internal compatibility (unchanged until a future major release): `biopentra_inbox_*` options/tables, REST `biopentra-support/v1`, capability `manage_biopentra_inbox`, admin menu slug `biopentra-inbox`.

**Do not** activate both plugin folders at once. See `docs/rename-migration-plan.md` for history and `docs/deployment-checklist.md` for deploy steps.

## Development

```bash
docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli \
  sh -c 'for f in $(find . -name "*.php" -not -path "./docs/*"); do php -l "$f"; done'
```

## Documentation

| Doc | Purpose |
|-----|---------|
| [docs/architecture-audit.md](docs/architecture-audit.md) | Structure, tables, hooks |
| [docs/security-review.md](docs/security-review.md) | Secrets, REST, permissions |
| [docs/rename-migration-plan.md](docs/rename-migration-plan.md) | biopentra → Fluent IMAP cutover |
| [docs/deployment-checklist.md](docs/deployment-checklist.md) | ZIP deploy steps |
| [docs/worker-integration.md](docs/worker-integration.md) | Proton Bridge + mail worker |
| [docs/rollback-plan.md](docs/rollback-plan.md) | Rollback and data |

## Repository

`git@github.com:magpern/fluent-imap-support-desk.git`

## License

GPL-2.0-or-later
