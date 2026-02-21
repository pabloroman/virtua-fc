<?php

namespace App\Http\Actions;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProcessExtraTime
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('game_id', $gameId)
            ->findOrFail($matchId);

        // Validate match is currently in play
        if ($game->pending_finalization_match_id !== $match->id) {
            return response()->json(['error' => 'Match not in progress'], 403);
        }

        // Validate this is a cup tie
        if (! $match->cup_tie_id) {
            return response()->json(['needed' => false]);
        }

        $cupTie = CupTie::with(['firstLegMatch', 'secondLegMatch'])->find($match->cup_tie_id);

        if (! $cupTie) {
            return response()->json(['needed' => false]);
        }

        // Determine if extra time is needed
        $needsExtraTime = $this->needsExtraTime($match, $cupTie);

        if (! $needsExtraTime) {
            return response()->json(['needed' => false]);
        }

        // Load players for simulation
        $allLineupIds = array_merge($match->home_lineup ?? [], $match->away_lineup ?? []);
        $players = GamePlayer::with('player')->whereIn('id', $allLineupIds)->get();
        $homePlayers = $players->filter(fn ($p) => $p->team_id === $match->home_team_id);
        $awayPlayers = $players->filter(fn ($p) => $p->team_id === $match->away_team_id);

        // Build entry minutes from existing substitutions
        $homeEntryMinutes = [];
        $awayEntryMinutes = [];
        foreach ($match->substitutions ?? [] as $sub) {
            $playerIn = $players->firstWhere('id', $sub['player_in_id']);
            if ($playerIn) {
                if ($playerIn->team_id === $match->home_team_id) {
                    $homeEntryMinutes[$sub['player_in_id']] = $sub['minute'];
                } else {
                    $awayEntryMinutes[$sub['player_in_id']] = $sub['minute'];
                }
            }
        }

        // Simulate extra time
        $extraTimeResult = $this->matchSimulator->simulateExtraTime(
            $match->homeTeam,
            $match->awayTeam,
            $homePlayers,
            $awayPlayers,
            $homeEntryMinutes,
            $awayEntryMinutes,
        );

        // Store ET scores on match
        $match->update([
            'is_extra_time' => true,
            'home_score_et' => $extraTimeResult->homeScore,
            'away_score_et' => $extraTimeResult->awayScore,
        ]);

        // Store ET events as MatchEvent records
        $etEvents = $this->storeExtraTimeEvents($match, $game, $extraTimeResult->events);

        // Build response events for frontend
        $frontendEvents = $this->buildFrontendEvents($etEvents, $match);

        // Check if penalties are needed
        $totalHome = $match->home_score + $extraTimeResult->homeScore;
        $totalAway = $match->away_score + $extraTimeResult->awayScore;

        // For two-legged ties, include first leg in aggregate
        if ($cupTie->second_leg_match_id === $match->id) {
            $firstLeg = $cupTie->firstLegMatch;
            if ($firstLeg?->played) {
                // Second leg home = tie's away, so we need to swap
                $totalHome = ($firstLeg->home_score ?? 0) + ($match->away_score + $extraTimeResult->awayScore);
                $totalAway = ($firstLeg->away_score ?? 0) + ($match->home_score + $extraTimeResult->homeScore);
            }
        }

        $needsPenalties = $totalHome === $totalAway;

        return response()->json([
            'needed' => true,
            'extraTimeEvents' => $frontendEvents,
            'homeScoreET' => $extraTimeResult->homeScore,
            'awayScoreET' => $extraTimeResult->awayScore,
            'needsPenalties' => $needsPenalties,
        ]);
    }

    private function needsExtraTime(GameMatch $match, CupTie $cupTie): bool
    {
        $roundConfig = $cupTie->getRoundConfig();

        if (! $roundConfig) {
            return false;
        }

        if ($roundConfig->twoLegged) {
            // Two-legged tie: check if this is the second leg and aggregate is tied
            if ($cupTie->second_leg_match_id !== $match->id) {
                return false;
            }

            $aggregate = $cupTie->getAggregateScore();

            return $aggregate['home'] === $aggregate['away'];
        }

        // Single-leg: check if score is tied
        return $match->home_score === $match->away_score;
    }

    /**
     * @return \Illuminate\Support\Collection<MatchEvent>
     */
    private function storeExtraTimeEvents(GameMatch $match, Game $game, $events): \Illuminate\Support\Collection
    {
        $now = now();
        $storedEvents = collect();

        $rows = $events->map(fn (MatchEventData $e) => [
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'game_player_id' => $e->gamePlayerId,
            'team_id' => $e->teamId,
            'minute' => $e->minute,
            'event_type' => $e->type,
            'metadata' => $e->metadata ? json_encode($e->metadata) : null,
            'created_at' => $now,
        ])->all();

        if (! empty($rows)) {
            MatchEvent::insert($rows);

            // Re-load with relations for building frontend response
            $ids = array_column($rows, 'id');
            $storedEvents = MatchEvent::with('gamePlayer.player')
                ->whereIn('id', $ids)
                ->orderBy('minute')
                ->get();
        }

        return $storedEvents;
    }

    private function buildFrontendEvents($events, GameMatch $match): array
    {
        $formattedEvents = $events
            ->filter(fn ($e) => $e->event_type !== 'assist')
            ->map(fn ($e) => [
                'minute' => $e->minute,
                'type' => $e->event_type,
                'playerName' => $e->gamePlayer->player->name ?? '',
                'teamId' => $e->team_id,
                'gamePlayerId' => $e->game_player_id,
                'metadata' => $e->metadata,
            ])
            ->values()
            ->all();

        // Pair assists with goals
        $assists = $events
            ->filter(fn ($e) => $e->event_type === 'assist')
            ->keyBy('minute');

        return array_map(function ($event) use ($assists) {
            if (in_array($event['type'], ['goal', 'own_goal']) && isset($assists[$event['minute']])) {
                $event['assistPlayerName'] = $assists[$event['minute']]->gamePlayer->player->name ?? null;
            }

            return $event;
        }, $formattedEvents);
    }
}
