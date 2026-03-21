<?php

namespace App\Http\Actions\Admin;

use App\Models\GamePlayerTemplate;
use App\Modules\Editor\Services\PlayerTemplateAdminService;
use Illuminate\Http\Request;

class DeletePlayerTemplate
{
    public function __invoke(Request $request, int $id, PlayerTemplateAdminService $service)
    {
        $template = GamePlayerTemplate::findOrFail($id);
        $teamId = $template->team_id;
        $season = $template->season;

        $service->delete($template, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.template_deleted'),
            ]);
        }

        return redirect()
            ->route('editor.player-templates.squad', ['teamId' => $teamId, 'season' => $season])
            ->with('success', __('admin.template_deleted'));
    }
}
