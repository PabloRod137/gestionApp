<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\PowerAutomateController;

/*
|--------------------------------------------------------------------------
| API Routes — Nortia
|--------------------------------------------------------------------------
|
| Aquí se registran las rutas de la API pública/privada de la aplicación.
| Estas rutas se cargan dentro del grupo de middleware "api", el cual
| no aplica verificación CSRF (ideal para webhooks externos).
|
*/

// ── Webhook WhatsApp: apagado por defecto (WHATSAPP_WEBHOOK_ENABLED) y autenticado cuando se enciende ──
Route::get('/v1/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])
    ->middleware('throttle:webhooks')
    ->name('api.whatsapp.webhook.verify');

Route::post('/v1/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:webhooks')
    ->name('api.whatsapp.webhook');

// ── Integración Power Automate / Microsoft 365 ───────────────────────────
// Todas las rutas de este grupo requieren el Bearer token configurado en
// POWER_AUTOMATE_API_TOKEN para garantizar que solo Power Automate tiene acceso.
Route::middleware('auth.powerautomate')
    ->prefix('v1/power-automate')
    ->group(function () {

        // Endpoint principal: recibe un documento de OneDrive y lo encola para
        // clasificación con IA. Responde 202 Accepted de forma inmediata.
        Route::post('/ingest', [PowerAutomateController::class, 'ingest'])
            ->name('api.powerautomate.ingest');

        // Consulta de estado: Power Automate puede hacer polling para conocer
        // el resultado del procesamiento de un job concreto.
        Route::get('/job-status/{jobId}', [PowerAutomateController::class, 'jobStatus'])
            ->name('api.powerautomate.job-status');

        // Puente con SharePoint: Power Automate recoge los avances del panel y confirma los que ya ha reflejado.
        Route::get('/estados', [\App\Http\Controllers\Api\EstadosController::class, 'pendientes'])->name('api.powerautomate.estados');
        Route::post('/estados/confirmar', [\App\Http\Controllers\Api\EstadosController::class, 'confirmar'])->name('api.powerautomate.estados.confirmar');
    });

// ── Stripe: aviso firmado de pagos (única fuente de verdad del cobro) ─────
Route::post('/stripe/webhook', \App\Http\Controllers\Api\StripeWebhookController::class)
    ->name('api.stripe.webhook');
