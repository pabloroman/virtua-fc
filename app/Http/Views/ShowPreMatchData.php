<?php

namespace App\Http\Views;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;

class ShowPreMatchData
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'tactics'])->findOrFail($gameId);
        $match = $game->next_match;

        abort_unless($match, 404);

        $match->load(['homeTeam', 'awayTeam', 'competition']);

        $isHome = $match->home_team_id === $game->team_id;
        $matchDate = $match->scheduled_date;
        $competitionId = $match->competition_id;

        // Get the saved lineup for this match
        $lineup = $this->lineupService->getLineup($match, $game->team_id);
        $formation = $this->lineupService->getFormation($match, $game->team_id)
            ?? $game->tactics?->default_formation
            ?? '4-4-2';
        $mentality = $this->lineupService->getMentality($match, $game->team_id)
            ?? $game->tactics?->default_mentality
            ?? 'balanced';

        // Get tactical instructions from match or defaults
        $prefix = $isHome ? 'home' : 'away';
        $playingStyle = $match->{"{$prefix}_playing_style"}
            ?? $game->tactics?->default_playing_style
            ?? 'balanced';
        $pressing = $match->{"{$prefix}_pressing"}
            ?? $game->tactics?->default_pressing
            ?? 'standard';
        $defensiveLine = $match->{"{$prefix}_defensive_line"}
            ?? $game->tactics?->default_defensive_line
            ?? 'normal';

        // Resolve enum labels
        $formationEnum = Formation::tryFrom($formation);
        $mentalityEnum = Mentality::tryFrom($mentality);
        $playingStyleEnum = PlayingStyle::tryFrom($playingStyle);
        $pressingEnum = PressingIntensity::tryFrom($pressing);
        $defensiveLineEnum = DefensiveLineHeight::tryFrom($defensiveLine);

        // Build lineup player data and detect issues
        $issues = [];
        $lineupPlayers = collect();

        if (empty($lineup)) {
            $issues[] = [
                'type' => 'no_lineup',
                'message' => __('messages.pre_match_no_lineup'),
            ];
        } else {
            if (count($lineup) < 11) {
                $issues[] = [
                    'type' => 'incomplete',
                    'message' => __('messages.pre_match_incomplete'),
                ];
            }

            // Load lineup players with suspensions for availability check
            $lineupPlayers = GamePlayer::with(['player', 'suspensions'])
                ->where('game_id', $gameId)
                ->whereIn('id', $lineup)
                ->get()
                ->sortBy(fn ($p) => LineupService::positionSortOrder($p->position));

            // Batch load suspended IDs
            $suspendedPlayerIds = PlayerSuspension::suspendedPlayerIdsForCompetition($competitionId);

            foreach ($lineupPlayers as $player) {
                $isSuspended = in_array($player->id, $suspendedPlayerIds);
                $isInjured = $player->injury_until && $player->injury_until->gt($matchDate);

                if ($isSuspended) {
                    $issues[] = [
                        'type' => 'unavailable',
                        'message' => __('messages.pre_match_unavailable', [
                            'name' => $player->name,
                            'reason' => __('messages.pre_match_reason_suspended'),
                        ]),
                        'playerId' => $player->id,
                    ];
                } elseif ($isInjured) {
                    $issues[] = [
                        'type' => 'unavailable',
                        'message' => __('messages.pre_match_unavailable', [
                            'name' => $player->name,
                            'reason' => __('messages.pre_match_reason_injured'),
                        ]),
                        'playerId' => $player->id,
                    ];
                }
            }
        }

        $unavailablePlayerIds = collect($issues)
            ->where('type', 'unavailable')
            ->pluck('playerId')
            ->toArray();

        return view('partials.pre-match-modal-content', [
            'game' => $game,
            'match' => $match,
            'isHome' => $isHome,
            'lineup' => $lineup,
            'lineupPlayers' => $lineupPlayers,
            'formation' => $formation,
            'formationLabel' => $formationEnum?->label() ?? $formation,
            'mentalityLabel' => $mentalityEnum?->label() ?? $mentality,
            'playingStyleLabel' => $playingStyleEnum?->label() ?? $playingStyle,
            'pressingLabel' => $pressingEnum?->label() ?? $pressing,
            'defensiveLineLabel' => $defensiveLineEnum?->label() ?? $defensiveLine,
            'issues' => $issues,
            'hasIssues' => !empty($issues),
            'unavailablePlayerIds' => $unavailablePlayerIds,
        ]);
    }
}
