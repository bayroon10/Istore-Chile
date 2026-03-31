<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function __construct(
        private GeminiService $geminiService
    ) {
    }

    /**
     * Procesa los mensajes del chat y devuelve una respuesta de la IA.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $userMessage = $request->input('message');

        // 1. Obtener contexto del inventario real
        $products = Product::where('is_active', true)
            ->where('stock', '>', 0)
            ->with('category')
            ->get(['id', 'name', 'price', 'stock', 'category_id']);

        $inventoryContext = $products->map(function ($p) {
            return "- {$p->name} (Categoría: {$p->category->name}, Precio: \${$p->price}, Stock: {$p->stock})";
        })->implode("\n");

        // 2. Construir el System Prompt "Nivel Dios"
        $systemPrompt = "Eres 'Santi', el asistente virtual experto de iStore Chile. 
        Tu objetivo es ayudar a los clientes a encontrar productos y responder sus dudas de forma amable, cercana y profesional. 
        Usa un tono chileno educado (puedes usar un 'claro', 'al tiro', o un emoji 🇨🇱 de vez en cuando, pero mantén la profesionalidad). 
        
        REGLAS CRÍTICAS:
        1. Tu respuesta debe ser CONCISA (máximo 3 párrafos cortos).
        2. Solo recomienda productos que estén en la lista del INVENTARIO REAL de abajo.
        3. Si el cliente pregunta por algo que NO está en el inventario, dile amablemente que por el momento no tenemos stock, pero que estamos renovando el catálogo constantemente.
        4. No inventes precios ni características técnicas que no estén claras.
        5. Al final, si es pertinente, invita al cliente a agregar el producto al carrito.

        INVENTARIO REAL ACTUAL:
        {$inventoryContext}

        EL CLIENTE DICE: \"{$userMessage}\"";

        // 3. Obtener respuesta de Gemini
        $response = $this->geminiService->generateResponse($systemPrompt);

        return response()->json([
            'reply' => $response
        ]);
    }
}
