<?php

namespace App\Models;

use App\Support\CountryCodeMapper;
use App\Support\PositionMapper;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
