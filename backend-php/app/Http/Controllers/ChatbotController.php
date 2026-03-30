<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        $mensajeUsuario = $request->input('mensaje');
        
        // El bot lee tu base de datos en tiempo real
        $productos = Producto::where('stock_actual', '>', 0)
            ->get(['nombre', 'precio', 'stock_actual'])
            ->toJson();

        $apiKey = config('services.gemini.api_key');

        // Si aún no pones la llave de IA, el bot responde con tu inventario crudo
        if(!$apiKey) {
            return response()->json([
                'respuesta' => "¡Hola! Aún no me conectan mi cerebro de IA, pero revisé la bodega y este es nuestro inventario disponible hoy: " . $productos
            ]);
        }

        // Si tienes la llave, le damos instrucciones nivel Dios a la IA
        $prompt = "Eres el vendedor estrella de 'iStore Chile'. Responde en 2 o 3 líneas máximo, sé amable, usa emojis. El cliente dice: '$mensajeUsuario'. Nuestro inventario actual en base de datos es: $productos. Si pregunta por algo que no está en el inventario, dile que no tenemos stock por ahora.";

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$apiKey", [
                'contents' => [['parts' => [['text' => $prompt]]]]
            ]);

        $iaRespuesta = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Mano, los servidores de la IA están saturados. ¡Intenta en un minuto!';
        
        return response()->json(['respuesta' => $iaRespuesta]);
    }
}