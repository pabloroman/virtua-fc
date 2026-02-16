<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $team_id
 * @property string $reputation_level
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereReputationLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereTeamId($value)
 * @mixin \Eloquent
 */
class ClubProfile extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'reputation_level',
    ];

    public const REPUTATION_ELITE = 'elite';
    public const REPUTATION_CONTENDERS = 'contenders';
    public const REPUTATION_CONTINENTAL = 'continental';
    public const REPUTATION_ESTABLISHED = 'established';
    public const REPUTATION_MODEST = 'modest';
    public const REPUTATION_PROFESSIONAL = 'professional';
    public const REPUTATION_LOCAL = 'local';

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
