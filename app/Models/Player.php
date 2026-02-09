<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'transfermarkt_id',
        'name',
        'date_of_birth',
        'nationality',
        'height',
        'foot',
        'technical_ability',
        'physical_ability',
    ];

    protected $casts = [
        'nationality' => 'array',
        'date_of_birth' => 'date',
        'technical_ability' => 'integer',
        'physical_ability' => 'integer',
    ];

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth?->age ?? 0;
    }
}
