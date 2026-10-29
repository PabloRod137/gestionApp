<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Expedient;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\ContactRequest;
use App\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /**
     * Muestra la pantalla principal (Dashboard) del panel interno de control.
     */
    public function index()
    {
        // 1. Estadísticas globales rápidas
        $totalClientes = Client::count();
        $expedientesActivos = Expedient::whereIn('estado', ['abierto', 'en_proceso', 'pendiente_cliente'])->count();
        $citasPendientes = Appointment::where('fecha', '>=', now()->toDateString())
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->count();

        // 2. Facturación del mes en curso (suma del total de facturas 'pagadas' creadas este mes)
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();
        $facturacionMensual = Invoice::where('estado', 'pagada')->whereIn('emision', [Invoice::EMITIDA, Invoice::HISTORICA])
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->sum('total');

        // 3. Agenda del día de hoy
        $citasHoy = Appointment::where('fecha', now()->toDateString())
            ->with(['client', 'service', 'worker'])
            ->orderBy('hora', 'asc')
            ->get();

        // 4. Solicitudes de contacto nuevas sin gestionar
        $solicitudesNuevas = ContactRequest::where('estado', 'nuevo')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // 5. Mensajes de clientes: conversaciones cuyo ultimo mensaje es del cliente (hay que responderle)
        $ultimos = Message::with(['sender.roles', 'expedient.client'])
            ->whereIn('id', fn ($q) => $q->selectRaw('max(id)')->from('messages')->groupBy('expedient_id'))
            ->get()
            ->filter(fn ($m) => $m->expedient && $m->expedient->client && $m->sender?->hasRole('client'))
            ->sortBy('created_at')->values();
        $totalMensajesPendientes = $ultimos->count();
        $mensajesPendientes = $ultimos->take(6)->map(fn ($m) => [
            'expediente' => $m->expedient,
            'extracto' => Str::limit(trim((string) preg_replace('/\s+/u', ' ', $m->contenido)), 110, '…'),
            'cuando' => $m->created_at->diffForHumans(),
            'urgencia' => $m->created_at->lte(now()->subHours(48)) ? 'alta' : ($m->created_at->lte(now()->subHours(24)) ? 'media' : 'normal'),
        ]);

        // 6. Circuito de trabajo: lo que espera una decisión y lo que hay que preparar
        $decisiones = Proposal::with('expedient.client')
            ->where(fn ($q) => $q->whereIn('status', ['proposed', 'document_approved'])->orWhere(fn ($w) => $w->where('status', 'generated')->whereNotNull('pdf_document_id')))
            ->oldest('updated_at')->take(8)->get();
        $porPreparar = Proposal::with('expedient.client')
            ->where(fn ($q) => $q->where('status', 'approved')->orWhere(fn ($w) => $w->where('status', 'generated')->whereNull('pdf_document_id')))
            ->oldest('updated_at')->take(8)->get();
        $comunicacionesPreparadas = Proposal::where('status', 'communication_requested')->count();
        $actividadReciente = CaseActivity::with(['user', 'expedient'])->latest('id')->take(8)->get();

        // 7. Calidad de los datos que vienen de SharePoint
        $calidad = [
            'expedientes_sin_servicio' => Expedient::whereNull('service_id')->whereIn('estado', ['abierto', 'en_proceso', 'pendiente_cliente'])->count(),
            'clientes_sin_nif' => Client::whereNull('nif')->count(),
            'clientes_sin_contacto' => Client::whereNull('email')->whereNull('telefono')->count(),
            'servicios_sin_plantilla' => Service::where('genera_documento', true)->where('activo', true)
                ->whereDoesntHave('templates', fn ($q) => $q->whereNotNull('archivo'))->count(),
            'servicios_sin_desglosar' => Service::where('activo', true)->whereHas('requirements', fn ($q) => $q->where('normalizado', false))->count(),
        ];
        $cambiosPendientesSharePoint = \App\Models\SyncEvent::whereIn('estado', ['pendiente', 'exportado'])->count();
        $importadoEn = ($t = Setting::getValue('readmodel_imported_at')) ? Carbon::parse($t) : null;

        return view('panel.dashboard', compact(
            'decisiones',
            'porPreparar',
            'comunicacionesPreparadas',
            'actividadReciente',
            'calidad',
            'importadoEn',
            'cambiosPendientesSharePoint',
            'totalClientes',
            'expedientesActivos',
            'citasPendientes',
            'facturacionMensual',
            'citasHoy',
            'solicitudesNuevas',
            'mensajesPendientes',
            'totalMensajesPendientes'
        ));
    }
}
