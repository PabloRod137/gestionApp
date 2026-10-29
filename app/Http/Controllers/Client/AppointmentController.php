<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    /**
     * Listado de citas del cliente e inicio del formulario de solicitud.
     */
    public function index()
    {
        $client = Auth::user()->client;

        if (!$client) {
            return view('client.no_profile');
        }

        // Obtener citas del cliente (futuras primero, pasadas después)
        $citas = $client->appointments()
            ->with(['service', 'worker'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->get();

        // Obtener servicios activos que requieran cita
        $servicios = Service::where('activo', true)->get();

        return view('client.citas.index', compact('citas', 'servicios'));
    }

    /**
     * Procesa la solicitud de una nueva cita.
     */
    public function store(Request $request)
    {
        $client = Auth::user()->client;

        if (!$client) {
            abort(403, 'No tienes un perfil de cliente asociado.');
        }

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'fecha' => 'required|date|after_or_equal:today',
            'hora' => 'required',
            'notas' => 'nullable|string|max:1000',
        ], [
            'service_id.required' => 'Por favor, selecciona un servicio.',
            'service_id.exists' => 'El servicio seleccionado no es válido.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.after_or_equal' => 'La fecha de la cita no puede ser en el pasado.',
            'hora.required' => 'La hora es obligatoria.',
        ]);

        // Crear la cita en estado 'pendiente'
        Appointment::create([
            'client_id' => $client->id,
            'service_id' => $validated['service_id'],
            'worker_id' => null, // Se asignará cuando un asesor confirme la cita
            'fecha' => $validated['fecha'],
            'hora' => $validated['hora'],
            'estado' => 'pendiente',
            'notas' => $validated['notas'],
        ]);

        return redirect()->route('cliente.citas.index')
            ->with('success', '¡Cita solicitada correctamente! Queda registrada en estado pendiente. Nos comunicaremos contigo para confirmarla y asignarte un asesor.');
    }
}
