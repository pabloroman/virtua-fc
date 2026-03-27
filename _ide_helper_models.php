<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $team_id
 * @property string $name
 * @property array<array-key, mixed>|null $nationality
 * @property \Illuminate\Support\Carbon $date_of_birth
 * @property string $position
 * @property int $technical_ability
 * @property int $physical_ability
 * @property int $potential
 * @property int $potential_low
 * @property int $potential_high
 * @property \Illuminate\Support\Carbon $appeared_at
 * @property-read \App\Models\Game $game
 * @property-read int $age
 * @property-read array|null $nationality_flag
 * @property-read int $overall
 * @property-read array $position_display
 * @property-read string $position_group
 * @property-read string $potential_range
 * @property-read \App\Models\Team|null $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereAppearedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePhysicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePotential($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePotentialHigh($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer wherePotentialLow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademyPlayer whereTechnicalAbility($value)
 * @mixin \Eloquent
 */
	class AcademyPlayer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $team_id
 * @property string $reputation_level
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereReputationLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereTeamId($value)
 * @mixin \Eloquent
 */
	class ClubProfile extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string $country
 * @property int $tier
 * @property string $type
 * @property string $season
 * @property string $handler_type
 * @property string $role
 * @property string $scope
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @method static \Database\Factories\CompetitionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereHandlerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereType($value)
 * @mixin \Eloquent
 */
	class Competition extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $game_id
 * @property string $competition_id
 * @property string $team_id
 * @property int $entry_round
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry whereEntryRound($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry whereTeamId($value)
 * @mixin \Eloquent
 */
	class CompetitionEntry extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $competition_id
 * @property string $team_id
 * @property string $season
 * @property int $entry_round
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam whereEntryRound($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam whereTeamId($value)
 * @mixin \Eloquent
 */
	class CompetitionTeam extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $competition_id
 * @property int $round_number
 * @property string $home_team_id
 * @property string $away_team_id
 * @property string|null $first_leg_match_id
 * @property string|null $second_leg_match_id
 * @property string|null $winner_id
 * @property bool $completed
 * @property array<array-key, mixed>|null $resolution
 * @property-read \App\Models\Team $awayTeam
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\GameMatch|null $firstLegMatch
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\Team $homeTeam
 * @property-read \App\Models\GameMatch|null $secondLegMatch
 * @property-read \App\Models\Team|null $winner
 * @method static \Database\Factories\CupTieFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereAwayTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereFirstLegMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereHomeTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereResolution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereRoundNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereSecondLegMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CupTie whereWinnerId($value)
 * @mixin \Eloquent
 */
	class CupTie extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $type
 * @property string $category
 * @property int $amount
 * @property string $description
 * @property string|null $related_player_id
 * @property \Illuminate\Support\Carbon $transaction_date
 * @property-read \App\Models\Game $game
 * @property-read string $category_label
 * @property-read string $formatted_amount
 * @property-read string $signed_amount
 * @property-read \App\Models\GamePlayer|null $relatedPlayer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction whereRelatedPlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction whereTransactionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancialTransaction whereType($value)
 * @mixin \Eloquent
 */
	class FinancialTransaction extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property int $user_id
 * @property string $player_name
 * @property string $team_id
 * @property string $season
 * @property \Illuminate\Support\Carbon|null $current_date
 * @property int $current_matchday
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $default_formation
 * @property array<array-key, mixed>|null $default_lineup
 * @property string $default_mentality
 * @property bool $needs_onboarding
 * @property string|null $season_goal
 * @property string $competition_id
 * @property string $game_mode
 * @property \Illuminate\Support\Carbon|null $setup_completed_at
 * @property string $country
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Loan> $activeLoans
 * @property-read int|null $active_loans_count
 * @property-read \App\Models\ScoutReport|null $activeScoutReport
 * @property-read \App\Models\Competition $competition
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompetitionEntry> $competitionEntries
 * @property-read int|null $competition_entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CupTie> $cupTies
 * @property-read int|null $cup_ties_count
 * @property-read \App\Models\GameFinances|null $currentFinances
 * @property-read \App\Models\GameInvestment|null $currentInvestment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameFinances> $finances
 * @property-read int|null $finances_count
 * @property-read string $formatted_season
 * @property-read \App\Models\GameMatch|null $next_match
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameInvestment> $investments
 * @property-read int|null $investments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Loan> $loans
 * @property-read int|null $loans_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameMatch> $matches
 * @property-read int|null $matches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GamePlayer> $players
 * @property-read int|null $players_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoutReport> $scoutReports
 * @property-read int|null $scout_reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GamePlayer> $squad
 * @property-read int|null $squad_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameStanding> $standings
 * @property-read int|null $standings_count
 * @property-read \App\Models\Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameNotification> $unreadNotifications
 * @property-read int|null $unread_notifications_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\GameFactory factory($count = null, $state = [])
 * @method static Builder<static>|Game newModelQuery()
 * @method static Builder<static>|Game newQuery()
 * @method static Builder<static>|Game query()
 * @method static Builder<static>|Game whereCompetitionId($value)
 * @method static Builder<static>|Game whereCountry($value)
 * @method static Builder<static>|Game whereCreatedAt($value)
 * @method static Builder<static>|Game whereCurrentDate($value)
 * @method static Builder<static>|Game whereCurrentMatchday($value)
 * @method static Builder<static>|Game whereDefaultFormation($value)
 * @method static Builder<static>|Game whereDefaultLineup($value)
 * @method static Builder<static>|Game whereDefaultMentality($value)
 * @method static Builder<static>|Game whereGameMode($value)
 * @method static Builder<static>|Game whereId($value)
 * @method static Builder<static>|Game whereNeedsOnboarding($value)
 * @method static Builder<static>|Game wherePlayerName($value)
 * @method static Builder<static>|Game whereSeason($value)
 * @method static Builder<static>|Game whereSeasonGoal($value)
 * @method static Builder<static>|Game whereSetupCompletedAt($value)
 * @method static Builder<static>|Game whereTeamId($value)
 * @method static Builder<static>|Game whereUpdatedAt($value)
 * @method static Builder<static>|Game whereUserId($value)
 * @mixin \Eloquent
 */
	class Game extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property int $season
 * @property int|null $projected_position
 * @property int $projected_tv_revenue
 * @property int $projected_solidarity_funds_revenue
 * @property int $projected_matchday_revenue
 * @property int $projected_commercial_revenue
 * @property int $projected_total_revenue
 * @property int $projected_wages
 * @property int $projected_surplus
 * @property int $actual_tv_revenue
 * @property int $actual_cup_bonus_revenue
 * @property int $actual_matchday_revenue
 * @property int $actual_commercial_revenue
 * @property int $actual_transfer_income
 * @property int $actual_total_revenue
 * @property int $actual_wages
 * @property int $actual_surplus
 * @property int $variance
 * @property int $carried_debt
 * @property int $projected_operating_expenses
 * @property int $projected_taxes
 * @property int $actual_operating_expenses
 * @property int $actual_taxes
 * @property int $projected_subsidy_revenue
 * @property int $actual_subsidy_revenue
 * @property int $actual_solidarity_funds_revenue
 * @property-read \App\Models\Game $game
 * @property-read int $available_surplus
 * @property-read string $formatted_actual_commercial_revenue
 * @property-read string $formatted_actual_cup_bonus_revenue
 * @property-read string $formatted_actual_matchday_revenue
 * @property-read string $formatted_actual_operating_expenses
 * @property-read string $formatted_actual_solidarity_funds_revenue
 * @property-read string $formatted_actual_surplus
 * @property-read string $formatted_actual_total_revenue
 * @property-read string $formatted_actual_transfer_income
 * @property-read string $formatted_actual_tv_revenue
 * @property-read string $formatted_actual_wages
 * @property-read string $formatted_available_surplus
 * @property-read string $formatted_carried_debt
 * @property-read string $formatted_projected_commercial_revenue
 * @property-read string $formatted_projected_matchday_revenue
 * @property-read string $formatted_projected_operating_expenses
 * @property-read string $formatted_projected_solidarity_funds_revenue
 * @property-read string $formatted_projected_subsidy_revenue
 * @property-read string $formatted_projected_surplus
 * @property-read string $formatted_projected_total_revenue
 * @property-read string $formatted_projected_tv_revenue
 * @property-read string $formatted_projected_wages
 * @property-read string $formatted_variance
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualCommercialRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualCupBonusRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualMatchdayRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualOperatingExpenses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualSolidarityFundsRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualSubsidyRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualSurplus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualTotalRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualTransferIncome($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualTvRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualWages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereCarriedDebt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedCommercialRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedMatchdayRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedOperatingExpenses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedPosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedSolidarityFundsRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedSubsidyRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedSurplus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedTotalRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedTvRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedWages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereVariance($value)
 * @mixin \Eloquent
 */
	class GameFinances extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property int $season
 * @property int $available_surplus
 * @property int $youth_academy_amount
 * @property int $youth_academy_tier
 * @property int $medical_amount
 * @property int $medical_tier
 * @property int $scouting_amount
 * @property int $scouting_tier
 * @property int $facilities_amount
 * @property int $facilities_tier
 * @property int $transfer_budget
 * @property-read \App\Models\Game $game
 * @property-read float $facilities_multiplier
 * @property-read string $formatted_available_surplus
 * @property-read string $formatted_facilities_amount
 * @property-read string $formatted_medical_amount
 * @property-read string $formatted_scouting_amount
 * @property-read string $formatted_total_infrastructure
 * @property-read string $formatted_transfer_budget
 * @property-read string $formatted_youth_academy_amount
 * @property-read int $total_infrastructure
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereAvailableSurplus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereFacilitiesAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereFacilitiesTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereMedicalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereMedicalTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereScoutingAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereScoutingTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereTransferBudget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereYouthAcademyAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereYouthAcademyTier($value)
 * @mixin \Eloquent
 */
	class GameInvestment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $competition_id
 * @property int $round_number
 * @property string|null $round_name
 * @property string $home_team_id
 * @property string $away_team_id
 * @property \Illuminate\Support\Carbon $scheduled_date
 * @property int|null $home_score
 * @property int|null $away_score
 * @property bool $played
 * @property string|null $cup_tie_id
 * @property bool $is_extra_time
 * @property int|null $home_score_et
 * @property int|null $away_score_et
 * @property int|null $home_score_penalties
 * @property int|null $away_score_penalties
 * @property array<array-key, mixed>|null $home_lineup
 * @property array<array-key, mixed>|null $away_lineup
 * @property string|null $home_formation
 * @property string|null $away_formation
 * @property string|null $home_mentality
 * @property string|null $away_mentality
 * @property array<array-key, mixed>|null $substitutions
 * @property-read \App\Models\Team $awayTeam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchEvent> $cardEvents
 * @property-read int|null $card_events_count
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\CupTie|null $cupTie
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchEvent> $events
 * @property-read int|null $events_count
 * @property-read \App\Models\Game $game
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchEvent> $goalEvents
 * @property-read int|null $goal_events_count
 * @property-read \App\Models\Team $homeTeam
 * @method static \Database\Factories\GameMatchFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayFormation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayLineup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayMentality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayScoreEt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayScorePenalties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereCupTieId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeFormation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeLineup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeMentality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeScoreEt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeScorePenalties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereIsExtraTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch wherePlayed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereRoundName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereRoundNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereScheduledDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereSubstitutions($value)
 * @mixin \Eloquent
 */
	class GameMatch extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $type
 * @property string $title
 * @property string|null $message
 * @property string|null $icon
 * @property string $priority
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $game_date
 * @property-read \App\Models\Game $game
 * @method static Builder<static>|GameNotification byPriority(string $priority)
 * @method static Builder<static>|GameNotification newModelQuery()
 * @method static Builder<static>|GameNotification newQuery()
 * @method static Builder<static>|GameNotification ofType(string $type)
 * @method static Builder<static>|GameNotification query()
 * @method static Builder<static>|GameNotification read()
 * @method static Builder<static>|GameNotification unread()
 * @method static Builder<static>|GameNotification whereGameDate($value)
 * @method static Builder<static>|GameNotification whereGameId($value)
 * @method static Builder<static>|GameNotification whereIcon($value)
 * @method static Builder<static>|GameNotification whereId($value)
 * @method static Builder<static>|GameNotification whereMessage($value)
 * @method static Builder<static>|GameNotification whereMetadata($value)
 * @method static Builder<static>|GameNotification wherePriority($value)
 * @method static Builder<static>|GameNotification whereReadAt($value)
 * @method static Builder<static>|GameNotification whereTitle($value)
 * @method static Builder<static>|GameNotification whereType($value)
 * @mixin \Eloquent
 */
	class GameNotification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $player_id
 * @property string $team_id
 * @property string $position
 * @property string|null $market_value
 * @property int $market_value_cents
 * @property \Illuminate\Support\Carbon|null $contract_until
 * @property int $fitness
 * @property int $morale
 * @property \Illuminate\Support\Carbon|null $injury_until
 * @property string|null $injury_type
 * @property int|null $suspended_until_matchday
 * @property int $appearances
 * @property int $goals
 * @property int $own_goals
 * @property int $assists
 * @property int $yellow_cards
 * @property int $red_cards
 * @property int|null $game_technical_ability
 * @property int|null $game_physical_ability
 * @property int|null $potential
 * @property int|null $potential_low
 * @property int|null $potential_high
 * @property int $season_appearances
 * @property int $goals_conceded
 * @property int $clean_sheets
 * @property int $annual_wage
 * @property string|null $transfer_status
 * @property \Illuminate\Support\Carbon|null $transfer_listed_at
 * @property int|null $pending_annual_wage
 * @property int $durability
 * @property string|null $retiring_at_season
 * @property int|null $number
 * @property-read \App\Models\Loan|null $activeLoan
 * @property-read int|null $active_offers_count
 * @property-read \App\Models\RenewalNegotiation|null $activeRenewalNegotiation
 * @property-read \App\Models\Game $game
 * @property-read int $age
 * @property-read int $annual_wage_euros
 * @property-read int|null $contract_expiry_year
 * @property-read int $current_physical_ability
 * @property-read int $current_technical_ability
 * @property-read string $development_status
 * @property-read string $formatted_market_value
 * @property-read string|null $formatted_pending_wage
 * @property-read string $formatted_wage
 * @property-read string $name
 * @property-read array|null $nationality
 * @property-read array{name: string, flag: string}|null $nationality_flag
 * @property-read int $overall_score
 * @property-read int $physical_ability
 * @property-read string $position_abbreviation
 * @property-read array{abbreviation: string, name: string}|null $position_display
 * @property-read string $position_group
 * @property-read string $position_name
 * @property-read string $potential_range
 * @property-read int $technical_ability
 * @property-read \App\Models\RenewalNegotiation|null $latestRenewalNegotiation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchEvent> $matchEvents
 * @property-read int|null $match_events_count
 * @property-read \App\Models\Player $player
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PlayerSuspension> $suspensions
 * @property-read int|null $suspensions_count
 * @property-read \App\Models\Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TransferOffer> $transferOffers
 * @property-read int|null $transfer_offers_count
 * @method static \Database\Factories\GamePlayerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereAnnualWage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereAppearances($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereAssists($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereCleanSheets($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereContractUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereDurability($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereFitness($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGamePhysicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGameTechnicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGoals($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGoalsConceded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereInjuryType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereInjuryUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereMarketValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereMarketValueCents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereMorale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereOwnGoals($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePendingAnnualWage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePotential($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePotentialHigh($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePotentialLow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereRedCards($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereRetiringAtSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereSeasonAppearances($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereSuspendedUntilMatchday($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereTransferListedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereTransferStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereYellowCards($value)
 * @mixin \Eloquent
 */
	class GamePlayer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $game_id
 * @property string $competition_id
 * @property string $team_id
 * @property int $position
 * @property int|null $prev_position
 * @property int $played
 * @property int $won
 * @property int $drawn
 * @property int $lost
 * @property int $goals_for
 * @property int $goals_against
 * @property int $points
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\Game $game
 * @property-read int $goal_difference
 * @property-read int $position_change
 * @property-read string $position_change_icon
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereDrawn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereGoalsAgainst($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereGoalsFor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereLost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding wherePlayed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding wherePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding wherePrevPosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereWon($value)
 * @mixin \Eloquent
 */
	class GameStanding extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $code
 * @property string|null $email
 * @property int $max_uses
 * @property int $times_used
 * @property bool $invite_sent
 * @property \Illuminate\Support\Carbon|null $invite_sent_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereInviteSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereInviteSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereMaxUses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereTimesUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class InviteCode extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $game_player_id
 * @property string $parent_team_id
 * @property string $loan_team_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon $return_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read \App\Models\Team $loanTeam
 * @property-read \App\Models\Team $parentTeam
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereLoanTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereParentTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereReturnAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class Loan extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $game_match_id
 * @property string $game_player_id
 * @property string $team_id
 * @property int $minute
 * @property string $event_type
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GameMatch $gameMatch
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read string $display_string
 * @property-read string $player_name
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereGameMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereMinute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereTeamId($value)
 * @mixin \Eloquent
 */
	class MatchEvent extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $transfermarkt_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property array<array-key, mixed>|null $nationality
 * @property string|null $height
 * @property string|null $foot
 * @property int $technical_ability
 * @property int $physical_ability
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GamePlayer> $gamePlayers
 * @property-read int|null $game_players_count
 * @property-read int $age
 * @method static \Database\Factories\PlayerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereFoot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player wherePhysicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereTechnicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereTransfermarktId($value)
 * @mixin \Eloquent
 */
	class Player extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_player_id
 * @property string $competition_id
 * @property int $matches_remaining
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Competition|null $competition
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereMatchesRemaining($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class PlayerSuspension extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $game_player_id
 * @property string $status
 * @property int $round
 * @property int|null $player_demand
 * @property int|null $preferred_years
 * @property int|null $user_offer
 * @property int|null $offered_years
 * @property int|null $counter_offer
 * @property int|null $contract_years
 * @property float|null $disposition
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read string $formatted_counter_offer
 * @property-read string $formatted_player_demand
 * @property-read string $formatted_user_offer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereContractYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereCounterOffer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereDisposition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereOfferedYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation wherePlayerDemand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation wherePreferredYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereRound($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereUserOffer($value)
 * @mixin \Eloquent
 */
	class RenewalNegotiation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $status
 * @property array<array-key, mixed> $filters
 * @property int $weeks_total
 * @property int $weeks_remaining
 * @property array<array-key, mixed>|null $player_ids
 * @property \Illuminate\Support\Carbon $game_date
 * @property-read \App\Models\Game $game
 * @property-read mixed $players
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereGameDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport wherePlayerIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereWeeksRemaining($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereWeeksTotal($value)
 * @mixin \Eloquent
 */
	class ScoutReport extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $season
 * @property array<array-key, mixed> $final_standings
 * @property array<array-key, mixed> $player_season_stats
 * @property array<array-key, mixed> $season_awards
 * @property array<array-key, mixed> $match_results
 * @property string|null $match_events_archive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Game $game
 * @property-read array|null $best_goalkeeper
 * @property-read array|null $champion
 * @property-read array $match_events
 * @property-read array|null $most_assists
 * @property-read array|null $top_scorer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereFinalStandings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereMatchEventsArchive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereMatchResults($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive wherePlayerSeasonStats($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereSeasonAwards($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class SeasonArchive extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $game_id
 * @property string $season
 * @property string $competition_id
 * @property array<array-key, mixed> $results
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\Game $game
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereResults($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class SimulatedSeason extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property int|null $transfermarkt_id
 * @property string $name
 * @property string $country
 * @property string|null $image
 * @property string|null $stadium_name
 * @property int $stadium_seats
 * @property-read \App\Models\ClubProfile|null $clubProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Competition> $competitions
 * @property-read int|null $competitions_count
 * @property-read int $goal_difference
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GamePlayer> $players
 * @property-read int|null $players_count
 * @method static \Database\Factories\TeamFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereStadiumName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereStadiumSeats($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereTransfermarktId($value)
 * @mixin \Eloquent
 */
	class Team extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $game_id
 * @property string $game_player_id
 * @property string $offering_team_id
 * @property string $offer_type
 * @property int $transfer_fee
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon $game_date
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string $direction
 * @property string|null $selling_team_id
 * @property int|null $asking_price
 * @property int|null $offered_wage
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read int $days_until_expiry
 * @property-read string $formatted_asking_price
 * @property-read string $formatted_offered_wage
 * @property-read string $formatted_transfer_fee
 * @property-read string|null $selling_team_name
 * @property-read \App\Models\Team $offeringTeam
 * @property-read \App\Models\Team|null $sellingTeam
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer agreed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereAskingPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereGameDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereOfferType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereOfferedWage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereOfferingTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereResolvedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereSellingTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereTransferFee($value)
 * @mixin \Eloquent
 */
	class TransferOffer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $feedback_requested_at
 * @property bool $is_admin
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game> $games
 * @property-read int|null $games_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFeedbackRequestedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\InviteCode|null $inviteCode
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class WaitlistEntry extends \Eloquent {}
}

