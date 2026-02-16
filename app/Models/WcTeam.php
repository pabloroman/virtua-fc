<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WcTeam extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'short_name',
        'country_code',
        'confederation',
        'image',
        'strength',
        'pot',
    ];

    protected $casts = [
        'strength' => 'integer',
        'pot' => 'integer',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(WcPlayer::class);
    }
}
