<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Verifica que el usuario autenticado tenga rol 'admin'.
     *
     * Este middleware SIEMPRE debe ir DESPUÉS de 'auth:sanctum',
     * porque necesita que $request->user() ya exista.
     *
     * Uso en rutas:
     *   Route::middleware(['auth:sanctum', 'admin'])->group(...)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si el usuario no existe (no debería pasar si auth:sanctum está antes)
        // o si su rol no es 'admin', bloqueamos con 403
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere rol de administrador.',
            ], 403);
        }

        return $next($request);
    }
}
