<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\SyncEvent;
use App\Support\Sync\ExportadorSharePoint;

/** Estado del puente con SharePoint: qué avances han salido, cuáles esperan y cuáles no se reflejan. */
class SincronizacionController extends Controller
{
    public function index(ExportadorSharePoint $exportador)
    {
        $horas = (int) config('sync.aviso_horas', 2);
        $sinConfirmar = SyncEvent::where('estado', 'exportado')->where('exportado_en', '<=', now()->subHours($horas))->count();

        return view('panel.sincronizacion.index', [
            'eventos' => SyncEvent::with('expedient.client', 'client')->latest('id')->limit(60)->get(),
            'pendientes' => SyncEvent::where('estado', 'pendiente')->count(),
            'entregados' => SyncEvent::where('estado', 'exportado')->count(),
            'sinConfirmar' => $sinConfirmar,
            'horas' => $horas,
            'carpeta' => $exportador->carpeta(),
        ]);
    }

    public function exportar(ExportadorSharePoint $exportador)
    {
        $r = $exportador->exportar();

        return back()->with('success', $r
            ? 'Se han dejado '.($r['eventos'] + $r['clientes'])." cambio(s) en la carpeta de salida para Power Automate ({$r['eventos']} de expedientes, {$r['clientes']} de datos de clientes)."
            : 'No hay cambios pendientes de exportar.');
    }
}
