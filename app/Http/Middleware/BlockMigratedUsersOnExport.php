<?php

namespace App\Http\Middleware;

use App\Modules\Migration\MigrationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the export-side deployment, block authenticated users whose migration
 * has already completed from doing anything except viewing the "migration
 * completed" page or logging out. Stops them accidentally playing matches
 * that would never reach the new home.
 *
 * No-op when the deployment is not in export mode, or when the user is
 * still pending/in_progress/failed.
 */
class BlockMigratedUsersOnExport
{
    /** Routes that stay accessible to migrated users (must end with str_starts_with). */
    private const ALLOWED_PATHS = [
        '/migration/completed',
        '/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (config('migration.mode') !== 'export') {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null || $user->migration_status !== MigrationStatus::COMPLETED) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');
        foreach (self::ALLOWED_PATHS as $allowed) {
            if (str_starts_with($path, $allowed)) {
                return $next($request);
            }
        }

        return redirect()->route('migration.completed');
    }
}
