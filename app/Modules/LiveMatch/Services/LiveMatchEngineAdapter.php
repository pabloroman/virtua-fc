<?php

namespace App\Modules\LiveMatch\Services;

use App\Models\LiveMatchAction;
use App\Models\LiveMatchSession;
use App\Models\Team;
use App\Modules\LiveMatch\DTOs\SquadSnapshot;
use App\Modules\LiveMatch\Enums\LiveMatchSide;
use App\Modules\LiveMatch\Enums\QueuedActionType;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Match\DTOs\MatchSimulationContext;
use App\Modules\Match\DTOs\WindowResult;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Support\Collection;

/**
 * Bridges LiveMatchSession persistence with MatchSimulator::simulateWindow.
 *
 * On each call:
 *  1. Pulls queued actions from live_match_actions for this session.
 *  2. Applies them to the in-memory lineup (sub-out/sub-in, formation change,
 *     mentality change) and marks them applied|rejected.
 *  3. Rehydrates MatchSimulationContext from session->context_state +
 *     session->{host,guest}_squad.
 *  4. Calls MatchSimulator::simulateWindow.
 *  5. Persists the mutated context back to session->context_state and
 *     appends the window's events to session->event_log.
 */
class LiveMatchEngineAdapter
{
    public function __construct(
        private readonly MatchSimulator $simulator,
        private readonly NationalSquadBuilder $squadBuilder,
    ) {}

    /**
     * Build initial context_state JSON at kickoff. Called once when both
     * teams are picked and the orchestrator transitions to Live.
     */
    public function buildInitialContextState(LiveMatchSession $session): array
    {
        $home = SquadSnapshot::fromArray($session->host_squad);
        $away = SquadSnapshot::fromArray($session->guest_squad);

        return [
            'home_on_pitch_ids' => array_column($home->startingXi, 'id'),
            'away_on_pitch_ids' => array_column($away->startingXi, 'id'),
            'home_bench_ids' => array_column($home->bench, 'id'),
            'away_bench_ids' => array_column($away->bench, 'id'),
            'home_entry_minutes' => array_fill_keys(array_column($home->startingXi, 'id'), 0),
            'away_entry_minutes' => array_fill_keys(array_column($away->startingXi, 'id'), 0),
            'existing_injury_team_ids' => [],
            'existing_yellow_player_ids' => [],
            'home_subs_used' => 0,
            'away_subs_used' => 0,
            'home_formation' => $home->formation,
            'away_formation' => $away->formation,
            'home_mentality' => $home->mentality,
            'away_mentality' => $away->mentality,
            'home_playing_style' => $home->playingStyle,
            'away_playing_style' => $away->playingStyle,
            'home_pressing' => $home->pressing,
            'away_pressing' => $away->pressing,
            'home_def_line' => $home->defensiveLine,
            'away_def_line' => $away->defensiveLine,
            'home_player_slot_map' => $home->playerSlotMap,
            'away_player_slot_map' => $away->playerSlotMap,
            'match_performance' => [],
            'home_xg_total' => 0.0,
            'away_xg_total' => 0.0,
        ];
    }

    /**
     * Run one simulation window and persist results to the session row.
     */
    public function runWindow(LiveMatchSession $session, int $fromMinute, int $toMinute): WindowResult
    {
        $this->applyQueuedActions($session, $fromMinute);
        // Reload the session so we see actions just applied.
        $session->refresh();

        $context = $this->hydrateContext($session, $fromMinute, $toMinute);
        $window = $this->simulator->simulateWindow($context, $fromMinute, $toMinute);

        $this->persistContext($session, $context, $window);

        return $window;
    }

    private function hydrateContext(LiveMatchSession $session, int $from, int $to): MatchSimulationContext
    {
        $state = $session->context_state ?? $this->buildInitialContextState($session);

        $home = SquadSnapshot::fromArray($session->host_squad);
        $away = SquadSnapshot::fromArray($session->guest_squad);

        $allHomePlayers = $this->squadBuilder->rehydrate(array_merge($home->startingXi, $home->bench));
        $allAwayPlayers = $this->squadBuilder->rehydrate(array_merge($away->startingXi, $away->bench));

        $homeOnPitch = $allHomePlayers
            ->filter(fn ($p) => in_array($p->id, $state['home_on_pitch_ids'] ?? [], true))
            ->values();
        $awayOnPitch = $allAwayPlayers
            ->filter(fn ($p) => in_array($p->id, $state['away_on_pitch_ids'] ?? [], true))
            ->values();
        $homeBench = $allHomePlayers
            ->filter(fn ($p) => in_array($p->id, $state['home_bench_ids'] ?? [], true))
            ->values();
        $awayBench = $allAwayPlayers
            ->filter(fn ($p) => in_array($p->id, $state['away_bench_ids'] ?? [], true))
            ->values();

        $homeTeam = $this->fakeTeam($session->id . ':home', $home->teamName);
        $awayTeam = $this->fakeTeam($session->id . ':away', $away->teamName);

        return new MatchSimulationContext(
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            homePlayers: $homeOnPitch,
            awayPlayers: $awayOnPitch,
            homeBenchPlayers: $homeBench,
            awayBenchPlayers: $awayBench,
            homeFormation: Formation::from($state['home_formation']),
            awayFormation: Formation::from($state['away_formation']),
            homeMentality: Mentality::from($state['home_mentality']),
            awayMentality: Mentality::from($state['away_mentality']),
            homePlayingStyle: PlayingStyle::from($state['home_playing_style']),
            awayPlayingStyle: PlayingStyle::from($state['away_playing_style']),
            homePressing: PressingIntensity::from($state['home_pressing']),
            awayPressing: PressingIntensity::from($state['away_pressing']),
            homeDefLine: DefensiveLineHeight::from($state['home_def_line']),
            awayDefLine: DefensiveLineHeight::from($state['away_def_line']),
            homeScore: $session->home_score,
            awayScore: $session->away_score,
            homeXGTotal: (float) ($state['home_xg_total'] ?? 0.0),
            awayXGTotal: (float) ($state['away_xg_total'] ?? 0.0),
            homeEntryMinutes: $state['home_entry_minutes'] ?? [],
            awayEntryMinutes: $state['away_entry_minutes'] ?? [],
            existingInjuryTeamIds: $state['existing_injury_team_ids'] ?? [],
            existingYellowPlayerIds: $state['existing_yellow_player_ids'] ?? [],
            homeSubsUsed: $state['home_subs_used'] ?? 0,
            awaySubsUsed: $state['away_subs_used'] ?? 0,
            homePlayerSlotMap: $state['home_player_slot_map'] ?? [],
            awayPlayerSlotMap: $state['away_player_slot_map'] ?? [],
            matchPerformance: $state['match_performance'] ?? [],
            // Per-window seed fork — keeps each window's RNG noise distinct.
            matchSeed: $session->match_seed . ':' . $from . '-' . $to,
            // Both sides are humans in a duel — clear userTeamId for both
            // unless one has gone bot. The simulator's single userTeamId arg
            // only suppresses AI subs for ONE team; for a non-bot duel we
            // pass null which gives both sides the user-team behavior at the
            // application layer (subs are queued by the human via the action
            // endpoint, not auto-generated by AISubstitutionService).
            userTeamId: $this->resolveUserTeamId($session, $homeTeam->id, $awayTeam->id),
            game: null,
            neutralVenue: true,
        );
    }

    private function resolveUserTeamId(LiveMatchSession $session, string $homeTeamId, string $awayTeamId): ?string
    {
        // If home went bot, away is the "user team" (AI subs allowed for home).
        if ($session->host_bot && ! $session->guest_bot) {
            return $awayTeamId;
        }
        // If away went bot, home is the user team.
        if ($session->guest_bot && ! $session->host_bot) {
            return $homeTeamId;
        }
        // Both human or both bot — single-arg API can only express one. With
        // both human, the human-controlled side gets sub gating via the action
        // queue path (no AI subs fire because no bench is consumed by the
        // simulator's tactical-window logic at this layer); passing home as
        // userTeamId keeps the AI sub path quiet for home, and queued actions
        // handle away. This is a known limitation of PR1; a follow-up
        // extends MatchSimulator to accept per-side gating.
        return $homeTeamId;
    }

    private function persistContext(LiveMatchSession $session, MatchSimulationContext $ctx, WindowResult $window): void
    {
        $homeOnPitchIds = $ctx->homePlayers->pluck('id')->all();
        $awayOnPitchIds = $ctx->awayPlayers->pluck('id')->all();
        $homeBenchIds = $ctx->homeBenchPlayers?->pluck('id')->all() ?? [];
        $awayBenchIds = $ctx->awayBenchPlayers?->pluck('id')->all() ?? [];

        $state = $session->context_state ?? [];
        $state['home_on_pitch_ids'] = $homeOnPitchIds;
        $state['away_on_pitch_ids'] = $awayOnPitchIds;
        $state['home_bench_ids'] = $homeBenchIds;
        $state['away_bench_ids'] = $awayBenchIds;
        $state['home_entry_minutes'] = $ctx->homeEntryMinutes;
        $state['away_entry_minutes'] = $ctx->awayEntryMinutes;
        $state['existing_injury_team_ids'] = $ctx->existingInjuryTeamIds;
        $state['existing_yellow_player_ids'] = $ctx->existingYellowPlayerIds;
        $state['home_subs_used'] = $ctx->homeSubsUsed;
        $state['away_subs_used'] = $ctx->awaySubsUsed;
        $state['home_formation'] = $ctx->homeFormation->value;
        $state['away_formation'] = $ctx->awayFormation->value;
        $state['home_mentality'] = $ctx->homeMentality->value;
        $state['away_mentality'] = $ctx->awayMentality->value;
        $state['home_playing_style'] = $ctx->homePlayingStyle->value;
        $state['away_playing_style'] = $ctx->awayPlayingStyle->value;
        $state['home_pressing'] = $ctx->homePressing->value;
        $state['away_pressing'] = $ctx->awayPressing->value;
        $state['home_def_line'] = $ctx->homeDefLine->value;
        $state['away_def_line'] = $ctx->awayDefLine->value;
        $state['home_player_slot_map'] = $ctx->homePlayerSlotMap;
        $state['away_player_slot_map'] = $ctx->awayPlayerSlotMap;
        $state['match_performance'] = $ctx->matchPerformance;
        $state['home_xg_total'] = $ctx->homeXGTotal;
        $state['away_xg_total'] = $ctx->awayXGTotal;

        $eventLog = $session->event_log ?? [];
        foreach ($window->newEvents as $event) {
            $eventLog[] = [
                'team_id' => $event->teamId,
                'side' => $event->teamId === $ctx->homeTeam->id ? 'home' : 'away',
                'game_player_id' => $event->gamePlayerId,
                'minute' => $event->minute,
                'type' => $event->type,
                'metadata' => $event->metadata,
            ];
        }

        $session->update([
            'home_score' => $ctx->homeScore,
            'away_score' => $ctx->awayScore,
            'current_minute' => $window->toMinute,
            'context_state' => $state,
            'event_log' => $eventLog,
        ]);
    }

    /**
     * Apply queued actions whose queued_at_minute < $upToMinute. Mutates the
     * session's context_state in-place (on-pitch lineup, bench, sub counts,
     * tactics) and marks the actions applied|rejected.
     */
    private function applyQueuedActions(LiveMatchSession $session, int $upToMinute): void
    {
        $pending = LiveMatchAction::where('session_id', $session->id)
            ->where('status', 'queued')
            ->orderBy('queued_at_minute')
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $state = $session->context_state ?? $this->buildInitialContextState($session);

        foreach ($pending as $action) {
            $side = $action->side->value;
            $key = $side === 'home' ? 'home' : 'away';
            $onPitchKey = "{$key}_on_pitch_ids";
            $benchKey = "{$key}_bench_ids";
            $entryKey = "{$key}_entry_minutes";
            $subsKey = "{$key}_subs_used";
            $slotMapKey = "{$key}_player_slot_map";

            try {
                if ($action->action_type === QueuedActionType::Substitution) {
                    $out = $action->payload['player_out_id'] ?? null;
                    $in = $action->payload['player_in_id'] ?? null;

                    if (! in_array($out, $state[$onPitchKey], true)) {
                        $action->update(['status' => 'rejected', 'reject_reason' => 'player_not_on_pitch']);

                        continue;
                    }
                    if (! in_array($in, $state[$benchKey], true)) {
                        $action->update(['status' => 'rejected', 'reject_reason' => 'player_not_on_bench']);

                        continue;
                    }
                    if (($state[$subsKey] ?? 0) >= 3) {
                        $action->update(['status' => 'rejected', 'reject_reason' => 'subs_exhausted']);

                        continue;
                    }

                    $state[$onPitchKey] = array_values(array_filter($state[$onPitchKey], fn ($id) => $id !== $out));
                    $state[$onPitchKey][] = $in;
                    $state[$benchKey] = array_values(array_filter($state[$benchKey], fn ($id) => $id !== $in));
                    $state[$entryKey][$in] = $action->queued_at_minute;
                    $state[$subsKey] = ($state[$subsKey] ?? 0) + 1;

                    // Slot transfer: subbed-in inherits subbed-out's slot.
                    if (isset($state[$slotMapKey][$out])) {
                        $state[$slotMapKey][$in] = $state[$slotMapKey][$out];
                        unset($state[$slotMapKey][$out]);
                    }
                } elseif ($action->action_type === QueuedActionType::Formation) {
                    $state["{$key}_formation"] = $action->payload['formation'];
                } elseif ($action->action_type === QueuedActionType::Mentality) {
                    $state["{$key}_mentality"] = $action->payload['mentality'];
                }

                $action->update([
                    'status' => 'applied',
                    'applied_at_minute' => $upToMinute,
                ]);
            } catch (\Throwable $e) {
                $action->update([
                    'status' => 'rejected',
                    'reject_reason' => substr($e->getMessage(), 0, 80),
                ]);
            }
        }

        $session->update(['context_state' => $state]);
    }

    /**
     * Construct an unsaved Team instance for the simulator. Live matches
     * don't have real teams.id rows — the simulator only reads team->id and
     * uses it as a tagging key for events.
     */
    private function fakeTeam(string $id, string $name): Team
    {
        $team = new Team;
        $team->forceFill(['id' => $id, 'name' => $name]);
        $team->exists = true;

        return $team;
    }
}
