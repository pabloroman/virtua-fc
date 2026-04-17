<?php

return [
    // =========================================================================
    // Broadcast shout (prefixed to every summary)
    // =========================================================================
    'shout' => [
        '¡¡FINAL DEL PARTIDO!!',
        '¡¡SE ACABÓ!!',
        '¡¡NO HAY TIEMPO PARA MÁS!!',
        '¡¡FINAL :en_venue!!',
        '¡¡SE TERMINÓ!!',
        '¡¡PITIDO FINAL!!',
        '¡¡FIN DEL PARTIDO!!',
        '¡¡YA NO HAY MÁS!!',
    ],

    // =========================================================================
    // Opening sentences — League
    // =========================================================================
    'opening_home_win' => [
        ':el_home gana :en_venue frente :al_away (:score).',
        'Victoria :del_home :en_venue frente :al_away (:score).',
        ':el_home se impone :al_away :en_venue (:score).',
        'Los aficionados :del_home celebran la victoria contra :el_away (:score)',
        'Victoria :del_home frente :al_away (:score).',
    ],
    'opening_away_win' => [
        ':el_away gana :en_venue y se lleva el partido (:score).',
        'Victoria :del_away en casa :del_home (:score).',
        ':el_away se lleva el gato al agua :en_venue ante :el_home (:score).',
    ],
    'opening_blowout' => [
        ':el_winner arrolla :al_loser :en_venue (:score) haciendo un gran partido.',
        'Goleada :del_winner :en_venue frente :al_loser (:score), que firma una gran actuación',
        'Paliza :del_winner :al_loser :en_venue (:score).',
        ':el_winner arrolla :al_loser (:score), que nunca tuvo opciones de llevarse el partido',
        'Los aficionados :del_winner celebran la goleada frente :al_loser (:score).',
    ],
    'opening_draw' => [
        'Empate a :goals_each :en_venue entre :el_home y :el_away.',
        '¡Final :en_venue! :el_home no puede pasar del empate contra :el_away.',
        'Reparto de puntos :en_venue entre :el_home y :el_away (:score).',
        'Empate a :goals_each entre :el_home y :el_away donde ninguno de los equipos supo imponerse al otro',
        ':el_home y :el_away se reparten los puntos y los goles (:score).',
    ],
    'opening_goalless' => [
        'Sin goles :en_venue entre :el_home y :el_away. Un empate a cero que no deja contento a ninguno de los dos equipos.',
        'Empate sin goles :en_venue. Ni :el_home ni :el_away logran marcar en un partido en que hubo de todo, menos goles.',
        'A cero :en_venue. :el_home y :el_away se reparten un punto en un partido soso y sin goles.',
        'Sin goles entre :el_home y :el_away, en un partido que no será recordado por su emoción',
        'Empate sin goles: ni :el_home ni :el_away logran marcar.',
    ],
    'opening_narrow_win' => [
        ':el_winner se lleva la victoria por la mínima :en_venue (:score).',
        'Triunfo ajustado :del_winner frente :al_loser :en_venue (:score).',
        ':el_winner sufre pero gana :en_venue frente :al_loser (:score).',
        ':el_winner se lleva la victoria por la mínima frente :al_loser (:score).',
        'Triunfo ajustado :del_winner ante :el_loser (:score).',
    ],

    // =========================================================================
    // Opening sentences — Extra time & penalties (knockout)
    // =========================================================================
    'opening_extra_time' => [
        ':el_winner se impone en la prórroga :en_venue (:score).',
        'Necesitó la prórroga, pero :el_winner se lleva la victoria :en_venue (:score).',
        ':el_winner se impone en la prórroga (:score).',
        'Tras la prórroga, :el_winner se lleva la victoria (:score).',
    ],
    'opening_penalties' => [
        ':el_winner se clasifica en la tanda de penaltis (:pen_score) tras empatar :score_regular.',
        'Los penaltis deciden :en_venue. :el_winner se clasifica (:pen_score).',
    ],

    // =========================================================================
    // Opening sentences — Cup-specific
    // =========================================================================
    'opening_cup_win' => [
        ':el_winner avanza en la :competition tras imponerse :al_loser :en_venue (:score).',
        ':el_winner se clasifica :en_venue frente :al_loser (:score).',
        ':el_loser queda eliminado :en_venue. :el_winner pasa de ronda (:score).',
        ':el_winner avanza en la :competition tras imponerse :al_loser (:score).',
        ':el_loser queda eliminado. :el_winner pasa de ronda (:score).',
    ],
    'opening_cup_draw' => [
        'Empate :en_venue entre :el_home y :el_away (:score) en la :competition.',
        ':el_home y :el_away empatan (:score) :en_venue por la :competition.',
        'Empate entre :el_home y :el_away (:score) en la :competition.',
    ],

    // =========================================================================
    // Opening sentences — High stakes (semifinals, finals)
    // =========================================================================
    'opening_high_stakes_win' => [
        '¡:el_winner se impone :en_venue y avanza en la :competition! (:score)',
        '¡Enorme victoria :del_winner frente :al_loser :en_venue! (:score)',
        '¡:el_winner lo consigue! Victoria :en_venue frente :al_loser (:score).',
        '¡:el_winner se impone y avanza en la :competition! (:score)',
        '¡Enorme victoria :del_winner frente :al_loser! (:score)',
    ],
    'opening_high_stakes_champion' => [
        '¡:el_winner es campeón de la :competition! Victoria en la final :en_venue frente :al_loser (:score).',
        '¡:el_winner levanta el título de la :competition tras imponerse en la final :al_loser (:score)!',
        '¡La :competition es :del_winner! Final resuelta :en_venue frente :al_loser (:score).',
    ],

    // =========================================================================
    // Goal narrative
    // =========================================================================
    'goals_one_team' => [
        ':scorers fueron los goleadores :del_team.',
        'Los goles :del_team los firmaron :scorers.',
        ':el_team contó con los tantos de :scorers.',
    ],
    'goals_one_team_single_scorer' => [
        ':scorer marcó el único gol para :el_team.',
        ':scorer firmó el único tanto :del_team.',
        'El gol :del_team lo firmó :scorer.',
    ],
    'goals_team_fragment_single' => [
        ':scorer marcó para :el_team',
        ':scorer firmó el tanto :del_team',
        'el gol :del_team fue obra de :scorer',
    ],
    'goals_team_fragment_multi' => [
        ':scorers hicieron los goles :del_team',
        ':scorers marcaron para :el_team',
        'los tantos :del_team los firmaron :scorers',
    ],
    'goals_two_teams_join' => [
        ':a y :b.',
    ],
    'scorer_join_and' => 'y',

    // =========================================================================
    // Key moments
    // =========================================================================
    'comeback' => [
        ':el_winner remontó el partido tras ir por debajo en el marcador.',
        'Remontada :del_winner, que supo reponerse tras encajar primero.',
        ':el_winner dio la vuelta al marcador para llevarse la victoria.',
    ],
    'red_card_single' => [
        'La expulsión de :player (:minute\') marcó el devenir del encuentro para :el_team.',
        'El partido cambió con la roja a :player (:team) en el minuto :minute.',
    ],
    'red_cards_multiple' => [
        'Las expulsiones en :el_team condicionaron el resultado.',
        ':el_team se quedó con inferioridad numérica tras :count expulsiones.',
    ],
    'dominant_first_half' => [
        'Los goles se concentraron en la primera mitad del partido.',
        'Primera parte intensa con todos los goles del encuentro.',
    ],
    'dominant_second_half' => [
        'La emoción se concentró en la segunda mitad, donde llegaron los goles.',
        'El partido se abrió en la segunda parte, con muchas oportunidades para ambos equipos.',
    ],

    // =========================================================================
    // Form / streak (league only)
    // =========================================================================
    'form_losing_streak' => [
        ':el_team se hunde tras encadenar :count derrotas consecutivas.',
        ':el_team sigue sin conocer la victoria tras :count derrotas seguidas.',
        'Crisis en :el_team, que suma :count derrotas consecutivas.',
    ],
    'form_winning_streak' => [
        ':el_team sigue imparable y encadena :count victorias consecutivas.',
        'Racha espectacular :del_team, que suma :count triunfos seguidos.',
        ':el_team no para de ganar, ya van :count victorias seguidas.',
    ],
    'form_winless' => [
        ':el_team sigue sin ganar, ya van :count partidos sin victoria.',
        ':el_team no levanta cabeza y acumula :count encuentros sin ganar.',
    ],

    // =========================================================================
    // Color commentary (emotion, drama, opinion)
    // =========================================================================
    'last_minute_winner' => [
        '¡Gol de :player en el :minute\' para darle la victoria a :el_team in extremis!',
        '¡:player (:team) apareció en el :minute\' para sentenciar cuando todo parecía perdido!',
        'Locura en el descuento: :player firmó la victoria para :el_team en el :minute\'.',
    ],
    'last_minute_equalizer' => [
        '¡Gol de :player (:team) en el :minute\' para rescatar un punto cuando nadie lo esperaba!',
        ':player (:team) igualó la contienda en el :minute\'. El empate se cocinó sobre la bocina.',
        'Agónico empate: :player marcó en el :minute\' para evitar la derrota de :el_team.',
    ],
    'hat_trick' => [
        '¡Hat-trick de :player! Exhibición con :goals goles.',
        'Recital de :player (:team), que se fue a casa con :goals goles.',
        '¡:player se llevó el balón a casa! :goals goles para enmarcar.',
    ],
    'upset' => [
        '¡Batacazo :del_loser! :el_winner dio la sorpresa y se lleva el partido.',
        ':el_winner da la sorpresa y tumba :al_loser con una gran actuación colectiva.',
        ':el_winner puso patas arriba los pronósticos y gana :en_venue.',
    ],
    'expected_win' => [
        ':el_winner no dio opción y demostró su superioridad en todo momento.',
        ':el_winner hizo valer su superioridad ante :el_loser en un partido que no tuvo historia.',
        ':el_winner derrotó :al_loser con facilidad :en_venue.',
    ],
    'high_scoring' => [
        'Los delanteros estuvieron acertados en un partido donde vimos :total goles.',
        'Espectáculo para el aficionado con :total goles. ¡Esto es fútbol, papá!',
        'Impresionante partido donde un total de :total goles subieron al marcador',
    ],
    'few_chances' => [
        'Un partido aburrido :en_venue donde se vió poco juego y ocasiones de gol.',
        'Poco fútbol y menos peligro. A los aficionados se les hizo largo.',
        'Partido gris en el que apenas hubo ocasiones de gol.',
    ],

    // =========================================================================
    // Annotations
    // =========================================================================
    'penalty_goal_note' => 'de penalti',
    'own_goal_note' => 'en propia puerta',

    // =========================================================================
    // MVP closing
    // =========================================================================
    'mvp_closing' => [
        ':player (:team), nombrado MVP del partido.',
        'Además, :player (:team) se lleva el MVP del encuentro.',
        ':player (:team), elegido como el mejor del partido.',
        'MVP del partido: :player (:team).',
    ],
];
