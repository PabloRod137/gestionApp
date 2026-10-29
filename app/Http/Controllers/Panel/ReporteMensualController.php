<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\ReporteMensual as Reporte;
use App\Support\Comunidades\ReporteMensual;
use App\Support\Workflow\ProposalWorkflow;
use Illuminate\Http\Request;

/** Reporte mensual de una comunidad: cifras y textos del mes, que luego rellenan el modelo Word. */
class ReporteMensualController extends Controller
{
    public function edit(Request $request, Community $community)
    {
        $periodo = ReporteMensual::periodoValido($request->query('periodo')) ? $request->query('periodo') : ReporteMensual::periodoPorDefecto();
        $reporte = Reporte::where('community_id', $community->id)->where('periodo', $periodo)->first();
        $sugerencias = ReporteMensual::sugerencias($community, $periodo);
        $periodos = ReporteMensual::periodosElegibles() + [$periodo => ReporteMensual::etiqueta($periodo)];
        krsort($periodos);

        return view('panel.comunidades.reporte', [
            'comunidad' => $community->load('client'), 'periodo' => $periodo, 'reporte' => $reporte, 'sugerencias' => $sugerencias, 'periodos' => $periodos,
            'expediente' => ReporteMensual::expedienteDe($community),
            'historial' => Reporte::where('community_id', $community->id)->orderByDesc('periodo')->take(6)->get(),
        ]);
    }

    public function guardar(Request $request, Community $community)
    {
        $datos = $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'ingresos' => 'nullable|string|max:20', 'gastos' => 'nullable|string|max:20', 'saldo' => 'nullable|string|max:20', 'cuotas_pendientes' => 'nullable|string|max:20',
            'incidencias' => 'nullable|string|max:4000', 'proximas_actuaciones' => 'nullable|string|max:4000',
        ]);
        foreach (['ingresos', 'gastos', 'saldo', 'cuotas_pendientes'] as $campo) {
            if (filled($datos[$campo] ?? null) && ReporteMensual::numero($datos[$campo]) === null) {
                return back()->withInput()->withErrors([$campo => 'Escribe un importe, por ejemplo 1.250,40.']);
            }
        }

        Reporte::updateOrCreate(['community_id' => $community->id, 'periodo' => $datos['periodo']], [
            'ingresos' => ReporteMensual::numero($datos['ingresos'] ?? null),
            'gastos' => ReporteMensual::numero($datos['gastos'] ?? null),
            'saldo' => ReporteMensual::numero($datos['saldo'] ?? null),
            'cuotas_pendientes' => ReporteMensual::numero($datos['cuotas_pendientes'] ?? null),
            'incidencias' => $datos['incidencias'] ?? null,
            'proximas_actuaciones' => $datos['proximas_actuaciones'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('panel.comunidades.reporte', [$community, 'periodo' => $datos['periodo']])->with('success', 'Reporte guardado. Ya puedes preparar el documento.');
    }

    public function preparar(Request $request, Community $community, ProposalWorkflow $flujo)
    {
        $periodo = ReporteMensual::periodoValido($request->input('periodo')) ? $request->input('periodo') : ReporteMensual::periodoPorDefecto();
        $propuesta = ReporteMensual::preparar($community, $periodo, $request->user(), $flujo);
        if (! $propuesta) {
            return back()->with('error', 'No se puede preparar: la comunidad necesita un expediente de administración abierto y el modelo «Reporte mensual» con su archivo Word.');
        }

        return redirect()->route('panel.expedientes.show', $propuesta->expedient_id)->with('success', 'Propuesta del reporte creada: apruébala y genera el documento.');
    }
}
