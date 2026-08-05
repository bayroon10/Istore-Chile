<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class ClienteAuthController extends Controller
{
    public function registro(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->numbers()],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            // 'role' default es 'customer', no hace falta pasarlo
        ]);

        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $token = $user->createToken('customer_token')->plainTextToken;

        $this->syncGuestCartIfExists($user, $request);

        return response()->json(['token' => $token, 'user' => $user], 201)
            ->cookie('token_istore', $token, 10080, '/', null, true, true, false, 'None');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Correo o contraseña incorrectos'], 401);
        }

        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $token = $user->createToken('customer_token')->plainTextToken;

        $this->syncGuestCartIfExists($user, $request);

        return response()->json(['token' => $token, 'user' => $user])
            ->cookie('token_istore', $token, 10080, '/', null, true, true, false, 'None');
    }

    /**
     * Sincroniza el carrito de invitado con el usuario autenticado si existe X-Session-Id válido.
     */
    private function syncGuestCartIfExists(User $user, Request $request): void
    {
        $sessionId = $request->header('X-Session-Id');
        if ($sessionId && \Illuminate\Support\Str::isUuid($sessionId)) {
            try {
                $cartService = app(\App\Services\CartService::class);
                $cartService->syncGuestCartToUser($user, $sessionId);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error al sincronizar carrito en auth de cliente: ' . $e->getMessage());
            }
        }
    }

    public function perfil(Request $request)
    {
        $user = $request->user();

        $historial = \App\Models\Order::where('user_id', $user->id)
            ->withCount('items')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($order) => [
                'id'             => $order->id,
                'total'          => (float) $order->total,
                'status'         => $order->status,
                'created_at'     => $order->created_at->toDateTimeString(),
                'items_count'    => $order->items_count,
                'cantidad_items' => $order->items_count,
            ]);

        return response()->json([
            'user'              => $user,
            'historial_compras' => $historial,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $token = $user->currentAccessToken();

            // currentAccessToken() devuelve TransientToken en flujos stateful (sesión),
            // el cual no tiene método delete(). Solo eliminamos si es un token real de API.
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $token->delete();
            }

            // Invalida la sesión web si aplica
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response()->json(['message' => 'Sesión cerrada con éxito'])
            ->withoutCookie('token_istore');
    }
}