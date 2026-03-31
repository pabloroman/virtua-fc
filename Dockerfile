# =============================================================================
# Base stage: PHP runtime with extensions (shared by dev and production)
# =============================================================================
FROM dunglas/frankenphp:php8.4-alpine AS base

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    bcmath \
    intl \
    zip \
    gd \
    pcntl \
    opcache \
    mbstring \
    && rm -rf /tmp/* /var/cache/apk/*

WORKDIR /app

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]

# =============================================================================
# Development target: source code mounted via volumes, includes Node for Vite
# =============================================================================
FROM base AS dev

RUN apk add --no-cache nodejs npm

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 5173

# Use artisan serve in dev — no worker file needed, fresh PHP on each request.
# Vite handles frontend hot reload. Octane is a production optimization.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# =============================================================================
# Production build stages
# =============================================================================

# Build frontend assets
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY resources/ resources/
COPY vite.config.js ./
COPY public/ public/

RUN npm run build

# Install PHP dependencies
FROM composer:2 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize

# =============================================================================
# Production target: optimized image with built assets baked in
# =============================================================================
FROM base AS production

# Copy application from composer stage
COPY --from=composer-builder /app /app

# Copy built frontend assets from node stage
COPY --from=node-builder /app/public/build public/build

# Re-copy entrypoint (overwritten by COPY --from=composer-builder)
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Clear stale package cache from dev and re-discover for production
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && php artisan package:discover --ansi

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]
