<?php

namespace App\Support;

/**
 * Bundles the translated narrative template set consumed by the
 * client-side live-match atmosphere and match-summary generators.
 */
class LiveMatchNarrativeTemplates
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        return array_merge(self::atmosphere(), self::summary());
    }

    /**
     * @return array<string, mixed>
     */
    private static function atmosphere(): array
    {
        return [
            'shotOnTarget' => __('commentary.atmosphere_shot_on_target'),
            'shotOffTarget' => __('commentary.atmosphere_shot_off_target'),
            'foul' => __('commentary.atmosphere_foul'),
            'contextualDrawOpen' => __('commentary.contextual_draw_open'),
            'contextualDrawWithGoals' => __('commentary.contextual_draw_with_goals'),
            'contextualHomeLeading' => __('commentary.contextual_home_leading'),
            'contextualAwayLeading' => __('commentary.contextual_away_leading'),
            'contextualHomeDominant' => __('commentary.contextual_home_dominant'),
            'contextualAwayDominant' => __('commentary.contextual_away_dominant'),
            'contextualTightGame' => __('commentary.contextual_tight_game'),
            'contextualEndLosing' => __('commentary.contextual_end_losing'),
            'contextualEndLosingByOne' => __('commentary.contextual_end_losing_by_one'),
            'contextualEndWinning' => __('commentary.contextual_end_winning'),
            'contextualEndDraw' => __('commentary.contextual_end_draw'),
            'contextualEndDrawKnockout' => __('commentary.contextual_end_draw_knockout'),
            'contextualSecondHalfStart' => __('commentary.contextual_second_half_start'),
            'contextualAwayFans' => __('commentary.contextual_away_fans'),
            'contextualHomeFans' => __('commentary.contextual_home_fans'),
            'goalPrefix' => __('commentary.goal_prefix'),
            'goalAssisted' => __('commentary.goal_assisted'),
            'goalSolo' => __('commentary.goal_solo'),
            'goalPenalty' => __('commentary.goal_penalty'),
            'tacticalHighPressWorking' => __('commentary.tactical_high_press_working'),
            'tacticalHighPressFading' => __('commentary.tactical_high_press_fading'),
            'tacticalHighPressExhausted' => __('commentary.tactical_high_press_exhausted'),
            'tacticalOppPressFading' => __('commentary.tactical_opp_press_fading'),
            'tacticalOppExhausted' => __('commentary.tactical_opp_exhausted'),
            'tacticalLowBlockWall' => __('commentary.tactical_low_block_wall'),
            'tacticalLowBlockFresh' => __('commentary.tactical_low_block_fresh'),
            'tacticalPossessionControl' => __('commentary.tactical_possession_control'),
            'tacticalPossessionFrustrated' => __('commentary.tactical_possession_frustrated'),
            'tacticalCounterWaiting' => __('commentary.tactical_counter_waiting'),
            'tacticalCounterExploiting' => __('commentary.tactical_counter_exploiting'),
            'tacticalDirectPlay' => __('commentary.tactical_direct_play'),
            'tacticalDirectBypassingPress' => __('commentary.tactical_direct_bypassing_press'),
            'goalCounterAttack' => __('commentary.goal_counter_attack'),
            'goalPossession' => __('commentary.goal_possession'),
            'goalDirect' => __('commentary.goal_direct'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(): array
    {
        return [
            'summaryShout' => __('match_summary.shout'),
            'summaryOpeningHomeWin' => __('match_summary.opening_home_win'),
            'summaryOpeningAwayWin' => __('match_summary.opening_away_win'),
            'summaryOpeningBlowout' => __('match_summary.opening_blowout'),
            'summaryOpeningDraw' => __('match_summary.opening_draw'),
            'summaryOpeningGoalless' => __('match_summary.opening_goalless'),
            'summaryOpeningNarrowWin' => __('match_summary.opening_narrow_win'),
            'summaryOpeningExtraTime' => __('match_summary.opening_extra_time'),
            'summaryOpeningPenalties' => __('match_summary.opening_penalties'),
            'summaryOpeningCupWin' => __('match_summary.opening_cup_win'),
            'summaryOpeningCupDraw' => __('match_summary.opening_cup_draw'),
            'summaryOpeningHighStakesWin' => __('match_summary.opening_high_stakes_win'),
            'summaryOpeningHighStakesChampion' => __('match_summary.opening_high_stakes_champion'),
            'summaryGoalsOneTeam' => __('match_summary.goals_one_team'),
            'summaryGoalsOneTeamSingleScorer' => __('match_summary.goals_one_team_single_scorer'),
            'summaryGoalsTeamFragmentSingle' => __('match_summary.goals_team_fragment_single'),
            'summaryGoalsTeamFragmentMulti' => __('match_summary.goals_team_fragment_multi'),
            'summaryGoalsTwoTeamsJoin' => __('match_summary.goals_two_teams_join'),
            'summaryScorerJoinAnd' => __('match_summary.scorer_join_and'),
            'summaryComeback' => __('match_summary.comeback'),
            'summaryRedCardSingle' => __('match_summary.red_card_single'),
            'summaryRedCardsMultiple' => __('match_summary.red_cards_multiple'),
            'summaryDominantFirstHalf' => __('match_summary.dominant_first_half'),
            'summaryDominantSecondHalf' => __('match_summary.dominant_second_half'),
            'summaryPenaltyGoalNote' => __('match_summary.penalty_goal_note'),
            'summaryOwnGoalNote' => __('match_summary.own_goal_note'),
            'summaryFormLosingStreak' => __('match_summary.form_losing_streak'),
            'summaryFormWinningStreak' => __('match_summary.form_winning_streak'),
            'summaryFormWinless' => __('match_summary.form_winless'),
            'summaryMvpClosing' => __('match_summary.mvp_closing'),
            'summaryLastMinuteWinner' => __('match_summary.last_minute_winner'),
            'summaryLastMinuteEqualizer' => __('match_summary.last_minute_equalizer'),
            'summaryHatTrick' => __('match_summary.hat_trick'),
            'summaryUpset' => __('match_summary.upset'),
            'summaryExpectedWin' => __('match_summary.expected_win'),
            'summaryHighScoring' => __('match_summary.high_scoring'),
            'summaryFewChances' => __('match_summary.few_chances'),
        ];
    }
}
