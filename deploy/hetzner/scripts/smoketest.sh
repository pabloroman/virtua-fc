#!/usr/bin/env bash
# Post-deploy smoke test. Returns non-zero on any failure.

set -euo pipefail

ROOT="${VFC_ROOT:-/srv/virtua-fc}"
ENV_FILE="${VFC_ENV_FILE:-$ROOT/env/.env}"
COMPOSE="$(dirname "$0")/compose.sh"

# shellcheck disable=SC1090
set -a; . "$ENV_FILE"; set +a

fail() { echo "FAIL: $*" >&2; exit 1; }
ok()   { echo "OK:   $*"; }

echo "==> Checking compose service health"
UNHEALTHY=$("$COMPOSE" ps --format json | jq -r 'select(.Health != null and .Health != "" and .Health != "healthy") | .Service' || true)
[ -z "$UNHEALTHY" ] || fail "unhealthy services: $UNHEALTHY"
ok "all containers healthy"

echo "==> Checking https://$APP_DOMAIN/up"
HTTP_CODE=$(curl -fsS -o /dev/null -w "%{http_code}" --max-time 10 "https://$APP_DOMAIN/up" || true)
[ "$HTTP_CODE" = "200" ] || fail "/up returned $HTTP_CODE"
ok "/up returned 200"

echo "==> Checking Postgres connectivity"
"$COMPOSE" exec -T postgres pg_isready -U "$DB_USERNAME" -d "$DB_DATABASE" >/dev/null || fail "pg_isready failed"
ok "postgres ready"

echo "==> Checking Redis connectivity"
"$COMPOSE" exec -T redis redis-cli ping | grep -q PONG || fail "redis ping failed"
ok "redis pong"

echo "==> Checking Horizon supervisors"
"$COMPOSE" exec -T app php artisan horizon:status | grep -qi "running" || fail "horizon not running"
ok "horizon running"

echo "==> Checking Grafana"
HTTP_CODE=$(curl -fsS -o /dev/null -w "%{http_code}" --max-time 10 "https://grafana.$APP_DOMAIN/api/health" || true)
[ "$HTTP_CODE" = "200" ] || fail "grafana health returned $HTTP_CODE"
ok "grafana healthy"

echo
echo "==> Smoke test PASSED"
