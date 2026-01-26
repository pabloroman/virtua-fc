<?php

namespace App\Providers;

use App\Domain\Competition\CompetitionRepository;
use App\Game\Handlers\KnockoutCupHandler;
use App\Game\Handlers\LeagueHandler;
use App\Game\Services\CompetitionHandlerResolver;
use App\Infrastructure\Repositories\DatabaseCompetitionRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CompetitionRepository::class, function () {
            return new DatabaseCompetitionRepository();
        });

        // Register competition handler resolver as singleton
        $this->app->singleton(CompetitionHandlerResolver::class, function ($app) {
            $resolver = new CompetitionHandlerResolver();

            // Register handlers
            $resolver->register($app->make(LeagueHandler::class));
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
