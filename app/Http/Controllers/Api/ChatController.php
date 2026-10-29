<?php

namespace App\Http\Controllers\Api;

use App\Chat\InvalidChatInput;
use App\Chat\PublicChat;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Asistente público (sin sesión): no guarda mensajes ni toca datos de clientes. */
class ChatController extends Controller
{
    public function public(Request $request, PublicChat $chat): JsonResponse
    {
        // Al motor solo llegan texto o acción; cualquier otro campo se descarta aquí.
        $input = $request->has('action') ? ['action' => $request->input('action')] : ['text' => $request->input('text')];
        try {
            $result = $chat->handle($input, $request->input('state'));
        } catch (InvalidChatInput) {
            return response()->json([
                'ok' => false, 'error' => 'invalid_input',
                'message' => 'No he podido leer ese mensaje. Escribe entre 1 y 500 caracteres o usa los botones.',
            ], 400);
        }

        return response()->json(['ok' => true, 'messages' => $result['messages'], 'state' => $result['state']]);
    }
}
