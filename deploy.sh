#!/usr/bin/env bash
#
# Live store ko GitHub ke latest code par update karta hai (aaPanel / Linux server).
# Server par root SSH se chalayein:
#
#   bash /www/wwwroot/knorganic.site/deploy.sh
#
# Kisi aur folder ya PHP version ke liye:
#   SITE_DIR=/www/wwwroot/other.site PHP_BIN=/www/server/php/83/bin/php bash deploy.sh
#
set -euo pipefail

SITE_DIR="${SITE_DIR:-/www/wwwroot/knorganic.site}"
PHP_BIN="${PHP_BIN:-php}"
WEB_USER="${WEB_USER:-www}"

cd "$SITE_DIR"

echo "==> Maintenance mode ON"
"$PHP_BIN" artisan down --retry=30 || true
# Kuch bhi fail ho to bhi site wapas online ho jaye.
trap '"$PHP_BIN" artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Latest code (git pull)"
# Folder is owned by the web user; let root run git here without "dubious ownership" errors.
git config --global --get-all safe.directory 2>/dev/null | grep -qx "$SITE_DIR" || git config --global --add safe.directory "$SITE_DIR"
git config core.fileMode false
git pull --ff-only origin main

echo "==> Composer packages"
COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction

echo "==> Database migrations (data safe rehta hai)"
"$PHP_BIN" artisan migrate --force

echo "==> Photos: fast WebP copies"
"$PHP_BIN" artisan images:optimize || true

echo "==> Cache rebuild"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan optimize

echo "==> Permissions"
mkdir -p public/uploads
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache public/uploads
chmod -R ug+rwX storage bootstrap/cache public/uploads

"$PHP_BIN" artisan up
echo "==> Done! Site updated: $(git log -1 --format='%h %s')"
