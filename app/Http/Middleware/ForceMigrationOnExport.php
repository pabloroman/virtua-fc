<?php

namespace App\Http\Middleware;

use App\Modules\Migration\MigrationGate;
use App\Modules\Migration\MigrationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the export-side deployment, redirect authenticated users whose
 * migration is still `pending` (and who are inside the MigrationGate
 * allow-list) to the lockout page at /migration/required. They cannot
 * keep playing on beta once the cutover is live for them — the only
 * path forward is the handoff to the new server.
 *
 * Mirrors RequireMigrationOnImport on the import side. No-op outside
 * export mode, for unauthenticated requests, for users not in the
 * gate, and for migration / logout paths themselves. Migrated users
 * (status COMPLETED) keep being handled by BlockMigratedUsersOnExport.
 */
class ForceMigrationOnExport
{
    /** Paths that stay accessible to forced users (matched via str_starts_with). */
    private const ALLOWED_PATHS = [
        '/migration/',
        '/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (config('migration.mode') !== 'export') {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if ($user->migration_status !== MigrationStatus::PENDING) {
            return $next($request);
        }

        if (! MigrationGate::isUserAllowed($user->id)) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');
        foreach (self::ALLOWED_PATHS as $allowed) {
            if (str_starts_with($path, $allowed)) {
                return $next($request);
            }
        }

        return redirect()->route('migration.required');
    }
}
