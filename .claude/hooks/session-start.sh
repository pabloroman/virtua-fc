#!/bin/bash
#
# SessionStart hook for Claude Code on the web.
#
# Provisions the container so the test suite runs locally:
#   - PHP 8.5 if the apt repo is reachable (the project requires ^8.5; base
#     images may ship 8.4). If the PHP repo is blocked by the environment's
#     network policy, falls back to the system PHP and bypasses *only*
#     Composer's php version gate locally (CI still enforces 8.5).
#   - Composer + npm dependencies
#   - a .env with an app key
#   - a running PostgreSQL with the virtua_fc role/database, migrated
#
# Idempotent: safe to re-run. Only provisions in the remote (web) environment.
set -euo pipefail

# Only run in Claude Code on the web. Locally, developers manage their own env.
# (Set CLAUDE_CODE_REMOTE=true to force-run for validation.)
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}"

PHP_VERSION="8.5"
# Mirror the php8.4 extension set the image already ships with, on 8.5.
PHP_PACKAGES=(
    "php${PHP_VERSION}-cli" "php${PHP_VERSION}-common" "php${PHP_VERSION}-curl"
    "php${PHP_VERSION}-gd" "php${PHP_VERSION}-igbinary" "php${PHP_VERSION}-intl"
    "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-opcache"
    "php${PHP_VERSION}-pgsql" "php${PHP_VERSION}-readline" "php${PHP_VERSION}-redis"
    "php${PHP_VERSION}-sqlite3" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-zip"
)

# 1. Install PHP 8.5 + extensions, but only if the ondrej/php apt repo is
#    actually reachable. Some network policies block it (returns 403); in that
#    case we fall back to the system PHP rather than aborting the whole hook.
if ! command -v "php${PHP_VERSION}" >/dev/null 2>&1; then
    if curl -sf --max-time 10 -o /dev/null https://packages.sury.org/php/ 2>/dev/null; then
        echo "==> Installing PHP ${PHP_VERSION} and extensions"
        export DEBIAN_FRONTEND=noninteractive
        apt-get update -y && apt-get install -y --no-install-recommends "${PHP_PACKAGES[@]}" || \
            echo "!! PHP ${PHP_VERSION} install failed; continuing with system PHP."
    else
        echo "!! PHP ${PHP_VERSION} apt repo is unreachable (blocked by network policy)."
        echo "   Falling back to system PHP. To get a true 8.5 environment, allow"
        echo "   packages.sury.org / ppa.launchpadcontent.net in the environment's"
        echo "   network policy, or use a base image with PHP 8.5 preinstalled."
    fi
fi

# Make php8.5 the default `php` when it is available.
if command -v "php${PHP_VERSION}" >/dev/null 2>&1; then
    update-alternatives --install /usr/bin/php php "/usr/bin/php${PHP_VERSION}" 100 >/dev/null 2>&1 || true
    update-alternatives --set php "/usr/bin/php${PHP_VERSION}" >/dev/null 2>&1 || true
fi
echo "==> Using $(php -v | head -1)"

# 2. PHP dependencies. When the runtime is older than 8.5 (fallback path), the
#    composer.json "php": "^8.5" gate would abort the install, so bypass just
#    that platform requirement locally. CI installs on real 8.5 and is unaffected.
COMPOSER_FLAGS=(--no-interaction --prefer-dist --no-progress)
if [ "$(php -r 'echo PHP_VERSION_ID >= 80500 ? 1 : 0;')" != "1" ]; then
    echo "!! Runtime is $(php -r 'echo PHP_VERSION;') (< 8.5); bypassing Composer's php gate locally."
    COMPOSER_FLAGS+=(--ignore-platform-req=php)
fi
echo "==> composer install"
composer install "${COMPOSER_FLAGS[@]}"

# 3. JS dependencies.
echo "==> npm install"
npm install --no-audit --no-fund

# 4. Environment file + app key.
if [ ! -f .env ]; then
    echo "==> Creating .env from .env.example"
    cp .env.example .env
fi
if grep -qE '^APP_KEY=$' .env; then
    php artisan key:generate --ansi
fi

# 5. PostgreSQL: start the cluster and ensure the virtua_fc role + database exist.
echo "==> Ensuring PostgreSQL is running"
PG_VERSION="$(pg_lsclusters -h | awk 'NR==1{print $1}')"
PG_CLUSTER="$(pg_lsclusters -h | awk 'NR==1{print $2}')"
if [ "$(pg_lsclusters -h | awk 'NR==1{print $4}')" != "online" ]; then
    pg_ctlcluster "${PG_VERSION}" "${PG_CLUSTER}" start || true
fi

# Wait for the socket to accept connections (max ~15s).
for _ in $(seq 1 15); do
    pg_isready -q && break
    sleep 1
done

# Role + database, created idempotently as the postgres superuser.
sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='virtua_fc'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE ROLE virtua_fc LOGIN PASSWORD 'password' CREATEDB;"
sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='virtua_fc'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE virtua_fc OWNER virtua_fc;"

# 6. Migrate the schema (graceful = no-op if already migrated).
echo "==> Running migrations"
php artisan migrate --graceful --force --ansi

echo "==> Session setup complete"
