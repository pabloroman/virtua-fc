<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubProfile extends Model
{
    use HasUuids;

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
