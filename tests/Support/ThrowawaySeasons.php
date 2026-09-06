<?php

namespace Tests\Support;

/**
 * The season folders the console tests write into.
 *
 * Every one of these commands reads and writes the real `base_path('data')`
 * tree, and each test deletes its own folder in `tearDown()`. Under
 * `php artisan test --parallel` they run at the same time, so two tests
 * sharing a year means one deletes the other's fixtures mid-run — a failure
 * that only shows up in parallel, names an innocent test, and passes on a
 * re-run.
 *
 * That happened: FixSeasonClashesCommandTest was added on 2097, which
 * NormalizeSeasonCommandTest already held. Each file documented its own year
 * in a comment, which is not somewhere you look before picking one. They live
 * here instead, so the whole allocation is in front of you the moment you add
 * to it.
 *
 * Pick from 2092 downward when you need another, and keep well clear of any
 * season the app might really seed.
 */
final class ThrowawaySeasons
{
    public const FIX_CLASHES = '2093';

    public const DIFF_FROM = '2094';

    public const DIFF_TO = '2095';

    public const VALIDATE = '2096';

    public const NORMALIZE = '2097';

    public const SCAFFOLD_FROM = '2098';

    public const SCAFFOLD_TO = '2099';
}
