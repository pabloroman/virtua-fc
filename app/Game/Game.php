<?php

namespace App\Game;

use App\Game\Commands\AdvanceMatchday;
use App\Game\Commands\ConductCupDraw;
use App\Game\Commands\CreateGame;
use App\Game\Events\CupDrawConducted;
use App\Game\Events\CupTieCompleted;
use App\Game\Events\GameCreated;
use App\Game\Events\MatchdayAdvanced;
use App\Game\Events\MatchResultRecorded;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

final class Game extends AggregateRoot
{
    private ?Carbon $currentDate = null;
    private int $currentMatchday = 0;
    private string $teamId = '';
    private int $cupRound = 0;
    private bool $cupEliminated = false;

    public static function create(CreateGame $command): Game
    {
        $id = Uuid::uuid4();
        $game = self::retrieve($id);

        $game->recordThat(new GameCreated(
            $command->userId,
            $command->teamId,
            $command->playerName
        ));

        $game->persist();

        return $game;
    }

    public function advanceMatchday(AdvanceMatchday $command): self
    {
        // Record the matchday advancement
        $this->recordThat(new MatchdayAdvanced(
            $command->matchday,
            $command->currentDate,
        ));

        // Record each match result
        foreach ($command->matchResults as $result) {
            $this->recordThat(new MatchResultRecorded(
                matchId: $result['matchId'],
                homeTeamId: $result['homeTeamId'],
                awayTeamId: $result['awayTeamId'],
                homeScore: $result['homeScore'],
                awayScore: $result['awayScore'],
                competitionId: $result['competitionId'],
                matchday: $command->matchday,
                events: $result['events'] ?? [],
            ));
        }

        $this->persist();

        return $this;
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

    // Event applicators for state reconstruction

    protected function applyGameCreated(GameCreated $event): void
    {
        $this->teamId = $event->teamId;
    }

    protected function applyMatchdayAdvanced(MatchdayAdvanced $event): void
    {
        $this->currentMatchday = $event->matchday;
        $this->currentDate = Carbon::parse($event->currentDate);
    }

    protected function applyMatchResultRecorded(MatchResultRecorded $event): void
    {
        // Match results don't change aggregate state directly
        // They're processed by the projector to update read models
    }

    protected function applyCupDrawConducted(CupDrawConducted $event): void
    {
        $this->cupRound = $event->roundNumber;
    }

    protected function applyCupTieCompleted(CupTieCompleted $event): void
    {
        // Check if player's team was eliminated
        if ($event->loserId === $this->teamId) {
            $this->cupEliminated = true;
        }
    }

    // Getters for aggregate state

    public function getCurrentMatchday(): int
    {
        return $this->currentMatchday;
    }

    public function getCurrentDate(): ?Carbon
    {
        return $this->currentDate;
    }

    public function getTeamId(): string
    {
        return $this->teamId;
    }

    public function getCupRound(): int
    {
        return $this->cupRound;
    }

    public function isCupEliminated(): bool
    {
        return $this->cupEliminated;
    }
}
