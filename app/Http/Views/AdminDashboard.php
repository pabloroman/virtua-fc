<?php

namespace App\Http\Views;

use App\Modules\Analytics\Services\DashboardStatsService;
use App\Modules\Analytics\Services\DeviceStatsService;
use Illuminate\Http\Request;

class AdminDashboard
{
    public function __invoke(Request $request, DashboardStatsService $stats, DeviceStatsService $deviceStats)
    {
        return view('admin.dashboard', array_merge(
            $stats->getSummary(),
            $deviceStats->getStats(),
        ));
    }
}
