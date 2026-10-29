<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Expedient;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

use App\Exports\ExpedientsExport;
use App\Imports\ExpedientsImport;
use Maatwebsite\Excel\Facades\Excel;

class ExpedientController extends Controller
{
    /**
     * Listado y filtrado de expedientes.
     */
    public function index(Request $request)
    {
        $buscar = $request->input('search');
        $estado = $request->input('estado');
        $clienteId = $request->input('client_id');
        $asesorId = $request->input('worker_id');

        $expedientes = Expedient::with(['client', 'service', 'worker'])
            ->when($buscar, function ($query, $buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('titulo', 'like', "%{$buscar}%")
                      ->orWhere('codigo', 'like', "%{$buscar}%")
                      ->orWhereHas('client', fn ($c) => $c->where('razon_social', 'like', "%{$buscar}%")->orWhere('nif', 'like', "%{$buscar}%"));
                });
            })
            ->when($estado, function ($query, $estado) {
                $query->where('estado', $estado);
            })
            ->when($clienteId, function ($query, $clienteId) {
                $query->where('client_id', $clienteId);
            })
            ->when($asesorId, function ($query, $asesorId) {
                $query->where('worker_id', $asesorId);
            })
            ->orderBy('fecha_apertura', 'desc')
            ->paginate(10);

        // Listas para los select de filtros
        $clientes = Client::orderBy('razon_social', 'asc')->get();
        $asesores = User::role(['worker', 'admin'])->get();

        return view('panel.expedientes.index', compact('expedientes', 'clientes', 'asesores', 'buscar', 'estado', 'clienteId', 'asesorId'));
    }

    /**
     * Formulario de creación de expediente.
     */
    public function create()
    {
        $clientes = Client::orderBy('razon_social', 'asc')->get();
        $servicios = Service::where('activo', true)->orderBy('nombre', 'asc')->get();
        $asesores = User::role(['worker', 'admin'])->get();

        return view('panel.expedientes.create', compact('clientes', 'servicios', 'asesores'));
    }

    /**
     * Guarda un nuevo expediente de cliente.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'service_id' => 'required|exists:services,id',
            'worker_id' => 'nullable|exists:users,id',
            'titulo' => 'required|string|max:255',
            'estado' => 'required|in:abierto,en_proceso,pendiente_cliente,finalizado,cancelado',
            'fecha_apertura' => 'required|date',
            'notas_internas' => 'nullable|string',
        ], [
            'client_id.required' => 'El cliente es obligatorio.',
            'service_id.required' => 'El servicio asociado es obligatorio.',
            'titulo.required' => 'El título del expediente es obligatorio.',
            'fecha_apertura.required' => 'La fecha de apertura es obligatoria.',
        ]);

        $expData = $validated;
        
        // Si el estado inicial es finalizado, se asigna fecha de cierre
        if ($validated['estado'] === 'finalizado') {
            $expData['fecha_cierre'] = now()->toDateString();
        }

        Expedient::create($expData);

        return redirect()->route('panel.expedientes.index')
            ->with('success', 'Expediente creado con éxito.');
    }

    /**
     * Muestra la vista de seguimiento del expediente (Ficha, Documentos, Conversaciones).
     */
    public function show($id)
    {
        $expediente = Expedient::with(['client.user', 'service', 'worker', 'documents.uploader', 'messages.sender'])
            ->findOrFail($id);

        $documentos = $expediente->documents;
        $mensajes = $expediente->messages;

        $propuesta = \App\Support\PropuestaDocumental::para($expediente);
        $situacion = \App\Support\SituacionExpediente::para($expediente);
        session(['expediente_activo' => $expediente->id]);
        $comunicacion = request('comunicacion');
        $tipoSugerido = request('tipo');
        $propuestas = $expediente->proposals()->with('creator')->get();
        $actividad = $expediente->activities()->with('user')->take(15)->get();
        $otrosExpedientes = Expedient::where('client_id', $expediente->client_id)->where('id', '!=', $expediente->id)->with('service')->latest('id')->take(10)->get();

        return view('panel.expedientes.show', compact('expediente', 'documentos', 'mensajes', 'propuesta', 'propuestas', 'actividad', 'otrosExpedientes', 'situacion', 'comunicacion', 'tipoSugerido'));
    }

    /**
     * Formulario de edición del expediente.
     */
    public function edit($id)
    {
        $expediente = Expedient::findOrFail($id);
        $clientes = Client::orderBy('razon_social', 'asc')->get();
        $servicios = Service::where('activo', true)->orderBy('nombre', 'asc')->get();
        $asesores = User::role(['worker', 'admin'])->get();

        return view('panel.expedientes.edit', compact('expediente', 'clientes', 'servicios', 'asesores'));
    }

    /**
     * Actualiza el expediente e imputa la fecha de cierre si se finaliza.
     */
    public function update(Request $request, $id)
    {
        $expediente = Expedient::findOrFail($id);

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'service_id' => 'required|exists:services,id',
            'worker_id' => 'nullable|exists:users,id',
            'titulo' => 'required|string|max:255',
            'estado' => 'required|in:abierto,en_proceso,pendiente_cliente,finalizado,cancelado',
            'fecha_apertura' => 'required|date',
            'notas_internas' => 'nullable|string',
        ], [
            'client_id.required' => 'El cliente es obligatorio.',
            'service_id.required' => 'El servicio asociado es obligatorio.',
            'titulo.required' => 'El título del expediente es obligatorio.',
        ]);

        $expData = $validated;

        // Lógica de fecha de cierre automática
        if ($validated['estado'] === 'finalizado' && $expediente->estado !== 'finalizado') {
            $expData['fecha_cierre'] = now()->toDateString();
        } elseif ($validated['estado'] !== 'finalizado') {
            $expData['fecha_cierre'] = null;
        }

        $estadoAntes = $expediente->estado;
        $expediente->update($expData);
        // un cambio de estado hecho a mano también tiene que llegar a SharePoint
        \App\Support\Sync\PuenteSharePoint::cambioDeEstado($expediente, (string) $estadoAntes, (string) $expediente->estado, $request->user());

        return redirect()->route('panel.expedientes.show', $expediente->id)
            ->with('success', 'Expediente actualizado con éxito.');
    }

    /**
     * Realiza el borrado lógico del expediente.
     */
    public function destroy($id)
    {
        $expediente = Expedient::findOrFail($id);
        $expediente->delete();

        return redirect()->route('panel.expedientes.index')
            ->with('success', 'Expediente archivado/eliminado correctamente.');
    }

    /**
     * Exporta los expedientes a un archivo Excel.
     */
    public function exportExcel()
    {
        return Excel::download(new ExpedientsExport, 'expedientes-nortia.xlsx');
    }

    /**
     * Importa los expedientes desde un archivo Excel cargado.
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'archivo_excel' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'archivo_excel.required' => 'El archivo Excel es obligatorio para realizar la importación.',
            'archivo_excel.mimes' => 'El archivo debe estar en formato Excel (.xlsx, .xls o .csv).',
        ]);

        Excel::import(new ExpedientsImport, $request->file('archivo_excel'));

        return redirect()->route('panel.expedientes.index')
            ->with('success', 'Expedientes importados con éxito desde el archivo Excel.');
    }
}
