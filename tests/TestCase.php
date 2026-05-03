<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Both planes are transacted so RefreshDatabase rolls back writes on
     * identity / leaderboard / reference tables (control plane) as well as
     * per-game tables (tenant plane). Without this, factories that hit
     * control-plane models would leak rows between tests because their
     * writes go through a separate PDO connection that the default
     * RefreshDatabase transaction doesn't wrap. See CLAUDE.md → "Control
     * plane / tenant plane".
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = ['pgsql', 'pgsql_control'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Mirror the tenant database name onto the control connection so both
     * connections target the same physical database during tests.
     *
     * Single-process testing relies on this because RefreshDatabase only
     * runs `migrate:fresh` on the default (pgsql) connection — pointing
     * pgsql_control at the same DB makes those tables visible to both
     * connections.
     *
     * Parallel testing relies on this even more: ParallelTesting suffixes
     * `pgsql.database` per process (e.g. `virtua_fc_test_test_2`) but
     * doesn't touch `pgsql_control.database`, which would otherwise leave
     * every process pointing at the un-migrated env-default database and
     * sharing identity/reference state across processes.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        Config::set(
            'database.connections.pgsql_control.database',
            Config::get('database.connections.pgsql.database'),
        );
        DB::purge('pgsql_control');
    }
}
