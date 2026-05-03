<?php

namespace App\Providers;

use App\Database\CrossPlaneQueryGuard;
use App\Events\SeasonCompleted;
use App\Events\SeasonStarted;
use App\Events\TournamentCompleted;
use App\Events\TournamentEnded;
use App\Modules\Academy\Listeners\GenerateInitialAcademyBatch;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Match\Handlers\PreSeasonHandler;
use App\Modules\Match\Handlers\GroupStageCupHandler;
use App\Modules\Match\Handlers\KnockoutCupHandler;
use App\Modules\Match\Handlers\LeagueHandler;
use App\Modules\Match\Handlers\LeagueWithPlayoffHandler;
use App\Modules\Match\Handlers\SwissFormatHandler;
use App\Modules\Match\Listeners\AwardCupPrizeMoney;
use App\Modules\Match\Listeners\ConductNextCupRoundDraw;
use App\Modules\Match\Listeners\EnsureMatchAttendance;
use App\Modules\Notification\Listeners\NotifyTransferWindowClosed;
use App\Modules\Notification\Listeners\NotifyTransferWindowClosing;
use App\Modules\Notification\Listeners\NotifyTransferWindowOpen;
use App\Modules\Notification\Listeners\SendCupTieNotifications;
use App\Modules\Notification\Listeners\SendCompetitionProgressNotifications;
use App\Modules\Notification\Listeners\SendMatchNotifications;
use App\Modules\Match\Listeners\UpdateGoalkeeperStats;
use App\Modules\Match\Listeners\UpdateLeagueStandings;
use App\Modules\Match\Listeners\UpdateManagerStats;
use App\Modules\Report\Listeners\CreateTournamentSnapshot;
use App\Modules\Season\Listeners\GrantCareerAccessToChampion;
use App\Modules\Season\Listeners\RecordTournamentCompletedActivation;
use App\Modules\Season\Listeners\SoftDeleteCompletedTournamentGame;
use App\Modules\Squad\Listeners\CheckRecoveredPlayers;
use App\Modules\Squad\Listeners\EnforceSquadRegistration;
use App\Modules\Transfer\Listeners\ApplyWageGapMoraleDrip;
use App\Modules\Transfer\Listeners\CompleteAgreedTransfersOnWindowOpen;
use App\Modules\Transfer\Listeners\ProcessTransferWindowClose;
use App\Modules\Season\Listeners\RecordSeasonCompleted;
use App\Modules\Season\Listeners\SimulateOtherLeagues;
use App\Modules\Competition\Services\CompetitionHandlerResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        // Register competition handler resolver as singleton
        $this->app->singleton(CompetitionHandlerResolver::class, function ($app) {
            $resolver = new CompetitionHandlerResolver();

            // Register handlers
            $resolver->register($app->make(LeagueHandler::class));
            $resolver->register($app->make(LeagueWithPlayoffHandler::class));
            $resolver->register($app->make(KnockoutCupHandler::class));
            $resolver->register($app->make(SwissFormatHandler::class));
            $resolver->register($app->make(GroupStageCupHandler::class));
            $resolver->register($app->make(PreSeasonHandler::class));

            return $resolver;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewPulse', function ($user) {
            return $user->is_admin;
        });

        // Runtime guard: fail loudly in non-production when a query crosses
        // the control/tenant plane boundary. The guard is opt-in via
        // `database_planes.guard_enabled` because it can only run after
        // control-plane models have been annotated with the `pgsql_control`
        // connection — enabling it earlier would false-positive every query
        // touching a control-plane table on the default connection. See
        // CLAUDE.md → "Control plane / tenant plane".
        if (! $this->app->environment('production') && config('database_planes.guard_enabled', false)) {
            $crossPlaneGuard = new CrossPlaneQueryGuard(
                controlTables: config('database_planes.control', []),
                ignoredConnections: ['pulse_pgsql'],
            );
            DB::listen(fn ($event) => $crossPlaneGuard($event));
        }

        // Tests run against a single physical Postgres, but the control and
        // tenant planes use distinct connection NAMES — and two PDO handles
        // means two independent transactions, which breaks FK consistency
        // between, say, `games.user_id` (tenant) and `users.id` (control)
        // during a `RefreshDatabase` test where the user is inserted via
        // pgsql_control before the game is inserted via pgsql.
        //
        // Alias pgsql_control to pgsql in the testing environment so both
        // connection names resolve to the same Connection instance (one PDO
        // handle, one transaction). Eloquent models still declare their
        // logical connection — the alias is transparent — but transactional
        // integrity is preserved. The runtime guard would obviously not work
        // under this alias, which is fine because it's gated off by default.
        if ($this->app->environment('testing')) {
            $this->app['db']->extend('pgsql_control', function () {
                return $this->app['db']->connection('pgsql');
            });
        }

        RateLimiter::for('game-creation', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('tournament-simulation', fn (Request $request) => Limit::perMinute(3)->by($request->user()?->id ?: $request->ip()));

        // Order matters: standings must be updated before competition progress notifications
        Event::listen(MatchFinalized::class, UpdateLeagueStandings::class);
        Event::listen(MatchFinalized::class, UpdateGoalkeeperStats::class);
        Event::listen(MatchFinalized::class, SendMatchNotifications::class);
        Event::listen(MatchFinalized::class, SendCompetitionProgressNotifications::class);
        Event::listen(MatchFinalized::class, UpdateManagerStats::class);
        Event::listen(MatchFinalized::class, EnsureMatchAttendance::class);
        // Tournament-end detection is NOT wired here on purpose: it must run
        // after MatchFinalizationService::beforeMatches has had a chance to
        // generate the next knockout round for group_stage_cup competitions
        // (otherwise the "no unplayed matches" check is true between rounds
        // and the tournament ends prematurely). It is invoked directly by
        // MatchFinalizationService::finalize after beforeMatches, and by the
        // tournament fast-forward actions for AI-only completions.

        Event::listen(CupTieResolved::class, AwardCupPrizeMoney::class);
        Event::listen(CupTieResolved::class, ConductNextCupRoundDraw::class);
        Event::listen(CupTieResolved::class, SendCupTieNotifications::class);

        Event::listen(SeasonStarted::class, GenerateInitialAcademyBatch::class);

        Event::listen(SeasonCompleted::class, SimulateOtherLeagues::class);
        Event::listen(SeasonCompleted::class, RecordSeasonCompleted::class);

        // Order matters: snapshot must run BEFORE soft-delete (snapshot reads many
        // game relations). Activation is first so the analytics row exists even
        // if the snapshot build throws.
        Event::listen(TournamentEnded::class, RecordTournamentCompletedActivation::class);
        Event::listen(TournamentEnded::class, CreateTournamentSnapshot::class);
        Event::listen(TournamentEnded::class, SoftDeleteCompletedTournamentGame::class);

        Event::listen(TournamentCompleted::class, GrantCareerAccessToChampion::class);

        Event::listen(GameDateAdvanced::class, CheckRecoveredPlayers::class);
        Event::listen(GameDateAdvanced::class, NotifyTransferWindowOpen::class);
        Event::listen(GameDateAdvanced::class, CompleteAgreedTransfersOnWindowOpen::class);
        Event::listen(GameDateAdvanced::class, NotifyTransferWindowClosing::class);
        Event::listen(GameDateAdvanced::class, ProcessTransferWindowClose::class);
        Event::listen(GameDateAdvanced::class, NotifyTransferWindowClosed::class);
        Event::listen(GameDateAdvanced::class, EnforceSquadRegistration::class);
        Event::listen(GameDateAdvanced::class, ApplyWageGapMoraleDrip::class);

        Queue::failing(function (JobFailed $event) {
            try {
                RateLimiter::attempt(
                    'job_failure_alert',
                    1,
                    fn () => Mail::raw(
                        "Job: {$event->job->resolveName()}\n\n"
                        ."Queue: {$event->job->getQueue()}\n"
                        ."Exception: {$event->exception->getMessage()}\n\n"
                        ."Trace:\n{$event->exception->getTraceAsString()}",
                        function ($message) use ($event) {
                            $message->to(config('mail.from.address'))
                                ->subject("[VirtuaFC] Job failed: {$event->job->resolveName()}");
                        }
                    ),
                    300,
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send job failure alert email', [
                    'job' => $event->job->resolveName(),
                    'mail_error' => $e->getMessage(),
                    'original_error' => $event->exception->getMessage(),
                ]);
            }
        });
    }
}
