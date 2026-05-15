# Worker integration (Proton Bridge + mail worker)

> Copied from biopentra-contact-inbox lineage. REST namespace remains `biopentra-support/v1`. Compose reference: Biopentra `woocommerce/docker-compose.yml`.

# Proton Mail Bridge and Docker (Biopentra Support Desk)

This document describes how to run **Proton Mail Bridge** in Docker alongside the **WordPress** service from this project’s `docker-compose.yml`, so the **Biopentra Support Desk** plugin can reach Bridge over an **internal Docker network** without publishing IMAP or SMTP ports to the public internet.

## Overview

- **Proton Bridge** exposes IMAP and SMTP to clients on your LAN or container network using credentials that Bridge generates (not your main Proton password).
- The WordPress container should resolve the Bridge hostname (for example `proton-bridge`) on a **private** `bridge-net` network.
- **Inbound import** can use the **published** **`ghcr.io/magpern/proton-mail-worker`** image (referenced from `docker-compose.yml` only; this repository does not ship worker source). It talks to Bridge over IMAP and posts to WordPress over HTTP (`biopentra-support/v1`), so you do not need **PHP `imap`** in the WordPress image for that path.
- The **MariaDB** container should stay on the **default** Compose network only; WordPress remains attached to **default** (to talk to `db`) and **bridge-net** (to talk to Bridge).
- **PHP `imap`** is required only for **legacy in-PHP IMAP import** inside WordPress. The stock `wordpress:php8.3-apache` image (Debian trixie) does **not** include `ext-imap` by default. Building the legacy UW `imap-2007f` sources against OpenSSL 3.x on trixie fails without patches, and mixing Debian **bookworm** `libc-client` packages into trixie conflicts on Kerberos/OpenSSL. **This repository’s `docker-compose.yml` does not ship a custom WordPress Dockerfile for `imap`**; use the external worker, or add the extension yourself (child image on a compatible base, host PHP, or another approach). See [PHP IMAP extension](#php-imap-extension-legacy-import-only) below.

## Docker Compose layout

| Service                 | Networks              | Ports                    |
|------------------------|------------------------|--------------------------|
| `db` (MariaDB)         | `default` only          | (none published by plugin doc) |
| `wordpress`          | `default` + `bridge-net` | `80:80` (or your choice) |
| `proton-bridge`        | `bridge-net` only       | **Do not** map IMAP/SMTP to the host for public access |
| `biopentra-mail-worker`| `default` + `bridge-net` | none (polls IMAP, calls WordPress HTTP) |
| `wpcli`                | `default` only          | none                     |

Keeping Bridge off the default network reduces accidental exposure; WordPress joins both networks so it can use `db` as hostname and `proton-bridge` as hostname. The mail worker joins **`default`** and **`bridge-net`** so it can resolve **`wordpress`** (REST) and **`proton-bridge`** (IMAP). Compose sets **`API_BASE_URL`** to the plugin REST base URL inside WordPress.

## External mail worker (`biopentra-mail-worker`)

The Compose file runs **`biopentra-mail-worker`** from the **published** image **`ghcr.io/magpern/proton-mail-worker:latest`** (no `build:` stanza in this repository). Configure it only via the **`environment`** entries in `docker-compose.yml` and secrets in your host **`.env`** (see `woocommerce/.env.example`).

Typical behavior (see upstream image documentation for exact env contract):

1. Connects to **Bridge IMAP** on the internal Docker network.
2. Searches the mailbox (Compose defaults include **`UNSEEN`**) and posts each candidate message to **`POST /wp-json/biopentra-support/v1/messages/import`** with the bearer token supplied as **`API_TOKEN`**.
3. May report status to **`POST /wp-json/biopentra-support/v1/worker/status`**, depending on image version.

### Worker internal HTTP (not published)

The published worker image exposes a small **internal HTTP** server (default port **8080**) for operations **inside the Docker network only**. Compose sets **`WORKER_HTTP_PORT: "8080"`** on the **`biopentra-mail-worker`** service; **do not** add a `ports:` mapping for this port — it must not be exposed to the public internet.

| Method | Path | Auth | Purpose |
|--------|------|------|--------|
| `GET` | `/health` | None | Liveness / readiness JSON (expect HTTP 200 and `"ok": true` when healthy). |
| `POST` | `/poll` | None (internal network only) | On-demand mailbox check (same as periodic runs; **not** for public exposure). |

**Internal URLs** (from other containers on the same Compose networks, e.g. `wordpress` on `default` + `bridge-net`):

- `http://biopentra-mail-worker:8080/health`
- `http://biopentra-mail-worker:8080/poll`

**WordPress admin:** **Support Desk → Settings → Sync / Worker** includes **Check worker health** (internal `GET /health` from PHP) and **Check for new messages now**, which sends an unauthenticated internal **`POST /poll`** to the mail worker. WordPress does **not** need the worker API token in its environment for that button — only network reachability on Docker.

**Security model:** **`/poll`** and **`/health`** must remain **unpublished** (no `ports:` for worker HTTP). **Bearer `API_TOKEN`** is still required for worker → WordPress **`POST .../messages/import`** and **`POST .../worker/status`** (configure **`API_TOKEN`** from host **`BIOPENTRA_WORKER_TOKEN`** on the **`biopentra-mail-worker`** service only).

**Host CLI (optional):** you can also trigger **`POST /poll`** from inside the worker container, for example:

```bash
docker compose exec biopentra-mail-worker \
  sh -lc 'curl -s -X POST http://localhost:8080/poll'
```

If the worker image does not ship `curl`:

```bash
docker compose exec biopentra-mail-worker \
  python - <<'PY'
import urllib.request
req = urllib.request.Request("http://localhost:8080/poll", method="POST")
print(urllib.request.urlopen(req).read().decode())
PY
```

**WordPress setup**

1. In **Support Desk → Settings → Sync / Worker**, set the import driver to **Worker**, enable **Worker import**, and **Generate/rotate** the token. Copy the plaintext token once.
2. Put the same value in **`BIOPENTRA_WORKER_TOKEN`** in your host `.env`; Compose passes it to the worker container as **`API_TOKEN`** for REST calls **into** WordPress (import + status). The **`wordpress`** service does **not** need this variable for the dashboard mailbox check.
3. Set **`PROTON_BRIDGE_IMAP_USER`** and **`PROTON_BRIDGE_IMAP_PASS`** in `.env` to the same Bridge mailbox username and Bridge-generated password as in **Email / Proton Bridge** (or inject equivalent secrets another way).

**Health check**

`GET http://YOUR_SITE/wp-json/biopentra-support/v1/health` returns JSON including `worker_token_configured`.

**Dedupe**

Imports require at least one of **`message_id`** or **`imap_dedupe_key`**. The worker always computes **`imap_dedupe_key`** from folder + UIDVALIDITY + UID (SHA1, same as the PHP plugin), so messages without a Message-ID still import safely.

## Example `proton-bridge` service

The repository `woocommerce/docker-compose.yml` defines Bridge with **named volumes** so login state, GnuPG material, and `pass` store survive container recreation:

```yaml
  proton-bridge:
    image: ghcr.io/magpern/proton-bridge:latest
    container_name: proton-bridge
    restart: unless-stopped
    volumes:
      - bridge_config:/root/.config/protonmail
      - bridge_gnupg:/root/.gnupg
      - bridge_pass:/root/.password-store
    networks:
      - bridge-net
    stdin_open: true
    tty: true
```

Top-level volume declarations: `bridge_config`, `bridge_gnupg`, `bridge_pass` (alongside existing `db_data` and `wp_data`). **No** host ports are published for IMAP/SMTP. Adjust mount paths only if your Bridge image documents different directories.

## Bridge first-time login (typical flow)

Exact commands depend on the Bridge image; a common pattern is:

1. Start the stack (`docker compose up -d`).
2. **Stop** the Bridge background daemon if the image starts it automatically (so you can use the CLI).
3. Run an **interactive shell** in the Bridge container (`docker compose exec proton-bridge sh` or the image’s documented shell).
4. Run the Bridge **CLI** (often `bridge` or as documented by the image).
5. Run **`login`** and complete two-factor / device steps as prompted.
6. Run **`info`** (or equivalent) and copy the **generated IMAP and SMTP credentials** (host, ports, username, password).
7. **Restart** the Bridge daemon/service so it listens for connections from WordPress.

Use the **Bridge-generated password** in WordPress — not your normal Proton account password.

## WordPress plugin settings (defaults aligned with Bridge)

In **Support Desk → Settings → Email / Proton Bridge**:

| Field        | Typical value     |
|-------------|-------------------|
| IMAP host   | `proton-bridge`   |
| IMAP port   | `2143`            |
| SMTP host   | `proton-bridge`   |
| SMTP port   | `2025`            |
| Username    | Your Proton **email address** (as shown by Bridge `info`) |
| Password    | **Bridge-generated** password from `info` |

IMAP mailbox and search defaults (for example `INBOX` and `UNSEEN`) are on the same settings screen.

## PHP IMAP extension (legacy import only)

The plugin’s **in-PHP** IMAP sync **requires** the PHP **`imap`** extension. The recommended **Worker** import path uses the **`biopentra-mail-worker`** container instead and does **not** need `ext-imap` in WordPress.

When the import driver is **PHP IMAP (legacy)** and the extension is missing, import is disabled and the admin shows a notice.

Verify inside the WordPress container:

```bash
docker compose exec wordpress php -m | grep -i imap
```

### Why the default Compose file does not build `imap`

The `wordpress` service uses `wordpress:php8.3-apache`. That image is based on **Debian trixie**, where:

- `docker-php-ext-install imap` is not available out of the box (no compatible `libc-client` dev stack in trixie in the same way older Debian releases shipped it).
- Building the UW **`imap-2007f`** tarball against **OpenSSL 3.5** fails on current compilers/OpenSSL headers (for example incomplete `X509` typedefs and legacy locking helpers).

So **networking** for Proton Bridge is wired in `docker-compose.yml`, but **PHP `imap` is a manual operator concern** unless you maintain a private child image.

### Practical ways to get `imap`

1. **Private Docker image** — Use a `FROM` line that matches a Debian/Ubuntu release where `libc-client2007e-dev` (or your distro’s equivalent) installs cleanly next to your PHP/OpenSSL stack, then run `docker-php-ext-configure imap` and `docker-php-ext-install imap` as documented for the official PHP Docker images. Pin and test your image; avoid mixing bookworm and trixie Kerberos/OpenSSL dev packages on one image without expert dependency pinning.
2. **Host or alternate PHP** — Run WordPress elsewhere with `php-imap` (or equivalent) already enabled.
3. **Defer IMAP** — Use Fluent migration and ticket UI without Docker IMAP until you have a suitable PHP runtime.

Example **shape** of a `Dockerfile` you might maintain privately (package names **must** match your base image; this is **not** validated in this repository):

```dockerfile
FROM wordpress:php8.3-apache
# When your base OS has working libc-client + ssl dev headers:
# RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
#  && docker-php-ext-install imap
```

Point `docker-compose.yml` at your image with `build:` and/or a private registry `image:` tag once `php -m` shows `imap`.

## SMTP scope

Under **Email / Proton Bridge → SMTP scope**:

- **`plugin_only`** — Only mail sent while the plugin marks an outbound “plugin mail” window (support desk replies) uses Bridge SMTP settings.
- **`all_wp_mail`** — Every `wp_mail()` call may use Bridge SMTP; use only if you intend site-wide mail through Bridge.

## WP-Cron vs real cron

When the import driver is **PHP IMAP (legacy)**, WordPress schedules `biopentra_inbox_imap_sync` according to **Sync / Worker** settings. For predictable import on production hosts, trigger cron from the system scheduler, for example:

```cron
*/5 * * * * curl -s "https://example.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
```

Replace `https://example.com` with your site URL. You may set `DISABLE_WP_CRON` in `wp-config.php` when using a real cron job.

With the **Worker** driver, inbound mail is imported by the **`biopentra-mail-worker`** process (poll interval from its environment); WordPress cron is not used for IMAP polling in that mode.

## Security notes

- Keep Bridge on an **internal** Docker network; **do not** publish IMAP/SMTP ports to the public internet unless you fully understand the risk.
- **Bridge credentials are sensitive** — protect database backups and anyone with access to **Support Desk → Settings**.
- The plugin may relax **TLS certificate verification** for local Bridge / self-signed certificates when sending via SMTP; that is appropriate only on trusted networks, not on untrusted paths.

## Troubleshooting

| Symptom | Things to check |
|--------|------------------|
| “PHP IMAP extension is not enabled” | Shown when the driver is **PHP IMAP (legacy)**. Use the **Worker** driver + `biopentra-mail-worker`, or install **ext-imap** in PHP. |
| Authentication failures | Use the **Bridge `info`** password, not the Proton web password; username is usually the full email. |
| No mail imported | Confirm Bridge completed **login** and the daemon is running; for Worker mode, check worker logs and `GET .../biopentra-support/v1/health`. |
| REST 401 / “token not configured” | Generate a worker token in **Sync / Worker** and set **`BIOPENTRA_WORKER_TOKEN`** in `.env` (mapped to **`API_TOKEN`** for the worker service). |
| Worker “Check worker health” fails from wp-admin | WordPress must reach **`http://biopentra-mail-worker:8080/health`** on the Compose network (attach **`wordpress`** to **`default`** + **`bridge-net`** as in this repo). If you run WordPress elsewhere, use worker logs or `docker compose exec` instead. |
| Sync appears stuck | A **sync lock** transient (`biopentra_inbox_imap_sync_lock`) clears after a few minutes if a run crashed; uninstall with “delete on uninstall” also removes it. Applies to **PHP IMAP** cron sync. |
| Cron never runs | For **PHP IMAP** import: traffic-driven WP-Cron, or server cron not hitting `wp-cron.php`; check **Sync / Worker** and server logs. With the **Worker** driver, polling is done by the worker container, not WP-Cron. |
| SMTP conflicts | Another SMTP plugin or `all_wp_mail` + Bridge may override global mail; try **`plugin_only`** first or disable conflicting plugins for testing. |

## Manual compose changes (if you did not apply the repo patch)

If your `docker-compose.yml` was not updated, add:

1. Top-level **`networks:`** with **`bridge-net:`** (driver `bridge`).
2. **`proton-bridge`** service on **`bridge-net` only**, **no** `ports:` for IMAP/SMTP.
3. **`wordpress`** → **`networks: [ default, bridge-net ]`** (explicit `default` keeps `db` reachable).
4. Leave **`db`** on the implicit **`default`** network only (do not attach `db` to `bridge-net` unless you have a specific reason).
5. Optional: add **`biopentra-mail-worker`** with **`image: ghcr.io/magpern/proton-mail-worker:latest`** (no local build), on **`default`** + **`bridge-net`**, **`API_BASE_URL`** pointing at the REST base URL under WordPress, **`API_TOKEN`** from **`BIOPENTRA_WORKER_TOKEN`**, and Bridge IMAP credentials; see [External mail worker](#external-mail-worker-biopentra-mail-worker).

To add **PHP imap**, introduce a **`build:`** section (or a prebuilt image) for the `wordpress` service **after** you have a Dockerfile that succeeds on your chosen base OS; see [PHP IMAP extension](#php-imap-extension-legacy-import-only).
