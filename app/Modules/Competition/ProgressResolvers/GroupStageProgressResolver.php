<?php

namespace App\Modules\Competition\ProgressResolvers;

use App\Models\GameStanding;
use App\Modules\Competition\Contracts\ProgressResolver;
use App\Modules\Competition\DTOs\QualificationOutcome;
use App\Modules\Competition\Services\WorldCupKnockoutGenerator;

class GroupStageProgressResolver implements ProgressResolver
{
    public function __construct(
        private readonly WorldCupKnockoutGenerator $knockoutGenerator,
    ) {}

    public function resolve(string $gameId, string $competitionId, GameStanding $standing): ?QualificationOutcome
    {
        $qualifiedTeams = $this->knockoutGenerator->getQualifiedTeams($gameId, $competitionId);

        if (in_array($standing->team_id, $qualifiedTeams)) {
            return QualificationOutcome::advanced(
                'cup.group_stage_qualified',
                ['group' => $standing->group_label],
            );
        }

        return QualificationOutcome::eliminated(
            'cup.group_stage_eliminated',
            ['group' => $standing->group_label],
        );
    }
}
