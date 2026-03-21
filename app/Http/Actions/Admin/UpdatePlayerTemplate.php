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
            'team_id' => ['nullable', 'uuid', 'exists:teams,id'],
            'number' => [
                'nullable', 'integer', 'min:1', 'max:99',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $template) {
                    if ($value === null) return;
                    $teamId = $request->input('team_id') ?: $template->team_id;
                    if (!$teamId) return;
                    $exists = GamePlayerTemplate::where('team_id', $teamId)
                        ->where('season', $template->season)
                        ->where('number', $value)
                        ->where('id', '!=', $template->id)
                        ->exists();
                    if ($exists) {
                        $fail(__('admin.number_taken'));
                    }
                },
            ],
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
            'nationality' => ['nullable', 'string', 'max:255'],
        ]);

        $nationality = $validated['nationality'] ?? null;
        unset($validated['nationality']);

        $service->update($template, $validated, $request->user());

        if ($nationality !== null && $template->player) {
            $template->player->update(['nationality' => array_filter([$nationality])]);
        }

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
            ->route('editor.player-templates.squad', ['teamId' => $template->team_id, 'season' => $template->season])
            ->with('success', __('admin.template_updated'));
    }
}
