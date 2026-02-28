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
    'transfer_window_closed' => 'The transfer window is closed.',
    'wage_budget_exceeded' => 'Signing this player would exceed your wage budget.',

    // Bid/loan submission confirmations
    'bid_submitted' => 'Your bid for :player has been submitted. You will receive a response soon.',
    'bid_already_exists' => 'You already have a pending bid for this player.',
    'loan_request_submitted' => 'Your loan request for :player has been submitted. You will receive a response soon.',

    // Counter offer
    'counter_offer_accepted' => 'Counter offer accepted! :player will join when the :window window opens.',
    'counter_offer_accepted_immediate' => 'Transfer complete! :player has joined your squad.',
    'counter_offer_expired' => 'This offer is no longer available.',

    // Loan messages
    'loan_agreed' => ':message The loan will begin when the :window window opens.',
    'loan_in_complete' => ':message The loan is now active.',
    'already_on_loan' => ':player is already on loan.',
    'loan_search_started' => 'A loan destination search has started for :player. You will be notified when a club is found.',
    'loan_search_active' => ':player already has an active loan search.',

    // Contract messages
    'renewal_agreed' => ':player has accepted a :years-year extension at :wage/yr (effective from next season).',
    'renewal_failed' => 'Could not process the renewal.',
    'renewal_declined' => 'You have decided not to renew :player. They will leave at the end of the season.',
    'renewal_reconsidered' => 'You have reconsidered :player\'s renewal.',
    'cannot_renew' => 'This player cannot receive a renewal offer.',
    'renewal_offer_submitted' => 'Renewal offer sent to :player for :wage/yr. Response on the next matchday.',
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

    // Shortlist messages
    'shortlist_added' => ':player added to your shortlist.',
    'shortlist_removed' => ':player removed from your shortlist.',

    // Budget messages
    'budget_saved' => 'Budget allocation saved.',
    'budget_no_projections' => 'No financial projections found.',

    // Season messages
    'budget_exceeds_surplus' => 'Total allocation exceeds available surplus.',
    'budget_minimum_tier' => 'All infrastructure areas must be at least Tier 1.',

    // Onboarding
    'welcome_to_team' => 'Welcome :team_a! Your season awaits.',

    // Season
    'season_not_complete' => 'Cannot start a new season - the current season has not ended.',

    // Academy
    'academy_player_promoted' => ':player has been promoted to the first team.',
    'academy_evaluation_required' => 'You must evaluate academy players before continuing.',
    'academy_evaluation_complete' => 'Academy evaluation complete.',
    'academy_player_dismissed' => ':player has been dismissed from the academy.',
    'academy_player_loaned' => ':player has been loaned out.',
    'academy_over_capacity' => 'The academy is over capacity. You must free :excess place(s).',
    'academy_must_decide_21' => 'Players aged 21+ must be promoted or dismissed.',

    // Pending actions
    'action_required' => 'There are pending actions you must resolve before continuing.',
    'action_required_short' => 'Action Required',

    // Game management
    'game_deleted' => 'Game deleted successfully.',
    'game_limit_reached' => 'You have reached the maximum limit of 3 games. Delete one to create another.',
];
