<?php

return [
    // Page chrome
    'planner' => 'Planner',
    'title' => 'Squad Planner',
    'subtitle_next_season' => 'Projected roster for season :year. See who stays, who leaves, and who arrives.',
    'subtitle_current_season' => 'Current squad for season :year. Switch to Next Season to see projected changes.',
    'next_season_label' => 'Next season (:year)',
    'toggle_current_season' => 'Current (:year)',
    'toggle_next_season' => 'Next (:year)',

    // Sections
    'section_staying' => 'Staying',
    'section_outgoing' => 'Leaving at season end',
    'section_incoming' => 'Joining at season end',
    'section_staying_count' => ':count player|:count players',

    // Position group labels
    'goalkeepers' => 'Goalkeepers',
    'defenders' => 'Defenders',
    'midfielders' => 'Midfielders',
    'forwards' => 'Forwards',

    // Player row chrome
    'age_next' => 'Age :age next season',
    'contract_until' => 'Until :year',
    'no_contract' => 'No contract',

    // Reasons — STAYING
    'reason_owned' => 'On the books',
    'reason_renewed' => 'Renewal agreed',
    'reason_returning_from_loan' => 'Returning from loan',
    'reason_still_on_loan' => 'On loan until :date',

    // Reasons — OUTGOING
    'reason_retiring' => 'Retiring',
    'reason_transfer_agreed' => 'Transfer agreed',
    'reason_pre_contract_departing' => 'Pre-contract elsewhere',
    'reason_contract_expiring_unrenewed' => 'Contract expiring, no renewal',
    'reason_loan_ending' => 'Loan ending',

    // Reasons — INCOMING
    'reason_pre_contract_joining' => 'Pre-contract signed',

    // Empty states
    'empty_staying' => 'No projected staying players.',
    'empty_outgoing' => 'No projected departures.',
    'empty_incoming' => 'No projected arrivals.',

    // Ability/potential row labels
    'current_ability' => 'Current',
    'projected_ability' => 'Projected next season',
    'potential' => 'Potential',

    // Squad role badges
    'role_wonderkid' => 'Wonderkid',
    'role_key_player' => 'Key player',
    'role_first_team' => 'First team',
    'role_rotation' => 'Rotation',
    'role_prospect' => 'Prospect',
    'role_reserves' => 'Reserves',
    'role_departing' => 'Departing',

    // Transfer Recommendations
    'transfer_recommendations' => 'Transfer Recommendations',
    'advisory_empty' => 'No squad-level recommendations. The projected roster looks balanced.',
    'advisory_depth_gap' => 'Reinforce :position — :count short of the chosen formation.',
    'advisory_age_gap' => ':position pipeline thin — no players :age or younger projected next season.',
    'advisory_wage_cliff' => "Renew :name — contract ends :year and no agreement is in place.",
    'advisory_development' => 'Give minutes to :names to maximize their development.',
    'advisory_key_departure' => 'Replace :name (:position) — projected departure leaves a hole.',

    // Position group labels used inside advisories (singular & lowercase for sentence flow)
    'group_goalkeeper' => 'goalkeeper',
    'group_defender' => 'defence',
    'group_midfielder' => 'midfield',
    'group_forward' => 'attack',

    // Tactics Hub
    'tactics_hub' => 'Tactics Hub',
    'target_formation' => 'Target formation',
    'projected_xi_fit' => 'Projected XI fit',
    'fit_summary' => ':have / :need',

    // Action chips
    'action_play_often' => 'Play often',
    'action_develop' => 'Develop',
    'action_keep' => 'Keep',
    'action_renew' => 'Renew',
    'action_list' => 'List',
    'action_replace' => 'Replace',

    // Auto-generated blurbs
    'blurb_wonderkid' => 'Huge potential — already useful and getting better.',
    'blurb_key_player' => 'Cornerstone player. Build the team around them.',
    'blurb_first_team' => 'Reliable starter at :position.',
    'blurb_prospect' => 'Promising youth, still developing.',
    'blurb_rotation' => 'Squad option, close to the starting XI.',
    'blurb_reserves' => 'Deep in the depth chart for :position.',
    'blurb_departing' => 'Leaving the squad at season end.',
];
