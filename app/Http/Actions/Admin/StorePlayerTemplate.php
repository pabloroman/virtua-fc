<?php

namespace App\Http\Actions\Admin;

use App\Modules\Editor\Services\PlayerTemplateAdminService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorePlayerTemplate
{
    public function __invoke(Request $request, PlayerTemplateAdminService $service)
    {
        $validated = $request->validate([
            'season' => ['required', 'string', 'max:10'],
            'player_id' => ['required', 'uuid', 'exists:players,id'],
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'position' => ['required', 'string', Rule::in(PlayerTemplateAdminService::allPositions())],
            'market_value' => ['nullable', 'string', 'max:50'],
            'market_value_cents' => ['required', 'integer', 'min:0'],
            'contract_until' => ['nullable', 'date'],
            'annual_wage' => ['required', 'integer', 'min:0'],
            'fitness' => ['required', 'integer', 'min:0', 'max:100'],
            'morale' => ['required', 'integer', 'min:0', 'max:100'],
            'durability' => ['required', 'integer', 'min:0', 'max:100'],
            'game_technical_ability' => ['nullable', 'integer', 'min:1', 'max:99'],
            'game_physical_ability' => ['nullable', 'integer', 'min:1', 'max:99'],
            'potential' => ['nullable', 'integer', 'min:1', 'max:99'],
            'potential_low' => ['nullable', 'integer', 'min:1', 'max:99'],
            'potential_high' => ['nullable', 'integer', 'min:1', 'max:99'],
            'tier' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $service->create($validated, $request->user());

        return redirect()
            ->route('admin.player-templates.squad', ['teamId' => $validated['team_id'], 'season' => $validated['season']])
            ->with('success', __('admin.template_created'));
    }
}
