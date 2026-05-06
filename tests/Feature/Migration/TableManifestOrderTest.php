<?php

namespace Tests\Feature\Migration;

use App\Modules\Migration\TableManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Structural test for TENANT_TABLES_IN_INSERT_ORDER.
 *
 * For every real foreign-key constraint between two tables in the manifest,
 * the parent table must come at or before the child table — otherwise the
 * importer will trip the FK on insert. This is independent of whether any
 * specific factory exercises the relationship, so it protects against
 * future migrations adding a new cross-table FK without bumping the
 * manifest order.
 */
class TableManifestOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_order_is_consistent_with_every_fk_between_listed_tables(): void
    {
        $manifest = TableManifest::TENANT_TABLES_IN_INSERT_ORDER;
        $position = array_flip($manifest);

        // Pull every FK between tables in the manifest from
        // information_schema. We only care about FKs whose child AND parent
        // are both listed — FKs to teams/users/competitions are control-
        // plane tables we never touch in the per-game pass.
        $fks = DB::select(
            <<<'SQL'
            SELECT
                tc.table_name      AS child_table,
                kcu.column_name    AS child_column,
                ccu.table_name     AS parent_table,
                ccu.column_name    AS parent_column
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema  = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
                ON tc.constraint_name = ccu.constraint_name
                AND tc.table_schema  = ccu.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema    = current_schema()
              AND tc.table_name      = ANY(?)
              AND ccu.table_name     = ANY(?)
            SQL,
            [
                '{'.implode(',', $manifest).'}',
                '{'.implode(',', $manifest).'}',
            ],
        );

        $this->assertNotEmpty($fks, 'Expected to find FKs between manifest tables; query returned none.');

        $violations = [];
        foreach ($fks as $fk) {
            // Self-referential FKs are inserted in one statement so order
            // within the manifest doesn't matter for them.
            if ($fk->child_table === $fk->parent_table) {
                continue;
            }
            $childPos = $position[$fk->child_table] ?? null;
            $parentPos = $position[$fk->parent_table] ?? null;
            if ($childPos === null || $parentPos === null) {
                continue;
            }
            if ($parentPos > $childPos) {
                $violations[] = sprintf(
                    '%s.%s -> %s.%s: parent comes after child in manifest',
                    $fk->child_table,
                    $fk->child_column,
                    $fk->parent_table,
                    $fk->parent_column,
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "TENANT_TABLES_IN_INSERT_ORDER violates one or more FK orderings:\n  - "
                .implode("\n  - ", $violations),
        );
    }
}
