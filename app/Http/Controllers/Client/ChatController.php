<?php

namespace App\Http\Controllers\Client;

use App\Chat\ChatCliente;
use App\Chat\InvalidChatInput;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Asistente privado del cliente: el cliente sale siempre de la sesión. Deja en la auditoría qué se consultó, no el contenido. */
class ChatController extends Controller
{
    public function message(Request $request, ChatCliente $chat): JsonResponse
    {
        $user = $request->user();
        $cliente = $user->client;
        if (! $cliente) {
            abort(403, 'No tienes un perfil de cliente asociado.');
        }

        // Al motor solo llegan texto o acción; cualquier otro campo (incluido un id de cliente) se descarta aquí.
        $input = $request->has('action') ? ['action' => $request->input('action')] : ['text' => $request->input('text')];
        try {
            $result = $chat->handle($cliente, $user, $input, $request->input('state'));
        } catch (InvalidChatInput) {
            return response()->json([
                'ok' => false, 'error' => 'invalid_input',
                'message' => 'No he podido leer ese mensaje. Escribe entre 1 y 500 caracteres o usa los botones.',
            ], 400);
        }

        AuditLog::create([
            'user_id' => $user->id, 'accion' => 'chat_cliente', 'modelo' => 'Client', 'modelo_id' => $cliente->id,
            'datos_nuevos' => ['intencion' => $result['intent']], 'ip' => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'messages' => $result['messages'], 'state' => $result['state']])
            ->header('Cache-Control', 'no-store');
    }
}
