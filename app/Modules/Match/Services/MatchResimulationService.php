<?php

namespace App\Modules\Match\Services;

use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\DTOs\MatchResult;
use App\Modules\Match\DTOs\ResimulationResult;
use App\Modules\Match\DTOs\TacticalConfig;
use App\Modules\Match\Enums\MatchPhase;
use App\Modules\Match\Support\MinuteCoordinates;
use App\Modules\Match\Support\StoppageDurations;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchRating;
use App\Models\GamePlayerMatchState;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Modules\Squad\Services\EligibilityService;

class MatchResimulationService
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
        private readonly EligibilityService $eligibilityService,
        private readonly MatchEventRepository $matchEventRepository,
        private readonly MatchRatingCalculator $ratingCalculator,
    ) {}

    /**
     * Revert events after a given minute, re-simulate the match remainder,
     * apply new events, update score and standings.
     *
     * @param  array  $allSubstitutions  All subs (previous + new) [{playerOutId, playerInId, minute}]
     */
    public function resimulate(
        GameMatch $match,
        Game $game,
        int $minute,
        Collection $homePlayers,
        Collection $awayPlayers,
        array $allSubstitutions = [],
        ?Collection $homeBenchPlayers = null,
        ?Collection $awayBenchPlayers = null,
        bool $autoSubUserTeam = false,
    ): ResimulationResult {
        return DB::transaction(function () use ($match, $game, $minute, $homePlayers, $awayPlayers, $allSubstitutions, $homeBenchPlayers, $awayBenchPlayers, $autoSubUserTeam) {
            return $this->doResimulate($match, $game, $minute, $homePlayers, $awayPlayers, $allSubstitutions, $homeBenchPlayers, $awayBenchPlayers, $autoSubUserTeam);
        });
    }

    private function doResimulate(
        GameMatch $match,
        Game $game,
        int $minute,
        Collection $homePlayers,
        Collection $awayPlayers,
        array $allSubstitutions = [],
        ?Collection $homeBenchPlayers = null,
        ?Collection $awayBenchPlayers = null,
        bool $autoSubUserTeam = false,
    ): ResimulationResult {
        $competitionId = $match->competition_id;
        $stoppage = StoppageDurations::fromMatch($match);

        // 1. Capture old scores
        $oldHomeScore = $match->home_score;
        $oldAwayScore = $match->away_score;

        // When the user pauses at minute 90, regulation isn't actually over —
        // stoppage time (minute 91..90+shs) is still in the regulation phase.
        // Pin the cutoff to the regulation end so a sub stamped at minute 90
        // doesn't wipe stoppage events that count toward home_score/away_score.
        // Phases give us this for free: anywhere in regulation maps to a
        // SECOND_HALF_STOPPAGE-or-earlier tuple, and the simulator's remainder
        // restarts from the corresponding raw minute.
        //
        // Same idea at half-time: a tactical action stamped at minute 45 must
        // not wipe goals/cards that landed in 1H stoppage (raw minute 46..45+fhs).
        // The live clock snaps back to 45 when entering half-time, so the
        // frontend POSTs minute=45 even though the user has already watched
        // events from the stoppage window — those events would be reverted
        // without this lift.
        $resimAnchor = match (true) {
            $minute >= 90 => $stoppage->regulationEnd(),
            $minute === 45 => 45 + $stoppage->firstHalf,
            default => $minute,
        };

        // 2. Revert all events that happened after the cutoff. Uses phase
        // tuple comparison so a 91' ET goal (phase=ET_FIRST_HALF) is not
        // confused with a 91' regulation-stoppage goal (phase=SECOND_HALF_STOPPAGE).
        $this->revertEventsAfterMinute($match, $resimAnchor, $competitionId, $stoppage);

        // 3. Calculate regulation score from remaining events. We count goals
        // in regulation phases (FH/FHS/SH/SHS); ET goals don't roll into
        // home_score/away_score.
        $scoreAtMinute = $this->scoreFromEventsInPhases($match, MatchPhase::regulation());

        // 4. Read formation/mentality/instructions from match record (already updated by caller)
        $tc = TacticalConfig::fromMatch($match);

        // Single load of post-revert events at the anchor — every classifier
        // below (red-card / substitution exclusion, auto-sub re-add, opponent
        // sub windows) projects from the same set, so one query suffices.
        $eventsAtAnchor = $this->eventsUpTo($match->id, $resimAnchor, $stoppage);

        // 5. Exclude red-carded and substituted-out players. Phase-aware so
        // late-regulation events at minute 91+ aren't misclassified.
        $unavailablePlayerIds = $eventsAtAnchor
            ->whereIn('event_type', ['red_card', 'substitution'])
            ->pluck('game_player_id')
            ->all();

        $homePlayers = $homePlayers->reject(fn ($p) => in_array($p->id, $unavailablePlayerIds));
        $awayPlayers = $awayPlayers->reject(fn ($p) => in_array($p->id, $unavailablePlayerIds));

        // 5b. Add back players who entered via substitution (injury auto-subs
        // or tactical subs that happened during pre-simulation). Without this,
        // the team plays with fewer than 11 players because the subbed-out
        // player was removed above but the replacement was never added.
        $isUserHome = $match->isHomeTeam($game->team_id);
        $userTeamId = $game->team_id;

        $userAutoSubEvents = $eventsAtAnchor
            ->where('team_id', $userTeamId)
            ->where('event_type', 'substitution')
            ->whereNotIn('game_player_id', collect($allSubstitutions)->pluck('playerOutId')->all());

        $autoSubPlayerInIds = $userAutoSubEvents
            ->map(fn ($e) => $e->metadata['player_in_id'] ?? null)
            ->filter()
            ->all();

        if (! empty($autoSubPlayerInIds)) {
            $autoSubPlayersIn = GamePlayer::query()
                ->whereIn('id', $autoSubPlayerInIds)
                ->get();

            if ($isUserHome) {
                $homePlayers = $homePlayers->merge($autoSubPlayersIn)->values();
            } else {
                $awayPlayers = $awayPlayers->merge($autoSubPlayersIn)->values();
            }
        }

        // 6. Get existing injuries/yellows for context
        $existingInjuryTeamIds = $eventsAtAnchor
            ->where('event_type', 'injury')
            ->pluck('team_id')
            ->unique()
            ->all();

        $existingYellowPlayerIds = $eventsAtAnchor
            ->where('event_type', 'yellow_card')
            ->pluck('game_player_id')
            ->unique()
            ->all();

        // 7. Build entry minute maps from substitutions
        $homeEntryMinutes = [];
        $awayEntryMinutes = [];
        // User's manual substitutions
        foreach ($allSubstitutions as $sub) {
            if ($isUserHome) {
                $homeEntryMinutes[$sub['playerInId']] = $sub['minute'];
            } else {
                $awayEntryMinutes[$sub['playerInId']] = $sub['minute'];
            }
        }
        // User's pre-simulated auto-subs (injury auto-subs from the initial
        // simulation that aren't in the frontend's manual sub list). Entry
        // minutes use raw absolute time — the simulator's internal coord —
        // so DB-loaded events get converted from their phase tuple.
        foreach ($userAutoSubEvents as $subEvent) {
            $playerInId = $subEvent->metadata['player_in_id'] ?? null;
            if ($playerInId === null) {
                continue;
            }
            $entryMinute = $this->absoluteMinuteFor($subEvent, $stoppage);
            if ($isUserHome) {
                $homeEntryMinutes[$playerInId] = $entryMinute;
            } else {
                $awayEntryMinutes[$playerInId] = $entryMinute;
            }
        }
        // Opponent's substitutions up to the resimulation minute. Read from match_events
        // (source of truth after any prior resimulations) rather than $match->substitutions,
        // which is populated by the initial simulation and not updated by subsequent resims
        // — leaving it stale and out of sync with the actual events.
        $opponentTeamId = $isUserHome ? $match->away_team_id : $match->home_team_id;
        $opponentSubEvents = $eventsAtAnchor
            ->where('team_id', $opponentTeamId)
            ->where('event_type', 'substitution');
        foreach ($opponentSubEvents as $subEvent) {
            $playerInId = $subEvent->metadata['player_in_id'] ?? null;
            if ($playerInId === null) {
                continue;
            }
            $entryMinute = $this->absoluteMinuteFor($subEvent, $stoppage);
            if ($isUserHome) {
                $awayEntryMinutes[$playerInId] = $entryMinute;
            } else {
                $homeEntryMinutes[$playerInId] = $entryMinute;
            }
        }

        // 8. Count existing substitutions and windows per team to enforce limits.
        // Both teams' counts come from match_events (post-revert state) so repeated
        // resimulations can't push past the sub/window caps by counting against
        // stale snapshots. The user's manual subs are combined with any pre-simulated
        // auto-subs that occurred before the resimulation minute.
        $userSubCount = count($allSubstitutions) + $userAutoSubEvents->count();
        $opponentSubCount = $opponentSubEvents->count();
        $homeExistingSubs = $isUserHome ? $userSubCount : $opponentSubCount;
        $awayExistingSubs = $isUserHome ? $opponentSubCount : $userSubCount;

        $opponentWindowsUsed = $opponentSubEvents
            ->pluck('minute')
            ->unique()
            ->count();
        // Combine manual and auto-sub minutes to get total windows used.
        // Subs at the same minute share a window (counted once).
        $userWindowsUsed = collect($allSubstitutions)
            ->pluck('minute')
            ->merge($userAutoSubEvents->pluck('minute'))
            ->unique()
            ->count();
        $homeWindowsUsed = $isUserHome ? $userWindowsUsed : $opponentWindowsUsed;
        $awayWindowsUsed = $isUserHome ? $opponentWindowsUsed : $userWindowsUsed;

        // 9. Re-simulate the remainder with AI substitutions.
        // By default only the opponent gets auto-subs (the user controls their own
        // team via the tactical panel). When $autoSubUserTeam is true — set by the
        // "Skip to end" flow — both teams get auto-subs so the match finishes with
        // realistic substitutions instead of the tired starting 11.
        $hasOpponentBench = $isUserHome
            ? ($awayBenchPlayers !== null && $awayBenchPlayers->isNotEmpty())
            : ($homeBenchPlayers !== null && $homeBenchPlayers->isNotEmpty());
        $hasUserBench = $isUserHome
            ? ($homeBenchPlayers !== null && $homeBenchPlayers->isNotEmpty())
            : ($awayBenchPlayers !== null && $awayBenchPlayers->isNotEmpty());
        $hasAnyBench = $hasOpponentBench || ($autoSubUserTeam && $hasUserBench);

        $aiSubMode = config('match_simulation.ai_substitutions.mode', 'all');
        $aiSubsActive = $hasAnyBench && match ($aiSubMode) {
            'all' => true,
            'ai_only' => false, // user is in the match, so skip in ai_only mode
            default => false,
        };

        // Slot maps come straight from the persisted {side}_slot_assignments,
        // which TacticalChangeService recomputes via LineupService after every
        // substitution or formation change. No mid-stream sub replay is
        // needed — the authoritative map already reflects the active XI.
        $homePlayerSlots = $match->playerSlotMap('home');
        $awayPlayerSlots = $match->playerSlotMap('away');

        // Seed the simulator with previously rolled performance modifiers so a
        // player's "form on the day" persists across the full match. Without
        // this, every resimulation (tactical change, half-time play, skip to
        // end) re-rolls performance — making the in-match player rating
        // non-predictive and misleading the user's sub decisions.
        // Subs who weren't on the pitch yet will get a fresh roll naturally
        // via getMatchPerformance() when first called.
        $cachedPerformances = Cache::get("match_performances:{$match->id}", []);
        $this->matchSimulator->seedPerformance($cachedPerformances);

        $regulationEnd = $stoppage->regulationEnd();

        if ($aiSubsActive) {
            $remainderOutput = $this->matchSimulator->simulateRemainderWithAISubs(
                $match->homeTeam,
                $match->awayTeam,
                $homePlayers,
                $awayPlayers,
                $tc->homeFormation,
                $tc->awayFormation,
                $tc->homeMentality,
                $tc->awayMentality,
                $resimAnchor,
                $game,
                $existingInjuryTeamIds,
                $existingYellowPlayerIds,
                $homeEntryMinutes,
                $awayEntryMinutes,
                $tc->homePlayingStyle,
                $tc->awayPlayingStyle,
                $tc->homePressing,
                $tc->awayPressing,
                $tc->homeDefLine,
                $tc->awayDefLine,
                $homeBenchPlayers,
                $awayBenchPlayers,
                homeExistingSubstitutions: $homeExistingSubs,
                awayExistingSubstitutions: $awayExistingSubs,
                homeWindowsUsed: $homeWindowsUsed,
                awayWindowsUsed: $awayWindowsUsed,
                scoreHomeAtMinute: $scoreAtMinute['home'],
                scoreAwayAtMinute: $scoreAtMinute['away'],
                // Passing null opts the user team INTO auto-subs for this remainder.
                userTeamId: $autoSubUserTeam ? null : $game->team_id,
                homePlayerSlots: $homePlayerSlots,
                awayPlayerSlots: $awayPlayerSlots,
                preservePerformance: true,
                toMinute: $regulationEnd,
            );
        } else {
            $remainderOutput = $this->matchSimulator->simulateRemainder(
                $match->homeTeam,
                $match->awayTeam,
                $homePlayers,
                $awayPlayers,
                $tc->homeFormation,
                $tc->awayFormation,
                $tc->homeMentality,
                $tc->awayMentality,
                $resimAnchor,
                $game,
                $existingInjuryTeamIds,
                $existingYellowPlayerIds,
                $homeEntryMinutes,
                $awayEntryMinutes,
                $tc->homePlayingStyle,
                $tc->awayPlayingStyle,
                $tc->homePressing,
                $tc->awayPressing,
                $tc->homeDefLine,
                $tc->awayDefLine,
                $homeBenchPlayers,
                $awayBenchPlayers,
                homeExistingSubstitutions: $homeExistingSubs,
                awayExistingSubstitutions: $awayExistingSubs,
                neutralVenue: $match->isNeutralVenue(),
                preservePerformance: true,
                toMinute: $regulationEnd,
                homePlayerSlots: $homePlayerSlots,
                awayPlayerSlots: $awayPlayerSlots,
                // Mirrors the AI-subs branch above: passing null opts the
                // user team INTO injury auto-subs (Skip to end / fast mode).
                userTeamId: $autoSubUserTeam ? null : $game->team_id,
            );
        }

        // 10. Calculate new final score
        $remainderResult = $remainderOutput->result;
        $newHomeScore = $scoreAtMinute['home'] + $remainderResult->homeScore;
        $newAwayScore = $scoreAtMinute['away'] + $remainderResult->awayScore;

        // 11. Merge performances with cached values.
        // The simulator was seeded with these cached values so players on the
        // pitch keep their original "form on the day" and appear unchanged in
        // $remainderOutput->performances. array_merge still serves a purpose:
        // it keeps the records of subbed-out players (who aren't touched by
        // the remainder simulation) and folds in any fresh rolls for subs who
        // came onto the pitch. It also preserves any xG-adjustment tweaks
        // applied during the remainder (±0.04).
        $mergedPerformances = array_merge($cachedPerformances, $remainderOutput->performances);
        Cache::put("match_performances:{$match->id}", $mergedPerformances, now()->addHours(24));

        // 12. Apply the new remainder events
        $this->applyNewEvents($match, $game, $remainderResult, $competitionId);

        // 13. Recalculate MVP from all events and merged performances
        $allEvents = MatchEvent::where('game_match_id', $match->id)->get();

        // Rebuild full player collections including substituted-out players
        // (they were excluded from resimulation but still have performances)
        $performancePlayerIds = array_keys($mergedPerformances);
        $allMvpPlayers = GamePlayer::whereIn('id', $performancePlayerIds)->get();
        $allHomePlayers = $allMvpPlayers->filter(fn ($p) => $p->team_id === $match->home_team_id);
        $allAwayPlayers = $allMvpPlayers->filter(fn ($p) => $p->team_id === $match->away_team_id);

        $mvpPlayerId = MvpCalculator::calculate(
            $mergedPerformances,
            $allHomePlayers,
            $allAwayPlayers,
            $match->home_team_id,
            $match->away_team_id,
            $newHomeScore,
            $newAwayScore,
            $allEvents,
        );

        // 13b. Rewrite persisted ratings so the post-match summary reflects the
        // new outcome. Scoped delete-then-insert is simpler than maintaining an
        // upsert key across two write sites, and is bounded to ~22 rows.
        $this->rewriteMatchRatings(
            $match,
            $mergedPerformances,
            $allHomePlayers,
            $allAwayPlayers,
            $newHomeScore,
            $newAwayScore,
            $allEvents,
        );

        // 14. Update match score, possession, and MVP
        // Note: Score-dependent side effects (standings, cup ties, GK stats, prize money)
        // are NOT handled here. They are deferred to FinalizeMatch, which applies them
        // once after the user finishes the live match. This eliminates the need for
        // fragile reversal logic on every resimulation.
        $match->update([
            'home_score' => $newHomeScore,
            'away_score' => $newAwayScore,
            'home_possession' => $remainderResult->homePossession,
            'away_possession' => $remainderResult->awayPossession,
            'mvp_player_id' => $mvpPlayerId,
        ]);

        return new ResimulationResult(
            $newHomeScore, $newAwayScore, $oldHomeScore, $oldAwayScore,
            $remainderResult->homePossession, $remainderResult->awayPossession,
            $mergedPerformances,
            $mvpPlayerId,
        );
    }

    /**
     * Re-simulate extra time from a given minute (after an ET substitution or tactical change).
     * Same structure as doResimulate() but targets ET scores and uses simulateExtraTime().
     */
    public function resimulateExtraTime(
        GameMatch $match,
        Game $game,
        int $minute,
        Collection $homePlayers,
        Collection $awayPlayers,
        array $allSubstitutions = [],
        ?Collection $homeBenchPlayers = null,
        ?Collection $awayBenchPlayers = null,
    ): ResimulationResult {
        return DB::transaction(function () use ($match, $game, $minute, $homePlayers, $awayPlayers, $allSubstitutions, $homeBenchPlayers, $awayBenchPlayers) {
            $competitionId = $match->competition_id;
            $stoppage = StoppageDurations::fromMatch($match);

            // 1. Capture old ET scores
            $oldHomeScore = $match->home_score_et ?? 0;
            $oldAwayScore = $match->away_score_et ?? 0;

            // In early ET the clock can sit anywhere from "just past regulation
            // end" upward. Pin the cutoff to no earlier than regulation end so
            // a sub stamped at the start of ET first half doesn't accidentally
            // wipe regulation-stoppage events.
            $regulationEnd = $stoppage->regulationEnd();
            $resimAnchor = max($minute, $regulationEnd);

            // 2. Revert all events after the cutoff (phase tuple comparison
            // — a 91' ET goal carries phase=ET_FIRST_HALF, not regulation
            // stoppage, even though the raw minute would be ambiguous).
            $this->revertEventsAfterMinute($match, $resimAnchor, $competitionId, $stoppage);

            // 3. Calculate ET-only score from remaining ET-phase goals.
            $scoreAtMinute = $this->scoreFromEventsInPhases($match, MatchPhase::extraTime());

            // 4. Read formation/mentality/instructions from match record
            $tc = TacticalConfig::fromMatch($match);

            // Single load of post-revert events at the anchor — every
            // classifier below projects from the same set.
            $eventsAtAnchor = $this->eventsUpTo($match->id, $resimAnchor, $stoppage);

            // 5. Exclude red-carded and substituted-out players
            $unavailablePlayerIds = $eventsAtAnchor
                ->whereIn('event_type', ['red_card', 'substitution'])
                ->pluck('game_player_id')
                ->all();

            $homePlayers = $homePlayers->reject(fn ($p) => in_array($p->id, $unavailablePlayerIds));
            $awayPlayers = $awayPlayers->reject(fn ($p) => in_array($p->id, $unavailablePlayerIds));

            // 5b. Add back players who entered via auto-sub (same fix as doResimulate)
            $isUserHome = $match->isHomeTeam($game->team_id);
            $userTeamId = $game->team_id;

            $userAutoSubEvents = $eventsAtAnchor
                ->where('team_id', $userTeamId)
                ->where('event_type', 'substitution')
                ->whereNotIn('game_player_id', collect($allSubstitutions)->pluck('playerOutId')->all());

            $autoSubPlayerInIds = $userAutoSubEvents
                ->map(fn ($e) => $e->metadata['player_in_id'] ?? null)
                ->filter()
                ->all();

            if (! empty($autoSubPlayerInIds)) {
                $autoSubPlayersIn = GamePlayer::query()
                    ->whereIn('id', $autoSubPlayerInIds)
                    ->get();

                if ($isUserHome) {
                    $homePlayers = $homePlayers->merge($autoSubPlayersIn)->values();
                } else {
                    $awayPlayers = $awayPlayers->merge($autoSubPlayersIn)->values();
                }
            }

            // 6. Build entry minute maps from substitutions
            $homeEntryMinutes = [];
            $awayEntryMinutes = [];
            // User's manual substitutions
            foreach ($allSubstitutions as $sub) {
                if ($isUserHome) {
                    $homeEntryMinutes[$sub['playerInId']] = $sub['minute'];
                } else {
                    $awayEntryMinutes[$sub['playerInId']] = $sub['minute'];
                }
            }
            // User's pre-simulated auto-subs. Entry minutes use raw absolute
            // time so the simulator's internal scheduling stays consistent
            // when the DB-loaded event lives in a stoppage phase.
            foreach ($userAutoSubEvents as $subEvent) {
                $playerInId = $subEvent->metadata['player_in_id'] ?? null;
                if ($playerInId === null) {
                    continue;
                }
                $entryMinute = $this->absoluteMinuteFor($subEvent, $stoppage);
                if ($isUserHome) {
                    $homeEntryMinutes[$playerInId] = $entryMinute;
                } else {
                    $awayEntryMinutes[$playerInId] = $entryMinute;
                }
            }

            // 7. Re-simulate extra time remainder.
            // Slot maps come from the persisted {side}_slot_assignments —
            // always current thanks to TacticalChangeService's post-sub
            // recompute via LineupService.
            $homePlayerSlots = $match->playerSlotMap('home');
            $awayPlayerSlots = $match->playerSlotMap('away');

            // Seed the simulator with previously rolled performance modifiers so
            // players keep their "form on the day" into extra time instead of
            // getting fresh rolls. Subs who came on during ET will get a fresh
            // roll naturally on first call to getMatchPerformance().
            $etSeedPerformances = Cache::get("match_performances:{$match->id}", []);
            $this->matchSimulator->seedPerformance($etSeedPerformances);

            $remainderResult = $this->matchSimulator->simulateExtraTime(
                $match->homeTeam,
                $match->awayTeam,
                $homePlayers,
                $awayPlayers,
                $homeEntryMinutes,
                $awayEntryMinutes,
                fromMinute: $resimAnchor,
                homeFormation: $tc->homeFormation,
                awayFormation: $tc->awayFormation,
                homeMentality: $tc->homeMentality,
                awayMentality: $tc->awayMentality,
                homePlayingStyle: $tc->homePlayingStyle,
                awayPlayingStyle: $tc->awayPlayingStyle,
                homePressing: $tc->homePressing,
                awayPressing: $tc->awayPressing,
                homeDefLine: $tc->homeDefLine,
                awayDefLine: $tc->awayDefLine,
                neutralVenue: $match->isNeutralVenue(),
                homePlayerSlots: $homePlayerSlots,
                awayPlayerSlots: $awayPlayerSlots,
                regulationStoppage: $stoppage->secondHalf,
            );

            // 8. Calculate new ET score
            $newHomeScore = $scoreAtMinute['home'] + $remainderResult->homeScore;
            $newAwayScore = $scoreAtMinute['away'] + $remainderResult->awayScore;

            // 9. Apply the new remainder events
            $this->applyNewEvents($match, $game, $remainderResult, $competitionId);

            // 10. Recalculate MVP from all events (regular + ET)
            $cachedPerformances = Cache::get("match_performances:{$match->id}", []);
            $allEvents = MatchEvent::where('game_match_id', $match->id)->get();
            $performancePlayerIds = array_keys($cachedPerformances);
            $allMvpPlayers = GamePlayer::whereIn('id', $performancePlayerIds)->get();

            $totalHomeScore = $match->home_score + $newHomeScore;
            $totalAwayScore = $match->away_score + $newAwayScore;

            $etHomePlayers = $allMvpPlayers->filter(fn ($p) => $p->team_id === $match->home_team_id);
            $etAwayPlayers = $allMvpPlayers->filter(fn ($p) => $p->team_id === $match->away_team_id);

            $mvpPlayerId = MvpCalculator::calculate(
                $cachedPerformances,
                $etHomePlayers,
                $etAwayPlayers,
                $match->home_team_id,
                $match->away_team_id,
                $totalHomeScore,
                $totalAwayScore,
                $allEvents,
            );

            // 10b. Rewrite persisted ratings — ET events (late goals, late cards,
            // clean-sheet evaporation) change the rating distribution.
            $this->rewriteMatchRatings(
                $match,
                $cachedPerformances,
                $etHomePlayers,
                $etAwayPlayers,
                $totalHomeScore,
                $totalAwayScore,
                $allEvents,
            );

            // 11. Update ET scores, possession, and MVP
            $match->update([
                'home_score_et' => $newHomeScore,
                'away_score_et' => $newAwayScore,
                'home_possession' => $remainderResult->homePossession,
                'away_possession' => $remainderResult->awayPossession,
                'mvp_player_id' => $mvpPlayerId,
            ]);

            return new ResimulationResult(
                $newHomeScore, $newAwayScore, $oldHomeScore, $oldAwayScore,
                $remainderResult->homePossession, $remainderResult->awayPossession,
                $cachedPerformances,
                $mvpPlayerId,
            );
        });
    }

    /**
     * Delete all persisted ratings for this match and rewrite them from the
     * post-resimulation performances + events. Scoped to a single match so
     * other matches in the table are untouched.
     */
    private function rewriteMatchRatings(
        GameMatch $match,
        array $performances,
        Collection $homePlayers,
        Collection $awayPlayers,
        int $homeScore,
        int $awayScore,
        Collection $events,
    ): void {
        $ratings = $this->ratingCalculator->calculate(
            [
                'performances' => $performances,
                'homeTeamId' => $match->home_team_id,
                'awayTeamId' => $match->away_team_id,
                'homeScore' => $homeScore,
                'awayScore' => $awayScore,
                'events' => $events,
            ],
            $homePlayers,
            $awayPlayers,
        );

        GamePlayerMatchRating::where('game_match_id', $match->id)->delete();

        if (empty($ratings)) {
            return;
        }

        $rows = [];
        foreach ($ratings as $playerId => $row) {
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $match->game_id,
                'game_match_id' => $match->id,
                'game_player_id' => $playerId,
                'rating' => $row['rating'],
                'performance_modifier' => $row['performance_modifier'],
            ];
        }

        GamePlayerMatchRating::insert($rows);
    }

    /**
     * Revert all match events after a given minute and rebuild affected player stats.
     *
     * Instead of manually decrementing stats (fragile mirror of applyNewEvents),
     * we delete the events, clear side-effects (suspensions/injuries), then
     * recalculate each affected player's stats from all their remaining events.
     */
    private function revertEventsAfterMinute(GameMatch $match, int $minute, string $competitionId, ?StoppageDurations $stoppage = null): void
    {
        $stoppage ??= StoppageDurations::fromMatch($match);

        // Phase-aware revert: an event survives if its raw absolute minute is
        // ≤ the cutoff. Computed in PHP because the cutoff comparison spans
        // (phase, minute, stoppage_minute) and the match-specific stoppage
        // durations — straightforward SQL would be a CASE-heavy expression
        // for negligible gain on ~20 events.
        $eventsToRevert = MatchEvent::where('game_match_id', $match->id)
            ->get()
            ->filter(fn (MatchEvent $e) => $this->absoluteMinuteFor($e, $stoppage) > $minute)
            ->values();

        if ($eventsToRevert->isEmpty()) {
            return;
        }

        $affectedPlayerIds = $eventsToRevert->pluck('game_player_id')->unique()->values()->all();

        // Clear side-effects that can't be recalculated from events alone
        $competition = \App\Models\Competition::find($competitionId);
        $handlerType = $competition->handler_type ?? 'league';
        $rules = $this->eligibilityService->rulesForHandlerType($handlerType);

        // Skip card suspension reversal for pre-season matches (no suspensions to revert)
        $isPreseason = $handlerType === 'preseason';

        if (! $isPreseason) {
            // Pre-load all suspensions for affected players in this competition (single query).
            // game_id scoping is redundant here since whereIn on globally-unique game_player_ids
            // already constrains the result, but we keep it for index alignment and hygiene.
            $suspensionsByPlayer = PlayerSuspension::where('game_id', $match->game_id)
                ->where('competition_id', $competitionId)
                ->whereIn('game_player_id', $affectedPlayerIds)
                ->get()
                ->keyBy('game_player_id');
        }

        foreach ($eventsToRevert as $event) {
            if (! $isPreseason && $event->event_type === 'yellow_card') {
                // Check if this yellow was at a suspension threshold before reverting
                $record = $suspensionsByPlayer->get($event->game_player_id);
                $yellowsBefore = $record->yellow_cards ?? 0;
                $wasAtThreshold = $rules->checkAccumulation($yellowsBefore) !== null;

                PlayerSuspension::revertYellowCard($event->game_player_id, $competitionId);

                // Only clear suspension if this specific yellow caused it
                if ($wasAtThreshold && $record && $record->fresh()->matches_remaining > 0) {
                    $record->update(['matches_remaining' => 0]);
                }
            }

            if (! $isPreseason && $event->event_type === 'red_card') {
                $suspension = $suspensionsByPlayer->get($event->game_player_id);
                if ($suspension && $suspension->matches_remaining > 0) {
                    $suspension->update(['matches_remaining' => 0]);
                }
            }

            if ($event->event_type === 'injury') {
                GamePlayerMatchState::clearInjury($event->game_player_id);
            }
        }

        // Decrement appearances for players who were subbed in via events being reverted
        $subbedInPlayerIds = $eventsToRevert
            ->filter(fn ($e) => $e->event_type === 'substitution' && isset($e->metadata['player_in_id']))
            ->pluck('metadata.player_in_id')
            ->unique()
            ->values()
            ->all();

        if (! empty($subbedInPlayerIds)) {
            GamePlayerMatchState::bulkDecrementAppearances($subbedInPlayerIds);
        }

        // Delete the events
        MatchEvent::whereIn('id', $eventsToRevert->pluck('id')->all())->delete();

        // Recalculate stats for affected players from all their remaining events
        $this->recalculatePlayerStats($affectedPlayerIds, $match->game_id);
    }

    /**
     * Recalculate season stats for the given players from their match events.
     */
    private function recalculatePlayerStats(array $playerIds, string $gameId): void
    {
        if (empty($playerIds)) {
            return;
        }

        // Count each stat type per player from all remaining events
        $statCounts = MatchEvent::where('game_id', $gameId)
            ->whereIn('game_player_id', $playerIds)
            ->whereIn('event_type', ['goal', 'own_goal', 'assist', 'yellow_card', 'red_card'])
            ->selectRaw('game_player_id, event_type, count(*) as cnt')
            ->groupBy('game_player_id', 'event_type')
            ->get();

        // Build a map: [playerId => [column => count]]
        $statsMap = [];
        $columnMap = [
            'goal' => 'goals',
            'own_goal' => 'own_goals',
            'assist' => 'assists',
            'yellow_card' => 'yellow_cards',
            'red_card' => 'red_cards',
        ];

        /** @var object{game_player_id: string, event_type: string, cnt: int} $row */
        foreach ($statCounts as $row) {
            $column = $columnMap[$row->event_type] ?? null;
            if ($column) {
                $statsMap[$row->game_player_id][$column] = $row->cnt;
            }
        }

        // Set stats to counted values (0 if no events remain). Only
        // resimulated matches involve active-team lineups, so the
        // satellite row is guaranteed to exist.
        $updates = [];
        foreach ($playerIds as $playerId) {
            $counts = $statsMap[$playerId] ?? [];
            $updates[$playerId] = [
                'goals' => $counts['goals'] ?? 0,
                'own_goals' => $counts['own_goals'] ?? 0,
                'assists' => $counts['assists'] ?? 0,
                'yellow_cards' => $counts['yellow_cards'] ?? 0,
                'red_cards' => $counts['red_cards'] ?? 0,
            ];
        }

        GamePlayerMatchState::bulkSetValues($updates);
    }

    /**
     * Count goals from remaining events restricted to the given phases.
     *
     * Used in two places: resimulating regulation (count regulation-phase
     * goals) and resimulating extra time (count ET-phase goals). Phase
     * filtering replaces the older `minute > 93` heuristic, which conflated
     * 91-93 regulation-stoppage events with ET first-half events.
     *
     * @param  list<MatchPhase>  $phases
     * @return array{home:int, away:int}
     */
    private function scoreFromEventsInPhases(GameMatch $match, array $phases): array
    {
        $phaseValues = array_map(fn (MatchPhase $p) => $p->value, $phases);

        $events = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('phase', $phaseValues)
            ->whereIn('event_type', ['goal', 'own_goal'])
            ->get();

        $homeScore = 0;
        $awayScore = 0;

        foreach ($events as $event) {
            $isHome = $event->team_id === $match->home_team_id;
            if ($event->event_type === 'goal') {
                $isHome ? $homeScore++ : $awayScore++;
            } else { // own_goal credits the opposing side
                $isHome ? $awayScore++ : $homeScore++;
            }
        }

        return ['home' => $homeScore, 'away' => $awayScore];
    }

    /**
     * Load match events whose raw absolute minute is at-or-before $cutoff.
     * Stoppage-aware: an event at phase=SECOND_HALF_STOPPAGE / minute=90 /
     * stoppage_minute=2 is treated as absolute minute 92+fhs (not 90).
     */
    private function eventsUpTo(string $matchId, int $cutoff, StoppageDurations $stoppage): Collection
    {
        return MatchEvent::where('game_match_id', $matchId)
            ->get()
            ->filter(fn (MatchEvent $e) => $this->absoluteMinuteFor($e, $stoppage) <= $cutoff)
            ->values();
    }

    private function absoluteMinuteFor(MatchEvent $event, StoppageDurations $stoppage): int
    {
        return MinuteCoordinates::toAbsoluteWith($event->phase, $event->minute, $event->stoppage_minute, $stoppage);
    }

    /**
     * Apply new events from re-simulation to the database.
     */
    private function applyNewEvents(GameMatch $match, Game $game, MatchResult $result, string $competitionId): void
    {
        $events = $result->events;
        $competition = \App\Models\Competition::find($competitionId);
        $handlerType = $competition->handler_type ?? 'league';

        $this->matchEventRepository->bulkInsert($events, $game->id, $match->id);

        // Update player stats
        $statIncrements = [];
        $specialEvents = [];
        $subbedInPlayerIds = [];

        foreach ($events as $event) {
            $playerId = $event->gamePlayerId;
            $type = $event->type;

            if (! isset($statIncrements[$playerId])) {
                $statIncrements[$playerId] = [];
            }

            switch ($type) {
                case 'goal':
                case 'own_goal':
                case 'assist':
                    $column = match ($type) {
                        'goal' => 'goals',
                        'own_goal' => 'own_goals',
                        'assist' => 'assists',
                    };
                    $statIncrements[$playerId][$column] = ($statIncrements[$playerId][$column] ?? 0) + 1;
                    break;
                case 'yellow_card':
                    $statIncrements[$playerId]['yellow_cards'] = ($statIncrements[$playerId]['yellow_cards'] ?? 0) + 1;
                    $specialEvents[] = $event;
                    break;
                case 'red_card':
                    $statIncrements[$playerId]['red_cards'] = ($statIncrements[$playerId]['red_cards'] ?? 0) + 1;
                    $specialEvents[] = $event;
                    break;
                case 'injury':
                    $specialEvents[] = $event;
                    break;
                case 'substitution':
                    // Mirrors MatchResultProcessor::bulkUpdateAppearances and
                    // TacticalChangeService: subbed-in players get an appearance.
                    // revertEventsAfterMinute() decrements on revert, so without
                    // this the count drifts negative across resimulations.
                    $playerInId = $event->metadata['player_in_id'] ?? null;
                    if ($playerInId !== null) {
                        $subbedInPlayerIds[] = $playerInId;
                    }
                    break;
            }
        }

        // Batch-load players
        $allPlayerIds = array_unique(array_merge(
            array_keys($statIncrements),
            $specialEvents ? array_map(fn ($e) => $e->gamePlayerId, $specialEvents) : [],
        ));
        $players = GamePlayer::whereIn('id', $allPlayerIds)->get()->keyBy('id');

        // Apply stat increments (small dataset — typically ≤5 event-producing
        // players per re-simulation). Filter to players that actually exist.
        $validIncrements = array_intersect_key($statIncrements, $players->all());
        $validIncrements = array_filter($validIncrements, fn ($inc) => ! empty($inc));
        GamePlayerMatchState::bulkIncrementStats($validIncrements);

        if (! empty($subbedInPlayerIds)) {
            GamePlayerMatchState::bulkIncrementAppearances(array_values(array_unique($subbedInPlayerIds)));
        }

        // Process special events
        // Skip card suspensions for pre-season matches (cards are recorded but don't carry over)
        $isPreseason = $handlerType === 'preseason';

        foreach ($specialEvents as $event) {
            $player = $players->get($event->gamePlayerId);
            if (! $player) {
                continue;
            }

            switch ($event->type) {
                case 'yellow_card':
                    if (! $isPreseason) {
                        $this->eligibilityService->processYellowCard($player->id, $player->game_id, $competitionId, $handlerType);
                    }
                    break;
                case 'red_card':
                    if (! $isPreseason) {
                        $isSecondYellow = $event->metadata['second_yellow'] ?? false;
                        $this->eligibilityService->processRedCard($player, $isSecondYellow, $competitionId);
                    }
                    break;
                case 'injury':
                    $injuryType = $event->metadata['injury_type'] ?? 'Unknown injury';
                    $weeksOut = $event->metadata['weeks_out'] ?? 2;
                    $this->eligibilityService->applyInjury(
                        $player,
                        $injuryType,
                        $weeksOut,
                        Carbon::parse($match->scheduled_date),
                    );
                    break;
            }
        }
    }

    /**
     * Build formatted events response for the frontend after re-simulation.
     */
    public function buildEventsResponse(GameMatch $match, int $minute): array
    {
        $stoppage = StoppageDurations::fromMatch($match);

        $newEvents = MatchEvent::with('gamePlayer')
            ->where('game_match_id', $match->id)
            ->orderedChronologically()
            ->get()
            ->filter(fn (MatchEvent $e) => $this->absoluteMinuteFor($e, $stoppage) > $minute)
            ->values();

        return self::formatMatchEvents($newEvents);
    }

    /**
     * Format a collection of MatchEvent models for the frontend.
     *
     * Resolves player-in names for substitution events, pairs assists with goals,
     * and returns a sorted array ready for JSON serialization.
     *
     * Each emitted event carries:
     *   - minute:        raw absolute clock minute (for the live-match JS
     *                    which compares against state.currentMinute)
     *   - baseMinute:    base minute in phase (1-45, 46-90, 91-105, 106-120)
     *   - stoppageMinute: nullable; e.g. 2 for "45+2'"
     *   - displayMinute: pre-formatted string ("45+2'" or "47'")
     *   - phase:         phase value (frontend can use this to draw HT markers)
     */
    public static function formatMatchEvents(Collection $events): array
    {
        // Batch-load player-in names for substitution events
        $playerInIds = $events
            ->filter(fn ($e) => $e->event_type === 'substitution')
            ->map(fn ($e) => $e->metadata['player_in_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $playerInNames = [];
        if (! empty($playerInIds)) {
            $playerInNames = GamePlayer::query()
                ->whereIn('id', $playerInIds)
                ->get()
                ->mapWithKeys(fn ($gp) => [$gp->id => $gp->name ?? ''])
                ->all();
        }

        // Resolve match-level stoppage once so we can emit raw absolute minute
        // alongside the phase-coordinate fields. All events here belong to the
        // same match (callers pass per-match slices).
        $firstEvent = $events->first();
        $stoppage = $firstEvent
            ? StoppageDurations::fromMatch($firstEvent->gameMatch)
            : new StoppageDurations(0, 3);

        $absoluteMinute = fn ($e) => MinuteCoordinates::toAbsoluteWith(
            $e->phase,
            $e->minute,
            $e->stoppage_minute,
            $stoppage,
        );

        $formatted = $events
            ->filter(fn ($e) => $e->event_type !== 'assist')
            ->map(function ($e) use ($playerInNames, $absoluteMinute) {
                $data = [
                    'minute' => $absoluteMinute($e),
                    'baseMinute' => $e->minute,
                    'stoppageMinute' => $e->stoppage_minute,
                    'phase' => $e->phase->value,
                    'displayMinute' => $e->displayMinute(),
                    'type' => $e->event_type,
                    'playerName' => $e->gamePlayer->name ?? '',
                    'teamId' => $e->team_id,
                    'gamePlayerId' => $e->game_player_id,
                    'metadata' => $e->metadata,
                ];

                if ($e->event_type === 'substitution') {
                    $playerInId = $e->metadata['player_in_id'] ?? null;
                    $data['playerInName'] = $playerInNames[$playerInId] ?? '';
                }

                return $data;
            })
            ->sortBy('minute')
            ->values()
            ->all();

        // Pair assists with their goals. Key includes phase + stoppage so an
        // assist on a 90+3' goal isn't confused with an unrelated 90' event.
        $assists = $events
            ->filter(fn ($e) => $e->event_type === 'assist')
            ->keyBy(fn ($e) => $e->phase->value.':'.$e->minute.':'.($e->stoppage_minute ?? 0).':'.$e->team_id);

        return array_map(function ($event) use ($assists) {
            if ($event['type'] === 'goal') {
                $key = $event['phase'].':'.$event['baseMinute'].':'.($event['stoppageMinute'] ?? 0).':'.$event['teamId'];
                if (isset($assists[$key])) {
                    $event['assistPlayerName'] = $assists[$key]->gamePlayer->name ?? null;
                    $event['assistPlayerId'] = $assists[$key]->game_player_id;
                }
            }

            return $event;
        }, $formatted);
    }

}
