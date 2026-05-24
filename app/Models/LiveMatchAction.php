<?php

namespace App\Models;

use App\Modules\LiveMatch\Enums\LiveMatchSide;
use App\Modules\LiveMatch\Enums\QueuedActionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single queued in-match action (sub / formation / mentality change).
 *
 * Lives alongside LiveMatchSession on the control plane. Inserted by the
 * action queue endpoint while a match is running; applied at the start of
 * the next simulation window by LiveMatchEngineAdapter, then marked
 * applied|rejected.
 *
 * @property string $id
 * @property string $session_id
 * @property int $user_id
 * @property LiveMatchSide $side
 * @property QueuedActionType $action_type
 * @property array $payload
 * @property int $queued_at_minute
 * @property int|null $applied_at_minute
 * @property string $status
 * @property string|null $reject_reason
 */
class LiveMatchAction extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_control';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'side' => LiveMatchSide::class,
            'action_type' => QueuedActionType::class,
            'payload' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveMatchSession::class, 'session_id');
    }
}
