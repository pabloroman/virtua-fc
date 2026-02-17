<?php

namespace App\Models;

use App\Support\CountryCodeMapper;
use App\Support\PositionMapper;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $team_id
 * @property string $name
 * @property array<array-key, mixed>|null $nationality
 * @property \Illuminate\Support\Carbon $date_of_birth
 * @property string $position
 * @property int $technical_ability
 * @property int $physical_ability
 * @property int $potential
 * @property int $potential_low
 * @property int $potential_high
 * @property \Illuminate\Support\Carbon $appeared_at
 * @property bool $is_on_loan
 * @property int|null $joined_season
 * @property int|null $initial_technical
 * @property int|null $initial_physical
 * @property-read \App\Models\Game $game
 * @property-read int $age
 * @property-read array|null $nationality_flag
 * @property-read int $overall
 * @property-read array $position_display
 * @property-read string $position_group
 * @property-read string $potential_range
 * @property-read \App\Models\Team|null $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereAppearedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePhysicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePotential($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePotentialHigh($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePotentialLow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereTechnicalAbility($value)
 * @mixin \Eloquent
 */
class AcademyPlayer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'team_id',
        'name',
        'nationality',
        'date_of_birth',
        'position',
        'technical_ability',
        'physical_ability',
        'potential',
        'potential_low',
        'potential_high',
        'appeared_at',
        'is_on_loan',
        'joined_season',
        'initial_technical',
        'initial_physical',
    ];

    protected $casts = [
        'nationality' => 'array',
        'date_of_birth' => 'date',
        'appeared_at' => 'date',
        'technical_ability' => 'integer',
        'physical_ability' => 'integer',
        'potential' => 'integer',
        'potential_low' => 'integer',
        'potential_high' => 'integer',
        'is_on_loan' => 'boolean',
        'joined_season' => 'integer',
        'initial_technical' => 'integer',
        'initial_physical' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    public function getSeasonsInAcademyAttribute(): int
    {
        if (!$this->joined_season) {
            return 1;
        }

        return max(1, (int) $this->game->season - $this->joined_season + 1);
    }

    public function getOverallAttribute(): int
    {
        return (int) round(($this->technical_ability + $this->physical_ability) / 2);
    }

    public function getPotentialRangeAttribute(): string
    {
        return "{$this->potential_low}-{$this->potential_high}";
    }

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

    public function getPositionDisplayAttribute(): array
    {
        return PositionMapper::getPositionDisplay($this->position);
    }

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
