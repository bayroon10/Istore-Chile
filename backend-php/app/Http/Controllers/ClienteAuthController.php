<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ClienteAuthController extends Controller
{
    public function registro(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            // 'role' default es 'customer', no hace falta pasarlo
        ]);

        $token = $user->createToken('customer_token')->plainTextToken;

        // Sincronizar carrito de invitado si existe session_id
        $sessionId = $request->header('X-Session-Id');
        if ($sessionId) {
            try {
                $cartService = app(\App\Services\CartService::class);
                $cartService->syncGuestCartToUser($user, $sessionId);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error al sincronizar carrito en registro de cliente: ' . $e->getMessage());
            }
        }

        return response()->json(['token' => $token, 'user' => $user], 201)
            ->cookie('token_istore', $token, 10080, '/', null, true, true, false, 'None');
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Correo o contraseña incorrectos'], 401);
        }

        $token = $user->createToken('customer_token')->plainTextToken;

        // Sincronizar carrito de invitado si existe session_id
        $sessionId = $request->header('X-Session-Id');
        if ($sessionId) {
            try {
                $cartService = app(\App\Services\CartService::class);
                $cartService->syncGuestCartToUser($user, $sessionId);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error al sincronizar carrito en login de cliente: ' . $e->getMessage());
            }
        }

        return response()->json(['token' => $token, 'user' => $user])
            ->cookie('token_istore', $token, 10080, '/', null, true, true, false, 'None');
    }

    public function perfil(Request $request)
    {
        $user = $request->user();

        // El historial de compras se reincorporará en la Fase 1 Paso 2
        // cuando tengamos los modelos Order y OrderItem
        return response()->json([
            'user'             => $user,
            'historial_compras' => [],
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Sesión cerrada con éxito'])
            ->withoutCookie('token_istore');
    }
}