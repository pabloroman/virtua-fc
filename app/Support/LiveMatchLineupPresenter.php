<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Lineup\Services\LineupService;
use Carbon\CarbonInterface;

/**
 * Shapes GamePlayer rows into the array payloads the live-match view
 * consumes — starting XI, user bench, opponent bench (minimal), and
 * home/away display rosters.
 */
class LiveMatchLineupPresenter
{
    /**
     * Full-detail payload for the user's starting XI (feeds the sub-out picker).
     * No performance data — ratings are only relevant at full-time for the bench.
     *
     * @param  array<int, string>  $lineupIds
     * @return array<int, array<string, mixed>>
     */
    public static function startingLineup(array $lineupIds, CarbonInterface $currentDate): array
    {
        return GamePlayer::with(['player', 'matchState'])
            ->whereIn('id', $lineupIds)
            ->get()
            ->map(fn ($p) => self::fullCard($p, $currentDate, null, 0))
            ->sortBy('positionSort')
            ->values()
            ->all();
    }

    /**
     * Full-detail payload for the user's bench: all squad players not in the
     * starting XI, not suspended, not injured, optionally requiring enrollment.
     *
     * @param  array<int, string>  $lineupIds
     * @param  array<int, string>  $suspendedIds
     * @param  array<string, mixed>  $performances
     * @return array<int, array<string, mixed>>
     */
    public static function userBench(
        Game $game,
        array $lineupIds,
        array $suspendedIds,
        CarbonInterface $matchDate,
        CarbonInterface $currentDate,
        array $performances,
    ): array {
        return GamePlayer::with(['player', 'matchState'])
            ->where('game_players.game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotIn('id', $lineupIds)
            ->whereNotIn('id', $suspendedIds)
            ->when($game->requiresSquadEnrollment(), fn ($q) => $q->whereNotNull('number'))
            ->notInjuredOn($matchDate)
            ->get()
            ->map(fn ($p) => self::fullCard($p, $currentDate, $performances[$p->id] ?? null, null))
            ->sortBy('positionSort')
            ->values()
            ->all();
    }

    /**
     * Minimal payload for the opponent's bench — only the fields the client-side
     * substitute-rating formula needs.
     *
     * @param  array<int, string>  $lineupIds
     * @param  array<string, mixed>  $performances
     * @return array<int, array<string, mixed>>
     */
    public static function opponentBench(
        string $gameId,
        string $opponentTeamId,
        array $lineupIds,
        array $performances,
    ): array {
        return GamePlayer::where('game_players.game_id', $gameId)
            ->where('team_id', $opponentTeamId)
            ->whereNotIn('id', $lineupIds)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'positionGroup' => $p->position_group,
                'performance' => $performances[$p->id] ?? null,
                'teamId' => $opponentTeamId,
            ])
            ->all();
    }

    /**
     * Display-only payload for home/away lineup rosters shown in the ratings
     * tab.
     *
     * @param  array<int, string>  $lineupIds
     * @param  array<string, mixed>  $performances
     * @return array<int, array<string, mixed>>
     */
    public static function displayRoster(array $lineupIds, array $performances): array
    {
        return GamePlayer::with(['player', 'matchState'])
            ->whereIn('id', $lineupIds)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->player->name ?? '',
                'positionAbbr' => PositionMapper::toAbbreviation($p->position),
                'positionGroup' => $p->position_group,
                'positionSort' => LineupService::positionSortOrder($p->position),
                'performance' => $performances[$p->id] ?? null,
            ])
            ->sortBy('positionSort')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function fullCard(
        GamePlayer $p,
        CarbonInterface $currentDate,
        mixed $performance,
        ?int $minuteEntered,
    ): array {
        $card = [
            'id' => $p->id,
            'name' => $p->player->name ?? '',
            'position' => $p->position,
            'positionAbbr' => PositionMapper::toAbbreviation($p->position),
            'positionGroup' => $p->position_group,
            'positionSort' => LineupService::positionSortOrder($p->position),
            'positions' => $p->positions,
            'physicalAbility' => $p->physical_ability,
            'technicalAbility' => $p->technical_ability,
            'age' => $p->age($currentDate),
            'overallScore' => $p->overall_score,
            'fitness' => $p->fitness,
            'morale' => $p->morale,
            'minuteEntered' => $minuteEntered,
        ];
        if ($performance !== null) {
            $card['performance'] = $performance;
        }
        return $card;
    }
}
