<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Expedient;
use App\Models\Invoice;
use App\Observers\AuditObserver;
use App\Events\DocumentIngestedEvent;
use App\Listeners\SendDocumentIngestedNotification;
use App\Shop\StripeGateway;
use App\Shop\StripeSdkGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra servicios de la aplicación.
     */
    public function register(): void
    {
        // Pasarela de pago: Stripe real. Las pruebas la sustituyen por un doble.
        $this->app->singleton(StripeGateway::class, fn () => new StripeSdkGateway((string) config('shop.payments.stripe.secret_key')));

        // Correo del despacho (Microsoft 365): solo borradores, nunca envío
        $this->app->bind(\App\Support\Outlook\DraftGateway::class, \App\Support\Outlook\GraphDraftGateway::class);
        $this->app->bind(\App\Support\Pdf\ConversorPdf::class, fn () => \App\Support\Pdf\ConversorPdfAutomatico::desdeConfiguracion());
    }

    /**
     * Inicializa los servicios de la aplicación (bootstrapping).
     */
    public function boot(): void
    {
        // Límites de peticiones de la web pública: formularios (contacto y reservas) y compras
        $tooMany = fn () => response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        RateLimiter::for('forms', fn (Request $request) => Limit::perMinutes(15, 10)->by($request->ip())->response($tooMany));
        RateLimiter::for('shop', fn (Request $request) => Limit::perMinutes(15, 20)->by($request->ip())->response($tooMany));
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinutes(15, 60)->by($request->ip())->response($tooMany));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip())->response($tooMany));
        RateLimiter::for('chat-cliente', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())->response($tooMany));

        // Registrar el observador de auditoría para los modelos críticos
        Client::observe(AuditObserver::class);
        Expedient::observe(AuditObserver::class);
        Invoice::observe(AuditObserver::class);
        // Evento: un expediente cumple la regla de facturación -> borrador de factura (nunca se emite solo)
        Expedient::observe(\App\Observers\FacturaDesdeExpedienteObserver::class);

        // Registrar evento y oyente del motor de ingesta automática
        Event::listen(
            DocumentIngestedEvent::class,
            SendDocumentIngestedNotification::class
        );
    }
}
