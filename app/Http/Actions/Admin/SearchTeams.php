<?php

namespace App\Http\Actions\Admin;

use App\Modules\Editor\Services\PlayerTemplateAdminService;
use Illuminate\Http\Request;

class SearchTeams
{
    public function __invoke(Request $request, PlayerTemplateAdminService $service)
    {
        $query = $request->query('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $teams = $service->searchTeams($query);

        return response()->json($teams->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
        ]));
    }
}
