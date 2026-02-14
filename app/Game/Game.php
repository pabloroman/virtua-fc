<?php

namespace App\Game;

use App\Game\Commands\ConductCupDraw;
use App\Game\Commands\CreateGame;
use App\Game\Commands\ProcessSeasonDevelopment;
use App\Game\Commands\StartNewSeason;
use App\Game\Events\CupDrawConducted;
use App\Game\Events\CupTieCompleted;
use App\Game\Events\GameCreated;
use App\Game\Events\NewSeasonStarted;
use App\Game\Events\SeasonDevelopmentProcessed;
use Ramsey\Uuid\Uuid;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

final class Game extends AggregateRoot
{
    private string $teamId = '';

    public static function create(CreateGame $command): Game
    {
        $id = Uuid::uuid4();
        $game = self::retrieve($id);

        $game->recordThat(new GameCreated(
            $command->userId,
            $command->teamId,
            $command->playerName,
            $command->gameMode,
        ));

        $game->persist();

        return $game;
    }

    public function conductCupDraw(ConductCupDraw $command, array $tieIds): self
    {
        $this->recordThat(new CupDrawConducted(
            $command->competitionId,
            $command->roundNumber,
            $tieIds,
        ));

        $this->persist();

        return $this;
    }

    public function completeCupTie(string $tieId, string $competitionId, int $roundNumber, string $winnerId, string $loserId, array $resolution): self
    {
        $this->recordThat(new CupTieCompleted(
            $tieId,
            $competitionId,
            $roundNumber,
            $winnerId,
            $loserId,
            $resolution,
        ));

        $this->persist();

        return $this;
    }

    public function processSeasonDevelopment(ProcessSeasonDevelopment $command): self
    {
        $this->recordThat(new SeasonDevelopmentProcessed(
            $command->season,
            $command->teamId,
            $command->playerChanges,
        ));

        $this->persist();

        return $this;
    }

    public function startNewSeason(StartNewSeason $command): self
    {
        $this->recordThat(new NewSeasonStarted(
            $command->oldSeason,
            $command->newSeason,
            $command->playerChanges,
        ));

        $this->persist();

        return $this;
    }

    // Event applicators for state reconstruction

    protected function applyGameCreated(GameCreated $event): void
    {
        $this->teamId = $event->teamId;
    }

    protected function applyCupDrawConducted(CupDrawConducted $event): void
    {
        // Cup status is now derived per-competition from CupTie data
    }

    protected function applyCupTieCompleted(CupTieCompleted $event): void
    {
        // Cup status is now derived per-competition from CupTie data
    }

    protected function applySeasonDevelopmentProcessed(SeasonDevelopmentProcessed $event): void
    {
        // Development processing doesn't change aggregate state directly
        // The projector handles updating player records
    }

    protected function applyNewSeasonStarted(NewSeasonStarted $event): void
    {
        // No aggregate state to reset
    }

    public function getTeamId(): string
    {
        return $this->teamId;
    }
}
