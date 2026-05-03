<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePlayerTemplate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'season',
        'player_id',
        'team_id',
        'number',
        'position',
        'market_value',
        'market_value_cents',
        'contract_until',
        'annual_wage',
        'fitness',
        'morale',
        'durability',
        'overall_score',
        'potential',
        'potential_low',
        'potential_high',
        'tier',
    ];

    protected $casts = [
        'number' => 'integer',
        'market_value_cents' => 'integer',
        'contract_until' => 'date:Y-m-d',
        'annual_wage' => 'integer',
        'fitness' => 'integer',
        'morale' => 'integer',
        'durability' => 'integer',
        'overall_score' => 'integer',
        'potential' => 'integer',
        'potential_low' => 'integer',
        'potential_high' => 'integer',
        'tier' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(GamePlayerTemplateAudit::class)->orderByDesc('created_at');
    }
}
