<?php

namespace App\Http\Views\Migration;

use App\Modules\Migration\MigrationGate;
use App\Modules\Migration\MigrationStatus;
use Illuminate\Http\Request;

/**
 * Export side. GET /migration/required — lockout page shown to users
 * whose migration is still pending. They can either start the migration
 * (POST to migration.start) or log out. Reachable only when the user is
 * inside the MigrationGate allow-list, matching StartMigration.
 */
class ShowRequired
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(MigrationGate::isUserAllowed($user->id), 404);

        if ($user->migration_status === MigrationStatus::COMPLETED) {
            return redirect()->route('migration.completed');
        }

        return view('migration.required', [
            'startUrl' => route('migration.start'),
        ]);
    }
}
