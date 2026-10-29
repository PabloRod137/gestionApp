<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardRedirectController extends Controller
{
    /**
     * Redirige al usuario autenticado a su respectivo dashboard
     * según su rol (admin/worker -> panel, client -> cliente).
     */
    public function index()
    {
        $user = Auth::user();

        // Si el usuario es administrador o trabajador, va al panel interno
        if ($user->hasRole('admin') || $user->hasRole('worker')) {
            return redirect()->route('panel.dashboard');
        }

        // Si el usuario es un cliente, va a su portal personal
        if ($user->hasRole('client')) {
            return redirect()->route('cliente.dashboard');
        }

        // Redirección por defecto si no tiene roles asignados
        return redirect('/');
    }
}
