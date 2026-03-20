<?php

namespace App\Http\Actions\Admin;

use App\Models\GamePlayerTemplate;
use App\Modules\Editor\Services\PlayerTemplateAdminService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdatePlayerTemplate
{
    public function __invoke(Request $request, int $id, PlayerTemplateAdminService $service)
    {
        $template = GamePlayerTemplate::findOrFail($id);

        $validated = $request->validate([
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

        $service->update($template, $validated, $request->user());

        if ($request->wantsJson()) {
            $template->refresh();
            $template->load('player');
            return response()->json([
                'success' => true,
                'message' => __('admin.template_updated'),
                'template' => $template,
            ]);
        }

        return redirect()
            ->route('admin.player-templates.squad', ['teamId' => $template->team_id, 'season' => $template->season])
            ->with('success', __('admin.template_updated'));
    }
}
