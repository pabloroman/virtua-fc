<?php

namespace App\Modules\Match\DTOs;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use Illuminate\Support\Collection;

/**
 * Mutable cross-window state for a windowed match simulation.
 *
 * Bundles everything that must survive across multiple
 * MatchSimulator::simulateWindow() calls during a single match. The context
 * is mutated in place by simulateWindow so the next window sees updated
 * lineups, score, accumulated events, performance cache and accumulators.
 *
 * Intended for live multiplayer matches where each window runs in a
 * separate queued job and the context is persisted to JSON between calls.
 * The existing batch entry point (MatchSimulator::simulate) is unaffected.
 */
class MatchSimulationContext
{
    /** @var Collection<int, MatchEventData> */
    public Collection $accumulatedEvents;

    public function __construct(
        public Team $homeTeam,
        public Team $awayTeam,
        public Collection $homePlayers,
        public Collection $awayPlayers,
        public ?Collection $homeBenchPlayers = null,
        public ?Collection $awayBenchPlayers = null,
        public Formation $homeFormation = Formation::F_4_4_2,
        public Formation $awayFormation = Formation::F_4_4_2,
        public Mentality $homeMentality = Mentality::BALANCED,
        public Mentality $awayMentality = Mentality::BALANCED,
        public PlayingStyle $homePlayingStyle = PlayingStyle::BALANCED,
        public PlayingStyle $awayPlayingStyle = PlayingStyle::BALANCED,
        public PressingIntensity $homePressing = PressingIntensity::STANDARD,
        public PressingIntensity $awayPressing = PressingIntensity::STANDARD,
        public DefensiveLineHeight $homeDefLine = DefensiveLineHeight::NORMAL,
        public DefensiveLineHeight $awayDefLine = DefensiveLineHeight::NORMAL,
        public int $homeScore = 0,
        public int $awayScore = 0,
        public float $homeXGTotal = 0.0,
        public float $awayXGTotal = 0.0,
        public array $homeEntryMinutes = [],
        public array $awayEntryMinutes = [],
        public array $existingInjuryTeamIds = [],
        public array $existingYellowPlayerIds = [],
        public int $homeSubsUsed = 0,
        public int $awaySubsUsed = 0,
        public array $homePlayerSlotMap = [],
        public array $awayPlayerSlotMap = [],
        public array $matchPerformance = [],
        ?Collection $accumulatedEvents = null,
        public string $matchSeed = '',
        public ?string $userTeamId = null,
        public ?Game $game = null,
        public bool $neutralVenue = false,
    ) {
        $this->accumulatedEvents = $accumulatedEvents ?? collect();
    }
}
