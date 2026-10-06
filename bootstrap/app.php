<?php

use App\Http\Middleware\HeaderKeamananPendek;
use App\Http\Middleware\HeaderKeamananPublik;
use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Dimuat paling akhir agar tidak menelan rute sistem (BR-36).
            Route::middleware('pendek')->group(base_path('routes/pendek.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('pendek', [
            SubstituteBindings::class,
            HeaderKeamananPendek::class,
        ]);
        $middleware->alias(['publik' => HeaderKeamananPublik::class]);
        $middleware->redirectGuestsTo(fn () => Filament::getPanel('alias')->getLoginUrl());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
