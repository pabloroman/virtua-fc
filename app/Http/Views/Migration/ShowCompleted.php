<?php

namespace App\Http\Views\Migration;

use Illuminate\Http\Request;

/**
 * Export side. GET /migration/completed — terminal page shown to users whose
 * migration is sealed. They can log out, click through to the new home, or
 * close the tab.
 */
class ShowCompleted
{
    public function __invoke(Request $request)
    {
        return view('migration.completed', [
            'destinationUrl' => (string) config('migration.destination_url', ''),
        ]);
    }
}
