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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\AddSecurityHeaders::class);
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();

        $middleware->encryptCookies(except: [
            'token_istore',
        ]);

        $middleware->validateCsrfTokens(except: [
            'tienda/productos', // <-- Deja pasar a la tienda sin pase
        ]);

        // Registramos el alias 'admin' para usar en rutas protegidas
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('stderr')->error('[UNCAUGHT-EXCEPTION] ' . $e->getMessage(), [
                'exception_class'   => $e::class,
                'exception_message' => $e->getMessage(),
                'file'              => basename($e->getFile()),
                'line'              => $e->getLine(),
            ]);
        });

        // Si hay error de autenticación en la API, manda un JSON 401
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'No estás autenticado.'
                ], 401);
            }
        });
    })->create();