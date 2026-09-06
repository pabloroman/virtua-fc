<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the one thing that must never regress: RefreshDatabase runs
 * migrate:fresh, so if the suite is pointed at the development database it
 * silently destroys every local save.
 *
 * docker-compose.yml exports DB_DATABASE into the container as a real
 * environment variable, and PHPUnit will not override one of those without
 * force="true" — so phpunit.xml's <env> entry looks correct while doing
 * nothing at all. Deliberately does NOT use RefreshDatabase: this test has to
 * be safe to run precisely when the configuration is wrong.
 */
class TestDatabaseIsolationTest extends TestCase
{
    public function test_the_suite_never_runs_against_the_development_database(): void
    {
        $database = DB::connection()->getDatabaseName();

        $this->assertNotSame('virtua_fc', $database, 'The test suite is pointed at the development database');
        $this->assertStringStartsWith('virtua_fc_test', $database);
    }
}
