<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Expedient;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Support\Despacho;
use App\Support\Verifactu\FacturaNoEmitible;
use App\Support\Verifactu\Facturacion;
use App\Support\Verifactu\QrTributario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Facturas del despacho conforme a Veri*Factu. Una factura nace como borrador (se edita y se borra); al emitirla
 * recibe su número correlativo y su registro de facturación, y desde entonces no se toca: se cobra, se anula con un
 * registro de anulación o se corrige con una rectificativa.
 */
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $buscar = $request->input('search');
        $estado = $request->input('estado');
        $emision = $request->input('emision');

        $facturas = Invoice::with(['client', 'expedient', 'registros'])
            ->when($buscar, function ($query, $buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('numero', 'like', "%{$buscar}%")
                        ->orWhereHas('client', fn ($c) => $c->where('razon_social', 'like', "%{$buscar}%"));
                });
            })
            ->when($estado, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($emision, fn ($query, $emision) => $query->where('emision', $emision))
            ->orderByDesc('id')
            ->paginate(10);

        $pendientes = \App\Support\Cobros\PendienteDeFacturar::lista()->count();

        return view('panel.facturas.index', compact('facturas', 'buscar', 'estado', 'emision', 'pendientes'));
    }

    public function create(Request $request)
    {
        $origen = $request->integer('expediente') ? Expedient::with('service')->find($request->integer('expediente')) : null;
        $pre = $origen ? [
            'client_id' => $origen->client_id, 'expedient_id' => $origen->id,
            'concepto' => $origen->service ? $origen->service->nombre.' (expediente '.$origen->codigo.')' : $origen->titulo,
            'precio' => $origen->service ? number_format((float) $origen->service->precio_base, 2, '.', '') : null,
            'iva' => $origen->service?->iva !== null ? (float) $origen->service->iva : null,
        ] : [];

        return view('panel.facturas.form', $this->datosFormulario(null, $origen) + ['factura' => null, 'pre' => $pre]);
    }

    public function edit($id)
    {
        $factura = Invoice::with(['lines', 'client', 'rectificada'])->findOrFail($id);
        abort_unless($factura->esBorrador(), 403, 'Una factura emitida no se puede editar.');

        return view('panel.facturas.form', $this->datosFormulario($factura) + ['factura' => $factura, 'pre' => []]);
    }

    public function store(Request $request)
    {
        return $this->guardar($request, null);
    }

    public function update(Request $request, $id)
    {
        $factura = Invoice::findOrFail($id);
        abort_unless($factura->esBorrador(), 403, 'Una factura emitida no se puede editar.');

        return $this->guardar($request, $factura);
    }

    /** Valida y guarda el borrador (cabecera y líneas) y, si se pidió, lo emite. */
    private function guardar(Request $request, ?Invoice $factura)
    {
        $rectificativa = $factura?->esRectificativa() ?? false;
        $datos = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'expedient_id' => 'nullable|exists:expedients,id',
            'descripcion' => 'nullable|string|max:500',
            'fecha_operacion' => 'nullable|date',
            'retencion_irpf' => 'nullable|numeric|min:0|max:100',
            'estado' => ['required', Rule::in(['pendiente', 'pagada'])],
            'conceptos' => 'required|array|min:1',
            'conceptos.*' => 'required|string|max:255',
            'cantidades' => 'required|array',
            'cantidades.*' => 'required|integer|min:1',
            'precios' => 'required|array',
            'precios.*' => ['required', 'numeric', $rectificativa ? 'min:-99999999' : 'min:0', 'max:99999999'],
            'tipos_iva' => 'required|array',
            'tipos_iva.*' => 'required|numeric',
            'calificaciones' => 'required|array',
            'calificaciones.*' => ['required', Rule::in(array_keys(InvoiceLine::CALIFICACIONES))],
        ], [
            'client_id.required' => 'El cliente es obligatorio.',
            'conceptos.required' => 'Debes añadir al menos una línea de concepto en la factura.',
            'conceptos.*.required' => 'La descripción del concepto no puede estar vacía.',
        ]);

        $factura = DB::transaction(function () use ($factura, $datos) {
            $cabecera = [
                'client_id' => $datos['client_id'],
                'expedient_id' => $datos['expedient_id'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'fecha_operacion' => $datos['fecha_operacion'] ?? null,
                'retencion_irpf' => (float) ($datos['retencion_irpf'] ?? 0),
                'estado' => $datos['estado'],
            ];
            if ($factura) {
                $factura->update($cabecera);
            } else {
                $factura = Invoice::create($cabecera + [
                    'numero' => 'BORRADOR-'.bin2hex(random_bytes(6)),
                    'fecha' => now('Europe/Madrid')->toDateString(),
                    'emision' => Invoice::BORRADOR,
                ]);
            }

            $factura->lines()->delete();
            foreach ($datos['conceptos'] as $i => $concepto) {
                $calificacion = $datos['calificaciones'][$i] ?? 'S1';
                $cantidad = (int) $datos['cantidades'][$i];
                $precio = round((float) $datos['precios'][$i], 2);
                $factura->lines()->create([
                    'concepto' => $concepto,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => round($cantidad * $precio, 2),
                    'tipo_iva' => $calificacion === 'S1' ? (float) ($datos['tipos_iva'][$i] ?? 21) : 0,
                    'calificacion' => $calificacion,
                ]);
            }

            return $factura;
        });

        if ($request->input('accion') === 'emitir') {
            return $this->emitir($request, $factura->id);
        }

        return redirect()->route('panel.facturas.show', $factura->id)->with('success', 'Borrador guardado. Revísalo y emítelo cuando esté listo.');
    }

    public function show($id)
    {
        $factura = Invoice::with(['client', 'lines', 'expedient', 'registros', 'rectificada'])->findOrFail($id);
        $totales = Facturacion::totales($factura);
        $problemas = $factura->esBorrador() ? Facturacion::problemas($factura) : [];

        return view('panel.facturas.show', compact('factura', 'totales', 'problemas'));
    }

    public function emitir(Request $request, $id)
    {
        $factura = Invoice::findOrFail($id);
        try {
            $emitida = Facturacion::emitir($factura, $request->user());
        } catch (FacturaNoEmitible $e) {
            return redirect()->route('panel.facturas.show', $factura->id)->with('error', 'No se puede emitir todavía: '.implode(' ', $e->motivos));
        }
        $this->auditar($request, 'emitir_factura', $emitida, ['numero' => $emitida->numero, 'total' => $emitida->total]);

        return redirect()->route('panel.facturas.show', $emitida->id)
            ->with('success', "Factura {$emitida->numero} emitida. Su registro de facturación queda pendiente de envío a la AEAT.");
    }

    public function anular(Request $request, $id)
    {
        $factura = Invoice::findOrFail($id);
        $request->validate(['motivo' => 'required|string|max:255'], ['motivo.required' => 'Indica el motivo de la anulación.']);
        try {
            Facturacion::anular($factura, $request->input('motivo'), $request->user());
        } catch (FacturaNoEmitible $e) {
            return redirect()->route('panel.facturas.show', $factura->id)->with('error', implode(' ', $e->motivos));
        }
        $this->auditar($request, 'anular_factura', $factura, ['numero' => $factura->numero, 'motivo' => $request->input('motivo')]);

        return redirect()->route('panel.facturas.show', $factura->id)->with('success', "Factura {$factura->numero} anulada con un registro de anulación.");
    }

    public function rectificar(Request $request, $id)
    {
        $original = Invoice::with('lines')->findOrFail($id);
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['S', 'I'])],
            'clave' => ['required', Rule::in(array_keys(Facturacion::TIPOS_RECTIFICATIVA))],
            'motivo' => 'nullable|string|max:255',
        ]);
        try {
            $borrador = Facturacion::crearRectificativa($original, $datos['tipo'], $datos['clave'], $datos['motivo'] ?? null);
        } catch (FacturaNoEmitible $e) {
            return redirect()->route('panel.facturas.show', $original->id)->with('error', implode(' ', $e->motivos));
        }

        return redirect()->route('panel.facturas.edit', $borrador->id)->with('success', 'Borrador de la rectificativa creado: corrige las líneas y emítela.');
    }

    public function downloadPdf($id)
    {
        $invoice = Invoice::with(['client', 'lines', 'expedient', 'rectificada'])->findOrFail($id);

        return Pdf::loadView('pdf.invoice', $this->datosPdf($invoice))->download("factura-{$invoice->numero}.pdf");
    }

    /** Datos que necesita la vista del PDF; la misma para el panel y para el portal del cliente. */
    public static function datosPdf(Invoice $invoice): array
    {
        $emitida = ! $invoice->esBorrador() && $invoice->qr_url;

        return [
            'invoice' => $invoice,
            'despacho' => Despacho::datos(),
            'totales' => Facturacion::totales($invoice),
            'qr' => $emitida ? QrTributario::dataUri($invoice->qr_url) : null,
        ];
    }

    /** Solo se borra un borrador; una factura emitida se anula. */
    public function destroy($id)
    {
        $factura = Invoice::findOrFail($id);
        if (! $factura->esBorrador()) {
            return redirect()->route('panel.facturas.show', $factura->id)
                ->with('error', 'Una factura emitida no se puede borrar: se anula con un registro de anulación.');
        }
        $factura->delete();

        return redirect()->route('panel.facturas.index')->with('success', 'Borrador eliminado.');
    }

    public function exportExcel()
    {
        return Excel::download(new \App\Exports\InvoicesExport, 'facturas-nortia.xlsx');
    }

    /** Cobro de la factura (pendiente o pagada): lo único que cambia en una factura emitida. */
    public function updateStatus(Request $request, $id)
    {
        $factura = Invoice::findOrFail($id);
        $validated = $request->validate(['estado' => ['required', Rule::in(['pendiente', 'pagada'])]]);

        $factura->update(['estado' => $validated['estado']]);

        return redirect()->route('panel.facturas.index')
            ->with('success', "Cobro de la factura {$factura->numero} actualizado.");
    }

    private function datosFormulario(?Invoice $factura, ?Expedient $origen = null): array
    {
        $clientes = Client::orderBy('razon_social')->get();
        $expedientes = Expedient::with('client')->whereIn('estado', ['abierto', 'en_proceso', 'pendiente_cliente'])
            ->when($origen, fn ($q) => $q->orWhere('id', $origen->id))
            ->when($factura?->expedient_id, fn ($q) => $q->orWhere('id', $factura->expedient_id))->get();

        return ['clientes' => $clientes, 'expedientes' => $expedientes];
    }

    private function auditar(Request $request, string $accion, Invoice $factura, array $datos): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id, 'accion' => $accion, 'modelo' => 'Invoice', 'modelo_id' => $factura->id,
            'datos_nuevos' => $datos, 'ip' => $request->ip(),
        ]);
    }
}
