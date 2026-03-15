<?php

namespace App\Modules\Analytics\Services;

use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\InviteCode;
use Illuminate\Support\Facades\DB;

class ActivationFunnelService
{
    public function getFunnel(string $period, string $mode): array
    {
        $since = $period === 'all' ? null : now()->subDays((int) $period);

        if (! in_array($mode, ['all', Game::MODE_CAREER, Game::MODE_TOURNAMENT])) {
            $mode = 'all';
        }

        $inviteSentCount = $this->getInviteSentCount($since);
        $eventCounts = $this->getEventCounts($since, $mode);
        $steps = $this->buildSteps($inviteSentCount, $eventCounts, $mode);

        $registeredCount = $eventCounts[ActivationEvent::EVENT_REGISTERED] ?? 0;
        $firstMatchCount = $eventCounts[ActivationEvent::EVENT_FIRST_MATCH_PLAYED] ?? 0;
        $overallConversion = $registeredCount > 0
            ? round(($firstMatchCount / $registeredCount) * 100, 1)
            : 0;

        return [
            'steps' => $steps,
            'period' => $period,
            'mode' => $mode,
            'overallConversion' => $overallConversion,
            'totalInvites' => $inviteSentCount,
            'totalRegistered' => $registeredCount,
        ];
    }

    private function getInviteSentCount(?object $since): int
    {
        $query = InviteCode::where('invite_sent', true);

        if ($since) {
            $query->where('invite_sent_at', '>=', $since);
        }

        return $query->count();
    }

    private function getEventCounts(?object $since, string $mode): \Illuminate\Support\Collection
    {
        $cohortSubquery = ActivationEvent::query()
            ->select('user_id')
            ->where('event', ActivationEvent::EVENT_REGISTERED)
            ->when($since, fn ($q) => $q->where('occurred_at', '>=', $since));

        return ActivationEvent::query()
            ->select('event', DB::raw('COUNT(DISTINCT user_id) as user_count'))
            ->whereIn('user_id', $cohortSubquery)
            ->when($mode !== 'all', fn ($q) => $q->where(function ($q) use ($mode) {
                $q->where('game_mode', $mode)->orWhereNull('game_mode');
            }))
            ->groupBy('event')
            ->pluck('user_count', 'event');
    }

    private function buildSteps(int $inviteSentCount, \Illuminate\Support\Collection $eventCounts, string $mode): array
    {
        $funnelEvents = ActivationEvent::funnelForMode($mode === 'all' ? null : $mode);

        $steps = [
            [
                'key' => 'invite_sent',
                'label' => __('admin.funnel_invite_sent'),
                'count' => $inviteSentCount,
            ],
        ];

        foreach ($funnelEvents as $event) {
            $steps[] = [
                'key' => $event,
                'label' => __("admin.funnel_{$event}"),
                'count' => $eventCounts[$event] ?? 0,
            ];
        }

        $maxCount = max(1, $steps[0]['count']);
        foreach ($steps as $i => &$step) {
            $step['percentage'] = $maxCount > 0 ? round(($step['count'] / $maxCount) * 100, 1) : 0;
            $step['drop_off'] = $i > 0 && $steps[$i - 1]['count'] > 0
                ? round((1 - $step['count'] / $steps[$i - 1]['count']) * 100, 1)
                : 0;
        }
        unset($step);

        return $steps;
    }
}
