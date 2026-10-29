<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Muestra la pantalla principal (Dashboard) del portal de cliente.
     */
    public function index()
    {
        $user = Auth::user();
        $client = $user->client;

        if (!$client) {
            // Si el usuario registrado no tiene una ficha de cliente asociada
            return view('client.no_profile');
        }

        // 1. Obtener expedientes activos (abierto, en_proceso, pendiente_cliente)
        $expedientesActivosCount = $client->expedients()
            ->whereIn('estado', ['abierto', 'en_proceso', 'pendiente_cliente'])
            ->count();

        // 2. Obtener citas futuras/pendientes
        $proximasCitas = $client->appointments()
            ->where('fecha', '>=', now()->toDateString())
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->get();

        // 3. Obtener facturas pendientes de pago
        $facturasPendientes = $client->invoices()
            ->pendientesDeCobro()
            ->orderBy('fecha', 'desc')
            ->get();

        // 4. Últimos mensajes del chat en expedientes propios del cliente
        $ultimosMensajes = \App\Models\Message::whereIn('expedient_id', $client->expedients()->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->with(['sender', 'expedient'])
            ->get();

        return view('client.dashboard', compact(
            'client',
            'expedientesActivosCount',
            'proximasCitas',
            'facturasPendientes',
            'ultimosMensajes'
        ));
    }
}
