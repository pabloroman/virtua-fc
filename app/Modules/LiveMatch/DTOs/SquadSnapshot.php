<?php

namespace App\Modules\LiveMatch\DTOs;

/**
 * Frozen snapshot of one side's squad + auto-generated lineup.
 *
 * Stored as JSON on LiveMatchSession.{host,guest}_squad. The duel never
 * touches tenant data after this snapshot is taken — the engine adapter
 * rehydrates lightweight GamePlayer-like records from the JSON to feed
 * MatchSimulator::simulateWindow.
 */
readonly class SquadSnapshot
{
    /**
     * @param  array<int, array<string, mixed>>  $startingXi      Player records, ordered by slot.
     * @param  array<int, array<string, mixed>>  $bench           Player records.
     * @param  array<string, string>             $playerSlotMap   playerId → slot code (Formation::pitchSlots()).
     */
    public function __construct(
        public string $isoCode,
        public string $teamName,
        public string $formation,
        public string $mentality,
        public string $playingStyle,
        public string $pressing,
        public string $defensiveLine,
        public array $startingXi,
        public array $bench,
        public array $playerSlotMap,
    ) {}

    public function toArray(): array
    {
        return [
            'iso_code' => $this->isoCode,
            'team_name' => $this->teamName,
            'formation' => $this->formation,
            'mentality' => $this->mentality,
            'playing_style' => $this->playingStyle,
            'pressing' => $this->pressing,
            'defensive_line' => $this->defensiveLine,
            'starting_xi' => $this->startingXi,
            'bench' => $this->bench,
            'player_slot_map' => $this->playerSlotMap,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isoCode: $data['iso_code'],
            teamName: $data['team_name'],
            formation: $data['formation'],
            mentality: $data['mentality'],
            playingStyle: $data['playing_style'],
            pressing: $data['pressing'],
            defensiveLine: $data['defensive_line'],
            startingXi: $data['starting_xi'],
            bench: $data['bench'],
            playerSlotMap: $data['player_slot_map'],
        );
    }
}
