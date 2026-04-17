<?php

return [
    // =========================================================================
    // Broadcast shout (prefixed to every summary)
    // =========================================================================
    'shout' => [
        'FULL TIME!!',
        'IT\'S ALL OVER!!',
        'THAT\'S THE FINAL WHISTLE!!',
        'FULL TIME :en_venue!!',
        'IT\'S OVER!!',
        'THE REFEREE BLOWS THE WHISTLE!!',
        'AND THAT IS THAT!!',
        'THERE IT IS!!',
    ],

    // =========================================================================
    // Opening sentences — League
    // =========================================================================
    'opening_home_win' => [
        ':home win :en_venue against :away (:score).',
        'Victory for :home :en_venue against :away (:score).',
        ':home prevail :en_venue against :away (:score).',
        ':home fans celebrate a victory against :away (:score).',
        'Victory for :home against :away (:score).',
    ],
    'opening_away_win' => [
        ':away win :en_venue and take the match (:score).',
        'Away victory for :away at :home\'s ground (:score).',
        ':away do the business :en_venue against :home (:score).',
    ],
    'opening_blowout' => [
        ':winner demolish :loser :en_venue (:score) with a great performance.',
        'Emphatic win for :winner :en_venue against :loser (:score), a superb display.',
        ':winner thrash :loser :en_venue (:score).',
        ':winner demolish :loser (:score), who never had a chance to take anything from the match.',
        ':winner fans celebrate the rout against :loser (:score).',
    ],
    'opening_draw' => [
        ':goals_each-all draw :en_venue between :home and :away.',
        'Full time :en_venue! :home can\'t find a way past :away.',
        'Points shared :en_venue between :home and :away (:score).',
        ':goals_each-all draw between :home and :away, where neither side could impose themselves on the other.',
        ':home and :away share the points and goals (:score).',
    ],
    'opening_goalless' => [
        'No goals :en_venue between :home and :away. A scoreless draw that leaves neither side happy.',
        'Goalless draw :en_venue. Neither :home nor :away manage to find the net in a match that had everything but goals.',
        'Stalemate :en_venue. :home and :away share a point in a dull, goalless affair.',
        'No goals between :home and :away, in a match that won\'t be remembered for its excitement.',
        'Goalless draw: neither :home nor :away manage to find the net.',
    ],
    'opening_narrow_win' => [
        ':winner edge it :en_venue against :loser (:score).',
        'Tight win for :winner against :loser :en_venue (:score).',
        ':winner grind out a win :en_venue against :loser (:score).',
        ':winner edge it against :loser (:score).',
        'Tight win for :winner against :loser (:score).',
    ],

    // =========================================================================
    // Opening sentences — Extra time & penalties (knockout)
    // =========================================================================
    'opening_extra_time' => [
        ':winner win in extra time :en_venue (:score).',
        'It took extra time, but :winner prevail :en_venue (:score).',
        ':winner win in extra time (:score).',
        'It took extra time, but :winner prevail (:score).',
    ],
    'opening_penalties' => [
        ':winner go through on penalties (:pen_score) after drawing :score_regular.',
        'Penalties decide it :en_venue. :winner go through (:pen_score).',
    ],

    // =========================================================================
    // Opening sentences — Cup-specific
    // =========================================================================
    'opening_cup_win' => [
        ':winner advance in the :competition after beating :loser :en_venue (:score).',
        ':winner go through :en_venue against :loser (:score).',
        ':loser are knocked out :en_venue. :winner progress (:score).',
        ':winner advance in the :competition after beating :loser (:score).',
        ':loser are knocked out. :winner progress (:score).',
    ],
    'opening_cup_draw' => [
        'Draw :en_venue between :home and :away (:score) in the :competition.',
        ':home and :away share the spoils (:score) :en_venue in the :competition.',
        'Draw between :home and :away (:score) in the :competition.',
    ],

    // =========================================================================
    // Opening sentences — High stakes (semifinals, finals)
    // =========================================================================
    'opening_high_stakes_win' => [
        ':winner triumph :en_venue and advance in the :competition! (:score)',
        'Huge win for :winner against :loser :en_venue! (:score)',
        ':winner do it! Victory :en_venue against :loser (:score).',
        ':winner triumph and advance in the :competition! (:score)',
        'Huge win for :winner against :loser! (:score)',
    ],
    'opening_high_stakes_champion' => [
        ':winner are :competition champions! Final won :en_venue against :loser (:score).',
        ':winner lift the :competition trophy after beating :loser in the final (:score)!',
        'The :competition belongs to :winner! Final settled :en_venue against :loser (:score).',
    ],

    // =========================================================================
    // Goal narrative
    // =========================================================================
    'goals_one_team' => [
        ':scorers were the scorers for :team.',
        ':team\'s goals came from :scorers.',
        'The goals for :team were scored by :scorers.',
    ],
    'goals_one_team_single_scorer' => [
        ':scorer scored the only goal for :team.',
        ':team\'s only goal came from :scorer.',
        'The goal for :team was scored by :scorer.',
    ],
    'goals_team_fragment_single' => [
        ':scorer scored for :team',
        ':scorer got :team\'s goal',
        ':team\'s goal came from :scorer',
    ],
    'goals_team_fragment_multi' => [
        ':scorers scored for :team',
        ':team\'s goals came from :scorers',
        ':scorers got on the scoresheet for :team',
    ],
    'goals_two_teams_join' => [
        ':a and :b.',
    ],
    'scorer_join_and' => 'and',

    // =========================================================================
    // Key moments
    // =========================================================================
    'comeback' => [
        ':winner came from behind to win the match.',
        'A comeback from :winner, who responded after falling behind.',
        ':winner turned the game around to claim victory.',
    ],
    'red_card_single' => [
        'The sending off of :player (:minute\') shaped the outcome for :el_team.',
        'The match changed with the red card to :player (:team) on :minute minutes.',
    ],
    'red_cards_multiple' => [
        'The sending offs for :team shaped the outcome.',
        ':team were reduced after :count red cards.',
    ],
    'dominant_first_half' => [
        'All the goals were concentrated in the first half of the match.',
        'An intense first half produced all the goals of the match.',
    ],
    'dominant_second_half' => [
        'The excitement was concentrated in the second half, where the goals came.',
        'The match opened up in the second half, with plenty of chances for both sides.',
    ],

    // =========================================================================
    // Form / streak (league only)
    // =========================================================================
    'form_losing_streak' => [
        ':team are in freefall after :count defeats in a row.',
        ':team still can\'t find a win after :count consecutive losses.',
        'Crisis for :team, who have now lost :count in a row.',
    ],
    'form_winning_streak' => [
        ':team are unstoppable with :count wins in a row.',
        'Superb run for :team, who make it :count consecutive victories.',
        ':team keep winning, :count in a row now.',
    ],
    'form_winless' => [
        ':team remain winless, :count matches without a victory now.',
        ':team can\'t turn things around and now sit on :count games without a win.',
    ],

    // =========================================================================
    // Color commentary (emotion, drama, opinion)
    // =========================================================================
    'last_minute_winner' => [
        ':player scored in the :minute\' to snatch the win for :el_team at the death!',
        ':player (:team) popped up in the :minute\' to steal all three points when all seemed lost!',
        'Pandemonium in stoppage time: :player won it for :el_team in the :minute\'.',
    ],
    'last_minute_equalizer' => [
        ':player (:team) rescued a point in the :minute\' when nobody saw it coming!',
        ':player (:team) levelled it in the :minute\'. A point snatched from the jaws of defeat.',
        'Drama at the death: :player equalised in the :minute\' to deny :el_team the win.',
    ],
    'hat_trick' => [
        'Hat-trick for :player! A stunning display with :goals goals.',
        'A masterclass from :player (:team), who went home with :goals goals.',
        ':player ran the show with :goals goals. Unstoppable.',
    ],
    'upset' => [
        'What an upset! :winner pulled off the shock result against :loser.',
        'Nobody saw this coming. :winner stun :loser in a night to remember.',
        ':winner ripped up the script and took the match.',
    ],
    'expected_win' => [
        ':winner didn\'t give :loser a chance and showed their superiority throughout.',
        'No story to tell. :winner made their superiority count against :loser.',
        'No surprises: :winner beat :loser without too much trouble.',
    ],
    'high_scoring' => [
        'The forwards were on song in a match that saw :total goals.',
        'Pure entertainment for the fans with :total goals. That\'s what football is all about!',
        'An incredible match where a total of :total goals hit the scoreboard.',
    ],
    'few_chances' => [
        'A dull match :en_venue with little football and few chances on goal.',
        'Little football and even less danger. The fans found this one long.',
        'A grey match with barely any goalscoring chances.',
    ],

    // =========================================================================
    // Annotations
    // =========================================================================
    'penalty_goal_note' => 'pen.',
    'own_goal_note' => 'o.g.',

    // =========================================================================
    // MVP closing
    // =========================================================================
    'mvp_closing' => [
        ':player (:team) named MVP of the match.',
        ':player (:team) takes home the MVP award.',
        ':player (:team) voted the best player on the pitch.',
        'MVP: :player (:team).',
    ],
];
