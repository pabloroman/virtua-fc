<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coaching Module — Half-time Advisor
    |--------------------------------------------------------------------------
    |
    | Strings used by the coaching staff layer's half-time tactical advisor.
    | The advisor inspects first-half state and emits 1-3 tips, each shown
    | inside the live-match half-time pause block with optional one-click
    | apply via the existing tactical-actions endpoint.
    |
    */

    // Confidence labels attached to each tip.
    'confidence_high' => 'High',
    'confidence_medium' => 'Medium',
    'confidence_low' => 'Low',

    // Half-time advisor panel header.
    'advisor_title' => 'Coach\'s read',
    'advisor_apply' => 'Apply',
    'advisor_dismiss' => 'Dismiss',
    'advisor_all_dismissed' => 'All tips dismissed.',

    // Result-driven tips (responding to the scoreline at HT).
    'tip_chasing_headline' => 'We\'re chasing this — push everything forward',
    'tip_chasing_rationale' => 'Two goals down at the break. Go attacking, press high, and squeeze the field.',
    'tip_trailing_one_headline' => 'One down — time to take more risks',
    'tip_trailing_one_rationale' => 'A measured push: more bodies forward, but keep our shape.',
    'tip_protecting_lead_headline' => 'Protect the cushion',
    'tip_protecting_lead_rationale' => 'Two-goal lead. Drop the line, ease off the press, and manage the second half.',
    'tip_one_goal_lead_headline' => 'Don\'t over-commit',
    'tip_one_goal_lead_rationale' => 'A one-goal lead is fragile. Drop to balanced and keep our shape.',

    // Matchup tips (responding to the opponent's setup).
    'tip_release_press_headline' => 'They\'re pressing — release the pressure',
    'tip_release_press_rationale' => 'Drop the line and play more direct to bypass their press.',
    'tip_break_low_block_headline' => 'They\'re sitting deep — keep the ball',
    'tip_break_low_block_rationale' => 'Switch to possession to drag them out of shape.',
    'tip_counter_high_line_headline' => 'Their line is high — hit them on the break',
    'tip_counter_high_line_rationale' => 'Switch to counter-attack to exploit the space behind their defenders.',

    // Discipline tip (advisory only).
    'tip_card_risk_headline' => ':count yellows already — careful with challenges',
    'tip_card_risk_rationale' => 'Consider subbing the booked players if they\'re leading the count.',

    // Fallback tip when nothing notable surfaced.
    'tip_balanced_headline' => 'No big issues — stay the course',
    'tip_balanced_rationale' => 'Setup is working. Trust the plan and re-evaluate if the second half changes.',
];
