<?php

namespace App\Http\Views;

use App\Models\ActivationEvent;
use App\Models\InviteCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminActivation
{
    public function __invoke(Request $request)
    {
        $period = $request->get('period', '30');
        $since = $period === 'all' ? null : now()->subDays((int) $period);

        // Step 0: Invite sent (from invite_codes table directly)
        $inviteQuery = InviteCode::where('invite_sent', true);
        if ($since) {
            $inviteQuery->where('invite_sent_at', '>=', $since);
        }
        $inviteSentCount = $inviteQuery->count();

        // Steps 1-8: From activation_events read model
        $query = ActivationEvent::query()
            ->select('event', DB::raw('COUNT(DISTINCT user_id) as user_count'))
            ->when($since, fn ($q) => $q->where('occurred_at', '>=', $since))
            ->groupBy('event');

        $eventCounts = $query->pluck('user_count', 'event');

        // Build funnel steps
        $steps = [
            [
                'key' => 'invite_sent',
                'label' => __('admin.funnel_invite_sent'),
                'count' => $inviteSentCount,
            ],
        ];

        foreach (ActivationEvent::FUNNEL_ORDER as $event) {
            $steps[] = [
                'key' => $event,
                'label' => __("admin.funnel_{$event}"),
                'count' => $eventCounts[$event] ?? 0,
            ];
        }

        // Calculate drop-off percentages
        $maxCount = max(1, $steps[0]['count']);
        foreach ($steps as $i => &$step) {
            $step['percentage'] = $maxCount > 0 ? round(($step['count'] / $maxCount) * 100, 1) : 0;
            $step['drop_off'] = $i > 0 && $steps[$i - 1]['count'] > 0
                ? round((1 - $step['count'] / $steps[$i - 1]['count']) * 100, 1)
                : 0;
        }
        unset($step);

        // Summary stats
        $registeredCount = $eventCounts[ActivationEvent::EVENT_REGISTERED] ?? 0;
        $firstMatchCount = $eventCounts[ActivationEvent::EVENT_FIRST_MATCH_PLAYED] ?? 0;
        $overallConversion = $registeredCount > 0
            ? round(($firstMatchCount / $registeredCount) * 100, 1)
            : 0;

        return view('admin.activation', [
            'steps' => $steps,
            'period' => $period,
            'overallConversion' => $overallConversion,
            'totalInvites' => $inviteSentCount,
            'totalRegistered' => $registeredCount,
        ]);
    }
}
