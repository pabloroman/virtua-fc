#!/usr/bin/env bash
# Run `php artisan` inside the running app container.
#
# Examples:
#   ./artisan.sh tinker
#   ./artisan.sh queue:failed
#   ./artisan.sh app:cleanup-games
#   ssh deploy@<host> /srv/virtua-fc/scripts/artisan.sh schedule:run
#
# For non-interactive use (cron, scripts), pass -T-style flags via
# compose.sh directly.

set -euo pipefail
exec "$(dirname "$0")/compose.sh" exec app php artisan "$@"
