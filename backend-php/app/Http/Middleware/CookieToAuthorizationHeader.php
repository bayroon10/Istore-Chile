<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CookieToAuthorizationHeader
{
    /**
     * Transfiere el token Sanctum guardado en una cookie HttpOnly al encabezado de Authorization
     * si este último no viene especificado en la solicitud. Esto permite que Sanctum valide la
     * autenticación de manera segura sin necesidad de exponer el token en localStorage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // En Laravel las cookies vienen encriptadas por defecto por el middleware EncryptCookies.
        // FPM descifra la cookie 'token_istore' de forma transparente al acceder a $request->cookie().
        if ($request->hasCookie('token_istore') && !$request->hasHeader('Authorization')) {
            $token = $request->cookie('token_istore');
            if ($token) {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}
