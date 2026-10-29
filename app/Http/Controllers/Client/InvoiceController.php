<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    /**
     * Listado de facturas del cliente.
     */
    public function index()
    {
        $client = Auth::user()->client;

        if (!$client) {
            return view('client.no_profile');
        }

        // Obtener facturas ordenadas por fecha de emisión descendente
        $facturas = $client->invoices()
            ->visiblesAlCliente()
            ->with('expedient')
            ->orderBy('fecha', 'desc')
            ->get();

        return view('client.facturas.index', compact('facturas'));
    }

    /**
     * Genera y descarga el PDF oficial de una factura.
     */
    public function downloadPdf($id)
    {
        $client = Auth::user()->client;

        if (!$client) {
            abort(403, 'No tienes un perfil de cliente asociado.');
        }

        // Obtener factura validando pertenencia
        $invoice = Invoice::where('id', $id)
            ->where('client_id', $client->id)
            ->visiblesAlCliente()
            ->with(['client', 'lines', 'expedient', 'rectificada'])
            ->firstOrFail();

        // Cargar vista HTML (con el QR tributario si está emitida) y convertirla a PDF con DOMPDF
        $pdf = Pdf::loadView('pdf.invoice', \App\Http\Controllers\Panel\InvoiceController::datosPdf($invoice));
        
        // Retornar descarga del archivo
        return $pdf->download('factura-' . $invoice->numero . '.pdf');
    }
}
