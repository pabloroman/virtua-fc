<?php

namespace App\Game\Services;

use App\Game\DTO\MatchResult;
use App\Models\CupRoundTemplate;
use App\Models\CupTie;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use Illuminate\Support\Collection;

class CupTieResolver
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
    ) {}

    /**
     * Attempt to resolve a cup tie and determine the winner.
     * Returns the winner team_id if tie is complete, null if more matches needed.
     */
    public function resolve(CupTie $tie, Collection $allPlayers): ?string
    {
        $roundTemplate = CupRoundTemplate::where('competition_id', $tie->competition_id)
            ->where('round_number', $tie->round_number)
            ->first();

        if (!$roundTemplate) {
            return null;
        }

        $firstLeg = $tie->firstLegMatch;

        if (!$firstLeg?->played) {
            return null;
        }

        if ($roundTemplate->isTwoLegged()) {
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

        // Draw - need extra time
        $homePlayers = $allPlayers->get($match->home_team_id, collect());
        $awayPlayers = $allPlayers->get($match->away_team_id, collect());
        $homeTeam = Team::find($match->home_team_id);
        $awayTeam = Team::find($match->away_team_id);

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

        // Still tied - penalties
        [$homePens, $awayPens] = $this->matchSimulator->simulatePenalties($homePlayers, $awayPlayers);

        $match->update([
            'home_score_penalties' => $homePens,
            'away_score_penalties' => $awayPens,
        ]);

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

        // Tied on aggregate - check away goals (tie's home team = first leg home team)
        // Away goals rule: if aggregate is tied, team with more away goals wins
        $homeAwayGoals = $aggregate['home_away_goals']; // Home team's goals scored away
        $awayAwayGoals = $aggregate['away_away_goals']; // Away team's goals scored away

        if ($homeAwayGoals !== $awayAwayGoals) {
            $winnerId = $homeAwayGoals > $awayAwayGoals ? $tie->home_team_id : $tie->away_team_id;
            $this->completeTie($tie, $winnerId, [
                'type' => 'away_goals',
                'aggregate' => "{$homeTotal}-{$awayTotal}",
                'away_goals' => "{$homeAwayGoals}-{$awayAwayGoals}",
            ]);
            return $winnerId;
        }

        // Still tied - extra time in second leg
        // Second leg: away team (from tie perspective) plays at home
        $homePlayers = $allPlayers->get($secondLeg->home_team_id, collect());
        $awayPlayers = $allPlayers->get($secondLeg->away_team_id, collect());
        $homeTeam = Team::find($secondLeg->home_team_id);
        $awayTeam = Team::find($secondLeg->away_team_id);

        $extraTimeResult = $this->matchSimulator->simulateExtraTime(
            $homeTeam,
            $awayTeam,
            $homePlayers,
            $awayPlayers
        );

        $secondLeg->update([
            'is_extra_time' => true,
            'home_score_et' => $extraTimeResult->homeScore,
            'away_score_et' => $extraTimeResult->awayScore,
        ]);

        // Extra time goals affect aggregate
        // Second leg home team = tie's away team
        $homeTotal += $extraTimeResult->awayScore; // Tie's home team was away in 2nd leg
        $awayTotal += $extraTimeResult->homeScore; // Tie's away team was home in 2nd leg

        if ($homeTotal !== $awayTotal) {
            $winnerId = $homeTotal > $awayTotal ? $tie->home_team_id : $tie->away_team_id;
            $this->completeTie($tie, $winnerId, [
                'type' => 'extra_time',
                'aggregate' => "{$homeTotal}-{$awayTotal}",
            ]);
            return $winnerId;
        }

        // Penalties
        [$homePens, $awayPens] = $this->matchSimulator->simulatePenalties($homePlayers, $awayPlayers);

        $secondLeg->update([
            'home_score_penalties' => $homePens,
            'away_score_penalties' => $awayPens,
        ]);

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
