<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CaseActivity;
use App\Models\Expedient;
use App\Models\Service;
use App\Support\ClasificadorServicio;
use App\Support\Ia\ClasificaServicioIa;
use App\Support\Ia\IaException;
use App\Support\PlanActuaciones;
use Illuminate\Http\Request;

/** Actuaciones automáticas de un expediente: sugerir su servicio por el asunto, asignarlo y proponer los pasos. Nada se envía. */
class ActuacionesController extends Controller
{
    public function sugerir(Request $request, Expedient $expedient, ClasificadorServicio $clasificador, ClasificaServicioIa $ia)
    {
        $candidatos = collect($clasificador->sugerir((string) $expedient->titulo, $expedient->id, 5))->map(fn ($c) => [
            'id' => $c['servicio']->id, 'codigo' => $c['servicio']->codigo, 'nombre' => $c['servicio']->nombre,
            'puntos' => $c['puntos'], 'motivo' => $c['motivo'], 'claro' => $c['claro'],
        ])->all();

        $sugerencia = ['expediente_id' => $expedient->id, 'candidatos' => $candidatos, 'ia' => null, 'aviso_ia' => null];
        if ($request->boolean('usar_ia')) {
            try {
                $sugerencia['ia'] = $ia->sugerir($expedient);
                if ($sugerencia['ia']) {
                    // se deja constancia de que se usó, sin guardar el asunto ni la respuesta
                    CaseActivity::registrar($expedient, 'SERVICIO_SUGERIDO_IA', 'Modelo '.$sugerencia['ia']['modelo'].'; sugerencia '.$sugerencia['ia']['codigo'].'.', $request->user());
                } else {
                    $sugerencia['aviso_ia'] = 'La IA no encontró un servicio que encaje con claridad.';
                }
            } catch (IaException $e) {
                $sugerencia['aviso_ia'] = $e->getMessage();
            }
        }

        return redirect()->to(url()->previous().'#actuaciones')->with('servicio_sugerido', $sugerencia);
    }

    public function asignar(Request $request, Expedient $expedient)
    {
        $data = $request->validate(['service_id' => ['required', 'exists:services,id'], 'origen' => ['nullable', 'in:palabras_clave,ia,manual,sugerencia']]);
        if ($expedient->service_id && $expedient->service_id !== (int) $data['service_id'] && ! $request->boolean('cambiar')) {
            return back()->withErrors(['service_id' => 'Este expediente ya tiene un servicio. Marca "Cambiar el servicio actual" si quieres sustituirlo.']);
        }
        $servicio = Service::findOrFail($data['service_id']);
        $expedient->update(['service_id' => $servicio->id]);
        CaseActivity::registrar($expedient, 'SERVICIO_ASIGNADO', "Servicio {$servicio->codigo} (".($data['origen'] ?? 'manual').').', $request->user());

        return redirect()->to(url()->previous().'#actuaciones')->with('success', "Servicio asignado: {$servicio->nombre}.");
    }

    public function proponer(Request $request, Expedient $expedient, PlanActuaciones $plan)
    {
        if (! $expedient->service_id) {
            return back()->withErrors(['workflow' => 'Asigna primero un servicio al expediente: sin él no se pueden proponer actuaciones.']);
        }
        $n = $plan->crear($expedient, $request->user());

        return redirect()->to(url()->previous().'#actuaciones')->with('success', $n
            ? "Se han preparado {$n} propuesta(s) pendientes de aprobar. No se ha generado ni enviado nada."
            : 'No hay actuaciones nuevas que proponer: ya están propuestas o falta el modelo Word.');
    }
}
