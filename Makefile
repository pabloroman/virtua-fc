.PHONY: dev dev-build dev-down prod prod-build prod-down logs setup

# Development
dev:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml up

dev-build:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build

dev-down:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml down

# Production
prod:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

prod-build:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

prod-down:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml down

# Utilities
logs:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f

setup:
	cp -n .env.docker .env 2>/dev/null || true
	docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm app composer install
	docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm app php artisan key:generate
