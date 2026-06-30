<?php

namespace App\Modules\Match\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\TransferOffer;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Modules\Match\DTOs\MatchNarrative;

class MatchNarrativeService
{
    /**
     * Generate pre-match narrative snippets.
     *
     * The next-match card asks for the default 1-2; the dashboard briefing
     * requests a few more (`$limit`) for its wider canvas. selectTop() keeps the
     * selection varied (one per category) regardless of how many are requested.
     *
     * @return array<MatchNarrative>
     */
    public function generate(
        Game $game,
        GameMatch $nextMatch,
        ?GameStanding $playerStanding,
        ?GameStanding $opponentStanding,
        array $playerForm,
        array $opponentForm,
        int $limit = 2,
    ): array {
        if ($game->isTournamentMode()) {
            $candidates = $this->tournamentCandidates($game, $nextMatch, $playerStanding, $opponentStanding, $playerForm, $opponentForm);
        } else {
            $candidates = [
                ...$this->cupCandidates($nextMatch, $game),
                ...$this->europeanCandidates($nextMatch),
                ...$this->formCandidates($playerForm, $game),
                ...$this->stakesCandidates($game, $playerStanding, $opponentStanding, $nextMatch),
                ...$this->rivalryCandidates($game, $nextMatch),
                ...$this->marketCandidates($game),
                ...$this->moodCandidates($game),
                ...$this->scoutingCandidates($opponentStanding, $opponentForm, $nextMatch, $game),
            ];
        }

        return $this->selectTop($candidates, $nextMatch->round_number ?? 1, $limit);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Tournament-Specific Generators
    // ═══════════════════════════════════════════════════════════════════

    private function tournamentCandidates(
        Game $game,
        GameMatch $nextMatch,
        ?GameStanding $playerStanding,
        ?GameStanding $opponentStanding,
        array $playerForm,
        array $opponentForm,
    ): array {
        $isKnockout = $nextMatch->isCupMatch();

        $candidates = [];

        if ($isKnockout) {
            $candidates = [...$candidates, ...$this->tournamentKnockoutCandidates($nextMatch)];
        } else {
            $candidates = [...$candidates, ...$this->tournamentGroupCandidates($playerStanding)];
        }

        $candidates = [...$candidates, ...$this->tournamentOpponentCandidates($nextMatch, $game, $opponentStanding, $opponentForm)];
        $candidates = [...$candidates, ...$this->formCandidates($playerForm, $game)];
        $candidates = [...$candidates, ...$this->moodCandidates($game)];
        $candidates = [...$candidates, ...$this->tournamentAtmosphereCandidates($nextMatch)];

        return $candidates;
    }

    // ── Tournament: Group Stage ─────────────────────────────────────

    private function tournamentGroupCandidates(?GameStanding $standing): array
    {
        if (!$standing) {
            return [];
        }

        $candidates = [];
        $played = $standing->played ?? 0;
        $position = $standing->position;
        $points = $standing->points ?? 0;
        $group = $standing->group_label;

        // First match of the tournament
        if ($played === 0) {
            return [$this->candidate('group', 9, 'wc_group_opener')];
        }

        // Final group match — high drama
        if ($played === 2) {
            if ($points >= 6) {
                return [$this->candidate('group', 8, 'wc_group_qualified')];
            }

            if ($position >= 3 && $points <= 1) {
                return [$this->candidate('group', 10, 'wc_group_must_win')];
            }

            if ($position <= 3 && $points >= 3) {
                return [$this->candidate('group', 8, 'wc_group_on_brink')];
            }

            if ($position === 4 && $points === 0) {
                return [$this->candidate('group', 7, 'wc_group_eliminated')];
            }

            return [$this->candidate('group', 7, 'wc_group_gd')];
        }

        // After matchday 1
        if ($position === 1) {
            $candidates[] = $this->candidate('group', 8, 'wc_group_top', ['group' => $group]);
        }

        return $candidates;
    }

    // ── Tournament: Knockout Rounds ─────────────────────────────────

    private function tournamentKnockoutCandidates(GameMatch $match): array
    {
        $roundName = $match->round_name ?? '';

        if ($roundName === 'cup.final') {
            return [$this->candidate('knockout', 10, 'wc_knockout_final')];
        }

        if (str_contains($roundName, 'semi_finals')) {
            return [$this->candidate('knockout', 10, 'wc_knockout_sf')];
        }

        if (str_contains($roundName, 'third_place')) {
            return [$this->candidate('knockout', 7, 'wc_knockout_third')];
        }

        if (str_contains($roundName, 'quarter_finals')) {
            return [$this->candidate('knockout', 9, 'wc_knockout_qf')];
        }

        if (str_contains($roundName, 'round_of_16')) {
            return [$this->candidate('knockout', 8, 'wc_knockout_r16')];
        }

        if (str_contains($roundName, 'round_of_32')) {
            return [$this->candidate('knockout', 8, 'wc_knockout_r32')];
        }

        return [];
    }

    // ── Tournament: Opponent Context ────────────────────────────────

    private function tournamentOpponentCandidates(GameMatch $match, Game $game, ?GameStanding $opponentStanding, array $opponentForm): array
    {
        $candidates = [];

        $opponentName = $match->home_team_id === $game->team_id
            ? $match->awayTeam->short_name ?? $match->awayTeam->name
            : $match->homeTeam->short_name ?? $match->homeTeam->name;

        // Group stage: opponent position in group
        if ($opponentStanding && !$match->isCupMatch()) {
            if ($opponentStanding->position === 1 && ($opponentStanding->played ?? 0) > 0) {
                $candidates[] = $this->candidate('opponent', 6, 'wc_opponent_group_leader', [
                    'opponent' => $opponentName,
                ]);
            } elseif ($opponentStanding->position === 4 && ($opponentStanding->played ?? 0) > 0) {
                $candidates[] = $this->candidate('opponent', 5, 'wc_opponent_group_bottom', [
                    'opponent' => $opponentName,
                ]);
            }
        }

        // Opponent tournament form
        if (!empty($opponentForm)) {
            $oppWins = count(array_filter($opponentForm, fn ($r) => $r === 'W'));
            $total = count($opponentForm);

            if ($oppWins === $total && $total >= 2) {
                $candidates[] = $this->candidate('opponent', 6, 'wc_opponent_tournament_streak', [
                    'opponent' => $opponentName,
                ]);
            } elseif ($oppWins === 0 && $total >= 2) {
                $candidates[] = $this->candidate('opponent', 5, 'wc_opponent_winless', [
                    'opponent' => $opponentName,
                ]);
            }
        }

        return $candidates;
    }

    // ── Tournament: Atmosphere & Color ──────────────────────────────

    private function tournamentAtmosphereCandidates(GameMatch $match): array
    {
        $candidates = [];
        $isKnockout = $match->isCupMatch();
        $roundName = $match->round_name ?? '';

        // Weather — always applicable (summer in North America)
        $candidates[] = $this->candidate('atmosphere', 4, 'wc_weather');

        // Fans — always applicable
        $candidates[] = $this->candidate('atmosphere_fans', 3, 'wc_fans');

        // Media
        $candidates[] = $this->candidate('atmosphere_media', 3, 'wc_media');

        // Tournament phase color
        if (!$isKnockout) {
            $candidates[] = $this->candidate('atmosphere_phase', 3, 'wc_group_color');
        } elseif (str_contains($roundName, 'quarter') || str_contains($roundName, 'semi') || $roundName === 'cup.final') {
            $candidates[] = $this->candidate('atmosphere_phase', 4, 'wc_late_tournament');
        } else {
            $candidates[] = $this->candidate('atmosphere_phase', 4, 'wc_knockout_color');
        }

        return $candidates;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Career Mode Generators (gated — not currently active)
    // ═══════════════════════════════════════════════════════════════════

    private function cupCandidates(GameMatch $match, Game $game): array
    {
        if (!$match->isCupMatch()) {
            return [];
        }

        // European knockout ties get their own tailored continental-night prose
        // (europeanCandidates) rather than the generic domestic-cup framing.
        if (($match->competition?->role ?? '') === Competition::ROLE_EUROPEAN) {
            return [];
        }

        $candidates = [];
        $roundName = $match->round_name ?? '';
        $competitionName = __($match->competition->name ?? '');

        $cupTie = $match->cupTie;
        $isSecondLeg = $cupTie && $cupTie->second_leg_match_id === $match->id;
        $firstLegPlayed = $cupTie && $cupTie->firstLegMatch?->played;

        if (str_contains($roundName, 'final') && !str_contains($roundName, 'semi')) {
            return [$this->candidate('cup', 10, 'cup_final', ['competition' => $competitionName])];
        }

        if (str_contains($roundName, 'semi_finals')) {
            if ($isSecondLeg && $firstLegPlayed) {
                return [$this->candidate('cup', 10, 'cup_semi_second_leg', ['competition' => $competitionName])];
            }

            return [$this->candidate('cup', 9, 'cup_semi', ['competition' => $competitionName])];
        }

        if (str_contains($roundName, 'quarter_finals')) {
            return [$this->candidate('cup', 8, 'cup_quarter', ['competition' => $competitionName])];
        }

        if (str_contains($roundName, 'round_of_16')) {
            return [$this->candidate('cup', 7, 'cup_round_of_16', ['competition' => $competitionName])];
        }

        if ($isSecondLeg && $firstLegPlayed) {
            $fl = $cupTie->firstLegMatch;
            $userIsHome = $fl->home_team_id === $game->team_id;
            $userScore = $userIsHome ? $fl->home_score : $fl->away_score;
            $oppScore = $userIsHome ? $fl->away_score : $fl->home_score;

            if ($userScore > $oppScore) {
                return [$this->candidate('cup', 7, 'cup_second_leg_ahead', ['score' => $userScore . '-' . $oppScore])];
            } elseif ($userScore < $oppScore) {
                return [$this->candidate('cup', 8, 'cup_second_leg_behind', ['score' => $oppScore . '-' . $userScore])];
            }

            return [$this->candidate('cup', 7, 'cup_second_leg_drawn')];
        }

        return [$this->candidate('cup', 6, 'cup_generic', ['competition' => $competitionName, 'round' => __($roundName)])];
    }

    // ── Career: Transfer Market & Media Buzz ────────────────────────

    /**
     * Outward "buzz": rival clubs courting the user's players (real interest
     * records) and transfer-window deadline pressure. Brings the wider market
     * into the pre-match read.
     */
    private function marketCandidates(Game $game): array
    {
        $candidates = [];

        $offers = TransferOffer::with(['gamePlayer', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('offering_team_id', '!=', $game->team_id)
            ->whereIn('offer_type', [TransferOffer::TYPE_UNSOLICITED, TransferOffer::TYPE_PRE_CONTRACT])
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_FEE_AGREED, TransferOffer::STATUS_AGREED])
            ->whereHas('gamePlayer', fn ($q) => $q->where('team_id', $game->team_id))
            ->orderByDesc('transfer_fee')
            ->get();

        if ($offers->isNotEmpty()) {
            $distinctPlayers = $offers->pluck('game_player_id')->unique()->count();
            $lead = $offers->first();
            $playerName = $lead->gamePlayer?->name;
            $clubName = $lead->offeringTeam?->short_name ?? $lead->offeringTeam?->name;

            if ($playerName && $clubName) {
                // A bid is "breaking news" when fresh and fades to old news as it
                // lingers (offers persist for weeks), so the same transfer saga
                // stops leading the feed every single matchday.
                $ageDays = $lead->game_date ? abs($lead->game_date->diffInDays($game->current_date)) : 999;
                $priority = $ageDays <= 7 ? 8 : ($ageDays <= 21 ? 5 : 3);

                if ($distinctPlayers > 1) {
                    $candidates[] = $this->candidate('market', $priority, 'market_multiple_targets', ['count' => $distinctPlayers]);
                } elseif ($lead->isPreContract()) {
                    $candidates[] = $this->candidate('market', $priority, 'market_precontract', ['player' => $playerName, 'club' => $clubName]);
                } else {
                    $candidates[] = $this->candidate('market', $priority, 'market_courted', ['player' => $playerName, 'club' => $clubName]);
                }
            }
        }

        $countdown = $game->getWindowCountdown();
        if ($countdown && $countdown['action'] === 'closes' && $countdown['matchdays'] <= 3) {
            $candidates[] = $this->candidate('market', 6, 'market_window_closing');
        }

        return $candidates;
    }

    // ── Career: Rivalry & Head-to-Head ──────────────────────────────

    /**
     * The reverse league fixture earlier this season frames the meeting — a
     * win avenged, a result to protect, or a score to settle.
     */
    private function rivalryCandidates(Game $game, GameMatch $match): array
    {
        if ($match->competition_id !== $game->competition_id) {
            return [];
        }

        $userIsHome = $match->home_team_id === $game->team_id;
        $opponent = $userIsHome ? $match->awayTeam : $match->homeTeam;
        $opponentId = $userIsHome ? $match->away_team_id : $match->home_team_id;
        $opponentName = $opponent?->short_name ?? $opponent?->name;

        if (!$opponentName) {
            return [];
        }

        $reverse = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $match->competition_id)
            ->where('played', true)
            ->where('id', '!=', $match->id)
            ->where(function ($q) use ($game, $opponentId) {
                $q->where(fn ($q2) => $q2->where('home_team_id', $game->team_id)->where('away_team_id', $opponentId))
                    ->orWhere(fn ($q2) => $q2->where('home_team_id', $opponentId)->where('away_team_id', $game->team_id));
            })
            ->orderByDesc('scheduled_date')
            ->first();

        if (!$reverse) {
            return [];
        }

        $reverseUserHome = $reverse->home_team_id === $game->team_id;
        $userScore = $reverseUserHome ? $reverse->home_score : $reverse->away_score;
        $oppScore = $reverseUserHome ? $reverse->away_score : $reverse->home_score;

        if ($userScore > $oppScore) {
            return [$this->candidate('rivalry', 7, 'rivalry_won_reverse', ['opponent' => $opponentName, 'score' => "{$userScore}-{$oppScore}"])];
        }

        if ($userScore < $oppScore) {
            return [$this->candidate('rivalry', 8, 'rivalry_lost_reverse', ['opponent' => $opponentName, 'score' => "{$oppScore}-{$userScore}"])];
        }

        return [$this->candidate('rivalry', 6, 'rivalry_drew_reverse', ['opponent' => $opponentName])];
    }

    // ── Career: European Nights ─────────────────────────────────────

    /**
     * Tailored continental-competition framing (Champions / Europa / Conference
     * League), distinct from the domestic-cup prose — knockout drama, or a
     * group / league-phase night under the lights.
     */
    private function europeanCandidates(GameMatch $match): array
    {
        if (($match->competition?->role ?? '') !== Competition::ROLE_EUROPEAN) {
            return [];
        }

        $competitionName = __($match->competition->name ?? '');
        $roundName = $match->round_name ?? '';

        if ($match->isCupMatch()) {
            if (str_contains($roundName, 'final') && !str_contains($roundName, 'semi')) {
                return [$this->candidate('european', 10, 'euro_final', ['competition' => $competitionName])];
            }

            if (str_contains($roundName, 'semi')) {
                return [$this->candidate('european', 9, 'euro_semi', ['competition' => $competitionName])];
            }

            return [$this->candidate('european', 8, 'euro_knockout', ['competition' => $competitionName])];
        }

        return [$this->candidate('european', 7, 'euro_group', ['competition' => $competitionName])];
    }

    private function formCandidates(array $playerForm, Game $game): array
    {
        $candidates = [];

        if (empty($playerForm)) {
            return $candidates;
        }

        $winStreak = $this->detectStreak($playerForm, 'W');
        $loseStreak = $this->detectStreak($playerForm, 'L');
        $unbeaten = $this->detectUnbeatenRun($playerForm);
        $winless = $this->detectWinlessRun($playerForm);

        // Thresholds are deliberately modest, so form colour surfaces from a
        // short run rather than only after a long one — more week-to-week
        // variety. A "long" winning streak is the only mode-specific cutoff
        // (tournaments are shorter).
        $winStreakLong = $game->isTournamentMode() ? 4 : 5;
        $winStreakMin = 2;
        $loseStreakMin = 2;
        $unbeatenMin = $game->isTournamentMode() ? 3 : 4;
        $winlessMin = 3;

        if ($winStreak >= $winStreakLong) {
            $candidates[] = $this->candidate('form', 9, 'streak_win_long', ['count' => $winStreak]);
        } elseif ($winStreak >= $winStreakMin) {
            $candidates[] = $this->candidate('form', 8, 'streak_win', ['count' => $winStreak]);
        }

        if ($loseStreak >= $loseStreakMin) {
            $candidates[] = $this->candidate('form', 8, 'streak_lose', ['count' => $loseStreak]);
        }

        if ($unbeaten >= $unbeatenMin && $winStreak < $winStreakLong) {
            $candidates[] = $this->candidate('form', 7, 'unbeaten', ['count' => $unbeaten]);
        }

        if ($winless >= $winlessMin && $loseStreak < $loseStreakMin) {
            $candidates[] = $this->candidate('form', 7, 'winless', ['count' => $winless]);
        }

        return $candidates;
    }

    private function stakesCandidates(Game $game, ?GameStanding $playerStanding, ?GameStanding $opponentStanding, GameMatch $match): array
    {
        $candidates = [];

        if ($game->pre_season || !$playerStanding || $match->competition_id !== $game->competition_id) {
            return $candidates;
        }

        $matchday = $match->round_number ?? 0;
        $position = $playerStanding->position;
        $points = $playerStanding->points;

        if ($position === 1 && $matchday >= 4) {
            $candidates[] = $this->candidate('stakes', 9, 'top_of_table');
        }

        if ($matchday >= 4 && $position > 1 && $position <= 3) {
            $leaderPoints = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $game->competition_id)
                ->where('position', 1)
                ->value('points');

            if ($leaderPoints !== null) {
                $gap = $leaderPoints - $points;
                if ($gap <= 6) {
                    $candidates[] = $this->candidate('stakes', 9, 'title_race', ['points' => $gap]);
                }
            }
        }

        $totalTeams = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->count();

        if ($matchday >= 5 && $totalTeams > 0 && $position > $totalTeams - 3) {
            $candidates[] = $this->candidate('stakes', 9, 'relegation', [
                'position' => $this->ordinalPosition($position),
            ]);
        }

        if ($opponentStanding && $matchday >= 4) {
            $positionDiff = abs($position - $opponentStanding->position);
            $pointsDiff = abs($points - $opponentStanding->points);

            if ($positionDiff <= 3 && $pointsDiff <= 4) {
                $opponentName = $match->home_team_id === $game->team_id
                    ? $match->awayTeam->short_name ?? $match->awayTeam->name
                    : $match->homeTeam->short_name ?? $match->homeTeam->name;

                $candidates[] = $this->candidate('stakes', 8, 'direct_rival', [
                    'opponent' => $opponentName,
                    'points' => $pointsDiff,
                ]);
            }
        }

        if ($matchday >= 6 && $game->season_goal) {
            $competition = Competition::find($game->competition_id);
            $config = $competition?->getConfig();

            if ($config instanceof HasSeasonGoals) {
                $targetPosition = $config->getGoalTargetPosition($game->season_goal);
                $diff = $position - $targetPosition;

                if ($diff >= 3) {
                    $candidates[] = $this->candidate('stakes', 7, 'goal_behind', [
                        'position' => $this->ordinalPosition($position),
                        'diff' => $diff,
                    ]);
                } elseif ($diff <= -3) {
                    $candidates[] = $this->candidate('stakes', 6, 'goal_ahead', [
                        'position' => $this->ordinalPosition($position),
                    ]);
                }
            }
        }

        $totalMatchdays = ($totalTeams - 1) * 2;
        if ($totalMatchdays > 0 && $matchday > $totalMatchdays - 4) {
            $candidates[] = $this->candidate('stakes', 7, 'final_stretch');
        }

        return $candidates;
    }

    private function moodCandidates(Game $game): array
    {
        $candidates = [];

        $stats = GamePlayer::query()
            ->join('game_player_match_state', 'game_players.id', '=', 'game_player_match_state.game_player_id')
            ->where('game_players.game_id', $game->id)
            ->where('game_players.team_id', $game->team_id)
            ->selectRaw('AVG(game_player_match_state.morale) as avg_morale, AVG(game_player_match_state.fitness) as avg_fitness')
            ->first();

        $avgMorale = (int) round($stats->avg_morale ?? 50);
        $avgFitness = (int) round($stats->avg_fitness ?? 50);

        $injuryCount = GamePlayer::query()
            ->join('game_player_match_state', 'game_players.id', '=', 'game_player_match_state.game_player_id')
            ->where('game_players.game_id', $game->id)
            ->where('game_players.team_id', $game->team_id)
            ->where('game_player_match_state.injury_until', '>=', $game->current_date)
            ->count();

        if ($injuryCount >= 4) {
            $candidates[] = $this->candidate('mood', 8, 'injury_crisis', ['count' => $injuryCount]);
        }

        // Thresholds sit inside the [50,100] morale band: the default 80 must
        // read as neutral, not "sky-high", so `morale_high` is earned only above
        // 85 (a winning run) and `morale_low` becomes reachable below 62 (a side
        // slumping under the underperformance term). Neutral 62–85 stays quiet.
        // Problems lead the feed (priority 7); the stable positive state sits at
        // low priority so it fills a gap rather than leading every quiet week.
        if ($avgMorale < 62) {
            $candidates[] = $this->candidate('mood', 7, 'morale_low');
        } elseif ($avgMorale > 85) {
            $candidates[] = $this->candidate('mood', 3, 'morale_high');
        }

        if ($avgFitness < 55) {
            $candidates[] = $this->candidate('mood', 7, 'fitness_low');
        } elseif ($avgFitness > 80) {
            $candidates[] = $this->candidate('mood', 2, 'fitness_high');
        }

        return $candidates;
    }

    private function scoutingCandidates(?GameStanding $opponentStanding, array $opponentForm, GameMatch $match, Game $game): array
    {
        $candidates = [];

        $opponentName = $match->home_team_id === $game->team_id
            ? $match->awayTeam->short_name ?? $match->awayTeam->name
            : $match->homeTeam->short_name ?? $match->homeTeam->name;

        if (!empty($opponentForm)) {
            $oppWins = count(array_filter($opponentForm, fn ($r) => $r === 'W'));
            $total = count($opponentForm);

            if ($oppWins <= 1 && $total >= 4) {
                $candidates[] = $this->candidate('scouting', 6, 'opponent_poor_form', [
                    'opponent' => $opponentName,
                    'wins' => $oppWins,
                    'total' => $total,
                ]);
            } elseif ($oppWins >= 4 && $total >= 5) {
                $candidates[] = $this->candidate('scouting', 6, 'opponent_hot', [
                    'opponent' => $opponentName,
                    'wins' => $oppWins,
                    'total' => $total,
                ]);
            }
        }

        if ($opponentStanding && $opponentStanding->played >= 5) {
            $playerStanding = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $game->competition_id)
                ->where('team_id', $game->team_id)
                ->first();

            if ($playerStanding && $playerStanding->played >= 5 && $opponentStanding->position) {
                $posDiff = $playerStanding->position - $opponentStanding->position;

                if ($posDiff > 8) {
                    $candidates[] = $this->candidate('scouting', 7, 'opponent_strong', [
                        'opponent' => $opponentName,
                        'position' => $this->ordinalPosition($opponentStanding->position),
                    ]);
                } elseif ($posDiff < -8) {
                    $candidates[] = $this->candidate('scouting', 5, 'opponent_weak', [
                        'opponent' => $opponentName,
                        'position' => $this->ordinalPosition($opponentStanding->position),
                    ]);
                }
            }
        }

        // Always-on opponent preview floor: a notable angle (hot/poor/strong/weak)
        // wins on priority when one applies, but otherwise this guarantees a
        // fresh, opponent-specific line every matchday — the single biggest
        // source of week-to-week variety, since the opponent changes each round.
        // Home/away framing + matchday-rotated variants keep it from repeating.
        $userIsHome = $match->home_team_id === $game->team_id;
        $candidates[] = $this->candidate('scouting', 4, $userIsHome ? 'opponent_preview_home' : 'opponent_preview_away', [
            'opponent' => $opponentName,
        ]);

        return $candidates;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════════════

    private function detectStreak(array $form, string $result): int
    {
        $count = 0;
        foreach (array_reverse($form) as $r) {
            if ($r === $result) {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    private function detectUnbeatenRun(array $form): int
    {
        $count = 0;
        foreach (array_reverse($form) as $r) {
            if ($r !== 'L') {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    private function detectWinlessRun(array $form): int
    {
        $count = 0;
        foreach (array_reverse($form) as $r) {
            if ($r !== 'W') {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    private function candidate(string $category, int $priority, string $key, array $params = []): array
    {
        return [
            'category' => $category,
            'priority' => $priority,
            'key' => $key,
            'params' => $params,
        ];
    }

    /**
     * Select up to $limit candidates in priority order, one per category so
     * the snippets stay varied. The next-match card requests 2; the dashboard
     * briefing requests more for its wider canvas.
     *
     * @return array<MatchNarrative>
     */
    private function selectTop(array $candidates, int $matchday, int $limit = 2): array
    {
        if (empty($candidates) || $limit < 1) {
            return [];
        }

        usort($candidates, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        $result = [];
        $usedCategories = [];

        foreach ($candidates as $candidate) {
            if (in_array($candidate['category'], $usedCategories, true)) {
                continue;
            }

            $result[] = $this->toNarrative($candidate, $matchday);
            $usedCategories[] = $candidate['category'];

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function toNarrative(array $candidate, int $matchday): MatchNarrative
    {
        $variantKey = $this->pickVariant($candidate['key'], $matchday);

        return new MatchNarrative(
            text: __("narrative.{$variantKey}", $candidate['params']),
            category: $candidate['category'],
        );
    }

    /**
     * Pick a translation variant deterministically based on matchday.
     */
    private function pickVariant(string $baseKey, int $matchday): string
    {
        $variantCount = 0;
        for ($i = 1; $i <= 6; $i++) {
            if (__("narrative.{$baseKey}_v{$i}") !== "narrative.{$baseKey}_v{$i}") {
                $variantCount = $i;
            } else {
                break;
            }
        }

        if ($variantCount === 0) {
            return $baseKey;
        }

        $variant = ($matchday % $variantCount) + 1;

        return "{$baseKey}_v{$variant}";
    }

    private function ordinalPosition(int $position): string
    {
        if (app()->getLocale() === 'es') {
            return $position . 'º';
        }

        return match ($position % 10) {
            1 => $position . (($position % 100 === 11) ? 'th' : 'st'),
            2 => $position . (($position % 100 === 12) ? 'th' : 'nd'),
            3 => $position . (($position % 100 === 13) ? 'th' : 'rd'),
            default => $position . 'th',
        };
    }
}
