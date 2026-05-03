<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Only the default (pgsql) connection is listed because the testing
     * environment aliases pgsql_control to the same Connection instance —
     * see {@see \App\Providers\AppServiceProvider::boot()}. Both names share
     * a single PDO handle and a single transaction, so RefreshDatabase only
     * needs to wrap one. Listing both would open a second (savepoint)
     * transaction on the same PDO and complicate teardown for no benefit.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
