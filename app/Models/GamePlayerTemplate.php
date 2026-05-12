<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GamePlayerTemplate extends Model
{
    /**
     * Templates are the canonical real-world roster source — cross-tenant
     * reference data shared across every game. See CLAUDE.md → "Control
     * plane / tenant plane".
     */
    protected $connection = 'pgsql_control';

    public $timestamps = false;

    protected $fillable = [
        'season',
        'player_id',
        'transfermarkt_id',
        'name',
        'date_of_birth',
        'nationality',
        'height',
        'foot',
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
        'nationality' => 'array',
        'date_of_birth' => 'date:Y-m-d',
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(GamePlayerTemplateAudit::class)->orderByDesc('created_at');
    }

    public function tournamentInfo(): HasOne
    {
        return $this->hasOne(GamePlayerTemplateTournamentInfo::class, 'game_player_template_id');
    }
}
