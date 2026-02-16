<?php

namespace App\Models;

use App\Support\CountryCodeMapper;
use App\Support\PositionMapper;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WcPlayer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'wc_team_id',
        'name',
        'date_of_birth',
        'nationality',
        'height',
        'foot',
        'position',
        'number',
        'technical_ability',
        'physical_ability',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'nationality' => 'array',
        'number' => 'integer',
        'technical_ability' => 'integer',
        'physical_ability' => 'integer',
    ];

    public function wcTeam(): BelongsTo
    {
        return $this->belongsTo(WcTeam::class);
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth?->age ?? 0;
    }

    public function getOverallAttribute(): int
    {
        return (int) round(($this->technical_ability + $this->physical_ability) / 2);
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
