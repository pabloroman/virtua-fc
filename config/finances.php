<?php

return [
    // Employer social security contributions as % of wage bill (Spain ~30%, we use 25%)
    'taxes_rate' => 0.25,

    // Annual operating expenses by reputation level (in cents)
    // Covers: non-playing staff, admin, travel, insurance, legal, etc.
    'operating_expenses' => [
        'elite'       => 5_000_000_000, // €50M
        'contenders'  => 3_000_000_000, // €30M
        'continental' => 2_000_000_000, // €20M
        'established' => 1_000_000_000, // €10M
        'modest'      =>   700_000_000, // €7M
        'professional' =>   400_000_000, // €4M
        'local'        =>   200_000_000, // €2M
    ],
];
