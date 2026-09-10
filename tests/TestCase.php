<?php

namespace Tests;

use App\Modules\Transfer\Exceptions\LockedPlayerMovedException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var array<int, string> */
    protected $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Invariant violations are report()ed in production so a game never
        // gets stuck on them, but under test they must fail the run: any test
        // that trips one has found a real bug in whatever path moved the
        // player. A test that exercises the failure path on purpose opts out
        // with Exceptions::fake([LockedPlayerMovedException::class]).
        $this->app->make(ExceptionHandler::class)->reportable(
            fn (LockedPlayerMovedException $e) => throw $e,
        );
    }
}
