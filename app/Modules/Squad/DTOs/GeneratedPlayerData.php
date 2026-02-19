<?php

namespace App\Modules\Squad\DTOs;

use Carbon\Carbon;

/**
 * Configuration for generating a new computer-generated player.
 *
 * Callers provide the domain-specific values (position, abilities, age, team, etc.)
 * and PlayerGeneratorService handles the boilerplate (Player record, GamePlayer record,
 * market value estimation, potential generation, durability, etc.).
 */
final class GeneratedPlayerData
{
    public function __construct(
        public readonly string $teamId,
        public readonly string $position,
        public readonly int $technical,
        public readonly int $physical,
        public readonly Carbon $dateOfBirth,
        public readonly int $contractYears,
        public readonly ?string $name = null,
        public readonly ?array $nationality = null,
        public readonly ?int $marketValueCents = null,
        public readonly ?int $potential = null,
        public readonly ?int $potentialLow = null,
        public readonly ?int $potentialHigh = null,
        public readonly int $fitnessMin = 80,
        public readonly int $fitnessMax = 95,
        public readonly int $moraleMin = 65,
        public readonly int $moraleMax = 80,
    ) {}
}
