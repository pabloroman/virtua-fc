# =============================================================================
# Base stage: PHP runtime with extensions.
#
# This image serves local development only — production runs on Laravel Forge
# (not containerized), so there is no production build stage here.
# =============================================================================
FROM dunglas/frankenphp:php8.5-alpine AS base

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
# Vite handles frontend hot reload.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
