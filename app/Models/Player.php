<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $transfermarkt_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property array<array-key, mixed>|null $nationality
 * @property string|null $height
 * @property string|null $foot
 * @property int $technical_ability
 * @property int $physical_ability
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GamePlayer> $gamePlayers
 * @property-read int|null $game_players_count
 * @property-read int $age
 * @method static \Database\Factories\PlayerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereFoot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player wherePhysicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereTechnicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereTransfermarktId($value)
 * @mixin \Eloquent
 */
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
