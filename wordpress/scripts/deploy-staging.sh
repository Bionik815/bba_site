#!/usr/bin/env bash
#
# Deploy code to the bbaprintshop.com staging site over SSH (rsync).
# .github/workflows/deploy-staging.yml runs this same script, so local
# and GitHub deploys behave identically: bw-* plugins, fm-surcharges and
# our mu-plugins. Hostinger's own mu-plugins (hostinger-*) are left alone.
# Code only — DB and settings are not touched. Media is opt-in with --media.
#
# Usage:
#   ./deploy-staging.sh            # deploy code
#   ./deploy-staging.sh --media    # also sync wp-content/uploads (1.2G+, slow)
#   ./deploy-staging.sh --dry-run  # show what would change, upload nothing
#
# Config: wordpress/scripts/deploy.env (gitignored). Example:
#   STAGING_SSH_USER=u632291655
#   STAGING_SSH_HOST=us-imm-web1743.main-hosting.eu   # or server IP from hPanel
#   STAGING_SSH_PORT=65002
#   STAGING_REMOTE_DIR=domains/bbaprintshop.com/public_html

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC="$(cd "${SCRIPT_DIR}/../public_html" && pwd)"
ENV_FILE="${SCRIPT_DIR}/deploy.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "Missing ${ENV_FILE} — copy the example block from this script's header." >&2
  exit 1
fi
# shellcheck disable=SC1090
source "$ENV_FILE"

: "${STAGING_SSH_USER:?set in deploy.env}"
: "${STAGING_SSH_HOST:?set in deploy.env}"
: "${STAGING_SSH_PORT:=65002}"
: "${STAGING_REMOTE_DIR:?set in deploy.env}"

WITH_MEDIA=0
DRY=""
for arg in "$@"; do
  case "$arg" in
    --media)   WITH_MEDIA=1 ;;
    --dry-run) DRY="--dry-run" ;;
    *) echo "Unknown flag: $arg" >&2; exit 1 ;;
  esac
done

REMOTE="${STAGING_SSH_USER}@${STAGING_SSH_HOST}"
RSH="ssh -p ${STAGING_SSH_PORT}"
RSYNC_OPTS=(-az --itemize-changes $DRY -e "$RSH")
# Never ship Mac/iCloud clutter: Finder metadata and "name 2.ext" sync-conflict
# copies. Excluded files are also protected from --delete on the server.
JUNK=(--exclude '.DS_Store' --exclude '* [0-9].*' --exclude '* [0-9]')

echo "==> Deploying code to ${REMOTE}:${STAGING_REMOTE_DIR}"

# mu-plugins: mirror ours with delete. Hostinger auto-updates its own
# hostinger-* files on the server; the repo copies go stale, so never send or
# delete them.
rsync "${RSYNC_OPTS[@]}" --delete "${JUNK[@]}" --exclude 'hostinger-*' \
  "$SRC/wp-content/mu-plugins/" \
  "$REMOTE:$STAGING_REMOTE_DIR/wp-content/mu-plugins/"

# every bw-* plugin + fm-surcharges (ours entirely — mirror with delete)
for dir in "$SRC"/wp-content/plugins/bw-* "$SRC"/wp-content/plugins/fm-surcharges; do
  [ -d "$dir" ] || continue
  name="$(basename "$dir")"
  echo "==> $name"
  rsync "${RSYNC_OPTS[@]}" --delete "${JUNK[@]}" \
    "$dir/" \
    "$REMOTE:$STAGING_REMOTE_DIR/wp-content/plugins/$name/"
done

if [ "$WITH_MEDIA" = "1" ]; then
  echo "==> Syncing media library (this can take a while)"
  rsync "${RSYNC_OPTS[@]}" \
    --exclude 'cache/' --exclude '.DS_Store' \
    "$SRC/wp-content/uploads/" \
    "$REMOTE:$STAGING_REMOTE_DIR/wp-content/uploads/"
fi

# Purge LiteSpeed page cache so changes show immediately (a dry run changes nothing)
if [ -n "$DRY" ]; then
  echo "==> Dry run — cache left alone"
else
  echo "==> Purging LiteSpeed cache"
  $RSH "$REMOTE" "cd $STAGING_REMOTE_DIR && (wp litespeed-purge all 2>/dev/null || wp cache flush 2>/dev/null || rm -rf wp-content/litespeed/cache 2>/dev/null || true)"
fi

echo "==> Done${DRY:+ (dry run — nothing uploaded)}"
