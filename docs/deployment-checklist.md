# Deployment checklist — Fluent IMAP Support Desk

Private ZIP deploy. **Staging first** for any site currently on `biopentra-contact-inbox`.

---

## Pre-deploy

- [ ] Target version in `fluent-imap-support-desk.php` matches release tag / CHANGELOG
- [ ] `./scripts/build-zip.sh` succeeds
- [ ] PHP lint clean (Docker `php:8.2-cli`)
- [ ] ZIP contains single root folder `fluent-imap-support-desk/` (no `docs/`, `scripts/`, `.env`)
- [ ] **Production rename:** read `docs/rename-migration-plan.md` — do not install alongside old plugin
- [ ] DB backup: `./wp db export`
- [ ] Backup existing plugin folder (tar.gz)
- [ ] Worker token and `.env.worker` documented

---

## New site / staging (fresh folder)

1. Upload `builds/fluent-imap-support-desk-{version}.zip` via WP Admin, or:
   ```bash
   rsync -av fluent-imap-support-desk/ /path/to/wp-content/plugins/fluent-imap-support-desk/
   ```
2. `./wp plugin activate fluent-imap-support-desk`
3. Grant capability exists: `manage_biopentra_inbox` on administrator (activation hook)
4. Configure **Support Desk → Settings** (Fluent form, mail, worker)
5. `./wp cache flush`

---

## Example production site

**Folder cutover (2026-05-15):** deploy as **`fluent-imap-support-desk`** v2.0.0 under `wp-content/plugins/fluent-imap-support-desk/`.

- Internal IDs unchanged: `biopentra_inbox_*` options/tables, `biopentra-support/v1` REST, `manage_biopentra_inbox` — see [compatibility-identifiers.md](compatibility-identifiers.md)
- Legacy `biopentra-contact-inbox` plugin folder must not be active alongside this plugin

**Deploy fixes:** rsync from this repo → production `fluent-imap-support-desk/`, or release ZIP from `builds/`.

---

## Post-deploy verification

- [ ] `./wp plugin is-active fluent-imap-support-desk` (or legacy slug during transition)
- [ ] Admin menu **Support Desk** / configured display name
- [ ] `GET /wp-json/biopentra-support/v1/health` → 200, `worker_token_configured`
- [ ] Worker health from Settings (internal HTTP)
- [ ] Test outbound SMTP (Settings → test)
- [ ] Fluent form submission creates ticket
- [ ] No PHP Fatal in `docker compose logs wordpress --tail 100`

---

## Rollback

See `docs/rollback-plan.md`.
