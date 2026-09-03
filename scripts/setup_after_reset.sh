#!/usr/bin/env bash
# Re-provisions the dev toolchain after a sandbox reset.
# Snapshots persist /home/user source code and var/teako.db only; the PHP CLI,
# node_modules and the compiled public/build assets do NOT survive a reset.
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> PHP toolchain"
if ! command -v php >/dev/null 2>&1; then
  sudo apt-get update >/dev/null
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
    php-cli php-sqlite3 php-gd php-mbstring php-xml php-intl php-curl php-zip php-bcmath
fi

echo "==> JS dependencies"
if [ ! -d node_modules ]; then
  npm install --no-audit --no-fund
fi

echo "==> Compiled assets (public/build is excluded from snapshots)"
npx encore production

echo "==> Caches"
php bin/console cache:clear
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console cache:warmup

echo "==> Run the dev server with:"
echo "    php -S 0.0.0.0:8000 -t public"
echo "    Admin login: admin@teako.mg / teako2026"
