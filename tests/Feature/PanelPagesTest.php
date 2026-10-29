<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BlogPost;
use App\Models\Client;
use App\Models\ContactRequest;
use App\Models\Expedient;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recorre todas las pantallas del panel del despacho y del área cliente con los datos de demostración:
 * ninguna debe dar error. Lo que falla aquí es lo que vería una persona al entrar.
 */
class PanelPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** Estado o mensaje del error de una pantalla; null si carga bien. */
    private function failureOf(string $url): ?string
    {
        try {
            $this->withoutExceptionHandling();
            $status = $this->get($url)->getStatusCode();

            return $status >= 400 ? (string) $status : null;
        } catch (\Throwable $e) {
            return substr(strtok($e->getMessage(), "\n"), 0, 160);
        }
    }

    private function adminPages(): array
    {
        $client = Client::firstOrFail();
        $expedient = Expedient::firstOrFail();
        $invoice = Invoice::firstOrFail();
        $post = BlogPost::first();
        $contact = ContactRequest::first();
        $appointment = Appointment::first();

        return array_filter([
            '/panel/dashboard',
            '/panel/clientes', '/panel/clientes/create', "/panel/clientes/{$client->id}", "/panel/clientes/{$client->id}/edit",
            '/panel/expedientes', '/panel/expedientes/create', "/panel/expedientes/{$expedient->id}", "/panel/expedientes/{$expedient->id}/edit",
            '/panel/citas', '/panel/citas/create', $appointment ? "/panel/citas/{$appointment->id}/edit" : null,
            '/panel/facturas', '/panel/facturas/create', "/panel/facturas/{$invoice->id}",
            '/panel/blog', '/panel/blog/create', $post ? "/panel/blog/{$post->id}/edit" : null,
            '/panel/solicitudes', $contact ? "/panel/solicitudes/{$contact->id}" : null,
            '/panel/configuracion', '/panel/auditoria',
            '/panel/clientes/exportar/excel', '/panel/expedientes/exportar/excel', '/panel/facturas/exportar/excel',
            "/panel/facturas/{$invoice->id}/pdf", '/profile',
        ]);
    }

    public function test_el_administrador_ve_todas_las_pantallas_del_panel(): void
    {
        $this->actingAs(User::where('email', 'admin@admin.es')->firstOrFail());

        $failures = [];
        foreach ($this->adminPages() as $url) {
            $failure = $this->failureOf($url);
            if ($failure) {
                $failures[$url] = $failure;
            }
        }

        $this->assertSame([], $failures, 'Pantallas del panel con error: '.json_encode($failures, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function test_un_trabajador_ve_el_panel_salvo_lo_exclusivo_del_administrador(): void
    {
        $this->actingAs(User::where('email', 'worker1@nortia.com')->firstOrFail());

        $this->get('/panel/dashboard')->assertOk();
        $this->get('/panel/expedientes')->assertOk();
        $this->get('/panel/configuracion')->assertForbidden();
        $this->get('/panel/auditoria')->assertForbidden();
    }

    public function test_el_cliente_ve_todas_las_pantallas_de_su_area(): void
    {
        $user = User::where('email', 'cliente@cliente.es')->firstOrFail();
        $expedient = $user->client->expedients()->firstOrFail();
        $invoice = $user->client->invoices()->firstOrFail();
        $this->actingAs($user);

        $failures = [];
        foreach (['/cliente/dashboard', '/cliente/expedientes', "/cliente/expedientes/{$expedient->id}", '/cliente/citas', '/cliente/facturas', "/cliente/facturas/{$invoice->id}/pdf", '/profile'] as $url) {
            $failure = $this->failureOf($url);
            if ($failure) {
                $failures[$url] = $failure;
            }
        }

        $this->assertSame([], $failures, 'Pantallas del cliente con error: '.json_encode($failures, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function test_un_cliente_no_entra_al_panel_ni_ve_expedientes_de_otro(): void
    {
        $user = User::where('email', 'cliente@cliente.es')->firstOrFail();
        $other = Expedient::where('client_id', '!=', $user->client->id)->firstOrFail();
        $this->actingAs($user);

        $this->get('/panel/dashboard')->assertForbidden();
        $this->get("/cliente/expedientes/{$other->id}")->assertNotFound();
    }
}
