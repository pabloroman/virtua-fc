<?php

namespace App\Modules\Analytics\Services;

use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActivationFunnelService
{
    public function getFunnel(string $period, string $mode): array
    {
        $since = $period === 'all' ? null : now()->subDays((int) $period);

        if (! in_array($mode, [Game::MODE_CAREER, Game::MODE_TOURNAMENT])) {
            $mode = Game::MODE_CAREER;
        }

        if ($mode === Game::MODE_TOURNAMENT) {
            return $this->buildTournamentFunnel($period, $since);
        }

        return $this->buildCareerFunnel($period, $since);
    }

    private function buildCareerFunnel(string $period, ?object $since): array
    {
        $inviteSentCount = $this->getInviteSentCount($since, Game::MODE_CAREER);

        $careerUserIds = User::where('has_career_access', true)
            ->orWhere('is_admin', true)
            ->pluck('id');

        $cohortSubquery = ActivationEvent::query()
            ->select('user_id')
            ->where('event', ActivationEvent::EVENT_REGISTERED)
            ->whereIn('user_id', $careerUserIds)
            ->when($since, fn ($q) => $q->where('occurred_at', '>=', $since));

        $eventCounts = ActivationEvent::query()
            ->select('event', DB::raw('COUNT(DISTINCT user_id) as user_count'))
            ->whereIn('user_id', $cohortSubquery)
            ->where(function ($q) {
                $q->where('game_mode', Game::MODE_CAREER)->orWhereNull('game_mode');
            })
            ->groupBy('event')
            ->pluck('user_count', 'event');

        $steps = [
            ['key' => 'invite_sent', 'label' => __('admin.funnel_invite_sent'), 'count' => $inviteSentCount],
        ];

        foreach (ActivationEvent::funnelForMode(Game::MODE_CAREER) as $event) {
            $steps[] = ['key' => $event, 'label' => __("admin.funnel_{$event}"), 'count' => $eventCounts[$event] ?? 0];
        }

        $this->computePercentages($steps);

        $registeredCount = $eventCounts[ActivationEvent::EVENT_REGISTERED] ?? 0;
        $firstMatchCount = $eventCounts[ActivationEvent::EVENT_FIRST_MATCH_PLAYED] ?? 0;

        return [
            'steps' => $steps,
            'period' => $period,
            'mode' => Game::MODE_CAREER,
            'overallConversion' => $registeredCount > 0 ? round(($firstMatchCount / $registeredCount) * 100, 1) : 0,
            'totalInvites' => $inviteSentCount,
            'totalRegistered' => $registeredCount,
        ];
    }

    private function buildTournamentFunnel(string $period, ?object $since): array
    {
        $tournamentUserIds = User::where('has_tournament_access', true)
            ->orWhere('is_admin', true)
            ->pluck('id');
        $accessGrantedCount = $tournamentUserIds->count();

        $eventCounts = ActivationEvent::query()
            ->select('event', DB::raw('COUNT(DISTINCT user_id) as user_count'))
            ->whereIn('user_id', $tournamentUserIds)
            ->where('game_mode', Game::MODE_TOURNAMENT)
            ->when($since, fn ($q) => $q->where('occurred_at', '>=', $since))
            ->groupBy('event')
            ->pluck('user_count', 'event');

        $steps = [
            ['key' => 'access_granted', 'label' => __('admin.funnel_access_granted'), 'count' => $accessGrantedCount],
        ];

        foreach (ActivationEvent::funnelForMode(Game::MODE_TOURNAMENT) as $event) {
            $steps[] = ['key' => $event, 'label' => __("admin.funnel_{$event}"), 'count' => $eventCounts[$event] ?? 0];
        }

        $this->computePercentages($steps);

        $firstMatchCount = $eventCounts[ActivationEvent::EVENT_FIRST_MATCH_PLAYED] ?? 0;

        return [
            'steps' => $steps,
            'period' => $period,
            'mode' => Game::MODE_TOURNAMENT,
            'overallConversion' => $accessGrantedCount > 0 ? round(($firstMatchCount / $accessGrantedCount) * 100, 1) : 0,
            'totalInvites' => 0,
            'totalRegistered' => $accessGrantedCount,
        ];
    }

    private function getInviteSentCount(?object $since, ?string $mode = null): int
    {
        $query = InviteCode::where('invite_sent', true);

        if ($mode === Game::MODE_CAREER) {
            $query->where('grants_career', true);
        } elseif ($mode === Game::MODE_TOURNAMENT) {
            $query->where('grants_tournament', true);
        }

        if ($since) {
            $query->where('invite_sent_at', '>=', $since);
        }

        return $query->count();
    }

    private function computePercentages(array &$steps): void
    {
        $maxCount = max(1, ...array_column($steps, 'count'));

        foreach ($steps as $i => &$step) {
            $step['percentage'] = $maxCount > 0 ? round(($step['count'] / $maxCount) * 100, 1) : 0;
            $step['drop_off'] = $i > 0 && $steps[$i - 1]['count'] > 0
                ? round((1 - $step['count'] / $steps[$i - 1]['count']) * 100, 1)
                : 0;
        }
        unset($step);
    }
}
