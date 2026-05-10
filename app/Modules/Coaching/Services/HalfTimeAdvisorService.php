<?php

namespace App\Modules\Coaching\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Modules\Coaching\DTOs\CoachingTip;
use App\Modules\Coaching\DTOs\Confidence;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use Illuminate\Support\Collection;

/**
 * Computes half-time tactical recommendations for the user.
 *
 * Pure read service: inspects the first-half events of an already-simulated
 * match plus both teams' current tactics, and emits 0-3 advisory tips. When
 * a tip carries a `tacticalChange` payload, the UI's "Apply" button reuses
 * the existing /tactical-actions endpoint — no new write paths.
 *
 * Only invoked from the live-match view (the user's match), so the AI-vs-AI
 * fast-resolver path is untouched.
 */
class HalfTimeAdvisorService
{
    /** Minute at which the half-time read happens. Mirrors the live-match phase. */
    private const HALF_TIME_MINUTE = 45;

    /** Yellow cards on user players that trigger the discipline tip. */
    private const YELLOW_CARD_RISK_THRESHOLD = 2;

    /** Cap on the number of tips returned. Keeps the half-time UI scannable. */
    private const MAX_TIPS = 3;

    /**
     * Build half-time tips for the user's side of a match.
     *
     * @return array<int, CoachingTip>
     */
    public function buildTips(GameMatch $match, Game $game): array
    {
        $isUserHome = $match->isHomeTeam($game->team_id);
        $userTeamId = $game->team_id;
        $opponentTeamId = $isUserHome ? $match->away_team_id : $match->home_team_id;

        $firstHalfEvents = $match->events
            ->filter(fn (MatchEvent $e) => $e->minute <= self::HALF_TIME_MINUTE);

        $userScore = $this->scoreFor($firstHalfEvents, $userTeamId, $opponentTeamId);
        $opponentScore = $this->scoreFor($firstHalfEvents, $opponentTeamId, $userTeamId);

        $diff = $userScore - $opponentScore;

        $userTactics = $this->extractTactics($match, $isUserHome ? 'home' : 'away');
        $opponentTactics = $this->extractTactics($match, $isUserHome ? 'away' : 'home');

        $tips = [];

        $resultTip = $this->buildResultTip($diff, $userTactics);
        if ($resultTip !== null) {
            $tips[] = $resultTip;
        }

        $matchupTip = $this->buildMatchupTip($userTactics, $opponentTactics);
        if ($matchupTip !== null) {
            $tips[] = $matchupTip;
        }

        $disciplineTip = $this->buildDisciplineTip($firstHalfEvents, $userTeamId);
        if ($disciplineTip !== null) {
            $tips[] = $disciplineTip;
        }

        // If nothing notable surfaced, give the user one neutral read so the
        // panel never feels broken.
        if ($tips === []) {
            $tips[] = $this->buildBalancedTip();
        }

        return array_slice($tips, 0, self::MAX_TIPS);
    }

    /**
     * Goals scored by $teamId in the given event collection, accounting for
     * own goals (which credit the opposing team).
     */
    private function scoreFor(Collection $events, string $teamId, string $oppTeamId): int
    {
        return $events->reduce(function (int $carry, MatchEvent $event) use ($teamId, $oppTeamId): int {
            if ($event->event_type === MatchEvent::TYPE_GOAL && $event->team_id === $teamId) {
                return $carry + 1;
            }
            if ($event->event_type === MatchEvent::TYPE_OWN_GOAL && $event->team_id === $oppTeamId) {
                return $carry + 1;
            }

            return $carry;
        }, 0);
    }

    /**
     * Result-driven tip: respond to the scoreline.
     *
     * @param  array{mentality: string, playing_style: string, pressing: string, defensive_line: string}  $userTactics
     */
    private function buildResultTip(int $diff, array $userTactics): ?CoachingTip
    {
        // Heavy deficit: chase the game.
        if ($diff <= -2) {
            return $this->makeTip(
                id: 'result_chasing',
                headlineKey: 'coaching.tip_chasing_headline',
                rationaleKey: 'coaching.tip_chasing_rationale',
                tone: CoachingTip::TONE_WARNING,
                confidence: Confidence::HIGH,
                change: [
                    'mentality' => Mentality::ATTACKING->value,
                    'pressing' => PressingIntensity::HIGH_PRESS->value,
                    'defensive_line' => DefensiveLineHeight::HIGH_LINE->value,
                ],
            );
        }

        // One-goal deficit: measured push.
        if ($diff === -1) {
            $change = [];
            if ($userTactics['mentality'] !== Mentality::ATTACKING->value) {
                $change['mentality'] = Mentality::ATTACKING->value;
            }
            if ($userTactics['playing_style'] === PlayingStyle::COUNTER_ATTACK->value) {
                $change['playing_style'] = PlayingStyle::BALANCED->value;
            }

            return $this->makeTip(
                id: 'result_trailing_one',
                headlineKey: 'coaching.tip_trailing_one_headline',
                rationaleKey: 'coaching.tip_trailing_one_rationale',
                tone: CoachingTip::TONE_WARNING,
                confidence: Confidence::MEDIUM,
                change: $change ?: null,
            );
        }

        // Two-goal cushion: protect.
        if ($diff >= 2) {
            $change = [];
            if ($userTactics['mentality'] === Mentality::ATTACKING->value) {
                $change['mentality'] = Mentality::BALANCED->value;
            }
            if ($userTactics['pressing'] === PressingIntensity::HIGH_PRESS->value) {
                $change['pressing'] = PressingIntensity::STANDARD->value;
            }
            if ($userTactics['defensive_line'] === DefensiveLineHeight::HIGH_LINE->value) {
                $change['defensive_line'] = DefensiveLineHeight::NORMAL->value;
            }

            return $this->makeTip(
                id: 'result_two_goal_lead',
                headlineKey: 'coaching.tip_protecting_lead_headline',
                rationaleKey: 'coaching.tip_protecting_lead_rationale',
                tone: CoachingTip::TONE_OPPORTUNITY,
                confidence: Confidence::HIGH,
                change: $change ?: null,
            );
        }

        // One-goal cushion: stay disciplined; only intervene if currently aggressive.
        if ($diff === 1) {
            if ($userTactics['mentality'] === Mentality::ATTACKING->value) {
                return $this->makeTip(
                    id: 'result_one_goal_lead_attacking',
                    headlineKey: 'coaching.tip_one_goal_lead_headline',
                    rationaleKey: 'coaching.tip_one_goal_lead_rationale',
                    tone: CoachingTip::TONE_INFO,
                    confidence: Confidence::MEDIUM,
                    change: ['mentality' => Mentality::BALANCED->value],
                );
            }

            return null;
        }

        // Drawing: no automatic result tip — the matchup tip handles the rest.
        return null;
    }

    /**
     * Tactical-matchup tip: respond to the opponent's setup independent of score.
     *
     * @param  array{mentality: string, playing_style: string, pressing: string, defensive_line: string}  $userTactics
     * @param  array{mentality: string, playing_style: string, pressing: string, defensive_line: string}  $opponentTactics
     */
    private function buildMatchupTip(array $userTactics, array $opponentTactics): ?CoachingTip
    {
        // Opponent high-pressing AND our line is high → invert: drop deeper to release pressure.
        if ($opponentTactics['pressing'] === PressingIntensity::HIGH_PRESS->value
            && $userTactics['defensive_line'] === DefensiveLineHeight::HIGH_LINE->value) {
            return $this->makeTip(
                id: 'matchup_release_press',
                headlineKey: 'coaching.tip_release_press_headline',
                rationaleKey: 'coaching.tip_release_press_rationale',
                tone: CoachingTip::TONE_WARNING,
                confidence: Confidence::HIGH,
                change: [
                    'defensive_line' => DefensiveLineHeight::NORMAL->value,
                    'playing_style' => PlayingStyle::DIRECT->value,
                ],
            );
        }

        // Opponent sitting low-block + we're not in possession → switch to possession.
        if ($opponentTactics['pressing'] === PressingIntensity::LOW_BLOCK->value
            && $userTactics['playing_style'] !== PlayingStyle::POSSESSION->value) {
            return $this->makeTip(
                id: 'matchup_break_low_block',
                headlineKey: 'coaching.tip_break_low_block_headline',
                rationaleKey: 'coaching.tip_break_low_block_rationale',
                tone: CoachingTip::TONE_OPPORTUNITY,
                confidence: Confidence::MEDIUM,
                change: ['playing_style' => PlayingStyle::POSSESSION->value],
            );
        }

        // Opponent attacking + high line → counter-attack opportunity.
        if ($opponentTactics['mentality'] === Mentality::ATTACKING->value
            && $opponentTactics['defensive_line'] === DefensiveLineHeight::HIGH_LINE->value
            && $userTactics['playing_style'] !== PlayingStyle::COUNTER_ATTACK->value) {
            return $this->makeTip(
                id: 'matchup_counter_high_line',
                headlineKey: 'coaching.tip_counter_high_line_headline',
                rationaleKey: 'coaching.tip_counter_high_line_rationale',
                tone: CoachingTip::TONE_OPPORTUNITY,
                confidence: Confidence::MEDIUM,
                change: ['playing_style' => PlayingStyle::COUNTER_ATTACK->value],
            );
        }

        return null;
    }

    /**
     * Discipline tip: flag yellow-card risk on user players. Advisory only —
     * substitutions are out of scope for the apply flow at this stage.
     */
    private function buildDisciplineTip(Collection $firstHalfEvents, string $userTeamId): ?CoachingTip
    {
        $yellowCards = $firstHalfEvents
            ->filter(fn (MatchEvent $e) => $e->event_type === MatchEvent::TYPE_YELLOW_CARD
                && $e->team_id === $userTeamId)
            ->count();

        if ($yellowCards < self::YELLOW_CARD_RISK_THRESHOLD) {
            return null;
        }

        return $this->makeTip(
            id: 'discipline_card_risk',
            headlineKey: 'coaching.tip_card_risk_headline',
            rationaleKey: 'coaching.tip_card_risk_rationale',
            tone: CoachingTip::TONE_WARNING,
            confidence: Confidence::HIGH,
            change: null,
            replacements: ['count' => $yellowCards],
        );
    }

    /**
     * Fallback tip when nothing notable surfaced.
     */
    private function buildBalancedTip(): CoachingTip
    {
        return $this->makeTip(
            id: 'balanced_general',
            headlineKey: 'coaching.tip_balanced_headline',
            rationaleKey: 'coaching.tip_balanced_rationale',
            tone: CoachingTip::TONE_INFO,
            confidence: Confidence::LOW,
            change: null,
        );
    }

    /**
     * Read the current tactics for one side of the match, falling back to
     * the engine's defaults so callers never have to handle null.
     *
     * @return array{mentality: string, playing_style: string, pressing: string, defensive_line: string}
     */
    private function extractTactics(GameMatch $match, string $prefix): array
    {
        return [
            'mentality' => $match->{"{$prefix}_mentality"} ?? Mentality::BALANCED->value,
            'playing_style' => $match->{"{$prefix}_playing_style"} ?? PlayingStyle::BALANCED->value,
            'pressing' => $match->{"{$prefix}_pressing"} ?? PressingIntensity::STANDARD->value,
            'defensive_line' => $match->{"{$prefix}_defensive_line"} ?? DefensiveLineHeight::NORMAL->value,
        ];
    }

    /**
     * @param  array<string, string>|null  $change
     * @param  array<string, mixed>  $replacements
     */
    private function makeTip(
        string $id,
        string $headlineKey,
        string $rationaleKey,
        string $tone,
        Confidence $confidence,
        ?array $change,
        array $replacements = [],
    ): CoachingTip {
        return new CoachingTip(
            id: $id,
            headline: __($headlineKey, $replacements),
            rationale: __($rationaleKey, $replacements),
            tacticalChange: $change,
            tone: $tone,
            confidence: $confidence,
        );
    }
}
