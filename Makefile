DC_DEV = docker compose -f docker-compose.yml -f docker-compose.dev.yml
DC_PROD = docker compose -f docker-compose.yml -f docker-compose.prod.yml

.PHONY: dev dev-build dev-down prod prod-build prod-down logs setup artisan composer npm key

# Development
dev:
	$(DC_DEV) up

dev-build:
	$(DC_DEV) up --build

dev-down:
	$(DC_DEV) down

# Production
prod:
	$(DC_PROD) up -d

prod-build:
	$(DC_PROD) up -d --build

prod-down:
	$(DC_PROD) down

# Wrappers — pass any arguments after the target
# Usage: make artisan cmd="migrate:fresh --seed"
artisan:
	$(DC_DEV) exec app php artisan $(cmd)

composer:
	$(DC_DEV) exec app composer $(cmd)

npm:
	$(DC_DEV) exec vite npm $(cmd)

# Utilities
logs:
	$(DC_DEV) logs -f

key:
	$(DC_DEV) exec app php artisan key:generate

setup:
	cp -n .env.docker .env 2>/dev/null || true
	$(DC_DEV) run --rm app composer install
	$(DC_DEV) run --rm app php artisan key:generate
