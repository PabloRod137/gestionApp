<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Muestra la tabla de auditoría para supervisar las acciones del personal (solo administradores).
     */
    public function index(Request $request)
    {
        $buscarModelo = $request->input('modelo');
        $buscarAccion = $request->input('accion');

        $logs = AuditLog::with('user')
            ->when($buscarModelo, function ($query, $buscarModelo) {
                $query->where('modelo', 'like', "%{$buscarModelo}%");
            })
            ->when($buscarAccion, function ($query, $buscarAccion) {
                $query->where('accion', $buscarAccion);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('panel.auditoria.index', compact('logs', 'buscarModelo', 'buscarAccion'));
    }
}
