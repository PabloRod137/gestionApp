<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Expedient;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Mail;
use App\Mail\NewMessageNotification;

class MessageController extends Controller
{
    /**
     * Guarda un nuevo mensaje enviado por un asesor en el expediente.
     */
    public function store(Request $request, $expedientId)
    {
        $request->validate([
            'contenido' => 'required|string|max:2000',
        ], [
            'contenido.required' => 'El mensaje no puede enviarse vacío.',
        ]);

        $expediente = Expedient::with(['client.user'])->findOrFail($expedientId);

        // Si el cliente no tiene usuario asignado, no puede usar el portal ni recibir mensajes
        if (!$expediente->client->user_id) {
            return back()->with('error', 'No se puede enviar mensajes en este expediente porque el cliente no tiene una cuenta de usuario asociada.');
        }

        // Crear el mensaje
        $mensaje = Message::create([
            'expedient_id' => $expediente->id,
            'from_user_id' => Auth::id(), // ID del asesor/admin
            'to_user_id' => $expediente->client->user_id, // ID del usuario del cliente
            'contenido' => $request->input('contenido'),
            'leido' => false,
        ]);

        // Enviar notificación por correo
        try {
            Mail::to($expediente->client->user->email)->send(new NewMessageNotification($mensaje));
        } catch (\Exception $e) {
            logger()->error("No se pudo enviar la notificación de correo al cliente: " . $e->getMessage());
        }

        return redirect()->route('panel.expedientes.show', $expediente->id)
            ->with('success', 'Mensaje enviado correctamente al portal del cliente.');
    }
}
