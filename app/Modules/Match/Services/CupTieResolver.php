<?php

namespace App\Modules\Match\Services;

use App\Modules\Match\DTOs\MatchResult;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Models\CupTie;
use App\Models\GameMatch;
use App\Models\GamePlayer;
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

        // Check if ET was already simulated during the live match
        if ($match->is_extra_time) {
            $homeScoreEt = $match->home_score_et ?? 0;
            $awayScoreEt = $match->away_score_et ?? 0;
        } else {
            // Draw - need extra time. Use eager-loaded relations or fall back to query.
            $homePlayers = $allPlayers->get($match->home_team_id, collect());
            $awayPlayers = $allPlayers->get($match->away_team_id, collect());
            $homeTeam = $match->relationLoaded('homeTeam') ? $match->homeTeam : Team::find($match->home_team_id);
            $awayTeam = $match->relationLoaded('awayTeam') ? $match->awayTeam : Team::find($match->away_team_id);

            $extraTimeResult = $this->matchSimulator->simulateExtraTime(
                $homeTeam,
                $awayTeam,
                $homePlayers,
                $awayPlayers
            );

            $homeScoreEt = $extraTimeResult->homeScore;
            $awayScoreEt = $extraTimeResult->awayScore;

            $match->update([
                'is_extra_time' => true,
                'home_score_et' => $homeScoreEt,
                'away_score_et' => $awayScoreEt,
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
            $homePlayers = $homePlayers ?? $allPlayers->get($match->home_team_id, collect());
            $awayPlayers = $awayPlayers ?? $allPlayers->get($match->away_team_id, collect());

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

        // Tied on aggregate - extra time in second leg
        // Check if ET was already simulated during the live match
        if ($secondLeg->is_extra_time) {
            $homeScoreEt = $secondLeg->home_score_et ?? 0;
            $awayScoreEt = $secondLeg->away_score_et ?? 0;
        } else {
            $homePlayers = $allPlayers->get($secondLeg->home_team_id, collect());
            $awayPlayers = $allPlayers->get($secondLeg->away_team_id, collect());
            $homeTeam = $secondLeg->relationLoaded('homeTeam') ? $secondLeg->homeTeam : Team::find($secondLeg->home_team_id);
            $awayTeam = $secondLeg->relationLoaded('awayTeam') ? $secondLeg->awayTeam : Team::find($secondLeg->away_team_id);

            $extraTimeResult = $this->matchSimulator->simulateExtraTime(
                $homeTeam,
                $awayTeam,
                $homePlayers,
                $awayPlayers
            );

            $homeScoreEt = $extraTimeResult->homeScore;
            $awayScoreEt = $extraTimeResult->awayScore;

            $secondLeg->update([
                'is_extra_time' => true,
                'home_score_et' => $homeScoreEt,
                'away_score_et' => $awayScoreEt,
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
            $homePlayers = $homePlayers ?? $allPlayers->get($secondLeg->home_team_id, collect());
            $awayPlayers = $awayPlayers ?? $allPlayers->get($secondLeg->away_team_id, collect());

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
}
