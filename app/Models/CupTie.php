<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $competition_id
 * @property int $round_number
 * @property string $home_team_id
 * @property string $away_team_id
 * @property string|null $first_leg_match_id
 * @property string|null $second_leg_match_id
 * @property string|null $winner_id
 * @property bool $completed
 * @property array<array-key, mixed>|null $resolution
 * @property-read \App\Models\Team $awayTeam
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\GameMatch|null $firstLegMatch
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\Team $homeTeam
 * @property-read \App\Models\GameMatch|null $secondLegMatch
 * @property-read \App\Models\Team|null $winner
 * @method static \Database\Factories\CupTieFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereAwayTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereFirstLegMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereHomeTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereResolution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereRoundNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereSecondLegMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereWinnerId($value)
 * @mixin \Eloquent
 */
class CupTie extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'competition_id',
        'round_number',
        'home_team_id',
        'away_team_id',
        'first_leg_match_id',
        'second_leg_match_id',
        'winner_id',
        'completed',
        'resolution',
    ];

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

    /**
     * Get the round config for this tie from schedule.json.
     */
    public function getRoundConfig(): ?\App\Modules\Competition\DTOs\PlayoffRoundConfig
    {
        $competition = $this->competition ?? Competition::find($this->competition_id);
        $rounds = \App\Modules\Competition\Services\LeagueFixtureGenerator::loadKnockoutRounds(
            $this->competition_id,
            $competition->season ?? '2025',
        );

        foreach ($rounds as $round) {
            if ($round->round === $this->round_number) {
                return $round;
            }
        }

        return null;
    }

    /**
     * Determine if extra time is needed for the given match in this tie.
     */
    public function needsExtraTime(GameMatch $match): bool
    {
        $roundConfig = $this->getRoundConfig();

        if (! $roundConfig) {
            return false;
        }

        if ($roundConfig->twoLegged) {
            if ($this->second_leg_match_id !== $match->id) {
                return false;
            }

            $aggregate = $this->getAggregateScore();

            return $aggregate['home'] === $aggregate['away'];
        }

        return $match->home_score === $match->away_score;
    }

    public function isTwoLegged(): bool
    {
        return $this->getRoundConfig()->twoLegged ?? false;
    }

    /**
     * Get aggregate score for two-legged ties.
     *
     * @return array{home: int, away: int}
     */
    public function getAggregateScore(): array
    {
        $firstLeg = $this->firstLegMatch;
        $secondLeg = $this->secondLegMatch;

        $homeTotal = 0;
        $awayTotal = 0;

        if ($firstLeg?->played) {
            $homeTotal += $firstLeg->home_score ?? 0;
            $awayTotal += $firstLeg->away_score ?? 0;
        }

        if ($secondLeg?->played) {
            // Second leg: away team plays at home (teams swap)
            $homeTotal += $secondLeg->away_score ?? 0;
            $awayTotal += $secondLeg->home_score ?? 0;
        }

        return [
            'home' => $homeTotal,
            'away' => $awayTotal,
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
