# Hostinger Deployment

This repo is ready for a GitHub-driven deploy to Hostinger without exposing local-only config in Git.

## Recommended flow

1. Keep working locally in this repo.
2. Push commits to GitHub.
3. Let GitHub Actions sync the Barebones WordPress plugins and mu-plugins to Hostinger over FTP/SFTP.
4. Keep environment-specific secrets in `wp-config-local.php` on the server.

This avoids relying on Hostinger's built-in Git deployment for an already-populated `public_html` directory.

## GitHub secrets

Create these repository secrets before enabling the workflow:

- `HOSTINGER_HOST`: your Hostinger server hostname
- `HOSTINGER_PORT`: `22` for SFTP unless Hostinger shows a different port
- `HOSTINGER_PROTOCOL`: `sftp`
- `HOSTINGER_USERNAME`: your Hostinger SSH/SFTP username
- `HOSTINGER_PASSWORD`: your Hostinger SSH/SFTP password
- `HOSTINGER_REMOTE_DIR`: the actual web root on Hostinger. For this project, prefer `public_html` unless Hostinger explicitly requires an absolute path.

The workflow lives at `.github/workflows/deploy-hostinger.yml`. It is manual-only since 2026-10-14: its original target (the first Hostinger site on the temporary domain) was retired, so retarget `HOSTINGER_REMOTE_DIR` and switch it to dynamic `bw-*` discovery before using it for the production go-live.

## Staging (bbaprintshop.com)

Staging has its own push-to-deploy workflow: `.github/workflows/deploy-staging.yml`, which runs on pushes to the `staging` branch (or manual dispatch, with an optional dry run). It runs the same script as local deploys, `wordpress/scripts/deploy-staging.sh`, over SSH with a dedicated deploy key, so both paths ship exactly the same files: **all** `bw-*` plugins (discovered dynamically — new plugins deploy without editing anything), `fm-surcharges`, and our mu-plugins. Hostinger's own `hostinger-*` mu-plugins are never sent or deleted.

Repository secrets (GitHub → Settings → Secrets and variables → Actions):

- `STAGING_SSH_KEY`: the private half of the GitHub Actions deploy key. Its public half is added in hPanel → Advanced → SSH Access.
- `STAGING_DEPLOY_ENV`: the contents of `wordpress/scripts/deploy.env` (SSH user, host, port, remote dir).

The workflow pins the server's SSH host keys (hashed, so the address isn't published); if Hostinger rotates them, regenerate that block as described in the workflow file. Staging no longer uses the FTP `HOSTINGER_*` secrets.

### Day-to-day flow

```
# work on feature branches as usual, then:
git checkout staging
git merge feature/dtf-builder
git push origin staging          # → GitHub Actions deploys to bbaprintshop.com
```

### Instant deploys without GitHub (local rsync)

`wordpress/scripts/deploy-staging.sh` deploys the same file set straight from your Mac over SSH — useful mid-iteration. One-time setup:

1. hPanel → Advanced → **SSH Access** → enable, note host/port (usually 65002).
2. Create `wordpress/scripts/deploy.env` (gitignored) with `STAGING_SSH_USER`, `STAGING_SSH_HOST`, `STAGING_SSH_PORT`, `STAGING_REMOTE_DIR`.
3. Optional but recommended: `ssh-copy-id -p 65002 user@host` so you're not typing the password every deploy.

Then `./wordpress/scripts/deploy-staging.sh` (add `--media` to sync uploads, `--dry-run` to preview). The script also purges the LiteSpeed cache after upload.

### What git deploy does NOT cover

- **Database** — products, Astra/Elementor settings, menus. Changes made in the local DB must be re-applied on staging (wp-admin or wp-cli) or moved with a fresh DB export/import.
- **Media** — synced with `deploy-staging.sh --media` (uploads are gitignored; 1.2G+).
- **Server-only files** — `wp-config-local.php`, `object-cache.php` (LiteSpeed drop-in), `.htaccess` — never touched by deploys.

## Preview/password protection

Public WordPress pages can be gated with server-only config. Create `wordpress/public_html/wp-config-local.php` on the Hostinger server with values like:

```php
<?php
define( 'BBA_PREVIEW_GATE_ENABLED', true );
define( 'BBA_PREVIEW_GATE_USERNAME', 'barebones' );
define( 'BBA_PREVIEW_GATE_PASSWORD', 'replace-with-a-strong-password' );
```

That file is already git-ignored, so the credentials stay off GitHub.

## Notes

- The deploy workflow intentionally uploads only `mu-plugins` and the Barebones plugins instead of the full WordPress install.
- The deploy workflow intentionally does not overwrite `wp-config.php`, because it may contain production-only database settings on Hostinger.
- Local override files and runtime directories are skipped.
- If you later want Git to fully own WordPress core, plugins, and uploads, we should do that as a separate cleanup pass instead of mixing it into the first production rollout.
- Hostinger also documents directory-level password protection in hPanel if you want a second layer in front of the entire site.
