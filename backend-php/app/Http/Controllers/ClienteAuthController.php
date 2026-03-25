<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Pedido;
use Illuminate\Support\Facades\Hash;

class ClienteAuthController extends Controller
{
    public function registro(Request $request)
    {
        $request->validate(['name' => 'required', 'email' => 'required|email|unique:users', 'password' => 'required|min:6']);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $token = $user->createToken('cliente_token')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user], 201);
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Correo o contraseña incorrectos'], 401);
        }

        $token = $user->createToken('cliente_token')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function perfil(Request $request)
    {
        $user = $request->user();
        // Buscamos todas las compras que coincidan con el correo de este cliente
        $pedidos = Pedido::where('cliente_email', $user->email)->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'user' => $user,
            'historial_compras' => $pedidos
        ]);
    }
}