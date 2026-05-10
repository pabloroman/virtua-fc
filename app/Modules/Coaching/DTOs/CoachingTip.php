<?php

namespace App\Modules\Coaching\DTOs;

/**
 * A single half-time recommendation from the coaching staff.
 *
 * `headline` and `rationale` are pre-translated. When `tacticalChange` is
 * non-null, the UI exposes an "Apply" button that submits the payload to the
 * existing tactical-actions endpoint; advisory-only tips set it to null.
 *
 * Tips are precomputed server-side in {@see \App\Http\Views\ShowLiveMatch}
 * and passed into the Alpine half-time block as a static array — no extra
 * round-trips during the live match flow.
 */
final class CoachingTip
{
    /**
     * @param  array<string, string>|null  $tacticalChange
     *     Subset of {mentality, playing_style, pressing, defensive_line}
     *     keyed by the same field names accepted by ProcessTacticalActions.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $headline,
        public readonly string $rationale,
        public readonly ?array $tacticalChange,
        public readonly string $tone,
        public readonly Confidence $confidence,
    ) {}

    public const TONE_INFO = 'info';
    public const TONE_WARNING = 'warning';
    public const TONE_OPPORTUNITY = 'opportunity';

    /**
     * Blade-friendly array form. Used to hand the payload to Alpine via Js::from().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'rationale' => $this->rationale,
            'tacticalChange' => $this->tacticalChange,
            'tone' => $this->tone,
            'confidence' => $this->confidence->value,
        ];
    }
}
