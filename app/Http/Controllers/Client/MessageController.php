<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Expedient;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Mail;
use App\Mail\NewMessageNotification;

class MessageController extends Controller
{
    /**
     * Guarda un nuevo mensaje enviado por el cliente en el chat del expediente.
     */
    public function store(Request $request, $expedientId)
    {
        $request->validate([
            'contenido' => 'required|string|max:2000',
        ], [
            'contenido.required' => 'El mensaje no puede enviarse vacío.',
        ]);

        $client = Auth::user()->client;

        if (!$client) {
            abort(403, 'No tienes un perfil de cliente asociado.');
        }

        // Validar propiedad del expediente
        $expediente = Expedient::where('id', $expedientId)
            ->where('client_id', $client->id)
            ->firstOrFail();

        // Determinar el destinatario del mensaje
        // Por defecto va al asesor asignado al expediente; si no tiene, va al administrador
        $toUserId = $expediente->worker_id;
        if (!$toUserId) {
            $admin = User::role('admin')->first();
            $toUserId = $admin ? $admin->id : null;
        }

        if (!$toUserId) {
            return back()->with('error', 'No se ha podido enviar el mensaje porque no hay asesores disponibles en el sistema.');
        }

        // Crear el mensaje
        $mensaje = Message::create([
            'expedient_id' => $expediente->id,
            'from_user_id' => Auth::id(),
            'to_user_id' => $toUserId,
            'contenido' => $request->input('contenido'),
            'leido' => false,
        ]);

        // Enviar notificación por correo
        $recipient = User::find($toUserId);
        if ($recipient) {
            try {
                Mail::to($recipient->email)->send(new NewMessageNotification($mensaje));
            } catch (\Exception $e) {
                logger()->error("No se pudo enviar la notificación de correo al asesor: " . $e->getMessage());
            }
        }

        return redirect()->route('cliente.expedientes.show', $expediente->id)
            ->with('success', 'Mensaje enviado correctamente.');
    }
}
