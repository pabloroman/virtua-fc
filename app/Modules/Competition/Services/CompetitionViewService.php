<?php

namespace App\Modules\Competition\Services;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use App\Models\Team;
use Illuminate\Support\Collection;

class CompetitionViewService
{
    public function getStandings(Game $game, Competition $competition): Collection
    {
        return GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->orderBy('group_label')
            ->orderBy('position')
            ->get();
    }

    public function getTeamForms(Collection $standings): array
    {
        return $standings->mapWithKeys(fn ($s) => [
            $s->team_id => $s->form ? str_split($s->form) : [],
        ])->all();
    }

    public function getTopScorers(string $gameId, string $competitionId): Collection
    {
        $scorerRows = MatchEvent::where('match_events.game_id', $gameId)
            ->where('match_events.event_type', MatchEvent::TYPE_GOAL)
            ->join('game_matches', 'game_matches.id', '=', 'match_events.game_match_id')
            ->where('game_matches.competition_id', $competitionId)
            ->selectRaw('match_events.game_player_id, match_events.team_id, COUNT(*) as goals')
            ->groupBy('match_events.game_player_id', 'match_events.team_id')
            ->orderByDesc('goals')
            ->limit(10)
            ->get();

        if ($scorerRows->isEmpty()) {
            return collect();
        }

        $players = GamePlayer::with('player')
            ->whereIn('id', $scorerRows->pluck('game_player_id')->unique())
            ->get()
            ->keyBy('id');

        $teams = Team::whereIn('id', $scorerRows->pluck('team_id')->unique())
            ->get()
            ->keyBy('id');

        return $scorerRows->map(function ($row) use ($players, $teams) {
            $player = $players[$row->game_player_id] ?? null;
            if (!$player) {
                return null;
            }
            $player = clone $player;
            $player->goals = $row->goals;
            $player->scorer_team = $teams[$row->team_id] ?? null;

            return $player;
        })->filter()->sortByDesc('goals')->values();
    }

    public function getKnockoutRounds(Competition $competition, int $gameSeason): Collection
    {
        return collect(LeagueFixtureGenerator::loadKnockoutRounds(
            $competition->id,
            $competition->season,
            $gameSeason,
        ));
    }

    public function getKnockoutTies(Game $game, Competition $competition): Collection
    {
        return CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch', 'secondLegMatch', 'competition'])
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->get()
            ->groupBy('round_number');
    }

    public function isLeaguePhaseComplete(Game $game, Competition $competition, Collection $standings): bool
    {
        return !GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists() && $standings->first()?->played > 0;
    }

    public function findPlayerTie(Collection $rounds, Collection $tiesByRound, string $teamId): ?CupTie
    {
        foreach ($rounds->reverse() as $round) {
            $ties = $tiesByRound->get($round->round, collect());
            $playerTie = $ties->first(fn ($tie) => $tie->involvesTeam($teamId));
            if ($playerTie) {
                return $playerTie;
            }
        }

        return null;
    }

    public function resolveCupStatus(?CupTie $playerTie, string $teamId, int $maxRound): string
    {
        if (!$playerTie) {
            return 'not_entered';
        }

        if (!$playerTie->completed) {
            return 'active';
        }

        if ($playerTie->winner_id === $teamId) {
            return $playerTie->round_number === $maxRound ? 'champion' : 'advanced';
        }

        return 'eliminated';
    }
}
