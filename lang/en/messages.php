<?php

return [
    // Transfer messages
    'transfer_complete' => 'Transfer complete! :player has joined your squad.',
    'transfer_agreed' => ':message The transfer will be completed when the :window window opens.',
    'bid_exceeds_budget' => 'The bid exceeds your transfer budget.',
    'player_listed' => ':player listed for sale. Offers may arrive after the next matchday.',
    'player_unlisted' => ':player removed from the transfer list.',
    'offer_rejected' => ':team_de offer rejected.',
    'offer_accepted_sale' => ':player sold :team_a for :fee.',
    'offer_accepted_pre_contract' => 'Deal agreed! :player will sign for :team for :fee when the :window window opens.',

    // Free agent signing
    'free_agent_signed' => ':player has signed for your team as a free agent!',
    'not_free_agent' => 'This player is not a free agent.',
    'free_agent_reputation_too_low' => 'This player has no interest in joining a club of your reputation level.',
    'transfer_window_closed' => 'The transfer window is closed.',
    'wage_budget_exceeded' => 'Signing this player would exceed your wage budget.',

    // Bid/loan submission confirmations
    'bid_already_exists' => 'You already have a pending bid for this player.',
    'loan_request_submitted' => 'Your loan request for :player has been submitted. You will receive a response soon.',

    // Loan messages
    'loan_agreed' => ':message The loan will begin when the :window window opens.',
    'loan_in_complete' => ':message The loan is now active.',
    'already_on_loan' => ':player is already on loan.',
    'loan_search_started' => 'A loan destination search has started for :player. You will be notified when a club is found.',
    'loan_search_active' => ':player already has an active loan search.',
    'loan_search_cancelled' => ':player loan search has been cancelled.',

    // Contract messages
    'renewal_agreed' => ':player has accepted a :years-year extension at :wage/yr (effective from next season).',
    'renewal_failed' => 'Could not process the renewal.',
    'renewal_declined' => 'You have decided not to renew :player. They will leave at the end of the season.',
    'renewal_reconsidered' => 'You have reconsidered :player\'s renewal.',
    'cannot_renew' => 'This player cannot receive a renewal offer.',
    'renewal_invalid_offer' => 'The offer must be greater than zero.',

    // Pre-contract messages
    'pre_contract_accepted' => ':player has accepted your pre-contract offer! They will join your team at the end of the season.',
    'pre_contract_rejected' => ':player has rejected your pre-contract offer. Try improving the wage offer.',
    'pre_contract_not_available' => 'Pre-contract offers are only available between January and May.',
    'player_not_expiring' => 'This player\'s contract is not in its final year.',
    'pre_contract_submitted' => 'Pre-contract offer sent. The player will respond in the coming days.',
    'pre_contract_result_accepted' => ':player has accepted your pre-contract offer!',
    'pre_contract_result_rejected' => ':player has rejected your pre-contract offer.',

    // Scout messages
    'scout_search_started' => 'The scout has started searching.',
    'scout_already_searching' => 'You already have an active search. Cancel it first or wait for results.',
    'scout_search_cancelled' => 'Scout search cancelled.',
    'scout_search_deleted' => 'Search deleted.',
    'scout_search_limit' => 'You have reached the search limit (maximum :max). Delete an old search to start a new one.',

    // Shortlist messages
    'shortlist_added' => ':player added to your shortlist.',
    'shortlist_removed' => ':player removed from your shortlist.',
    'shortlist_full' => 'Your shortlist is full (maximum :max players).',

    // Budget messages
    'budget_saved' => 'Budget allocation saved.',
    'budget_no_projections' => 'No financial projections found.',

    // Season messages
    'budget_exceeds_surplus' => 'Total allocation exceeds available surplus.',
    'budget_minimum_tier' => 'All infrastructure areas must be at least Tier 1.',
    'budget_tier_below_previous' => 'Infrastructure tiers cannot be reduced below the previous season\'s levels.',

    // Infrastructure upgrades
    'infrastructure_upgraded' => ':area upgraded to Tier :tier.',
    'infrastructure_upgrade_invalid_area' => 'Invalid infrastructure area.',
    'infrastructure_upgrade_not_higher' => 'Target tier must be higher than current tier.',
    'infrastructure_upgrade_max_tier' => 'Maximum tier is 4.',
    'infrastructure_upgrade_insufficient_budget' => 'Insufficient transfer budget. Upgrade costs :cost.',

    // Onboarding
    'welcome_to_team' => 'Welcome :team_a! Your season awaits.',

    // Season
    'season_not_complete' => 'Cannot start a new season - the current season has not ended.',

    // Academy
    'academy_player_promoted' => ':player has been promoted to the first team.',
    'academy_player_dismissed' => ':player has been dismissed from the academy.',
    'academy_player_loaned' => ':player has been loaned out.',
    'academy_must_decide_21' => 'Players aged 21+ must be promoted or dismissed.',

    // Player release messages
    'player_released' => ':player has been released. Severance paid: :severance.',
    'release_not_your_player' => 'You can only release players from your own team.',
    'release_on_loan' => 'Cannot release a player who is on loan.',
    'release_has_agreed_transfer' => 'Cannot release a player with an agreed transfer.',
    'release_has_pre_contract' => 'Cannot release a player with a pre-contract agreement.',
    'release_squad_too_small' => 'Cannot release — your squad must have at least :min players.',
    'release_position_minimum' => 'Cannot release — you need at least :min :group.',

    // Squad cap
    'squad_full' => 'Your squad already has the maximum of :max players. Release or sell a player first.',
    'squad_trim_required' => 'Your squad has :count players. You must release at least :excess to meet the :max-player limit.',
    'cannot_loan_free_agent' => 'Cannot loan a free agent. Sign them directly instead.',

    // Pending actions
    'action_required' => 'There are pending actions you must resolve before continuing.',
    'action_required_short' => 'Action Required',

    // Tracking
    'tracking_started' => 'Now tracking :player.',
    'tracking_stopped' => 'Stopped tracking :player.',
    'tracking_slots_full' => 'All tracking slots are in use. Stop tracking another player first.',

    // Tactical presets
    'preset_saved' => 'Tactic saved.',
    'preset_updated' => 'Tactic updated.',
    'preset_deleted' => 'Tactic deleted.',
    'preset_limit_reached' => 'Maximum of 3 saved tactics reached.',

    // Game management
    'game_deleted' => 'Game is being deleted.',
    'game_limit_reached' => 'You have reached the maximum limit of 3 games. Delete one to create another.',
    'career_mode_requires_invite' => 'Career mode requires an invitation. Play the World Cup for free!',
    'tournament_mode_requires_access' => 'Tournament mode requires access. Contact an admin to get started.',

    // Budget loan messages
    'budget_loan_approved' => 'Loan of :amount approved and added to your transfer budget.',
    'loan_not_available' => 'A budget loan is not available right now.',
    'loan_below_minimum' => 'The loan amount is below the minimum.',
    'loan_exceeds_maximum' => 'The loan amount exceeds the maximum allowed.',
];
