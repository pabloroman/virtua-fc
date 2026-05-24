<?php

namespace App\Models;

use App\Modules\LiveMatch\Enums\LiveMatchPhase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Live (real-time) human-vs-human match session.
 *
 * Lives on the control plane (cross-tenant). The session does not belong to
 * either player's Game; squads are snapshotted at team-pick time and the
 * match runs entirely on those snapshots.
 *
 * @property string $id
 * @property LiveMatchPhase $phase
 * @property int $host_user_id
 * @property int|null $guest_user_id
 * @property string|null $host_team_id
 * @property string|null $guest_team_id
 * @property string|null $host_source_game_id
 * @property string|null $guest_source_game_id
 * @property array|null $host_squad
 * @property array|null $guest_squad
 * @property string $match_seed
 * @property int $current_minute
 * @property int $home_score
 * @property int $away_score
 * @property array|null $context_state
 * @property array $event_log
 * @property bool $host_bot
 * @property bool $guest_bot
 * @property string|null $pause_reason
 * @property bool $pause_acked_by_host
 * @property bool $pause_acked_by_guest
 * @property int $clock_version
 */
class LiveMatchSession extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_control';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'phase' => LiveMatchPhase::class,
            'host_squad' => 'array',
            'guest_squad' => 'array',
            'context_state' => 'array',
            'event_log' => 'array',
            'host_bot' => 'boolean',
            'guest_bot' => 'boolean',
            'pause_acked_by_host' => 'boolean',
            'pause_acked_by_guest' => 'boolean',
            'paused_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(LiveMatchAction::class, 'session_id');
    }

    public function isHost(?int $userId): bool
    {
        return $userId !== null && $this->host_user_id === $userId;
    }

    public function isGuest(?int $userId): bool
    {
        return $userId !== null && $this->guest_user_id === $userId;
    }

    public function isParticipant(?int $userId): bool
    {
        return $this->isHost($userId) || $this->isGuest($userId);
    }

    public function bothTeamsPicked(): bool
    {
        return $this->host_team_id !== null && $this->guest_team_id !== null;
    }

    public function isBot(string $side): bool
    {
        return $side === 'home' ? $this->host_bot : $this->guest_bot;
    }
}
