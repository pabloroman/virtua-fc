<?php

namespace App\Models;

use App\Support\CountryCodeMapper;
use App\Support\PositionMapper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePlayer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'market_value_cents' => 'integer',
        'contract_until' => 'date',
        'joined_on' => 'date',
        'fitness' => 'integer',
        'morale' => 'integer',
        'injury_until' => 'date',
        'suspended_until_matchday' => 'integer',
        'appearances' => 'integer',
        'goals' => 'integer',
        'own_goals' => 'integer',
        'assists' => 'integer',
        'yellow_cards' => 'integer',
        'red_cards' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class);
    }

    /**
     * Check if player is available for selection (not injured or suspended).
     */
    public function isAvailable(?Carbon $gameDate = null, ?int $matchday = null): bool
    {
        // Check suspension
        if ($matchday !== null && $this->suspended_until_matchday !== null) {
            if ($this->suspended_until_matchday > $matchday) {
                return false;
            }
        }

        // Check injury
        if ($this->injury_until && $gameDate && $this->injury_until->gt($gameDate)) {
            return false;
        }

        return true;
    }

    /**
     * Check if player is suspended for a given matchday.
     */
    public function isSuspended(int $matchday): bool
    {
        return $this->suspended_until_matchday !== null && $this->suspended_until_matchday > $matchday;
    }

    /**
     * Check if player is injured on a given date.
     */
    public function isInjured(?Carbon $date = null): bool
    {
        if ($this->injury_until === null) {
            return false;
        }

        $checkDate = $date ?? now();
        return $this->injury_until->gt($checkDate);
    }

    /**
     * Get the unavailability reason if player is not available.
     */
    public function getUnavailabilityReason(?Carbon $gameDate = null, ?int $matchday = null): ?string
    {
        if ($matchday !== null && $this->isSuspended($matchday)) {
            $remaining = $this->suspended_until_matchday - $matchday;
            return "Suspended ({$remaining} match" . ($remaining > 1 ? 'es' : '') . ")";
        }

        if ($this->isInjured($gameDate)) {
            return $this->injury_type ? "{$this->injury_type}" : "Injured";
        }

        return null;
    }

    /**
     * Get player's age from the reference Player model.
     */
    public function getAgeAttribute(): int
    {
        return $this->player->age;
    }

    /**
     * Get player's name from the reference Player model.
     */
    public function getNameAttribute(): string
    {
        return $this->player->name;
    }

    /**
     * Get player's nationality from the reference Player model.
     */
    public function getNationalityAttribute(): ?array
    {
        return $this->player->nationality;
    }

    /**
     * Group position into category for display.
     */
    public function getPositionGroupAttribute(): string
    {
        return match ($this->position) {
            'Goalkeeper' => 'Goalkeeper',
            'Centre-Back', 'Left-Back', 'Right-Back' => 'Defender',
            'Defensive Midfield', 'Central Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield' => 'Midfielder',
            'Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker' => 'Forward',
            default => 'Midfielder',
        };
    }

    /**
     * Calculate overall score from 4 attributes.
     * Technical + Physical (from Player) + Fitness + Morale (from GamePlayer)
     */
    public function getOverallScoreAttribute(): int
    {
        return (int) round(
            ($this->player->technical_ability +
             $this->player->physical_ability +
             $this->fitness +
             $this->morale) / 4
        );
    }

    /**
     * Get technical ability from the reference Player model.
     */
    public function getTechnicalAbilityAttribute(): int
    {
        return $this->player->technical_ability;
    }

    /**
     * Get physical ability from the reference Player model.
     */
    public function getPhysicalAbilityAttribute(): int
    {
        return $this->player->physical_ability;
    }

    /**
     * Get 2-letter position abbreviation (GK, DF, MF, FW).
     */
    public function getPositionAbbreviationAttribute(): string
    {
        return PositionMapper::toAbbreviation($this->position);
    }

    /**
     * Get position display data including abbreviation and CSS colors.
     *
     * @return array{abbreviation: string, bg: string, text: string}
     */
    public function getPositionDisplayAttribute(): array
    {
        return PositionMapper::getPositionDisplay($this->position);
    }

    /**
     * Get primary nationality flag data (first nationality only).
     *
     * @return array{name: string, code: string}|null
     */
    public function getNationalityFlagAttribute(): ?array
    {
        $nationalities = $this->nationality ?? [];

        if (empty($nationalities)) {
            return null;
        }

        $code = CountryCodeMapper::toCode($nationalities[0]);

        if ($code === null) {
            return null;
        }

        return [
            'name' => $nationalities[0],
            'code' => $code,
        ];
    }
}
