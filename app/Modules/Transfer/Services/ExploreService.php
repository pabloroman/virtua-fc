<?php

namespace App\Modules\Transfer\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Support\PositionMapper;
use Illuminate\Support\Collection;

class ExploreService
{
    private const POSITION_GROUP_ORDER = [
        'Goalkeeper' => 0,
        'Defender' => 1,
        'Midfielder' => 2,
        'Forward' => 3,
    ];

    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    /**
     * Get domestic league competitions with team counts for a game.
     */
    public function getCompetitionsWithTeamCounts(string $gameId): Collection
    {
        $competitionIds = CompetitionEntry::where('game_id', $gameId)
            ->distinct()
            ->pluck('competition_id');

        $teamCounts = CompetitionEntry::where('game_id', $gameId)
            ->whereIn('competition_id', $competitionIds)
            ->selectRaw('competition_id, count(*) as team_count')
            ->groupBy('competition_id')
            ->pluck('team_count', 'competition_id');

        return Competition::whereIn('id', $competitionIds)
            ->where('role', Competition::ROLE_LEAGUE)
            ->where('scope', Competition::SCOPE_DOMESTIC)
            ->orderBy('country')
            ->get()
            ->map(fn (Competition $comp) => [
                'id' => $comp->id,
                'name' => __($comp->name),
                'country' => $comp->country,
                'flag' => $comp->flag,
                'tier' => $comp->tier,
                'scope' => $comp->scope,
                'teamCount' => $teamCounts->get($comp->id, 0),
            ])
            ->filter(fn ($c) => $c['teamCount'] > 0)
            ->values();
    }

    /**
     * Get teams for a competition in a game, sorted by name.
     */
    public function getTeamsForCompetition(string $gameId, string $competitionId): Collection
    {
        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id');

        return Team::whereIn('id', $teamIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'image' => $team->image,
            ]);
    }

    /**
     * Get a team's squad for the explore view, with loan and shortlist status.
     */
    public function getSquadForTeam(Game $game, string $teamId): Collection
    {
        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->with(['player', 'team'])
            ->get();

        $playerIds = $players->pluck('id')->toArray();

        $activeLoans = Loan::where('game_id', $game->id)
            ->whereIn('game_player_id', $playerIds)
            ->active()
            ->with(['parentTeam', 'loanTeam'])
            ->get()
            ->keyBy('game_player_id');

        $shortlistedIds = $this->getShortlistedIds($game->id, $playerIds);

        return $players->map(function ($gp) use ($activeLoans, $shortlistedIds, $teamId) {
            $loan = $activeLoans->get($gp->id);
            $gp->is_loaned_in = $loan && $loan->loan_team_id === $teamId;
            $gp->is_shortlisted = in_array($gp->id, $shortlistedIds);

            return $gp;
        })->sort(fn ($a, $b) => $this->sortByPositionThenValue($a, $b));
    }

    /**
     * Get free agents for a game, optionally filtered by position group.
     * Each player is annotated with shortlist status and willingness level.
     */
    public function getFreeAgents(Game $game, string $positionFilter = 'all'): Collection
    {
        $query = GamePlayer::where('game_id', $game->id)
            ->whereNull('team_id')
            ->with('player');

        $positions = PositionMapper::getPositionsForGroupFilter($positionFilter);
        if ($positions !== null) {
            $query->whereIn('position', $positions);
        }

        $players = $query->get();

        $shortlistedIds = $this->getShortlistedIds($game->id, $players->pluck('id')->toArray());

        return $players->map(function ($gp) use ($shortlistedIds, $game) {
            $gp->is_shortlisted = in_array($gp->id, $shortlistedIds);
            $gp->free_agent_willingness = $this->scoutingService->getFreeAgentWillingnessLevel(
                $gp, $game->id, $game->team_id
            );

            return $gp;
        })->sort(fn ($a, $b) => $this->sortByPositionThenValue($a, $b));
    }

    /**
     * Count free agents in a game.
     */
    public function getFreeAgentCount(string $gameId): int
    {
        return GamePlayer::where('game_id', $gameId)
            ->whereNull('team_id')
            ->count();
    }

    /**
     * Get shortlisted player IDs from a set of player IDs.
     *
     * @return array<string>
     */
    private function getShortlistedIds(string $gameId, array $playerIds): array
    {
        return ShortlistedPlayer::where('game_id', $gameId)
            ->whereIn('game_player_id', $playerIds)
            ->pluck('game_player_id')
            ->toArray();
    }

    /**
     * Sort comparator: position group order, then market value descending.
     */
    private function sortByPositionThenValue(GamePlayer $a, GamePlayer $b): int
    {
        $groupA = self::POSITION_GROUP_ORDER[PositionMapper::getPositionGroup($a->position)] ?? 2;
        $groupB = self::POSITION_GROUP_ORDER[PositionMapper::getPositionGroup($b->position)] ?? 2;

        return $groupA <=> $groupB ?: $b->market_value_cents <=> $a->market_value_cents;
    }
}
