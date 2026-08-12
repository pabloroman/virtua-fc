#!/bin/sh
set -e

echo "Waiting for PostgreSQL..."
until php -r "new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '5432') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 1
done
echo "PostgreSQL is ready."

echo "Waiting for Redis..."
until php -r "try { \$r = new Redis(); \$r->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379)); \$r->ping(); exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
    sleep 1
done
echo "Redis is ready."

# vendor/ is an anonymous volume, so it starts empty on a fresh container.
if [ -f composer.json ] && command -v composer >/dev/null 2>&1; then
    if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
        echo "Installing Composer dependencies..."
        composer install --no-interaction
    fi
fi

# Ensure .env exists — prefer .env.docker (Docker-aware defaults) over .env.example
if [ ! -f .env ]; then
    if [ -f .env.docker ]; then
        echo "Creating .env from .env.docker..."
        cp .env.docker .env
    elif [ -f .env.example ]; then
        echo "Creating .env from .env.example..."
        cp .env.example .env
    fi
fi

# Run migrations only on the designated container. `app` and `horizon` share
# this image and start together, so exactly one of them may migrate — otherwise
# the two `migrate --force` runs race each other. Defaults to true so the `app`
# service needs no extra config; docker-compose.dev.yml sets it to false on
# `horizon`.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force
else
    echo "Skipping migrations (RUN_MIGRATIONS != true)."
fi

echo "Starting application..."
exec "$@"
