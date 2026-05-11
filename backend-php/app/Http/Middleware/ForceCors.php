<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class ForceCors {
    public function handle(Request $request, Closure $next) {
        $origin = $request->header('Origin');
        $allowed = [
            'https://istore-chile.vercel.app',
            'http://localhost:5173',
        ];
        if (in_array($origin, $allowed)) {
            if ($request->isMethod('OPTIONS')) {
                return response('', 200)
                    ->header('Access-Control-Allow-Origin', $origin)
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-XSRF-TOKEN')
                    ->header('Access-Control-Allow-Credentials', 'true')
                    ->header('Access-Control-Max-Age', '86400');
            }
            $response = $next($request);
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            return $response;
        }
        return $next($request);
    }
}
