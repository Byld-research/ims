#!/usr/bin/env bash
#
# Deploy the current working tree to the production VPS (docs/deployment.md).
#
#   scripts/deploy.sh                      # deploy
#   IMS_HOST=ubuntu@1.2.3.4 scripts/deploy.sh
#
# Builds the assets here, uploads the code with rsync, then on the server: installs PHP
# dependencies, runs migrations, refreshes caches and reloads PHP-FPM. The .env on the
# server, its storage and the backups are never touched. Tests run first; a failure stops it.
set -euo pipefail

HOST="${IMS_HOST:-ubuntu@144.217.90.113}"
KEY="${IMS_SSH_KEY:-$HOME/.ssh/vps_ims}"
APP_DIR="${IMS_APP_DIR:-/var/www/ims}"
SSH=(ssh -i "$KEY" -o IdentitiesOnly=yes "$HOST")

cd "$(dirname "$0")/.."

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Uncommitted changes. Commit first, so the server runs a known version." >&2
    exit 1
fi

echo "→ Tests"
php artisan test --compact

echo "→ Assets"
npm run build >/dev/null

echo "→ Upload $(git rev-parse --short HEAD) to $HOST"
rsync -az --delete -e "ssh -i $KEY -o IdentitiesOnly=yes" \
    --exclude .git --exclude node_modules --exclude vendor --exclude .env --exclude tests --exclude docs \
    --exclude .phpunit.cache --exclude .phpunit.result.cache --exclude .DS_Store \
    --exclude 'storage/logs/*' --exclude 'storage/app/backups' --exclude 'storage/framework/cache/data/*' \
    --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' --exclude 'storage/framework/testing' \
    ./ "$HOST:$APP_DIR/"

echo "→ Install and migrate"
"${SSH[@]}" "set -e; cd $APP_DIR
    composer install --no-dev --optimize-autoloader --no-interaction --quiet
    sudo chgrp -R www-data storage bootstrap/cache
    sudo -u www-data php artisan down --retry=15 >/dev/null || true
    sudo -u www-data php artisan migrate --force
    sudo -u www-data php artisan optimize >/dev/null
    sudo -u www-data php artisan up >/dev/null
    sudo systemctl reload php8.5-fpm
    echo \"$(git rev-parse --short HEAD)\" > REVISION"

echo "✓ Deployed $(git rev-parse --short HEAD)"
