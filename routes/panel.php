<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Panel\CatalogController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\ComunicacionController;
use App\Http\Controllers\Panel\ActuacionesController;
use App\Http\Controllers\Panel\IaController;
use App\Http\Controllers\Panel\ProposalController;
use App\Http\Controllers\Panel\TemplateController;
use App\Http\Controllers\Panel\ClientController;
use App\Http\Controllers\Panel\ExpedientController;
use App\Http\Controllers\Panel\DocumentController;
use App\Http\Controllers\Panel\MessageController;
use App\Http\Controllers\Panel\AppointmentController;
use App\Http\Controllers\Panel\InvoiceController;
use App\Http\Controllers\Panel\BlogPostController;
use App\Http\Controllers\Panel\ContactRequestController;
use App\Http\Controllers\Panel\SettingController;
use App\Http\Controllers\Panel\AuditLogController;

// Rutas protegidas para personal (con prefijo 'panel' y nombre de ruta 'panel.*')
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// CRUD de Clientes e Impersonación (Suplantar login)
Route::get('/clientes/exportar/excel', [ClientController::class, 'exportExcel'])->name('clientes.export');
Route::post('/clientes/importar/excel', [ClientController::class, 'importExcel'])->name('clientes.import');
Route::resource('/clientes', ClientController::class);
Route::post('/clientes/{client}/impersonate', [ClientController::class, 'impersonate'])->name('clientes.impersonate');

// CRUD de Expedientes
Route::get('/expedientes/exportar/excel', [ExpedientController::class, 'exportExcel'])->name('expedientes.export');
Route::post('/expedientes/importar/excel', [ExpedientController::class, 'importExcel'])->name('expedientes.import');
Route::resource('/expedientes', ExpedientController::class);

// Catalogo de servicios, comunidades y modelos de documento (datos del despacho)
Route::get('/servicios', [CatalogController::class, 'servicios'])->name('servicios.index');
Route::get('/servicios/{service}', [CatalogController::class, 'servicio'])->name('servicios.show');
Route::get('/comunidades', [CatalogController::class, 'comunidades'])->name('comunidades.index');
Route::get('/comunidades/{community}', [CatalogController::class, 'comunidad'])->name('comunidades.show');
Route::get('/comunidades/{community}/reporte', [\App\Http\Controllers\Panel\ReporteMensualController::class, 'edit'])->name('comunidades.reporte');
Route::post('/comunidades/{community}/reporte', [\App\Http\Controllers\Panel\ReporteMensualController::class, 'guardar'])->name('comunidades.reporte.guardar');
Route::post('/comunidades/{community}/reporte/preparar', [\App\Http\Controllers\Panel\ReporteMensualController::class, 'preparar'])->name('comunidades.reporte.preparar');
Route::get('/plantillas', [CatalogController::class, 'plantillas'])->name('plantillas.index');

// Generacion de documentos desde plantillas Word
Route::post('/expedientes/{expedient}/generar', [TemplateController::class, 'generar'])->name('expedientes.generar');

// Circuito de trabajo: propuestas, documento, revision y comunicacion preparada (nada se envia)
Route::get('/propuestas', [ProposalController::class, 'index'])->name('propuestas.index');
Route::get('/propuestas/{proposal}', [ProposalController::class, 'show'])->name('propuestas.show');
Route::post('/expedientes/{expedient}/propuestas', [ProposalController::class, 'store'])->name('propuestas.store');
Route::patch('/propuestas/{proposal}', [ProposalController::class, 'update'])->name('propuestas.update');
Route::post('/propuestas/{proposal}/aprobar', [ProposalController::class, 'aprobar'])->name('propuestas.aprobar');
Route::post('/propuestas/{proposal}/generar', [ProposalController::class, 'generar'])->name('propuestas.generar');
Route::post('/propuestas/{proposal}/pdf', [ProposalController::class, 'pdf'])->name('propuestas.pdf');
Route::post('/propuestas/{proposal}/revisar', [ProposalController::class, 'revisar'])->name('propuestas.revisar');
Route::post('/propuestas/{proposal}/comunicacion', [ProposalController::class, 'comunicacion'])->name('propuestas.comunicacion');
Route::post('/propuestas/{proposal}/rechazar', [ProposalController::class, 'rechazar'])->name('propuestas.rechazar');
Route::get('/comunicaciones', [ProposalController::class, 'comunicaciones'])->name('comunicaciones.index');
// Conocimiento documental local: bandeja de hallazgos y mapa por cliente
Route::get('/documental', [\App\Http\Controllers\Panel\DocumentalController::class, 'index'])->name('documental.index');
Route::post('/documental/revisar', [\App\Http\Controllers\Panel\DocumentalController::class, 'revisar'])->name('documental.revisar');
Route::post('/documental/hallazgos/{hallazgo}', [\App\Http\Controllers\Panel\DocumentalController::class, 'decidir'])->name('documental.decidir');
Route::get('/clientes/{client}/conocimiento', [\App\Http\Controllers\Panel\DocumentalController::class, 'conocimiento'])->name('documental.conocimiento');
Route::get('/sincronizacion', [\App\Http\Controllers\Panel\SincronizacionController::class, 'index'])->name('sincronizacion.index');
Route::post('/sincronizacion/exportar', [\App\Http\Controllers\Panel\SincronizacionController::class, 'exportar'])->name('sincronizacion.exportar');
Route::post('/comunicaciones/{draft}/crear', [ProposalController::class, 'crearBorrador'])->name('comunicaciones.crear');

// Comunicaciones sin documento (solicitud de borrador; nunca se envia)
Route::post('/expedientes/{expedient}/comunicaciones', [ComunicacionController::class, 'store'])->name('comunicaciones.store');

// Razonar con IA (modelo local): propone; una persona confirma
Route::post('/expedientes/{expedient}/sugerir-servicio', [ActuacionesController::class, 'sugerir'])->name('expedientes.sugerir-servicio');
Route::post('/expedientes/{expedient}/asignar-servicio', [ActuacionesController::class, 'asignar'])->name('expedientes.asignar-servicio');
Route::post('/expedientes/{expedient}/proponer-actuaciones', [ActuacionesController::class, 'proponer'])->name('expedientes.proponer-actuaciones');
Route::post('/ia/preguntar', [IaController::class, 'preguntar'])->name('ia.preguntar');
Route::post('/expedientes/{expedient}/razonar', [IaController::class, 'razonar'])->name('expedientes.razonar');
Route::post('/expedientes/{expedient}/incorporar-dato', [IaController::class, 'incorporar'])->name('expedientes.incorporar');

// Documentos de Expediente
Route::post('/expedientes/{expedient}/documentos', [DocumentController::class, 'store'])->name('documentos.store');
Route::delete('/documentos/{document}', [DocumentController::class, 'destroy'])->name('documentos.destroy');
Route::get('/documentos/{document}/download', [DocumentController::class, 'download'])->name('documentos.download');
Route::patch('/documentos/{document}/categoria', [DocumentController::class, 'updateCategory'])->name('documentos.categoria');

// Mensajes (Chat interno por expediente)
Route::post('/expedientes/{expedient}/mensajes', [MessageController::class, 'store'])->name('mensajes.store');

// CRUD de Citas / Agenda
Route::resource('/citas', AppointmentController::class)->except(['show']);
Route::post('/citas/{id}/confirmar', [AppointmentController::class, 'confirm'])->name('citas.confirmar');
Route::post('/citas/{id}/cancelar', [AppointmentController::class, 'cancel'])->name('citas.cancelar');

// CRUD de Facturación
Route::get('/facturas/exportar/excel', [InvoiceController::class, 'exportExcel'])->name('facturas.export');
Route::post('/expediente-activo/limpiar', function () {
    session()->forget('expediente_activo');

    return back();
})->name('expediente-activo.limpiar');

// Lo que el programa detecta que pide factura (se declaran antes del resource para que no las capture {factura})
Route::get('/facturas/pendientes', [\App\Http\Controllers\Panel\PendientesFacturarController::class, 'index'])->name('facturas.pendientes');
Route::post('/facturas/pendientes/preparar-todos', [\App\Http\Controllers\Panel\PendientesFacturarController::class, 'prepararTodos'])->name('facturas.preparar-todos');
Route::post('/facturas/pendientes/{expedient}/preparar', [\App\Http\Controllers\Panel\PendientesFacturarController::class, 'preparar'])->name('facturas.preparar');
// Veri*Factu: una factura se edita solo mientras es borrador; emitida, solo cambia su cobro, se anula o se rectifica
Route::resource('/facturas', InvoiceController::class);
Route::post('/facturas/{invoice}/emitir', [InvoiceController::class, 'emitir'])->name('facturas.emitir');
Route::post('/facturas/{invoice}/anular', [InvoiceController::class, 'anular'])->name('facturas.anular');
Route::post('/facturas/{invoice}/rectificar', [InvoiceController::class, 'rectificar'])->name('facturas.rectificar');
Route::get('/facturas/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('facturas.pdf');
Route::patch('/facturas/{invoice}/estado', [InvoiceController::class, 'updateStatus'])->name('facturas.update-status');

// CRUD del Blog de actualidad
Route::resource('/blog', BlogPostController::class);

// Leads y Solicitudes de contacto de la web pública
Route::get('/solicitudes', [ContactRequestController::class, 'index'])->name('solicitudes.index');
Route::get('/solicitudes/{request}', [ContactRequestController::class, 'show'])->name('solicitudes.show');
Route::post('/solicitudes/{request}/asignar', [ContactRequestController::class, 'assign'])->name('solicitudes.assign');
Route::patch('/solicitudes/{request}/estado', [ContactRequestController::class, 'updateStatus'])->name('solicitudes.status');

// Rutas exclusivas para el rol 'admin'
Route::middleware(['role:admin'])->group(function () {
    // Configuración global del despacho
    Route::get('/configuracion', [SettingController::class, 'index'])->name('configuracion.index');
    Route::put('/configuracion', [SettingController::class, 'update'])->name('configuracion.update');
    
    // Plantillas Word: subida y asignacion a servicios
    Route::post('/plantillas/{template}/archivo', [TemplateController::class, 'subir'])->name('plantillas.archivo');
    Route::post('/servicios/{service}/plantillas', [TemplateController::class, 'asignar'])->name('servicios.plantillas.asignar');
    Route::delete('/servicios/{service}/plantillas/{template}', [TemplateController::class, 'quitar'])->name('servicios.plantillas.quitar');

    // Logs de auditoría de acciones
    Route::get('/auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');
});
