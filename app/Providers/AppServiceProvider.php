<?php

namespace App\Providers;

use App\Events\SeasonStarted;
use App\Modules\Academy\Listeners\GenerateInitialAcademyBatch;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Match\Handlers\KnockoutCupHandler;
use App\Modules\Match\Handlers\LeagueHandler;
use App\Modules\Match\Handlers\LeagueWithPlayoffHandler;
use App\Modules\Match\Handlers\SwissFormatHandler;
use App\Modules\Match\Listeners\AwardCupPrizeMoney;
use App\Modules\Match\Listeners\ConductNextCupRoundDraw;
use App\Modules\Notification\Listeners\SendCupTieNotifications;
use App\Modules\Notification\Listeners\SendMatchNotifications;
use App\Modules\Match\Listeners\UpdateGoalkeeperStats;
use App\Modules\Match\Listeners\UpdateLeagueStandings;
use App\Modules\Competition\Services\CompetitionHandlerResolver;
use Illuminate\Support\Facades\Event;
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

            return $resolver;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MatchFinalized::class, UpdateLeagueStandings::class);
        Event::listen(MatchFinalized::class, UpdateGoalkeeperStats::class);
        Event::listen(MatchFinalized::class, SendMatchNotifications::class);

        Event::listen(CupTieResolved::class, AwardCupPrizeMoney::class);
        Event::listen(CupTieResolved::class, ConductNextCupRoundDraw::class);
        Event::listen(CupTieResolved::class, SendCupTieNotifications::class);

        Event::listen(SeasonStarted::class, GenerateInitialAcademyBatch::class);
    }
}
