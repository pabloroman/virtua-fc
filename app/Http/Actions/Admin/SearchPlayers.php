<?php

namespace App\Http\Actions\Admin;

use App\Modules\Editor\Services\PlayerTemplateAdminService;
use Illuminate\Http\Request;

class SearchPlayers
{
    public function __invoke(Request $request, PlayerTemplateAdminService $service)
    {
        $query = $request->query('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $players = $service->searchPlayers($query);

        return response()->json($players->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'date_of_birth' => $p->date_of_birth?->format('Y-m-d'),
        ]));
    }
}
