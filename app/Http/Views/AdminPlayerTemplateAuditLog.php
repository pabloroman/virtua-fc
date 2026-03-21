<?php

namespace App\Http\Views;

use App\Modules\Editor\Services\PlayerTemplateAdminService;
use Illuminate\Http\Request;

class AdminPlayerTemplateAuditLog
{
    public function __invoke(Request $request, PlayerTemplateAdminService $service)
    {
        $audits = $service->recentAudits();

        return view('editor.player-templates.audit-log', [
            'audits' => $audits,
        ]);
    }
}
