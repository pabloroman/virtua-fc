<?php

namespace App\Http\Actions\Admin;

use App\Models\GamePlayerTemplate;
use App\Models\GamePlayerTemplateAudit;
use App\Modules\Editor\Services\PlayerTemplateAdminService;
use Illuminate\Http\Request;

class RestorePlayerTemplate
{
    public function __invoke(Request $request, int $id, int $auditId, PlayerTemplateAdminService $service)
    {
        $template = GamePlayerTemplate::findOrFail($id);
        $audit = GamePlayerTemplateAudit::where('game_player_template_id', $id)->findOrFail($auditId);

        $service->restore($template, $audit, $request->user());

        return redirect()
            ->route('editor.player-templates.squad', ['teamId' => $template->team_id, 'season' => $template->season])
            ->with('success', __('admin.template_restored'));
    }
}
