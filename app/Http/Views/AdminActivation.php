<?php

namespace App\Http\Views;

use App\Modules\Analytics\Services\ActivationFunnelService;
use Illuminate\Http\Request;

class AdminActivation
{
    public function __invoke(Request $request, ActivationFunnelService $funnel)
    {
        $period = $request->get('period', '30');
        $mode = $request->get('mode', 'all');

        return view('admin.activation', $funnel->getFunnel($period, $mode));
    }
}
