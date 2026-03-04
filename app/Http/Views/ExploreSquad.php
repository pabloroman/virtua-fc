<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Support\Money;
use App\Support\PositionMapper;
use Illuminate\Http\Request;

class ExploreSquad
{
    public function __invoke(Request $request, string $gameId, string $teamId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $team = Team::findOrFail($teamId);

        $players = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->with(['player', 'team'])
            ->get();

        // Get active loans for these players
        $playerIds = $players->pluck('id')->toArray();
        $activeLoans = Loan::where('game_id', $gameId)
            ->whereIn('game_player_id', $playerIds)
            ->active()
            ->with(['parentTeam', 'loanTeam'])
            ->get()
            ->keyBy('game_player_id');

        // Shortlisted player IDs
        $shortlistedIds = ShortlistedPlayer::where('game_id', $gameId)
            ->whereIn('game_player_id', $playerIds)
            ->pluck('game_player_id')
            ->toArray();

        // Group players by position category
        $grouped = [
            'GK' => [],
            'DEF' => [],
            'MID' => [],
            'FWD' => [],
        ];

        foreach ($players as $gp) {
            $positionDisplay = PositionMapper::getPositionDisplay($gp->position);
            $positionGroup = PositionMapper::getPositionGroup($gp->position);

            $loan = $activeLoans->get($gp->id);
            $isLoanedIn = $loan && $loan->loan_team_id === $teamId;

            $category = match ($positionGroup) {
                'Goalkeeper' => 'GK',
                'Defender' => 'DEF',
                'Midfielder' => 'MID',
                'Forward' => 'FWD',
                default => 'MID',
            };

            $nationalityFlag = $gp->nationality_flag;

            $grouped[$category][] = [
                'id' => $gp->id,
                'name' => $gp->name,
                'nationalityName' => $nationalityFlag['name'] ?? null,
                'nationalityCode' => $nationalityFlag['code'] ?? null,
                'position' => $gp->position,
                'positionAbbr' => $positionDisplay['abbreviation'],
                'positionBg' => $positionDisplay['bg'],
                'positionText' => $positionDisplay['text'],
                'age' => $gp->age,
                'marketValue' => $gp->market_value_cents,
                'formattedMarketValue' => Money::format($gp->market_value_cents),
                'contractUntil' => $gp->contract_until?->year,
                'isShortlisted' => in_array($gp->id, $shortlistedIds),
                'isLoanedIn' => $isLoanedIn,
                'loanParentClub' => $isLoanedIn ? $loan->parentTeam->name : null,
                'number' => $gp->number,
            ];
        }

        // Sort each group by market value descending
        foreach ($grouped as &$group) {
            usort($group, fn ($a, $b) => $b['marketValue'] <=> $a['marketValue']);
        }

        return response()->json([
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'image' => $team->image,
                'stadiumName' => $team->stadium_name,
                'stadiumSeats' => $team->stadium_seats,
            ],
            'positions' => $grouped,
        ]);
    }
}
