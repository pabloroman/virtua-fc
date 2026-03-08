#!/usr/bin/env bash
#
# setup-promo-game.sh
# Seeds a full La Liga career game (Real Madrid) and advances to matchday 8
# for use in the promo video recording script.
#
# Prerequisites:
#   - `composer dev` must be running (for queue worker + vite)
#   - PHP + artisan available
#
# Usage:
#   bash scripts/setup-promo-game.sh
#
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Step 1: Seeding reference data (full production profile)..."
php artisan app:seed-reference-data --fresh
echo ""

echo "==> Step 2: Creating career game with Real Madrid..."
GAME_ID=$(php artisan tinker --execute="
    \$user = \App\Models\User::where('email', 'test@test.com')->first();
    \$team = \App\Models\Team::where('name', 'Real Madrid')->first();
    if (!\$user || !\$team) { echo 'ERROR'; exit(1); }
    \$game = app(\App\Modules\Season\Services\GameCreationService::class)->create(\$user->id, \$team->id);
    echo \$game->id;
" 2>/dev/null)

if [ -z "$GAME_ID" ] || [ "$GAME_ID" = "ERROR" ]; then
    echo "ERROR: Failed to create game. Make sure seeding completed successfully."
    exit 1
fi
echo "   Game ID: $GAME_ID"
echo ""

echo "==> Step 3: Processing SetupNewGame job..."
php artisan queue:work --stop-when-empty --timeout=120
echo ""

echo "==> Step 4: Skipping welcome, onboarding, and pre-season..."
php artisan tinker --execute="
    \$game = \App\Models\Game::find('$GAME_ID');
    \$game->needs_welcome = false;
    \$game->needs_onboarding = false;
    \$game->pre_season = false;
    \$game->save();
    echo 'Done: needs_welcome=false, needs_onboarding=false, pre_season=false';
"
echo ""

echo "==> Step 5: Simulating to matchday 8..."
php artisan game:simulate "$GAME_ID" 8
echo ""

echo "==> Step 6: Processing remaining queued jobs..."
php artisan queue:work --stop-when-empty --timeout=120 2>/dev/null || true
echo ""

# Extract the La Liga competition ID for the Playwright script
COMPETITION_ID=$(php artisan tinker --execute="
    echo \App\Models\Competition::where('code', 'ESP1')->first()?->id ?? '';
" 2>/dev/null)

echo "============================================"
echo "  Promo game setup complete!"
echo ""
echo "  GAME_ID=$GAME_ID"
echo "  COMPETITION_ID=$COMPETITION_ID"
echo ""
echo "  Run the recording script:"
echo "  GAME_ID=$GAME_ID COMPETITION_ID=$COMPETITION_ID node scripts/record-promo-video.mjs"
echo "============================================"
