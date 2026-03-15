<?php

namespace App\Http\Views;

use App\Modules\Analytics\Services\DashboardStatsService;
use Illuminate\Http\Request;

class AdminDashboard
{
    public function __invoke(Request $request, DashboardStatsService $stats)
    {
        return view('admin.dashboard', $stats->getSummary());
    }
}
