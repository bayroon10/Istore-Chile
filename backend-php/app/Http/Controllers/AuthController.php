<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credenciales = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($credenciales)) {
            $user = Auth::user();
            $token = $user->createToken('token_admin')->plainTextToken;
            
            // Sincronizar carrito de invitado si existe session_id
            $sessionId = $request->header('X-Session-Id');
            if ($sessionId) {
                try {
                    $cartService = app(\App\Services\CartService::class);
                    $cartService->syncGuestCartToUser($user, $sessionId);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error al sincronizar carrito en AuthController login: ' . $e->getMessage());
                }
            }

            return response()->json([
                'mensaje' => 'Bienvenido al panel',
                'token'   => $token,
                'usuario' => $user->name,
                'role'    => $user->role,   // campo renombrado: rol → role
            ])->cookie('token_istore', $token, 10080, '/', null, true, true, false, 'None');
        }

        return response()->json(['error' => 'Credenciales incorrectas'], 401);
    }
}