<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Contract Renewal
    |--------------------------------------------------------------------------
    |
    | Controls what AI clubs do with their players whose contracts expire at
    | season close (ContractExpirationProcessor). Most are auto-renewed for a
    | fresh 3-year deal; a tunable minority instead run their contracts down to
    | free agency, keeping a realistic supply of quality free agents on the
    | market each season.
    |
    */
    'ai_contract_renewal' => [
        // Base chance an expiring AI non-veteran (age <= PlayerAge::PRIME_END)
        // is NOT renewed and becomes a free agent instead of being re-upped.
        'non_veteran_non_renewal_base' => 0.12,

        // Extra non-renewal chance per tier the player sits ABOVE his club's
        // reputation tier. A gem at a small/relegated club is realistically the
        // one let go on a free — and the most attractive signing for the user.
        'non_veteran_above_club_bonus' => 0.10,

        // Hard ceiling on the combined non-renewal chance, so even the biggest
        // tier mismatch still keeps most stars at their club.
        'non_veteran_non_renewal_max' => 0.35,

        // Chance an expiring AI veteran (age > PlayerAge::PRIME_END) is let go
        // to free agency rather than re-signed (was hard-coded at 0.50).
        'veteran_non_renewal' => 0.50,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Pre-Contract Offers
    |--------------------------------------------------------------------------
    |
    | Per-matchday chance (Jan–May) that an AI club tables a free pre-contract
    | for one of the USER's expiring players. Lower values leave the user more
    | room to renew or sell their own expiring stars before they walk for free.
    | Bands are matched high-to-low by market value (cents).
    |
    */
    'ai_pre_contract' => [
        'offer_chance_default' => 0.07,

        'offer_chance_by_value' => [
            5_000_000_000 => 0.20, // €50M+
            2_000_000_000 => 0.15, // €20M+
            1_000_000_000 => 0.12, // €10M+
            500_000_000 => 0.10,   // €5M+
            0 => 0.07,             // < €5M
        ],
    ],

];
