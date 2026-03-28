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
            
            return response()->json([
                'mensaje' => 'Bienvenido al panel',
                'token' => $token,
                'usuario' => $user->name,
                'rol' => $user->rol
            ]);
        }

        return response()->json(['error' => 'Credenciales incorrectas'], 401);
    }
}