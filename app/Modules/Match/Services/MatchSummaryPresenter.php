<?php

namespace App\Modules\Match\Services;

use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\LineupService;
use App\Modules\Match\DTOs\MatchSummaryViewModel;
use App\Support\PitchGrid;
use App\Support\PositionMapper;
use App\Support\ShirtStyle;
use App\Support\TeamColors;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the prepared view-model for the shared match-summary partial.
 *
 * Keeps the data prep (player lookups, pitch coordinates, scorer lists,
 * event indices, viewer-perspective W/L/D pill) out of Blade.
 */
class MatchSummaryPresenter
{
    public function present(
        GameMatch $match,
        ?array $ratings = null,
        ?string $viewerTeamId = null,
    ): MatchSummaryViewModel {
        $ratings ??= Cache::get("match_performances:{$match->id}") ?? [];

        $playerCards = $this->loadPlayerCards($match, $ratings);
        $pitchEntries = $this->buildPitchEntries($match, $playerCards);
        [$homeRoster, $awayRoster] = $this->buildRosters($match, $playerCards);
        $subsByOutId = $this->indexSubstitutions($match, $playerCards);
        [$goalsByPlayer, $ownGoalsByPlayer, $yellowsByPlayer, $redsByPlayer, $assistsByGoalKey]
            = $this->indexEvents($match);
        [$homeScorers, $awayScorers] = $this->buildScorerLists($match, $assistsByGoalKey, $playerCards);

        // ET-inclusive score: 90-min score + ET goals (stored separately on
        // home_score_et / away_score_et).
        $homeTotal = (int) $match->home_score + (int) ($match->home_score_et ?? 0);
        $awayTotal = (int) $match->away_score + (int) ($match->away_score_et ?? 0);
        $hasPenalties = $match->home_score_penalties !== null;

        [$resultLabel, $resultColor, $resultBg] = $this->resolveViewerResult(
            $match,
            $homeTotal,
            $awayTotal,
            $hasPenalties,
            $viewerTeamId,
        );

        return new MatchSummaryViewModel(
            playerCards: $playerCards,
            pitchEntries: $pitchEntries,
            homeRoster: $homeRoster,
            awayRoster: $awayRoster,
            subsByOutId: $subsByOutId,
            goalsByPlayer: $goalsByPlayer,
            ownGoalsByPlayer: $ownGoalsByPlayer,
            yellowsByPlayer: $yellowsByPlayer,
            redsByPlayer: $redsByPlayer,
            homeScorers: $homeScorers,
            awayScorers: $awayScorers,
            homeTotal: $homeTotal,
            awayTotal: $awayTotal,
            hasPenalties: $hasPenalties,
            resultLabel: $resultLabel,
            resultColor: $resultColor,
            resultBg: $resultBg,
        );
    }

    /**
     * Fetch GamePlayer rows for everyone who appeared (lineups + subs + event
     * actors) and project them into lightweight cards consumed by the partial.
     * `rating` is precomputed from `performance` via MatchSimulator so the
     * Blade never needs to call back into the simulator.
     *
     * @return Collection<string,array<string,mixed>>
     */
    private function loadPlayerCards(GameMatch $match, array $ratings): Collection
    {
        $allPlayerIds = collect()
            ->merge($match->home_lineup ?? [])
            ->merge($match->away_lineup ?? [])
            ->merge(collect($match->substitutions ?? [])->flatMap(
                fn ($s) => [$s['player_out_id'] ?? null, $s['player_in_id'] ?? null]
            ))
            ->merge($match->events->pluck('game_player_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return GamePlayer::whereIn('id', $allPlayerIds)
            ->get()
            ->keyBy('id')
            ->map(function (GamePlayer $p) use ($ratings) {
                $performance = $ratings[$p->id] ?? null;
                $rating = $performance !== null
                    ? MatchSimulator::performanceToRating((float) $performance)
                    : null;

                return [
                    'id' => $p->id,
                    'name' => $p->name ?? '',
                    'number' => $p->number,
                    'positionAbbr' => PositionMapper::toAbbreviation($p->position),
                    'positionGroup' => $p->position_group,
                    'positionSort' => LineupService::positionSortOrder($p->position),
                    'performance' => $performance,
                    'rating' => $rating,
                ];
            });
    }

    /**
     * Build the flat list of pitch entries (one per assigned slot) for both teams.
     *
     * Coords are [col, row] on a 9×14 grid. *_pitch_positions only stores
     * overrides — positions that differ from the formation default — so we
     * start from the formation's default cells and let stored overrides win.
     * Home defends bottom (row 0 = home GK at bottom of pitch); away defends top.
     *
     * Shirt/number CSS is precomputed here so the Blade only has to position
     * each entry.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildPitchEntries(GameMatch $match, Collection $playerCards): array
    {
        $homeColors = TeamColors::toHex(
            $match->homeTeam->colors ?? TeamColors::get($match->homeTeam->getRawOriginal('name'))
        );
        $awayColors = TeamColors::toHex(
            $match->awayTeam->colors ?? TeamColors::get($match->awayTeam->getRawOriginal('name'))
        );

        $sides = [
            [
                'formation' => $match->home_formation,
                'overrides' => $match->home_pitch_positions,
                'assignments' => $match->home_slot_assignments,
                'colors' => $homeColors,
                'isHome' => true,
            ],
            [
                'formation' => $match->away_formation,
                'overrides' => $match->away_pitch_positions,
                'assignments' => $match->away_slot_assignments,
                'colors' => $awayColors,
                'isHome' => false,
            ],
        ];

        $entries = [];
        foreach ($sides as $data) {
            if (empty($data['assignments']) || empty($data['formation'])) {
                continue;
            }
            $formation = Formation::tryFrom($data['formation']);
            if ($formation === null) {
                continue;
            }

            $defaultCells = PitchGrid::getDefaultCells($formation);
            $overrides = $data['overrides'] ?? [];

            foreach ($data['assignments'] as $slotId => $playerId) {
                $card = $playerId ? $playerCards->get($playerId) : null;
                if (! $card) {
                    continue;
                }

                if (isset($overrides[$slotId])) {
                    [$col, $row] = $overrides[$slotId];
                } elseif (isset($defaultCells[$slotId])) {
                    $col = $defaultCells[$slotId]['col'];
                    $row = $defaultCells[$slotId]['row'];
                } else {
                    continue;
                }

                $xPct = (($col + 0.5) / 9) * 100;
                // Home: row 0 → bottom (~96%); higher row → up toward midfield (~50%).
                // Away: row 0 → top (~4%); higher row → down toward midfield (~50%).
                $rowPct = (($row + 0.5) / 14) * 100;
                $yPct = $data['isHome'] ? (100 - $rowPct / 2) : ($rowPct / 2);

                $role = ($card['positionGroup'] ?? '') === 'GK' ? 'Goalkeeper' : 'Outfield';

                $entries[] = [
                    'card' => $card,
                    'role' => $role,
                    'shirtStyle' => ShirtStyle::background($role, $data['colors']),
                    'numberStyle' => ShirtStyle::number($role, $data['colors']),
                    'xPct' => $xPct,
                    'yPct' => $yPct,
                    'isHome' => $data['isHome'],
                ];
            }
        }

        return $entries;
    }

    /**
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}
     */
    private function buildRosters(GameMatch $match, Collection $playerCards): array
    {
        $build = fn (?array $ids): array => collect($ids ?? [])
            ->map(fn ($id) => $playerCards->get($id))
            ->filter()
            ->sortBy('positionSort')
            ->values()
            ->all();

        return [
            $build($match->home_lineup),
            $build($match->away_lineup),
        ];
    }

    /**
     * Index substitutions by the outgoing player's id so the partial can show
     * the replacement inline under each starter.
     *
     * @return array<string,array<string,mixed>>
     */
    private function indexSubstitutions(GameMatch $match, Collection $playerCards): array
    {
        $subsByOutId = [];
        foreach (($match->substitutions ?? []) as $sub) {
            $outId = $sub['player_out_id'] ?? null;
            $inId = $sub['player_in_id'] ?? null;
            if (! $outId || ! $inId) {
                continue;
            }
            $subsByOutId[$outId] = [
                'minute' => $sub['minute'] ?? null,
                'auto' => ! empty($sub['auto']),
                'in' => $playerCards->get($inId),
            ];
        }

        return $subsByOutId;
    }

    /**
     * Per-player goal & card counters; assist lookup keyed by `minute:team_id`.
     *
     * @return array{0:array<string,int>,1:array<string,int>,2:array<string,bool>,3:array<string,bool>,4:array<string,string>}
     *   [goalsByPlayer, ownGoalsByPlayer, yellowsByPlayer, redsByPlayer, assistsByGoalKey]
     */
    private function indexEvents(GameMatch $match): array
    {
        $goalsByPlayer = [];
        $ownGoalsByPlayer = [];
        $yellowsByPlayer = [];
        $redsByPlayer = [];
        $assistsByGoalKey = [];

        foreach ($match->events as $event) {
            $pid = $event->game_player_id;
            if (! $pid) {
                continue;
            }
            match ($event->event_type) {
                'goal' => $goalsByPlayer[$pid] = ($goalsByPlayer[$pid] ?? 0) + 1,
                'own_goal' => $ownGoalsByPlayer[$pid] = ($ownGoalsByPlayer[$pid] ?? 0) + 1,
                'yellow_card' => $yellowsByPlayer[$pid] = true,
                'red_card' => $redsByPlayer[$pid] = true,
                'assist' => $assistsByGoalKey["{$event->minute}:{$event->team_id}"] = $pid,
                default => null,
            };
        }

        return [$goalsByPlayer, $ownGoalsByPlayer, $yellowsByPlayer, $redsByPlayer, $assistsByGoalKey];
    }

    /**
     * Build per-side scorer lists.
     *
     * Own goals are stored with team_id = the conceding side (the team whose
     * player kicked it in). For display they should appear under the team that
     * benefits — i.e. the opponent.
     *
     * @param array<string,string> $assistsByGoalKey
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>} [homeScorers, awayScorers]
     */
    private function buildScorerLists(GameMatch $match, array $assistsByGoalKey, Collection $playerCards): array
    {
        $build = function (string $homeOrAway) use ($match, $assistsByGoalKey, $playerCards): array {
            $scoringTeamId = $homeOrAway === 'home' ? $match->home_team_id : $match->away_team_id;
            $opposingTeamId = $homeOrAway === 'home' ? $match->away_team_id : $match->home_team_id;

            return $match->events
                ->filter(fn ($e) =>
                    ($e->event_type === 'goal' && $e->team_id === $scoringTeamId) ||
                    ($e->event_type === 'own_goal' && $e->team_id === $opposingTeamId)
                )
                ->sortBy('minute')
                ->map(function ($e) use ($assistsByGoalKey, $playerCards) {
                    $assistId = $e->event_type === 'goal'
                        ? ($assistsByGoalKey["{$e->minute}:{$e->team_id}"] ?? null)
                        : null;

                    return [
                        'name' => $playerCards->get($e->game_player_id)['name'] ?? '?',
                        'isOwnGoal' => $e->event_type === 'own_goal',
                        'minute' => $e->minute,
                        'assistName' => $assistId ? ($playerCards->get($assistId)['name'] ?? null) : null,
                    ];
                })
                ->values()
                ->all();
        };

        return [$build('home'), $build('away')];
    }

    /**
     * W/L/D pill (label + color + background) from the viewer's perspective.
     * Returns [null, null, null] when no viewer is supplied — the partial
     * skips the pill entirely in that case.
     *
     * @return array{0:?string,1:?string,2:?string}
     */
    private function resolveViewerResult(
        GameMatch $match,
        int $homeTotal,
        int $awayTotal,
        bool $hasPenalties,
        ?string $viewerTeamId,
    ): array {
        if ($viewerTeamId === null) {
            return [null, null, null];
        }

        $isHome = $match->home_team_id === $viewerTeamId;
        $yourTotal = $isHome ? $homeTotal : $awayTotal;
        $oppTotal = $isHome ? $awayTotal : $homeTotal;

        if ($yourTotal !== $oppTotal) {
            $result = $yourTotal > $oppTotal ? 'W' : 'L';
        } elseif ($hasPenalties) {
            $yourPens = $isHome ? $match->home_score_penalties : $match->away_score_penalties;
            $oppPens = $isHome ? $match->away_score_penalties : $match->home_score_penalties;
            $result = $yourPens > $oppPens ? 'W' : 'L';
        } else {
            $result = 'D';
        }

        return match ($result) {
            'W' => [
                __('game.live_result_win'),
                'text-accent-green',
                'bg-accent-green/10 border-accent-green/20',
            ],
            'L' => [
                __('game.live_result_loss'),
                'text-accent-red',
                'bg-accent-red/10 border-accent-red/20',
            ],
            'D' => [
                __('game.live_result_draw'),
                'text-text-secondary',
                'bg-surface-700 border-border-default',
            ],
        };
    }
}
