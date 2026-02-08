<?php

namespace App\Providers;

use App\Game\Handlers\KnockoutCupHandler;
use App\Game\Handlers\LeagueHandler;
use App\Game\Handlers\LeagueWithPlayoffHandler;
use App\Game\Services\CompetitionHandlerResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }

        // Register competition handler resolver as singleton
        $this->app->singleton(CompetitionHandlerResolver::class, function ($app) {
            $resolver = new CompetitionHandlerResolver();

            // Register handlers
            $resolver->register($app->make(LeagueHandler::class));
            $resolver->register($app->make(LeagueWithPlayoffHandler::class));
            $resolver->register($app->make(KnockoutCupHandler::class));

            return $resolver;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
