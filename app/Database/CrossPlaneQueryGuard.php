<?php

namespace App\Database;

use Illuminate\Database\Events\QueryExecuted;
use RuntimeException;

/**
 * Runtime guard that detects queries which span the control and tenant planes.
 *
 * Both the `pgsql` (tenant) and `pgsql_control` (control) connections currently
 * resolve to the same physical Postgres, so cross-plane queries succeed today
 * — but they will break the moment the planes are split onto separate
 * instances. This guard fails loudly in non-production environments so the
 * violation is caught in tests, not after a cutover.
 *
 * The guard inspects the SQL of every executed query, extracts every table
 * referenced via FROM/JOIN, and asserts they all sit on the same plane as the
 * query's connection. A mismatch throws.
 *
 * Registered in non-production from {@see \App\Providers\AppServiceProvider}.
 */
class CrossPlaneQueryGuard
{
    /** @var array<string, true> */
    private array $controlTables;

    /**
     * @param  array<int, string>  $controlTables  Tables that belong to the control plane.
     */
    public function __construct(array $controlTables)
    {
        $this->controlTables = array_fill_keys(array_map('strtolower', $controlTables), true);
    }

    public function __invoke(QueryExecuted $event): void
    {
        // Only the two split connections are subject to plane checks. Anything
        // else (pulse, custom dynamically-bound tenant connections we add
        // later, etc.) is out of scope for this guard.
        $isControlConnection = match ($event->connectionName) {
            'pgsql_control' => true,
            'pgsql'         => false,
            default         => null,
        };

        if ($isControlConnection === null) {
            return;
        }

        $tables = $this->extractTables($event->sql);

        if ($tables === []) {
            return;
        }

        $offenders = [];
        foreach ($tables as $table) {
            $tableIsControl = isset($this->controlTables[$table]);
            if ($tableIsControl !== $isControlConnection) {
                $offenders[] = $table;
            }
        }

        if ($offenders !== []) {
            $plane = $isControlConnection ? 'control' : 'tenant';
            throw new RuntimeException(sprintf(
                'Cross-plane query detected on the [%s] connection (%s plane). '
                .'Table(s) [%s] belong to the other plane. SQL: %s',
                $event->connectionName,
                $plane,
                implode(', ', array_unique($offenders)),
                $event->sql,
            ));
        }
    }

    /**
     * Extract every table referenced by FROM, JOIN, INTO, UPDATE or DELETE.
     *
     * Handles optional schema prefix ("public.users"), optional quoting, and
     * collapses duplicates. Aliases are ignored (the regex captures the table
     * name, not the alias).
     *
     * @return list<string>
     */
    private function extractTables(string $sql): array
    {
        $pattern = '/\b(?:from|join|into|update|delete\s+from)\s+(?:"?[a-z_][a-z0-9_]*"?\.)?"?([a-z_][a-z0-9_]*)"?/i';

        if (! preg_match_all($pattern, $sql, $matches)) {
            return [];
        }

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }
}
