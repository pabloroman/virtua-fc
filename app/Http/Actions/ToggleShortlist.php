<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use App\Modules\Transfer\Services\ScoutingService;
use App\Support\Money;
use App\Support\PositionMapper;
use Illuminate\Http\Request;

class ToggleShortlist
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $gamePlayer = GamePlayer::where('game_id', $gameId)->findOrFail($playerId);

        $existing = ShortlistedPlayer::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->first();

        if ($existing) {
            $existing->delete();
            $message = __('messages.shortlist_removed', ['player' => $gamePlayer->name]);
            $action = 'removed';
        } else {
            $entry = ShortlistedPlayer::create([
                'game_id' => $gameId,
                'game_player_id' => $playerId,
                'added_at' => $game->current_date,
            ]);

            // Auto-track if slots available
            $this->scoutingService->startTracking($entry, $game);

            $message = __('messages.shortlist_added', ['player' => $gamePlayer->name]);
            $action = 'added';
        }

        if ($request->ajax()) {
            $data = ['success' => true, 'message' => $message, 'action' => $action, 'playerId' => $playerId];

            if ($action === 'added') {
                $entry->refresh();
                $gamePlayer->load(['player', 'team']);
                $positionDisplay = PositionMapper::getPositionDisplay($gamePlayer->position);

                $data['player'] = [
                    'id' => $gamePlayer->id,
                    'name' => $gamePlayer->name,
                    'position' => $gamePlayer->position,
                    'positionAbbr' => $positionDisplay['abbreviation'],
                    'positionBg' => $positionDisplay['bg'],
                    'positionText' => $positionDisplay['text'],
                    'age' => $gamePlayer->age,
                    'teamName' => $gamePlayer->team?->name,
                    'teamImage' => $gamePlayer->team?->image,
                    'isExpiring' => $gamePlayer->contract_until && $gamePlayer->contract_until <= $game->getSeasonEndDate(),
                    'contractYear' => $gamePlayer->contract_until?->format('Y'),
                    'marketValue' => $gamePlayer->market_value_cents,
                    'formattedMarketValue' => Money::format($gamePlayer->market_value_cents),
                    'intelLevel' => $entry->intel_level ?? ShortlistedPlayer::INTEL_SURFACE,
                    'isTracking' => (bool) $entry->is_tracking,
                    'matchdaysTracked' => $entry->matchdays_tracked ?? 0,
                    'hasExistingOffer' => false,
                    'techRange' => null,
                    'formattedAskingPrice' => null,
                    'askingPrice' => null,
                    'canAffordFee' => false,
                    'wageDemand' => null,
                    'formattedWageDemand' => null,
                    'bidEuros' => 0,
                    'wageEuros' => 0,
                    'willingness' => null,
                    'willingnessLabel' => null,
                    'rivalInterest' => false,
                ];
            }

            $data['trackingCapacity'] = $this->scoutingService->getTrackingCapacity($game);

            return response()->json($data);
        }

        return redirect()->back()->with('success', $message);
    }
}
