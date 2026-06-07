# Production Sync Checklist

Items to verify/do on Hostinger at the **final sync** (we develop + test in local Docker until then).
Created from the 2026-06-05 security audit + red-tier remediation.

## Secrets / config (manual — wp-config.php is NOT deployed by CI)
- [ ] **Salts set on production.** Replace the `change_me_*` key/salt fallbacks with the 9 strong values
      (generated 2026-06-05; stored in hPanel, not in this repo). Saving logs out all sessions — that's the
      confirmation it applied. Use either hardcoded `define()`s OR `WP_AUTH_SALT` etc. env vars, not both.
- [ ] **`wp-config-local.php` must NOT exist on production.** It sets `WP_HOME`/`WP_SITEURL` from the raw
      `Host` header (host-header injection → password-reset link poisoning). It's a local-Docker-only helper.
      If present on prod: delete it (WP falls back to Settings → General URLs) or hardcode the real domain.
- [ ] Confirm production DB creds come from real `WP_DB_*` env vars (they do — site connects), not the
      `change_me_*` fallbacks.
- [ ] (Minor) Consider setting `WP_COOKIEHASH` to a real secret; today it falls back to `md5(db_name)`.

## Preview gate (deploys via mu-plugins)
- [ ] Decide gate state for launch: keep enabled for private preview, or disable for public. The audit fix
      makes the gate also cover `/wp-json` + `/xmlrpc.php` for anonymous users while it is enabled.

## Deploy pipeline
- [x] Added to the lftp mirror in `deploy-hostinger.yml` (were triggering CI but not uploading):
      `bw-player-packs`, `bw-product-templates`, `bw-client-codes-export`, `bw-creator-store-builder`, `fm-surcharges`.
- [ ] (Recommended) Generalize the mirror to ALL custom plugins so we stop adding them one-by-one
      every time a new plugin is touched.
- [ ] (Minor) `deploy-hostinger.yml` uses `ssl:verify-certificate no` — consider enabling cert verification.

## Repo hygiene
- [x] `.gitignore` now ignores `wp-config-local.php` in any location (was wrong path → unignored).

## Code fixes already in the tree (will deploy via CI at sync)
Red tier:
- [x] bw-player-packs: cart nonce + server-side min/max qty enforcement
- [x] bw-gang-sheet-builder: upload count/size caps, ignore client MIME, getimagesize content check, per-IP rate limit
- [x] bw-product-templates: `esc_url()` XSS fix
- [x] bba-preview-gate: REST/XML-RPC no longer exempt; `REQUEST_METHOD` guarded

Orange tier:
- [x] bw-client-codes-export: shared `csv_safe()` neutralizes CSV/formula injection in all 3 exporters
- [x] bw-creator-store-builder: requires `publish_products` + verifies `_bw_is_template` before cloning/publishing
- [x] fm-surcharges: idempotent recalc via base-price cache (replaces fragile `did_action>=2` guard)
- [x] bw-player-packs: per-child namespaced variation fields (fixes multi-variable-item collisions)

Critical (found during verification):
- [x] bw-player-packs LOAD-ORDER: the plugin was **dead on the live site** — its include-time
      `class_exists('WooCommerce')` guard ran before WooCommerce loaded, so the class that registers
      every hook (`BW_Player_Packs_Run`) was never declared and the product type never appeared.
      Fixed by instantiating on `plugins_loaded` (priority 25) instead. **After sync, verify the
      "Player Pack" product type now shows in Products → Add New → product type dropdown.**

> Verified end-to-end in local Docker via a WP-CLI harness: 11/11 assertions pass (player-packs loads,
> variation namespacing resolves distinct variations, surcharge stays idempotent across 8 recalcs,
> store-builder cap gate works). CSV sanitizer unit-tested 8/8.
