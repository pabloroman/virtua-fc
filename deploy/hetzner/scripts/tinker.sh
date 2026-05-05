#!/usr/bin/env bash
# Open an interactive Tinker REPL against the production app.
#   ssh -t deploy@<host> /srv/virtua-fc/scripts/tinker.sh
# (the -t on ssh allocates a TTY so REPL keystrokes are forwarded).

set -euo pipefail
exec "$(dirname "$0")/artisan.sh" tinker
