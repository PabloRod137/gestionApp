<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DocumentoIndexado;
use App\Models\Hallazgo;
use App\Support\Documental\Bandeja;
use App\Support\Documental\Conocimiento;
use App\Support\Documental\Inventario;
use Illuminate\Http\Request;

/** Bandeja documental: lo que el programa propone a partir de los documentos de los clientes, para aceptar o rechazar. */
class DocumentalController extends Controller
{
    public function index(Request $request)
    {
        $vista = in_array($request->query('vista'), ['pendientes', 'contradicciones', 'resueltos'], true) ? $request->query('vista') : 'pendientes';
        $consulta = Hallazgo::with(['client', 'documento', 'decididoPor'])->latest('id');
        $consulta = match ($vista) {
            'pendientes' => $consulta->where('estado', 'pendiente')->where('tipo', 'propuesta'),
            'contradicciones' => $consulta->where('estado', 'pendiente')->where('tipo', 'contradiccion'),
            'resueltos' => $consulta->whereIn('estado', ['aceptado', 'corregido', 'rechazado'])->with('decisiones.user'),
        };

        return view('panel.documental.index', [
            'vista' => $vista,
            'hallazgos' => $consulta->paginate(25)->withQueryString(),
            'resumen' => [
                'inventariados' => DocumentoIndexado::count(),
                'analizados' => DocumentoIndexado::where('estado', 'analizado')->count(),
                'omitidos' => DocumentoIndexado::where('estado', 'omitido')->count(),
                'con_error' => DocumentoIndexado::where('estado', 'error')->count(),
                'por_procesar' => DocumentoIndexado::whereIn('estado', ['pendiente', 'procesando'])->count(),
                'pendientes' => Hallazgo::where('estado', 'pendiente')->count(),
                'contradicciones' => Hallazgo::where('estado', 'pendiente')->where('tipo', 'contradiccion')->count(),
            ],
            'activo' => (bool) config('documental.activo'),
            'raiz' => (bool) (config('documental.raiz') && is_dir((string) config('documental.raiz'))),
        ]);
    }

    public function decidir(Request $request, Hallazgo $hallazgo, Bandeja $bandeja)
    {
        $datos = $request->validate(['accion' => 'required|in:aceptar,corregir,rechazar,reabrir', 'nota' => 'nullable|string|max:300']);
        try {
            $bandeja->decidir($hallazgo, $datos['accion'], $request->user(), $datos['nota'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', ['aceptar' => 'Dato aceptado y guardado en la ficha.', 'corregir' => 'Ficha corregida con el dato del documento.', 'rechazar' => 'Hallazgo rechazado.', 'reabrir' => 'Hallazgo reabierto.'][$datos['accion']]);
    }

    /** Revisión manual de las carpetas (la misma que hace el programa cada pocos minutos). */
    public function revisar(Inventario $inventario)
    {
        if (! config('documental.raiz') || ! is_dir((string) config('documental.raiz'))) {
            return back()->with('error', 'No hay una carpeta raíz documental configurada (DOCUMENTAL_RAIZ).');
        }
        $r = $inventario->inventariar();

        return back()->with('success', "Revisadas las carpetas de {$r['clientes']} clientes: {$r['nuevos']} nuevos, {$r['modificados']} modificados, {$r['encolados']} en cola.");
    }

    /** Mapa de conocimiento de un cliente: ficha, expedientes, requisitos, plantillas y documentos. */
    public function conocimiento(Client $client)
    {
        return view('panel.documental.conocimiento', ['cliente' => $client, 'mapa' => Conocimiento::de($client)]);
    }
}
