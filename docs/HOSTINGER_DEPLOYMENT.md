# Hostinger Deployment

This repo is ready for a GitHub-driven deploy to Hostinger without exposing local-only config in Git.

## Recommended flow

1. Keep working locally in this repo.
2. Push commits to GitHub.
3. Let GitHub Actions sync `wordpress/public_html` to Hostinger over SFTP.
4. Keep environment-specific secrets in `wp-config-local.php` on the server.

This avoids relying on Hostinger's built-in Git deployment for an already-populated `public_html` directory.

## GitHub secrets

Create these repository secrets before enabling the workflow:

- `HOSTINGER_HOST`: your Hostinger server hostname
- `HOSTINGER_PORT`: `22` for SFTP unless Hostinger shows a different port
- `HOSTINGER_PROTOCOL`: `sftp`
- `HOSTINGER_USERNAME`: your Hostinger SSH/SFTP username
- `HOSTINGER_PASSWORD`: your Hostinger SSH/SFTP password
- `HOSTINGER_REMOTE_DIR`: the full remote path for the site, for example `/home/u123456789/domains/example.com/public_html`

The workflow lives at `.github/workflows/deploy-hostinger.yml` and runs on pushes to `main` or manual dispatch.

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

- The deploy workflow intentionally skips `wp-content/uploads/` so production media is not overwritten by local files.
- It also skips local override files and runtime/cache directories.
- If you later want Git to fully own WordPress core, plugins, and uploads, we should do that as a separate cleanup pass instead of mixing it into the first production rollout.
- Hostinger also documents directory-level password protection in hPanel if you want a second layer in front of the entire site.
