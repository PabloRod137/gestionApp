<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentNotification;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $fechaFiltro = $request->input('fecha');
        $clientFiltro = $request->input('client_id');
        $workerFiltro = $request->input('worker_id');
        $estadoFiltro = $request->input('estado');

        $citas = Appointment::with(['client', 'service', 'worker'])
            ->when($fechaFiltro, function ($query, $fecha) {
                $query->where('fecha', $fecha);
            })
            ->when($clientFiltro, function ($query, $clientId) {
                $query->where('client_id', $clientId);
            })
            ->when($workerFiltro, function ($query, $workerId) {
                $query->where('worker_id', $workerId);
            })
            ->when($estadoFiltro, function ($query, $estado) {
                $query->where('estado', $estado);
            })
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'asc')
            ->paginate(10);

        $clientes = Client::orderBy('razon_social', 'asc')->get();
        $asesores = User::role(['worker', 'admin'])->get();

        return view('panel.citas.index', compact('citas', 'clientes', 'asesores', 'fechaFiltro', 'clientFiltro', 'workerFiltro', 'estadoFiltro'));
    }

    /**
     * Formulario de creación de cita.
     */
    public function create()
    {
        $clientes = Client::orderBy('razon_social', 'asc')->get();
        $servicios = Service::where('activo', true)->orderBy('nombre', 'asc')->get();
        $asesores = User::role(['worker', 'admin'])->get();

        return view('panel.citas.create', compact('clientes', 'servicios', 'asesores'));
    }

    /**
     * Guarda una nueva cita reservada desde el panel.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|required_without:guest_name|exists:clients,id',
            'guest_name' => 'nullable|required_without:client_id|string|max:255',
            'guest_email' => 'nullable|required_without:client_id|email|max:255',
            'guest_phone' => 'nullable|required_without:client_id|string|max:20',
            'service_id' => 'required|exists:services,id',
            'worker_id' => 'nullable|exists:users,id',
            'fecha' => 'required|date',
            'hora' => 'required',
            'estado' => 'required|in:pendiente,confirmada,realizada,cancelada',
            'notas' => 'nullable|string',
        ], [
            'client_id.required_without' => 'El cliente es obligatorio si no es una cita de invitado.',
            'guest_name.required_without' => 'El nombre del invitado es obligatorio si no selecciona un cliente.',
            'guest_email.required_without' => 'El email del invitado es obligatorio si no selecciona un cliente.',
            'guest_phone.required_without' => 'El teléfono del invitado es obligatorio si no selecciona un cliente.',
            'service_id.required' => 'El servicio es obligatorio.',
            'fecha.required' => 'La fecha es obligatoria.',
            'hora.required' => 'La hora es obligatoria.',
        ]);

        if (!empty($validated['client_id'])) {
            $validated['guest_name'] = null;
            $validated['guest_email'] = null;
            $validated['guest_phone'] = null;
        }

        $appointment = Appointment::create($validated);

        $recipientEmail = $appointment->client ? ($appointment->client->user->email ?? null) : $appointment->guest_email;
        if ($recipientEmail) {
            $type = $appointment->estado === 'confirmada' ? 'confirmed' : 'created';
            try {
                Mail::to($recipientEmail)->send(new AppointmentNotification($appointment, $type));
            } catch (\Exception $e) {
                logger()->error('Error al enviar email al crear cita desde el panel: ' . $e->getMessage());
            }
        }

        return redirect()->route('panel.citas.index')
            ->with('success', 'Cita agendada correctamente.');
    }

    /**
     * Formulario de edición de cita.
     */
    public function edit($id)
    {
        $cita = Appointment::findOrFail($id);
        $clientes = Client::orderBy('razon_social', 'asc')->get();
        $servicios = Service::where('activo', true)->orderBy('nombre', 'asc')->get();
        $asesores = User::role(['worker', 'admin'])->get();

        return view('panel.citas.edit', compact('cita', 'clientes', 'servicios', 'asesores'));
    }

    /**
     * Actualiza la cita de la agenda.
     */
    public function update(Request $request, $id)
    {
        $cita = Appointment::findOrFail($id);

        $validated = $request->validate([
            'client_id' => 'nullable|required_without:guest_name|exists:clients,id',
            'guest_name' => 'nullable|required_without:client_id|string|max:255',
            'guest_email' => 'nullable|required_without:client_id|email|max:255',
            'guest_phone' => 'nullable|required_without:client_id|string|max:20',
            'service_id' => 'required|exists:services,id',
            'worker_id' => 'nullable|exists:users,id',
            'fecha' => 'required|date',
            'hora' => 'required',
            'estado' => 'required|in:pendiente,confirmada,realizada,cancelada',
            'notas' => 'nullable|string',
        ], [
            'client_id.required_without' => 'El cliente es obligatorio si no es una cita de invitado.',
            'guest_name.required_without' => 'El nombre del invitado es obligatorio si no selecciona un cliente.',
            'guest_email.required_without' => 'El email del invitado es obligatorio si no selecciona un cliente.',
            'guest_phone.required_without' => 'El teléfono del invitado es obligatorio si no selecciona un cliente.',
        ]);

        $oldFecha = $cita->fecha;
        $oldHora = $cita->hora;
        $oldWorkerId = $cita->worker_id;
        $oldEstado = $cita->estado;

        if (!empty($validated['client_id'])) {
            $validated['guest_name'] = null;
            $validated['guest_email'] = null;
            $validated['guest_phone'] = null;
        }

        $cita->update($validated);

        $type = null;
        if ($cita->estado !== $oldEstado) {
            if ($cita->estado === 'confirmada') {
                $type = 'confirmed';
            } elseif ($cita->estado === 'cancelada') {
                $type = 'cancelled';
            }
        } elseif ($cita->fecha !== $oldFecha || $cita->hora !== $oldHora || $cita->worker_id !== $oldWorkerId) {
            $type = 'updated';
        }

        if ($type) {
            $recipientEmail = $cita->client ? ($cita->client->user->email ?? null) : $cita->guest_email;
            if ($recipientEmail) {
                try {
                    Mail::to($recipientEmail)->send(new AppointmentNotification($cita, $type));
                } catch (\Exception $e) {
                    logger()->error('Error al enviar email al actualizar cita desde el panel: ' . $e->getMessage());
                }
            }
        }

        return redirect()->route('panel.citas.index')
            ->with('success', 'Cita actualizada correctamente.');
    }

    /**
     * Elimina la cita física de la base de datos.
     */
    public function destroy($id)
    {
        $cita = Appointment::findOrFail($id);
        $cita->delete();

        return redirect()->route('panel.citas.index')
            ->with('success', 'Cita eliminada correctamente de la agenda.');
    }

    /**
     * Confirmar cita rápida.
     */
    public function confirm($id)
    {
        $cita = Appointment::findOrFail($id);
        $cita->update(['estado' => 'confirmada']);

        $recipientEmail = $cita->client ? ($cita->client->user->email ?? null) : $cita->guest_email;
        if ($recipientEmail) {
            try {
                Mail::to($recipientEmail)->send(new AppointmentNotification($cita, 'confirmed'));
            } catch (\Exception $e) {
                logger()->error('Error al enviar email de confirmación rápida: ' . $e->getMessage());
            }
        }

        return redirect()->route('panel.citas.index')
            ->with('success', 'La cita ha sido confirmada con éxito y se ha enviado la notificación por correo.');
    }

    /**
     * Cancelar cita rápida.
     */
    public function cancel($id)
    {
        $cita = Appointment::findOrFail($id);
        $cita->update(['estado' => 'cancelada']);

        $recipientEmail = $cita->client ? ($cita->client->user->email ?? null) : $cita->guest_email;
        if ($recipientEmail) {
            try {
                Mail::to($recipientEmail)->send(new AppointmentNotification($cita, 'cancelled'));
            } catch (\Exception $e) {
                logger()->error('Error al enviar email de cancelación rápida: ' . $e->getMessage());
            }
        }

        return redirect()->route('panel.citas.index')
            ->with('success', 'La cita ha sido cancelada con éxito y se ha enviado la notificación por correo.');
    }
}
