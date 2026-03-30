<?php

namespace App\Modules\Competition\ProgressResolvers;

use App\Models\GameStanding;
use App\Modules\Competition\Contracts\ProgressResolver;
use App\Modules\Competition\DTOs\QualificationOutcome;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;

class LeaguePlayoffProgressResolver implements ProgressResolver
{
    public function __construct(
        private readonly PlayoffGeneratorFactory $playoffFactory,
    ) {}

    public function resolve(string $gameId, string $competitionId, GameStanding $standing): ?QualificationOutcome
    {
        $generator = $this->playoffFactory->forCompetition($competitionId);

        if (! $generator) {
            return null;
        }

        if (in_array($standing->position, $generator->getDirectPromotionPositions())) {
            return QualificationOutcome::advanced('cup.direct_promotion');
        }

        if (in_array($standing->position, $generator->getQualifyingPositions())) {
            return QualificationOutcome::playoff('cup.promotion_playoff');
        }

        return null;
    }
}
