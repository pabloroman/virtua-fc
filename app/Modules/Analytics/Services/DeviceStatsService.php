<?php

namespace App\Modules\Analytics\Services;

use App\Models\DeviceSession;
use Illuminate\Support\Facades\DB;

class DeviceStatsService
{
    public function getStats(): array
    {
        $totalLogins = DeviceSession::count();
        $recent = DeviceSession::where('logged_in_at', '>=', now()->subDays(30));
        $recentLogins = $recent->count();

        return [
            'deviceTotalLogins' => $totalLogins,
            'deviceRecentLogins' => $recentLogins,
            'deviceTypes' => $this->getBreakdown('device_type', $totalLogins),
            'deviceTypesRecent' => $this->getBreakdown('device_type', $recentLogins, 30),
            'topBrowsers' => $this->getBreakdown('browser', $totalLogins, null, 5),
            'topOs' => $this->getBreakdown('os', $totalLogins, null, 5),
        ];
    }

    private function getBreakdown(string $column, int $total, ?int $days = null, ?int $limit = null): array
    {
        $query = DeviceSession::select($column, DB::raw('count(*) as count'))
            ->whereNotNull($column);

        if ($days) {
            $query->where('logged_in_at', '>=', now()->subDays($days));
        }

        $query->groupBy($column)->orderByDesc('count');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($row) use ($column, $total) {
            return [
                'label' => $row->$column,
                'count' => $row->count,
                'percentage' => $total > 0 ? round(($row->count / $total) * 100, 1) : 0,
            ];
        })->all();
    }
}
