<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CupTie extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'round_number' => 'integer',
        'completed' => 'boolean',
        'resolution' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    public function firstLegMatch(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'first_leg_match_id');
    }

    public function secondLegMatch(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'second_leg_match_id');
    }

    public function roundTemplate(): CupRoundTemplate
    {
        return CupRoundTemplate::where('competition_id', $this->competition_id)
            ->where('round_number', $this->round_number)
            ->first();
    }

    public function isTwoLegged(): bool
    {
        return $this->roundTemplate()?->isTwoLegged() ?? false;
    }

    /**
     * Get aggregate score for two-legged ties.
     *
     * @return array{home: int, away: int, home_away_goals: int, away_away_goals: int}
     */
    public function getAggregateScore(): array
    {
        $firstLeg = $this->firstLegMatch;
        $secondLeg = $this->secondLegMatch;

        $homeTotal = 0;
        $awayTotal = 0;
        $homeAwayGoals = 0;
        $awayAwayGoals = 0;

        if ($firstLeg?->played) {
            // First leg: home team plays at home
            $homeTotal += $firstLeg->home_score ?? 0;
            $awayTotal += $firstLeg->away_score ?? 0;
            $awayAwayGoals = $firstLeg->away_score ?? 0; // Away team's goals at home team's stadium
        }

        if ($secondLeg?->played) {
            // Second leg: away team plays at home (teams swap)
            $homeTotal += $secondLeg->away_score ?? 0;
            $awayTotal += $secondLeg->home_score ?? 0;
            $homeAwayGoals = $secondLeg->away_score ?? 0; // Home team's goals at away team's stadium
        }

        return [
            'home' => $homeTotal,
            'away' => $awayTotal,
            'home_away_goals' => $homeAwayGoals,
            'away_away_goals' => $awayAwayGoals,
        ];
    }

    /**
     * Get the score string for display (e.g., "3-2" or "3-2 (agg: 5-4)").
     */
    public function getScoreDisplay(): string
    {
        if (!$this->firstLegMatch?->played) {
            return '-';
        }

        $firstLeg = $this->firstLegMatch;

        if (!$this->isTwoLegged()) {
            $score = "{$firstLeg->home_score}-{$firstLeg->away_score}";

            if ($firstLeg->is_extra_time) {
                $score .= ' (AET)';
            }

            if ($firstLeg->home_score_penalties !== null) {
                $score .= " ({$firstLeg->home_score_penalties}-{$firstLeg->away_score_penalties} pen)";
            }

            return $score;
        }

        // Two-legged tie
        $aggregate = $this->getAggregateScore();
        $secondLeg = $this->secondLegMatch;

        if (!$secondLeg?->played) {
            return "1st leg: {$firstLeg->home_score}-{$firstLeg->away_score}";
        }

        $display = "agg: {$aggregate['home']}-{$aggregate['away']}";

        if ($secondLeg->is_extra_time) {
            $display .= ' (AET)';
        }

        if ($secondLeg->home_score_penalties !== null) {
            $display .= " ({$secondLeg->home_score_penalties}-{$secondLeg->away_score_penalties} pen)";
        }

        return $display;
    }

    /**
     * Check if this tie involves a specific team.
     */
    public function involvesTeam(string $teamId): bool
    {
        return $this->home_team_id === $teamId || $this->away_team_id === $teamId;
    }

    /**
     * Get the loser of this tie.
     */
    public function getLoserId(): ?string
    {
        if (!$this->winner_id) {
            return null;
        }

        return $this->winner_id === $this->home_team_id
            ? $this->away_team_id
            : $this->home_team_id;
    }
}
