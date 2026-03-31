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

# In development, install dependencies (vendor is an anonymous volume)
if [ "$APP_ENV" != "production" ] && [ -f composer.json ] && command -v composer >/dev/null 2>&1; then
    if [ ! -f vendor/autoload.php ]; then
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

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Cache configuration in production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

echo "Starting application..."
exec "$@"
