<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\DocumentTemplate;
use App\Models\Service;
use App\Support\GeneradorDocumentos;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

/**
 * Consulta de los datos del despacho que vienen de SharePoint: catalogo de servicios con su
 * documentacion, comunidades con sus propietarios y modelos de documento.
 */
class CatalogController extends Controller
{
    public function servicios(Request $request)
    {
        $buscar = $request->input('search');
        $tipo = $request->input('tipo_cliente');

        $servicios = Service::withCount(['requirements', 'expedients'])
            ->when($buscar, fn ($q, $b) => $q->where(fn ($w) => $w->where('nombre', 'like', "%{$b}%")->orWhere('codigo', 'like', "%{$b}%")))
            ->when($tipo, fn ($q, $t) => $q->where('tipo_cliente', $t))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $tipos = Service::whereNotNull('tipo_cliente')->distinct()->orderBy('tipo_cliente')->pluck('tipo_cliente');

        return view('panel.servicios.index', compact('servicios', 'buscar', 'tipo', 'tipos'));
    }

    public function servicio(Service $service)
    {
        $service->load(['requirements', 'templates']);
        $disponibles = DocumentTemplate::whereNotIn('id', $service->templates->pluck('id'))->orderBy('nombre')->get();
        $expedientes = $service->expedients()->with('client')->latest('id')->limit(10)->get();

        return view('panel.servicios.show', ['servicio' => $service, 'expedientes' => $expedientes, 'disponibles' => $disponibles]);
    }

    public function comunidades()
    {
        $comunidades = Community::withCount('owners')->orderBy('nombre')->get();

        return view('panel.comunidades.index', compact('comunidades'));
    }

    public function comunidad(Community $community)
    {
        $community->load(['client', 'owners.person']);

        return view('panel.comunidades.show', ['comunidad' => $community]);
    }

    public function plantillas(GeneradorDocumentos $generador)
    {
        $plantillas = DocumentTemplate::withCount('services')->orderBy('tipo_documento')->orderBy('nombre')->get();

        // campos que usa cada plantilla con archivo (los que el programa no conoce se marcan en la vista)
        $campos = [];
        foreach ($plantillas as $p) {
            if ($p->archivo && Storage::exists($p->archivo)) {
                try {
                    $campos[$p->id] = $generador->camposDe($p->archivo);
                } catch (\Throwable) {
                    $campos[$p->id] = null; // archivo ilegible
                }
            }
        }
        $conocidos = GeneradorDocumentos::CAMPOS;

        return view('panel.plantillas.index', compact('plantillas', 'campos', 'conocidos'));
    }
}
