<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CommunicationDraft;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\Proposal;
use App\Support\GeneradorDocumentos;
use App\Support\Outlook\DraftProcessor;
use App\Support\Workflow\ProposalWorkflow;
use App\Support\Workflow\WorkflowException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Circuito de trabajo diario. Todo el equipo del despacho prepara, corrige y aprueba; cada paso queda a nombre de quien lo hace.
 * Nada de lo que se hace aquí envía un correo.
 */
class ProposalController extends Controller
{
    public function __construct(private ProposalWorkflow $flujo, private GeneradorDocumentos $generador) {}

    public function index(Request $request)
    {
        $estado = $request->input('estado');
        $propuestas = Proposal::with(['expedient.client', 'creator'])
            ->when($estado, fn ($q, $e) => $q->where('status', $e))
            ->latest('id')->paginate(20)->withQueryString();

        return view('panel.propuestas.index', ['propuestas' => $propuestas, 'estado' => $estado, 'estados' => Proposal::ESTADOS]);
    }

    public function show(Proposal $proposal)
    {
        $proposal->load(['expedient.client', 'expedient.service', 'template', 'creator', 'approver', 'documentApprover', 'generatedDocument', 'pdfDocument', 'revisions', 'activities.user', 'communicationDraft']);

        return view('panel.propuestas.show', ['p' => $proposal, 'estados' => Proposal::ESTADOS]);
    }

    public function store(Request $request, Expedient $expedient)
    {
        // se puede proponer cualquier modelo del despacho, tenga o no el expediente servicio asignado
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'purpose' => ['required', 'string', 'max:3000'],
            'document_template_id' => ['required', 'exists:document_templates,id'],
        ], ['document_template_id.exists' => 'Ese modelo de documento no existe.', 'title.required' => 'Indica un título.', 'purpose.required' => 'Indica la finalidad.']);

        return $this->ejecutar(function () use ($expedient, $data, $request) {
            $p = $this->flujo->crear($expedient, $data['title'], $data['purpose'], DocumentTemplate::findOrFail($data['document_template_id']), $request->user());

            return redirect()->route('panel.propuestas.show', $p->id)->with('success', 'Propuesta creada. Hay que aprobarla antes de generar el documento.');
        });
    }

    public function update(Request $request, Proposal $proposal)
    {
        $data = $request->validate(['version' => ['required', 'integer'], 'title' => ['required', 'string', 'max:160'], 'purpose' => ['required', 'string', 'max:3000']]);

        return $this->ejecutar(function () use ($proposal, $data, $request) {
            $this->flujo->editar($proposal, (int) $data['version'], $data['title'], $data['purpose'], $request->user());

            return back()->with('success', 'Propuesta corregida.');
        });
    }

    public function aprobar(Request $request, Proposal $proposal)
    {
        return $this->ejecutar(function () use ($proposal, $request) {
            $this->flujo->aprobar($proposal, (int) $request->validate(['version' => ['required', 'integer']])['version'], $request->user());

            return back()->with('success', 'Propuesta aprobada. Ya se puede generar el documento.');
        });
    }

    public function generar(Request $request, Proposal $proposal)
    {
        $request->validate(['version' => ['required', 'integer']]);
        $p = $proposal->load('template', 'expedient');
        $extras = array_map(fn ($v) => trim((string) $v), (array) $request->input('extra', []));

        // los datos que faltan los escribe la persona en el momento (o se dejan en blanco); el programa no los inventa
        if ($p->status === 'approved' && $p->template && $p->template->archivo && Storage::exists($p->template->archivo)) {
            $pend = $this->generador->pendientes($p->template, $p->expedient, $extras);
            if ($pend['pedir'] || (! $request->boolean('forzar') && $pend['faltan'])) {
                return back()->with('pedir', $pend['pedir'])->with('faltan', $pend['faltan'])->with('extras', $extras);
            }
        }

        return $this->ejecutar(function () use ($proposal, $request, $extras) {
            $p = $this->flujo->generar($proposal, (int) $request->input('version'), $extras, $request->user(), $request->boolean('guardar_ficha'));

            return back()->with('success', $p->pdf_document_id
                ? 'Documento generado en Word y PDF. Revísalos y apruébalos.'
                : 'Documento Word generado, pero no se pudo crear el PDF automáticamente. Sube el PDF a mano para continuar.');
        });
    }

    public function pdf(Request $request, Proposal $proposal)
    {
        $data = $request->validate(['version' => ['required', 'integer'], 'pdf' => ['required', 'file', 'max:20480']], ['pdf.required' => 'Adjunta el PDF revisado.']);

        return $this->ejecutar(function () use ($proposal, $data, $request) {
            $this->flujo->adjuntarPdf($proposal, (int) $data['version'], $request->file('pdf'), $request->user());

            return back()->with('success', 'PDF adjuntado.');
        });
    }

    public function revisar(Request $request, Proposal $proposal)
    {
        $data = $request->validate(['version' => ['required', 'integer']]);

        return $this->ejecutar(function () use ($proposal, $data, $request) {
            $this->flujo->revisarDocumento($proposal, (int) $data['version'], $request->boolean('confirmado'), $request->user());

            return back()->with('success', 'Documento aprobado.');
        });
    }

    public function comunicacion(Request $request, Proposal $proposal)
    {
        $request->validate(['version' => ['required', 'integer']]);

        return $this->ejecutar(function () use ($proposal, $request) {
            $this->flujo->prepararComunicacion($proposal, (int) $request->input('version'), $request->only(['recipient', 'subject', 'body', 'confirmado']), $request->user());

            return back()->with('success', 'Comunicación preparada. No se ha enviado nada: queda lista para crear el borrador.');
        });
    }

    public function rechazar(Request $request, Proposal $proposal)
    {
        $data = $request->validate(['version' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:2000']], ['reason.required' => 'Indica el motivo del rechazo.']);

        return $this->ejecutar(function () use ($proposal, $data, $request) {
            $this->flujo->rechazar($proposal, (int) $data['version'], $data['reason'], $request->user());

            return back()->with('success', 'Propuesta rechazada.');
        });
    }

    /** Crea ahora el borrador en el correo del despacho (el mismo trabajo que hace la tarea programada). */
    public function crearBorrador(CommunicationDraft $draft, DraftProcessor $procesador)
    {
        $r = $procesador->procesar($draft);

        return $r->status === 'borrador_creado'
            ? back()->with('success', 'Borrador creado en el correo del despacho. No se ha enviado nada: revísalo y envíalo desde Outlook.')
            : back()->withErrors(['workflow' => 'No se pudo crear el borrador: '.$r->last_error]);
    }

    public function comunicaciones()
    {
        $solicitudes = CommunicationDraft::with(['proposal.expedient.client', 'expedient.client', 'requester'])->latest('id')->paginate(20);

        return view('panel.comunicaciones.index', compact('solicitudes'));
    }

    private function ejecutar(callable $accion)
    {
        try {
            return $accion();
        } catch (WorkflowException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()])->withInput();
        }
    }
}
