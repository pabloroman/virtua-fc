#!/usr/bin/env bash
# Tail logs for one or more services (defaults to the app's hot path:
# app, horizon, scheduler, traefik). Pass any compose-logs flag.
#
# Examples:
#   ./logs.sh                     # default services, follow, tail=200
#   ./logs.sh app                 # just app
#   ./logs.sh --tail=50 horizon   # last 50 lines of horizon, no follow
#   ./logs.sh -f traefik grafana  # multiple services
#
# For structured / queryable logs, use Grafana → Explore → Loki and
# filter by `{compose_service="app"}`.

set -euo pipefail

if [ "$#" -eq 0 ]; then
    set -- -f --tail=200 app horizon scheduler traefik
fi

exec "$(dirname "$0")/compose.sh" logs "$@"
