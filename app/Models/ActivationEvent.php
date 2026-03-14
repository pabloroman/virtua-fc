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
    public const EVENT_TOURNAMENT_COMPLETED = 'tournament_completed';

    public const FUNNEL_ORDER_ALL = [
        self::EVENT_REGISTERED,
        self::EVENT_GAME_CREATED,
        self::EVENT_SETUP_COMPLETED,
        self::EVENT_WELCOME_COMPLETED,
        self::EVENT_ONBOARDING_COMPLETED,
        self::EVENT_FIRST_MATCH_PLAYED,
        self::EVENT_MATCHDAY_5_REACHED,
        self::EVENT_SEASON_COMPLETED,
        self::EVENT_TOURNAMENT_COMPLETED,
    ];

    public const FUNNEL_ORDER_CAREER = [
        self::EVENT_REGISTERED,
        self::EVENT_GAME_CREATED,
        self::EVENT_SETUP_COMPLETED,
        self::EVENT_WELCOME_COMPLETED,
        self::EVENT_ONBOARDING_COMPLETED,
        self::EVENT_FIRST_MATCH_PLAYED,
        self::EVENT_MATCHDAY_5_REACHED,
        self::EVENT_SEASON_COMPLETED,
    ];

    public const FUNNEL_ORDER_TOURNAMENT = [
        self::EVENT_REGISTERED,
        self::EVENT_GAME_CREATED,
        self::EVENT_SETUP_COMPLETED,
        self::EVENT_FIRST_MATCH_PLAYED,
        self::EVENT_MATCHDAY_5_REACHED,
        self::EVENT_TOURNAMENT_COMPLETED,
    ];

    protected $fillable = [
        'user_id',
        'game_id',
        'game_mode',
        'event',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public static function funnelForMode(?string $mode): array
    {
        return match ($mode) {
            Game::MODE_CAREER => self::FUNNEL_ORDER_CAREER,
            Game::MODE_TOURNAMENT => self::FUNNEL_ORDER_TOURNAMENT,
            default => self::FUNNEL_ORDER_ALL,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
