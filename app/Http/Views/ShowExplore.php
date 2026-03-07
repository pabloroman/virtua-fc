<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class ShowExplore
{
    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        // Get all competitions that have teams with players in this game
        $competitionIds = CompetitionEntry::where('game_id', $gameId)
            ->distinct()
            ->pluck('competition_id');

        $teamCounts = CompetitionEntry::where('game_id', $gameId)
            ->whereIn('competition_id', $competitionIds)
            ->selectRaw('competition_id, count(*) as team_count')
            ->groupBy('competition_id')
            ->pluck('team_count', 'competition_id');

        $competitions = Competition::whereIn('id', $competitionIds)
            ->where('role', Competition::ROLE_LEAGUE)
            ->where('scope', Competition::SCOPE_DOMESTIC)
            ->orderBy('country')
            ->get()
            ->map(function (Competition $comp) use ($teamCounts) {
                return [
                    'id' => $comp->id,
                    'name' => __($comp->name),
                    'country' => $comp->country,
                    'flag' => $comp->flag,
                    'tier' => $comp->tier,
                    'scope' => $comp->scope,
                    'teamCount' => $teamCounts->get($comp->id, 0),
                ];
            })
            ->filter(fn ($c) => $c['teamCount'] > 0)
            ->values();

        // Shortlisted player IDs for star toggle state
        $shortlistedIds = ShortlistedPlayer::where('game_id', $gameId)
            ->pluck('game_player_id')
            ->toArray();

        // Transfer window info (for shared header)
        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();
        $windowCountdown = $game->getWindowCountdown();

        // Wage bill (for shared header)
        $totalWageBill = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');

        // Badge count for Salidas tab
        $salidaBadgeCount = TransferOffer::where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->where('expires_at', '>=', $game->current_date)
            ->whereIn('offer_type', [
                TransferOffer::TYPE_UNSOLICITED,
                TransferOffer::TYPE_LISTED,
                TransferOffer::TYPE_PRE_CONTRACT,
            ])
            ->count();

        // Badge count for Fichajes tab (counter-offers)
        $counterOfferCount = TransferOffer::where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->whereNotNull('asking_price')
            ->whereColumn('asking_price', '>', 'transfer_fee')
            ->count();

        return view('explore', [
            'game' => $game,
            'competitions' => $competitions,
            'shortlistedIds' => $shortlistedIds,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
            'windowCountdown' => $windowCountdown,
            'totalWageBill' => $totalWageBill,
            'salidaBadgeCount' => $salidaBadgeCount,
            'counterOfferCount' => $counterOfferCount,
        ]);
    }
}
