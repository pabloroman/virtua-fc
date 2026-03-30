<?php

namespace App\Providers;

use App\Events\SeasonCompleted;
use App\Events\SeasonStarted;
use App\Events\TournamentCompleted;
use App\Modules\Academy\Listeners\GenerateInitialAcademyBatch;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Match\Handlers\PreSeasonHandler;
use App\Modules\Match\Handlers\GroupStageCupHandler;
use App\Modules\Match\Handlers\KnockoutCupHandler;
use App\Modules\Match\Handlers\LeagueHandler;
use App\Modules\Match\Handlers\LeagueWithPlayoffHandler;
use App\Modules\Match\Handlers\SwissFormatHandler;
use App\Modules\Match\Listeners\AwardCupPrizeMoney;
use App\Modules\Match\Listeners\ConductNextCupRoundDraw;
use App\Modules\Notification\Listeners\SendCupTieNotifications;
use App\Modules\Notification\Listeners\SendCompetitionProgressNotifications;
use App\Modules\Notification\Listeners\SendMatchNotifications;
use App\Modules\Match\Listeners\UpdateGoalkeeperStats;
use App\Modules\Match\Listeners\UpdateLeagueStandings;
use App\Modules\Match\Listeners\UpdateManagerStats;
use App\Modules\Season\Listeners\GrantCareerAccessToChampion;
use App\Modules\Season\Listeners\RecordSeasonCompleted;
use App\Modules\Season\Listeners\SimulateOtherLeagues;
use App\Modules\Competition\Services\CompetitionHandlerResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('game-creation', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('tournament-simulation', fn (Request $request) => Limit::perMinute(3)->by($request->user()?->id ?: $request->ip()));

        // Order matters: standings must be updated before competition progress notifications
        Event::listen(MatchFinalized::class, UpdateLeagueStandings::class);
        Event::listen(MatchFinalized::class, UpdateGoalkeeperStats::class);
        Event::listen(MatchFinalized::class, SendMatchNotifications::class);
        Event::listen(MatchFinalized::class, SendCompetitionProgressNotifications::class);
        Event::listen(MatchFinalized::class, UpdateManagerStats::class);

        Event::listen(CupTieResolved::class, AwardCupPrizeMoney::class);
        Event::listen(CupTieResolved::class, ConductNextCupRoundDraw::class);
        Event::listen(CupTieResolved::class, SendCupTieNotifications::class);

        Event::listen(SeasonStarted::class, GenerateInitialAcademyBatch::class);

        Event::listen(SeasonCompleted::class, SimulateOtherLeagues::class);
        Event::listen(SeasonCompleted::class, RecordSeasonCompleted::class);

        Event::listen(TournamentCompleted::class, GrantCareerAccessToChampion::class);
    }
}
