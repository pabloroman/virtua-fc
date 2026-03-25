<?php

namespace App\Http\Actions\Admin;

use App\Models\GamePlayerTemplate;
use App\Models\Player;
use App\Modules\Editor\Services\PlayerTemplateAdminService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorePlayerTemplate
{
    public function __invoke(Request $request, PlayerTemplateAdminService $service)
    {
        $validated = $request->validate([
            'season' => ['required', 'string', 'max:10'],
            'team_id' => ['nullable', 'uuid', 'exists:teams,id'],
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'number' => [
                'nullable', 'integer', 'min:1', 'max:99',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if ($value === null) return;
                    $teamId = $request->input('team_id');
                    if (!$teamId) return;
                    $exists = GamePlayerTemplate::where('team_id', $teamId)
                        ->where('season', $request->input('season'))
                        ->where('number', $value)
                        ->exists();
                    if ($exists) {
                        $fail(__('admin.number_taken'));
                    }
                },
            ],
            'position' => ['required', 'string', Rule::in(PlayerTemplateAdminService::allPositions())],
            'market_value_euros' => ['required', 'integer', 'min:0'],
            'contract_until' => ['nullable', 'date'],
            'annual_wage_euros' => ['required', 'integer', 'min:0'],
            'durability' => ['required', 'integer', 'min:0', 'max:100'],
            'game_technical_ability' => ['nullable', 'integer', 'min:1', 'max:99'],
            'game_physical_ability' => ['nullable', 'integer', 'min:1', 'max:99'],
            'potential' => ['nullable', 'integer', 'min:1', 'max:99'],
            'potential_low' => ['nullable', 'integer', 'min:1', 'max:99'],
            'potential_high' => ['nullable', 'integer', 'min:1', 'max:99'],
            'tier' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $player = Player::create([
            'name' => $validated['name'],
            'date_of_birth' => $validated['date_of_birth'],
            'nationality' => array_filter([$validated['nationality'] ?? null]),
            'technical_ability' => $validated['game_technical_ability'] ?? 50,
            'physical_ability' => $validated['game_physical_ability'] ?? 50,
        ]);

        $teamId = $validated['team_id'] ?: null;

        $templateData = collect($validated)
            ->except(['name', 'date_of_birth', 'nationality', 'market_value_euros', 'annual_wage_euros'])
            ->merge([
                'player_id' => $player->id,
                'team_id' => $teamId,
                'market_value_cents' => $validated['market_value_euros'] * 100,
                'annual_wage' => $validated['annual_wage_euros'] * 100,
            ])
            ->toArray();

        $service->create($templateData, $request->user());

        if ($teamId) {
            return redirect()
                ->route('editor.player-templates.squad', ['teamId' => $teamId, 'season' => $validated['season']])
                ->with('success', __('admin.template_created'));
        }

        return redirect()
            ->route('editor.player-templates.index', ['season' => $validated['season']])
            ->with('success', __('admin.template_created'));
    }
}
