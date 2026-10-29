<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\ExpedientController;
use App\Http\Controllers\Client\DocumentController;
use App\Http\Controllers\Client\MessageController;
use App\Http\Controllers\Client\AppointmentController;
use App\Http\Controllers\Client\InvoiceController;
use App\Http\Controllers\Client\ChatController;
use App\Http\Controllers\Client\DatosController;

// Rutas protegidas para clientes (con prefijo 'cliente' y nombre de ruta 'cliente.*')
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Expedientes
Route::get('/expedientes', [ExpedientController::class, 'index'])->name('expedientes.index');
Route::get('/expedientes/{expedient}', [ExpedientController::class, 'show'])->name('expedientes.show');

// Descarga de documentos del expediente
Route::get('/documentos/{document}', [DocumentController::class, 'download'])->name('documentos.download');

// Mensajes (Chat interno por expediente)
Route::post('/expedientes/{expedient}/mensaje', [MessageController::class, 'store'])->name('mensajes.store');

// Citas de asesoramiento
Route::get('/citas', [AppointmentController::class, 'index'])->name('citas.index');
Route::post('/citas', [AppointmentController::class, 'store'])->name('citas.store');

// Facturas y descarga de PDF
Route::get('/facturas', [InvoiceController::class, 'index'])->name('facturas.index');
Route::get('/facturas/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('facturas.pdf');

// Asistente privado (sin IA): solo responde con los datos del cliente de la sesión
Route::post('/chat', [ChatController::class, 'message'])->middleware('throttle:chat-cliente')->name('chat');

// Mis datos: el cliente completa los datos que faltan en su ficha (solo huecos; nunca pisa lo que ya consta)
Route::get('/mis-datos', [DatosController::class, 'edit'])->name('datos.edit');
Route::post('/mis-datos', [DatosController::class, 'update'])->middleware('throttle:chat-cliente')->name('datos.update');
