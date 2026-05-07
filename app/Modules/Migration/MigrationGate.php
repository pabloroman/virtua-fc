<?php

namespace App\Modules\Migration;

/**
 * Visibility gate for the forced-migration lockout and start action.
 *
 * When config('migration.test_user_ids') is non-empty, only those user IDs
 * are allowed through — the rest of the user base sees beta as usual. Used
 * for production smoke tests where MIGRATION_MODE=export is set on beta but
 * the operator wants to verify the flow end-to-end before exposing it to
 * everyone.
 *
 * Empty/unset list → everyone allowed (the normal post-cutover state).
 */
final class MigrationGate
{
    public static function isUserAllowed(int $userId): bool
    {
        $allowed = config('migration.test_user_ids', []);
        if (empty($allowed)) {
            return true;
        }

        return in_array($userId, $allowed, true);
    }
}
