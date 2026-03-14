<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivationEvent extends Model
{
    public $timestamps = false;

    public const EVENT_REGISTERED = 'registered';
    public const EVENT_GAME_CREATED = 'game_created';
    public const EVENT_SETUP_COMPLETED = 'setup_completed';
    public const EVENT_WELCOME_COMPLETED = 'welcome_completed';
    public const EVENT_ONBOARDING_COMPLETED = 'onboarding_completed';
    public const EVENT_FIRST_MATCH_PLAYED = 'first_match_played';
    public const EVENT_MATCHDAY_5_REACHED = 'matchday_5_reached';
    public const EVENT_SEASON_COMPLETED = 'season_completed';

    public const FUNNEL_ORDER = [
        self::EVENT_REGISTERED,
        self::EVENT_GAME_CREATED,
        self::EVENT_SETUP_COMPLETED,
        self::EVENT_WELCOME_COMPLETED,
        self::EVENT_ONBOARDING_COMPLETED,
        self::EVENT_FIRST_MATCH_PLAYED,
        self::EVENT_MATCHDAY_5_REACHED,
        self::EVENT_SEASON_COMPLETED,
    ];

    protected $fillable = [
        'user_id',
        'game_id',
        'event',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
