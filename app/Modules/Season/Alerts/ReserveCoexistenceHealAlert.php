<?php

namespace App\Modules\Season\Alerts;

use App\Models\Game;
use App\Modules\Competition\Promotions\ReserveRepairResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Admin alerting for the in-band reserve/parent coexistence self-heal.
 *
 * Both outcomes are surfaced, by product decision:
 *  - healed():     a coexistence violation was auto-repaired and the transition
 *                  completed. Worth knowing so a recurring planner bug (the swap
 *                  masks it) stays visible.
 *  - unhealable(): a violation could not be auto-repaired; the game stays stuck
 *                  and needs the manual repair commands.
 *
 * Mirrors the rate-limited, fail-soft mail pattern in AppServiceProvider's
 * Queue::failing listener: a mail outage must never break a season transition.
 */
class ReserveCoexistenceHealAlert
{
    public function healed(Game $game, ReserveRepairResult $result): void
    {
        $swaps = implode("\n", array_map(fn (string $s) => "  - {$s}", $result->swapSummaries()));

        $body = "Auto-healed a reserve/parent coexistence violation during season transition.\n\n"
            . "Game: {$game->id}\n"
            . "Country: {$game->country}\n"
            . "Season: {$game->season}\n\n"
            . "Swaps applied:\n{$swaps}\n\n"
            . "The transition completed automatically. Review whether the planner "
            . "should have produced an invariant-satisfying plan without intervention.";

        $this->send(
            'reserve_coexistence_heal_alert:healed',
            "[VirtuaFC] Reserve/parent coexistence auto-healed (game {$game->id})",
            $body,
            ['game_id' => $game->id, 'outcome' => 'healed'],
        );
    }

    public function unhealable(Game $game, \Throwable $original, ReserveRepairResult $result): void
    {
        $body = "A reserve/parent coexistence violation could NOT be auto-healed and needs manual intervention.\n\n"
            . "Game: {$game->id}\n"
            . "Country: {$game->country}\n"
            . "Season: {$game->season}\n\n"
            . "Planner error: {$original->getMessage()}\n\n"
            . "Repair outcome: {$result->outcome->name}\n"
            . 'Reason: ' . ($result->reason ?? 'n/a') . "\n\n"
            . "Run:\n"
            . "  php artisan app:diagnose-stuck-game {$game->id}\n"
            . "  php artisan app:repair-reserve-parent-coexistence {$game->id} --apply\n"
            . "  php artisan app:resume-season-transition {$game->id}";

        $this->send(
            'reserve_coexistence_heal_alert:unhealable',
            "[VirtuaFC] Reserve/parent coexistence NEEDS MANUAL FIX (game {$game->id})",
            $body,
            ['game_id' => $game->id, 'outcome' => 'unhealable'],
        );
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    private function send(string $rateKey, string $subject, string $body, array $logContext): void
    {
        try {
            // Distinct rate-limiter keys per outcome so a flood of one does not
            // suppress the other. One mail per outcome per 5 minutes.
            RateLimiter::attempt(
                $rateKey,
                1,
                fn () => Mail::raw($body, function ($message) use ($subject) {
                    $message->to(config('mail.from.address'))->subject($subject);
                }),
                300,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send reserve coexistence heal alert', $logContext + [
                'mail_error' => $e->getMessage(),
            ]);
        }
    }
}
