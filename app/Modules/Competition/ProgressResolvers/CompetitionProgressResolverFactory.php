<?php

namespace App\Modules\Competition\ProgressResolvers;

use App\Modules\Competition\Contracts\ProgressResolver;

class CompetitionProgressResolverFactory
{
    /** @var array<string, ProgressResolver> */
    private array $resolvers;

    public function __construct(
        SwissProgressResolver $swiss,
        LeaguePlayoffProgressResolver $leaguePlayoff,
        GroupStageProgressResolver $groupStage,
    ) {
        $this->resolvers = [
            'swiss_format' => $swiss,
            'league_with_playoff' => $leaguePlayoff,
            'group_stage_cup' => $groupStage,
        ];
    }

    public function forHandlerType(string $handlerType): ?ProgressResolver
    {
        return $this->resolvers[$handlerType] ?? null;
    }
}
