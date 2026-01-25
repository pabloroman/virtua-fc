<?php

namespace App\Providers;

use App\Domain\Competition\CompetitionRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
