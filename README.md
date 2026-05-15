# Fluent IMAP Support Desk

Fluent Forms + IMAP/SMTP support desk bridge with optional Proton Bridge / external mail worker support.

WordPress plugin that turns **Fluent Forms** submissions into threaded **support tickets**, with **IMAP inbound** and **SMTP outbound** mail (including **Proton Mail Bridge** and an optional **mail worker** container).

**Compatibility lineage:** evolved from `biopentra-contact-inbox` (v2.0.0). Database tables, option keys, cron hooks, capabilities, and REST namespace remain **`biopentra_*` / `biopentra-support/v1`** until a future major migration — see [docs/compatibility-identifiers.md](docs/compatibility-identifiers.md).

## Features

- Fluent Forms → ticket creation and threading
- Admin support desk (tickets, replies, settings)
- IMAP sync and external **mail worker** import (`POST /wp-json/biopentra-support/v1/messages/import`)
- Proton Bridge–friendly Docker layout (internal IMAP/SMTP, no public mail ports)
- Branded HTML reply templates, archive retention, Fluent migration tools

## Requirements

| Component | Version |
|-----------|---------|
| WordPress | 6.0+ |
| PHP | 7.4+ |
| WooCommerce | Optional (store logo / address in email templates) |
| Fluent Forms | Required for form→ticket bridge |
| MariaDB/MySQL | Custom tables on activation |

Optional: Docker Compose stack with `proton-bridge` and a mail worker image — see [docs/worker-integration.md](docs/worker-integration.md).

## Private deployment (ZIP)

```bash
./scripts/build-zip.sh
# → builds/fluent-imap-support-desk-2.0.0.zip
```

Upload via **Plugins → Add New → Upload**, or rsync `fluent-imap-support-desk/` into `wp-content/plugins/`.

## Development

```bash
docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli \
  sh -c 'for f in $(find . -name "*.php" -not -path "./docs/*"); do php -l "$f"; done'
```

## Documentation

| Doc | Purpose |
|-----|---------|
| [docs/compatibility-identifiers.md](docs/compatibility-identifiers.md) | Internal `biopentra_*` names (unchanged in 2.x) |
| [docs/architecture-audit.md](docs/architecture-audit.md) | Structure, tables, hooks |
| [docs/security-review.md](docs/security-review.md) | Secrets, REST, permissions |
| [docs/rename-migration-plan.md](docs/rename-migration-plan.md) | Folder cutover history |
| [docs/deployment-checklist.md](docs/deployment-checklist.md) | ZIP deploy steps |
| [docs/worker-integration.md](docs/worker-integration.md) | Proton Bridge + mail worker |
| [docs/rollback-plan.md](docs/rollback-plan.md) | Rollback and data |

## Repository

`git@github.com:magpern/fluent-imap-support-desk.git`

## License

GPL-2.0-or-later
