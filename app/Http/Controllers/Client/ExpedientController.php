<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Expedient;
use Illuminate\Support\Facades\Auth;

class ExpedientController extends Controller
{
    /**
     * Listado de todos los expedientes del cliente.
     */
    public function index()
    {
        $client = Auth::user()->client;

        if (!$client) {
            return view('client.no_profile');
        }

        // Obtener todos sus expedientes, ordenados por fecha de apertura
        $expedientes = $client->expedients()
            ->with(['service', 'worker'])
            ->orderBy('fecha_apertura', 'desc')
            ->get();

        return view('client.expedients.index', compact('expedientes'));
    }

    /**
     * Vista de detalle de un expediente específico del cliente.
     */
    public function show($id)
    {
        $client = Auth::user()->client;

        if (!$client) {
            return view('client.no_profile');
        }

        // Obtener el expediente asegurando que pertenece a este cliente
        $expediente = Expedient::where('id', $id)
            ->where('client_id', $client->id)
            ->with(['service', 'worker'])
            ->firstOrFail();

        // Obtener documentos asociados con visibilidad pública/cliente
        $documentos = $expediente->documents()
            ->where('visibilidad', 'cliente')
            ->orderBy('created_at', 'desc')
            ->get();

        // Obtener el historial de mensajes ordenados cronológicamente
        $mensajes = $expediente->messages()
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get();

        // Marcar mensajes entrantes como leídos
        $expediente->messages()
            ->where('to_user_id', Auth::id())
            ->where('leido', false)
            ->update(['leido' => true]);

        return view('client.expedients.show', compact('expediente', 'documentos', 'mensajes'));
    }
}
