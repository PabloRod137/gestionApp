<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cabeceras de seguridad básicas en todas las respuestas web
        $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class]);
        $middleware->alias([
            // Middlewares de autorización por rol/permiso (Spatie Permission)
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            // Cabeceras de seguridad (modo «csp» para la web pública)
            'headers'            => \App\Http\Middleware\SecurityHeaders::class,
            // Autenticación por Bearer token para el webhook de Power Automate
            'auth.powerautomate' => \App\Http\Middleware\AuthenticatePowerAutomate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API de la web responde siempre con el mismo formato JSON que espera el JavaScript
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/nortia/*')) {
                return response()->json(['ok' => false, 'error' => 'csrf_required'], 419);
            }
        });
    })->create();
