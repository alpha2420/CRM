#!/usr/bin/env bash
# Deploy the latest code on the server:  sudo bash /var/www/crm/deploy/deploy.sh
# Safe to run repeatedly. Stops at the first error.
set -euo pipefail

cd "$(dirname "$0")/.."

# Run as the app's own user (www-data after install.sh), so new files stay writable.
OWNER="$(stat -c %U . 2>/dev/null || echo "$USER")"
if [[ $EUID -eq 0 && "$OWNER" != root ]]; then
    exec sudo -u "$OWNER" -H bash "$0" "$@"
fi

echo "→ Maintenance mode"
php artisan down --retry=15 || true

echo "→ Pulling code"
git pull --ff-only

echo "→ Installing dependencies"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "→ Migrating database (a backup runs first)"
php artisan backup:run --only-db --disable-notifications || echo "  (backup skipped — check BACKUP_* settings)"
php artisan migrate --force

echo "→ Caching config, routes and views"
php artisan optimize

echo "→ Restarting queue workers"
php artisan queue:restart

echo "→ Back online"
php artisan up

php artisan crm:health || echo "⚠ Some health checks need attention (see above)."
