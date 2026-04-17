<?php

namespace App\Modules\Lineup\Enums\Concerns;

/**
 * Trait for tactical-option enums that expose {value, label, tooltip}
 * triples to the UI. Expects the consuming enum to implement label()
 * and tooltip() and to be string-backed.
 */
trait HasTacticalOptions
{
    /**
     * @return array<int, array{value: string, label: string, tooltip: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'tooltip' => $case->tooltip(),
        ], self::cases());
    }
}
