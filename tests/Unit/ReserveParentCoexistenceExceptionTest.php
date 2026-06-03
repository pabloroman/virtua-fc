<?php

namespace Tests\Unit;

use App\Modules\Competition\Exceptions\ReserveParentCoexistenceException;
use PHPUnit\Framework\TestCase;

class ReserveParentCoexistenceExceptionTest extends TestCase
{
    public function test_for_violations_builds_message_and_exposes_tuples(): void
    {
        $violations = [
            ['reserve' => 'r1', 'parent' => 'p1', 'competition' => 'ESP2'],
            ['reserve' => 'r2', 'parent' => 'p2', 'competition' => 'ESP3A'],
        ];

        $e = ReserveParentCoexistenceException::forViolations($violations);

        // Historical prefix preserved so existing log searches keep matching.
        $this->assertStringContainsString('Planner produced a coexistence violation:', $e->getMessage());
        $this->assertStringContainsString('reserve=r1 parent=p1 competition=ESP2', $e->getMessage());
        $this->assertStringContainsString('reserve=r2 parent=p2 competition=ESP3A', $e->getMessage());
        $this->assertSame($violations, $e->violations());
    }

    public function test_is_a_logic_exception_so_existing_catch_sites_still_work(): void
    {
        $e = ReserveParentCoexistenceException::forViolations([
            ['reserve' => 'r', 'parent' => 'p', 'competition' => 'ESP2'],
        ]);

        $this->assertInstanceOf(\LogicException::class, $e);
    }
}
