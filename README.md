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

## Production rename warning

**Biopentra production today** uses the folder slug `biopentra-contact-inbox` and plugin file `biopentra-contact-inbox.php`. **Do not rename production** until you follow `docs/rename-migration-plan.md` (staged cutover, backups, worker token, REST clients).

Installing this ZIP as `fluent-imap-support-desk` alongside the old folder would register a **second** plugin — avoid on production until migration.

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
