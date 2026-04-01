<?php

namespace App\Modules\Match\Services;

use App\Modules\Match\DTOs\MatchResult;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Models\CupTie;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use Illuminate\Support\Collection;
use App\Modules\Match\Services\MatchSimulator;

class CupTieResolver
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
    ) {}

    /**
     * Attempt to resolve a cup tie and determine the winner.
     * Returns the winner team_id if tie is complete, null if more matches needed.
     */
    public function resolve(CupTie $tie, Collection $allPlayers, ?PlayoffRoundConfig $roundConfig = null): ?string
    {
        $roundConfig ??= $tie->getRoundConfig();

        if (!$roundConfig) {
            return null;
        }

        $firstLeg = $tie->firstLegMatch;

        if (!$firstLeg?->played) {
            return null;
        }

        if ($roundConfig->twoLegged) {
            return $this->resolveTwoLeggedTie($tie, $allPlayers);
        }

        return $this->resolveSingleLegMatch($tie, $firstLeg, $allPlayers);
    }

    /**
     * Resolve a single-leg knockout match.
     * If drawn after 90 minutes, goes to extra time then penalties.
     */
    private function resolveSingleLegMatch(CupTie $tie, GameMatch $match, Collection $allPlayers): string
    {
        $homeScore = $match->home_score;
        $awayScore = $match->away_score;

        // Clear winner after 90 minutes?
        if ($homeScore !== $awayScore) {
            $winnerId = $homeScore > $awayScore ? $match->home_team_id : $match->away_team_id;
            $this->completeTie($tie, $winnerId, ['type' => 'normal']);
            return $winnerId;
        }

        [$homePlayers, $awayPlayers, $homeEntryMinutes, $awayEntryMinutes] = $this->loadCurrentMatchState($match, $allPlayers);

        // Check if ET was already simulated during the live match
        if ($match->is_extra_time) {
            $homeScoreEt = $match->home_score_et ?? 0;
            $awayScoreEt = $match->away_score_et ?? 0;
        } else {
            $homeTeam = $match->relationLoaded('homeTeam') ? $match->homeTeam : Team::find($match->home_team_id);
            $awayTeam = $match->relationLoaded('awayTeam') ? $match->awayTeam : Team::find($match->away_team_id);

            $extraTimeResult = $this->matchSimulator->simulateExtraTime(
                $homeTeam,
                $awayTeam,
                $homePlayers,
                $awayPlayers,
                $homeEntryMinutes,
                $awayEntryMinutes,
                neutralVenue: $match->competition_id === 'WC2026',
            );

            $homeScoreEt = $extraTimeResult->homeScore;
            $awayScoreEt = $extraTimeResult->awayScore;

            $match->update([
                'is_extra_time' => true,
                'home_score_et' => $homeScoreEt,
                'away_score_et' => $awayScoreEt,
                'home_possession' => $extraTimeResult->homePossession,
                'away_possession' => $extraTimeResult->awayPossession,
            ]);
        }

        $totalHome = $homeScore + $homeScoreEt;
        $totalAway = $awayScore + $awayScoreEt;

        if ($totalHome !== $totalAway) {
            $winnerId = $totalHome > $totalAway ? $match->home_team_id : $match->away_team_id;
            $this->completeTie($tie, $winnerId, [
                'type' => 'extra_time',
                'score_after_et' => "{$totalHome}-{$totalAway}",
            ]);
            return $winnerId;
        }

        // Check if penalties were already simulated during the live match
        if ($match->home_score_penalties !== null) {
            $homePens = $match->home_score_penalties;
            $awayPens = $match->away_score_penalties;
        } else {
            [$homePens, $awayPens] = $this->matchSimulator->simulatePenalties($homePlayers, $awayPlayers);

            $match->update([
                'home_score_penalties' => $homePens,
                'away_score_penalties' => $awayPens,
            ]);
        }

        $winnerId = $homePens > $awayPens ? $match->home_team_id : $match->away_team_id;
        $this->completeTie($tie, $winnerId, [
            'type' => 'penalties',
            'score_after_et' => "{$totalHome}-{$totalAway}",
            'penalties' => "{$homePens}-{$awayPens}",
        ]);

        return $winnerId;
    }

    /**
     * Resolve a two-legged tie using aggregate score.
     * If tied on aggregate, extra time and penalties in second leg.
     */
    private function resolveTwoLeggedTie(CupTie $tie, Collection $allPlayers): ?string
    {
        $secondLeg = $tie->secondLegMatch;

        if (!$secondLeg?->played) {
            return null;
        }

        $aggregate = $tie->getAggregateScore();
        $homeTotal = $aggregate['home'];
        $awayTotal = $aggregate['away'];

        // Clear winner on aggregate?
        if ($homeTotal !== $awayTotal) {
            $winnerId = $homeTotal > $awayTotal ? $tie->home_team_id : $tie->away_team_id;
            $this->completeTie($tie, $winnerId, [
                'type' => 'aggregate',
                'aggregate' => "{$homeTotal}-{$awayTotal}",
            ]);
            return $winnerId;
        }

        [$homePlayers, $awayPlayers, $homeEntryMinutes, $awayEntryMinutes] = $this->loadCurrentMatchState($secondLeg, $allPlayers);

        // Tied on aggregate - extra time in second leg
        // Check if ET was already simulated during the live match
        if ($secondLeg->is_extra_time) {
            $homeScoreEt = $secondLeg->home_score_et ?? 0;
            $awayScoreEt = $secondLeg->away_score_et ?? 0;
        } else {
            $homeTeam = $secondLeg->relationLoaded('homeTeam') ? $secondLeg->homeTeam : Team::find($secondLeg->home_team_id);
            $awayTeam = $secondLeg->relationLoaded('awayTeam') ? $secondLeg->awayTeam : Team::find($secondLeg->away_team_id);

            $extraTimeResult = $this->matchSimulator->simulateExtraTime(
                $homeTeam,
                $awayTeam,
                $homePlayers,
                $awayPlayers,
                $homeEntryMinutes,
                $awayEntryMinutes,
                neutralVenue: $secondLeg->competition_id === 'WC2026',
            );

            $homeScoreEt = $extraTimeResult->homeScore;
            $awayScoreEt = $extraTimeResult->awayScore;

            $secondLeg->update([
                'is_extra_time' => true,
                'home_score_et' => $homeScoreEt,
                'away_score_et' => $awayScoreEt,
                'home_possession' => $extraTimeResult->homePossession,
                'away_possession' => $extraTimeResult->awayPossession,
            ]);
        }

        // Extra time goals affect aggregate
        // Second leg home team = tie's away team
        $homeTotal += $awayScoreEt; // Tie's home team was away in 2nd leg
        $awayTotal += $homeScoreEt; // Tie's away team was home in 2nd leg

        if ($homeTotal !== $awayTotal) {
            $winnerId = $homeTotal > $awayTotal ? $tie->home_team_id : $tie->away_team_id;
            $this->completeTie($tie, $winnerId, [
                'type' => 'extra_time',
                'aggregate' => "{$homeTotal}-{$awayTotal}",
            ]);
            return $winnerId;
        }

        // Check if penalties were already simulated during the live match
        if ($secondLeg->home_score_penalties !== null) {
            $homePens = $secondLeg->home_score_penalties;
            $awayPens = $secondLeg->away_score_penalties;
        } else {
            [$homePens, $awayPens] = $this->matchSimulator->simulatePenalties($homePlayers, $awayPlayers);

            $secondLeg->update([
                'home_score_penalties' => $homePens,
                'away_score_penalties' => $awayPens,
            ]);
        }

        // Second leg home team = tie's away team
        $tieHomeWins = $awayPens > $homePens;
        $winnerId = $tieHomeWins ? $tie->home_team_id : $tie->away_team_id;

        $this->completeTie($tie, $winnerId, [
            'type' => 'penalties',
            'aggregate' => "{$homeTotal}-{$awayTotal}",
            'penalties' => $tieHomeWins ? "{$awayPens}-{$homePens}" : "{$homePens}-{$awayPens}",
        ]);

        return $winnerId;
    }

    /**
     * Mark a tie as completed with the given winner.
     */
    private function completeTie(CupTie $tie, string $winnerId, array $resolution): void
    {
        $tie->update([
            'winner_id' => $winnerId,
            'completed' => true,
            'resolution' => $resolution,
        ]);
    }

    /**
     * Rebuild the current on-pitch players and their entry minutes from match state.
     *
     * @return array{0: Collection, 1: Collection, 2: array<string,int>, 3: array<string,int>}
     */
    private function loadCurrentMatchState(GameMatch $match, Collection $allPlayers): array
    {
        $homeIds = $match->home_lineup ?? [];
        $awayIds = $match->away_lineup ?? [];
        $homeEntryMinutes = [];
        $awayEntryMinutes = [];

        foreach ($match->substitutions ?? [] as $sub) {
            if (($sub['team_id'] ?? null) === $match->home_team_id) {
                $homeIds = array_values(array_filter($homeIds, fn ($id) => $id !== $sub['player_out_id']));
                $homeIds[] = $sub['player_in_id'];
                $homeEntryMinutes[$sub['player_in_id']] = $sub['minute'];
            } else {
                $awayIds = array_values(array_filter($awayIds, fn ($id) => $id !== $sub['player_out_id']));
                $awayIds[] = $sub['player_in_id'];
                $awayEntryMinutes[$sub['player_in_id']] = $sub['minute'];
            }
        }

        $redCardedIds = MatchEvent::where('game_match_id', $match->id)
            ->where('event_type', MatchEvent::TYPE_RED_CARD)
            ->pluck('game_player_id')
            ->all();

        $homeIds = array_values(array_filter($homeIds, fn ($id) => ! in_array($id, $redCardedIds)));
        $awayIds = array_values(array_filter($awayIds, fn ($id) => ! in_array($id, $redCardedIds)));

        $homePlayers = $allPlayers->get($match->home_team_id, collect())
            ->filter(fn ($player) => in_array($player->id, $homeIds))
            ->values();
        $awayPlayers = $allPlayers->get($match->away_team_id, collect())
            ->filter(fn ($player) => in_array($player->id, $awayIds))
            ->values();

        return [$homePlayers, $awayPlayers, $homeEntryMinutes, $awayEntryMinutes];
    }
}
