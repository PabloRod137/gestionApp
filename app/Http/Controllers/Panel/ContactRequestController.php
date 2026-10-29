<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ContactRequestController extends Controller
{
    /**
     * Listado de leads y solicitudes.
     */
    public function index()
    {
        $solicitudes = ContactRequest::with('assignedWorker')
            ->orderBy('estado', 'asc') // Nuevos primero
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('panel.solicitudes.index', compact('solicitudes'));
    }

    /**
     * Muestra el detalle de una solicitud de contacto.
     */
    public function show($id)
    {
        $solicitud = ContactRequest::with('assignedWorker')->findOrFail($id);
        $asesores = User::role(['worker', 'admin'])->get();

        return view('panel.solicitudes.show', compact('solicitud', 'asesores'));
    }

    /**
     * Asigna un lead a un asesor del despacho.
     */
    public function assign(Request $request, $id)
    {
        $request->validate([
            'worker_id' => 'required|exists:users,id',
        ]);

        $solicitud = ContactRequest::findOrFail($id);
        $solicitud->update([
            'asignado_a' => $request->input('worker_id'),
            'estado' => 'gestionado', // Se marca como gestionado al asignar
        ]);

        return back()->with('success', 'Solicitud asignada correctamente al asesor.');
    }

    /**
     * Modifica de forma manual el estado del lead (nuevo / gestionado).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:nuevo,gestionado',
        ]);

        $solicitud = ContactRequest::findOrFail($id);
        $solicitud->update([
            'estado' => $request->input('estado'),
        ]);

        return back()->with('success', 'El estado de la solicitud ha sido actualizado.');
    }
}
