<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use App\Modules\Transfer\Services\ScoutingService;
use App\Support\PositionMapper;
use Illuminate\Http\Request;

class ToggleShortlist
{
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
            ShortlistedPlayer::create([
                'game_id' => $gameId,
                'game_player_id' => $playerId,
                'added_at' => $game->current_date,
            ]);
            $message = __('messages.shortlist_added', ['player' => $gamePlayer->name]);
            $action = 'added';
        }

        if ($request->ajax()) {
            $data = ['success' => true, 'message' => $message, 'action' => $action, 'playerId' => $playerId];

            if ($action === 'added') {
                $gamePlayer->load(['player', 'team']);
                $scoutingService = app(ScoutingService::class);
                $detail = $scoutingService->getPlayerScoutingDetail($gamePlayer, $game);
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
                    'techRange' => $detail['tech_range'],
                    'formattedAskingPrice' => $detail['formatted_asking_price'],
                    'askingPrice' => $detail['asking_price'],
                    'canAffordFee' => $detail['can_afford_fee'],
                    'isExpiring' => $gamePlayer->contract_until && $gamePlayer->contract_until <= $game->getSeasonEndDate(),
                    'wageDemand' => $detail['wage_demand'],
                    'formattedWageDemand' => $detail['formatted_wage_demand'],
                    'hasExistingOffer' => false,
                    'bidEuros' => (int) ($detail['asking_price'] / 100),
                    'wageEuros' => (int) ($detail['wage_demand'] / 100),
                ];
            }

            return response()->json($data);
        }

        return redirect()->back()->with('success', $message);
    }
}
