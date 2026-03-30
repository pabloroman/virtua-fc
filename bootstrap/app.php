<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->booting(function () {
        $uuidPattern = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
        foreach (['gameId', 'matchId', 'playerId', 'offerId', 'reportId', 'teamId', 'presetId', 'notificationId', 'summaryId', 'auditId'] as $param) {
            Route::pattern($param, $uuidPattern);
        }
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'game.owner' => \App\Http\Middleware\EnsureGameOwnership::class,
            'beta.invite' => \App\Http\Middleware\RequireInviteForRegistration::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'database.editor' => \App\Http\Middleware\EnsureDatabaseEditor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('auth.session_expired'),
                    'redirect' => route('login'),
                ], 419);
            }

            return redirect()->route('login')
                ->with('warning', __('auth.session_expired'));
        });
    })->create();
