<?php

return [
    'renovation' => [
        'page_title' => 'Stadium & Facilities',
        'breadcrumb' => 'Club · Mockup',
        'mockup_badge' => 'Mockup',
        'intro' => 'Plan stadium and facility renovations. Start one project per season and choose which area gets your budget.',

        'summary' => [
            'budget' => 'Renovation budget',
            'invested' => 'Total invested',
            'projects' => 'Club areas',
            'in_progress' => 'Active works',
        ],

        'active' => [
            'eyebrow' => 'Under construction',
            'progress' => 'Progress',
            'eta' => ':weeks weeks until opening',
            'cancel' => 'Cancel works',
        ],

        'tier_label' => 'T:num',
        'tier_to' => 'Tier :from → :to',
        'current_effect' => 'Current effect',
        'next_tier' => 'Next tier',
        'upgrade_to' => 'Upgrade to tier :num',
        'what_changes' => "What changes?",
        'from' => 'Before',
        'to' => 'After',
        'cost' => 'Cost',
        'duration' => 'Duration',
        'weeks' => ':num weeks',
        'weeks_left' => ':num wks left',
        'budget_after' => 'Budget after works',
        'start_works' => 'Start works',
        'confirm_hint' => 'Funds are deducted when works begin.',
        'insufficient_budget' => 'Insufficient budget.',
        'max_help' => "You've reached the max tier in this area.",
        'footnote' => 'Only one project can be active at a time. Renovations complete within the season.',

        'badge' => [
            'max' => 'Max',
            'building' => 'Building',
        ],

        'delta' => [
            'seats' => 'seats',
            'matchday' => 'matchday revenue',
            'youth' => 'Higher youth potential',
            'medical' => '-30% → -50% recovery time',
        ],

        'buildings' => [
            'stadium' => [
                'name' => 'Stadium',
                'tagline' => 'Capacity · matchday gate revenue',
                'tier' => [
                    1 => 'Basic capacity, uncovered terraces',
                    2 => '38,000 seats · partial cover',
                    3 => '46,500 seats · full cover + VIP areas',
                    4 => 'Modern stadium · premium boxes and retail',
                ],
            ],
            'facilities' => [
                'name' => 'Stadium facilities',
                'tagline' => 'Matchday revenue multiplier',
                'tier' => [
                    1 => 'Basic upgrades · ×1.00 revenue',
                    2 => 'Modern facilities · ×1.15 revenue',
                    3 => 'Premium experience · ×1.35 revenue',
                    4 => 'World-class venue · ×1.60 revenue',
                ],
            ],
            'youth_academy' => [
                'name' => 'Youth Academy',
                'tagline' => 'Potential of homegrown players',
                'tier' => [
                    1 => 'Basic academy · occasional prospects',
                    2 => 'Good academy · steady output',
                    3 => 'Elite academy · high-potential youngsters',
                    4 => 'World-class · homegrown stars',
                ],
            ],
            'medical' => [
                'name' => 'Medical Centre',
                'tagline' => 'Recovery speed and injury prevention',
                'tier' => [
                    1 => 'Basic care · standard recovery',
                    2 => 'Good facilities · 15% faster',
                    3 => 'Elite staff · 30% faster, fewer injuries',
                    4 => 'World-class · 50% faster, active prevention',
                ],
            ],
            'scouting' => [
                'name' => 'Scouting Network',
                'tagline' => 'Reach and accuracy of scouting',
                'tier' => [
                    1 => 'Basic network · domestic only',
                    2 => 'Expanded network · more results, more precision',
                    3 => 'International reach · fast scouting',
                    4 => 'Global network · maximum speed and accuracy',
                ],
            ],
        ],
    ],
];
