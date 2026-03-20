<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Analytics\Services\ActivationFunnelService;
use Illuminate\Http\Request;

class AdminActivation
{
    public function __invoke(Request $request, ActivationFunnelService $funnel)
    {
        $period = $request->get('period', '30');
        $mode = $request->get('mode', Game::MODE_CAREER);

        return view('admin.activation', $funnel->getFunnel($period, $mode));
    }
}
