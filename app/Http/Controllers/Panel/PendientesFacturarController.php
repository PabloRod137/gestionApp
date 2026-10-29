<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Expedient;
use App\Support\Cobros\PendienteDeFacturar;
use App\Support\Cobros\PrepararFactura;

/** «Pendiente de facturar»: lo que el programa detecta que pide factura y los borradores que prepara. Nunca emite. */
class PendientesFacturarController extends Controller
{
    public function index()
    {
        $filas = PendienteDeFacturar::lista();
        $listos = $filas->where('estado', PendienteDeFacturar::LISTO)->count();

        return view('panel.facturas.pendientes', compact('filas', 'listos'));
    }

    /** Prepara el borrador de un expediente; con importe por pactar o tarifa ausente queda con importe 0 para completarlo. */
    public function preparar(Expedient $expedient)
    {
        $borrador = PrepararFactura::desdeExpediente($expedient, forzar: true);
        if (! $borrador) {
            return redirect()->route('panel.facturas.pendientes')->with('error', 'Este expediente no pide factura ahora mismo.');
        }

        return redirect()->route('panel.facturas.edit', $borrador->id)->with('success', 'Borrador preparado: revísalo, completa lo que falte y emítelo.');
    }

    public function prepararTodos()
    {
        $n = 0;
        foreach (PendienteDeFacturar::lista()->where('estado', PendienteDeFacturar::LISTO) as $fila) {
            if (PrepararFactura::desdeExpediente($fila['expediente'])) {
                $n++;
            }
        }

        return redirect()->route('panel.facturas.pendientes')->with('success', $n ? "$n borrador(es) preparado(s). Revísalos y emítelos desde Facturación." : 'No había nada listo para preparar.');
    }
}
