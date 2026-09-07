#!/usr/bin/env bash
#
# Sync a local asset directory to the Cloudflare R2 bucket the game's CDN serves.
#
#   scripts/upload-assets-to-r2.sh players            # public/players/ -> s3://$R2_BUCKET/players/
#   scripts/upload-assets-to-r2.sh crests --dry-run   # show what would change, upload nothing
#
# R2 speaks the S3 API, so this drives the aws CLI at R2's endpoint rather than
# adding aws/aws-sdk-php to the application. Nothing in the app writes to the
# bucket at runtime — only an operator does, occasionally, after a data refresh —
# so a ~15 MB SDK dependency would be carried by every deploy to serve a task
# that never runs in a request.
#
# CREDENTIALS come from the environment and are never written to the repo, never
# echoed, and never passed as command-line arguments (argv is visible to other
# processes via ps). Use an R2 API token scoped to *Object Read & Write* on this
# one bucket, not an account-wide key:
#
#   Cloudflare dashboard -> R2 -> Manage API tokens -> Create API token
#
#   export R2_ACCOUNT_ID=...          # R2 -> Overview, right-hand sidebar
#   export R2_ACCESS_KEY_ID=...
#   export R2_SECRET_ACCESS_KEY=...
#   export R2_BUCKET=virtua-fc        # optional, this is the default
#
# --size-only is deliberate: local mtimes never match R2's, so the default
# timestamp comparison re-uploads the entire directory every run. These objects
# are keyed by a stable external id and a given id's image does not change, so
# comparing size is the right test and keeps a re-run nearly free.
set -euo pipefail

PREFIX="${1:-}"
shift || true

if [ -z "$PREFIX" ] || [ "$PREFIX" = "-h" ] || [ "$PREFIX" = "--help" ]; then
    sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'
    exit 0
fi

case "$PREFIX" in
    players|crests) ;;
    *) echo "Refusing to sync unknown prefix '$PREFIX' (expected: players, crests)." >&2; exit 1 ;;
esac

command -v aws >/dev/null 2>&1 || {
    echo "aws CLI not found. Install it: brew install awscli" >&2
    exit 1
}

missing=""
for var in R2_ACCOUNT_ID R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY; do
    [ -n "${!var:-}" ] || missing="$missing $var"
done
if [ -n "$missing" ]; then
    echo "Missing required environment variable(s):$missing" >&2
    echo "See the header of $0 for how to create a scoped R2 token." >&2
    exit 1
fi

BUCKET="${R2_BUCKET:-virtua-fc}"
SOURCE="$(cd "$(dirname "$0")/.." && pwd)/public/$PREFIX"

[ -d "$SOURCE" ] || { echo "No such directory: $SOURCE" >&2; exit 1; }

FILES=$(find "$SOURCE" -type f ! -name '.*' | wc -l | tr -d ' ')
echo "Syncing $FILES file(s)"
echo "  from  $SOURCE/"
echo "  to    s3://$BUCKET/$PREFIX/"
echo

# Scoped to this invocation only, so the credentials never leak into the caller's
# shell or into any child beyond aws itself. region must be 'auto' for R2.
AWS_ACCESS_KEY_ID="$R2_ACCESS_KEY_ID" \
AWS_SECRET_ACCESS_KEY="$R2_SECRET_ACCESS_KEY" \
AWS_DEFAULT_REGION=auto \
AWS_REQUEST_CHECKSUM_CALCULATION=when_required \
aws s3 sync "$SOURCE/" "s3://$BUCKET/$PREFIX/" \
    --endpoint-url "https://$R2_ACCOUNT_ID.r2.cloudflarestorage.com" \
    --size-only \
    --no-progress \
    "$@"
