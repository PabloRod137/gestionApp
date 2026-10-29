<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BlogPost;
use App\Models\Client;
use App\Models\ContactRequest;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\SubmitsForms;
use Tests\TestCase;

/**
 * Envía cada formulario del panel y del área cliente tal y como lo dibuja la vista (ver SubmitsForms).
 * Si una vista y su controlador no se entienden, el envío falla aquí.
 */
class PanelFormsTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsForms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::where('email', 'admin@admin.es')->firstOrFail());
    }

    private function assertSaved(TestResponse $response, string $what): void
    {
        $this->assertContains($response->getStatusCode(), [200, 302], "$what: respuesta ".$response->getStatusCode());
        $bag = $response->baseResponse->getSession()?->get('errors');
        $errors = is_object($bag) ? $bag->all() : (array) $bag;
        $this->assertSame([], $errors, "$what: errores de validación");
        $this->assertNull($response->baseResponse->getSession()?->get('error'), "$what: ".json_encode($response->baseResponse->getSession()?->get('error')));
    }

    public function test_alta_y_edicion_de_clientes(): void
    {
        $before = Client::count();
        $this->assertSaved($this->submitForm('/panel/clientes/create', '/panel/clientes', method: 'POST'), 'crear cliente');
        $this->assertSame($before + 1, Client::count());

        $client = Client::firstOrFail();
        $this->assertSaved($this->submitForm("/panel/clientes/{$client->id}/edit", "/panel/clientes/{$client->id}", method: 'PUT'), 'editar cliente');
    }

    public function test_alta_y_edicion_de_expedientes(): void
    {
        $before = Expedient::count();
        $this->assertSaved($this->submitForm('/panel/expedientes/create', '/panel/expedientes', method: 'POST'), 'crear expediente');
        $this->assertSame($before + 1, Expedient::count());

        $expedient = Expedient::firstOrFail();
        $this->assertSaved($this->submitForm("/panel/expedientes/{$expedient->id}/edit", "/panel/expedientes/{$expedient->id}", method: 'PUT'), 'editar expediente');
    }

    public function test_documentos_y_mensajes_de_un_expediente(): void
    {
        $expedient = Expedient::firstOrFail();
        $page = "/panel/expedientes/{$expedient->id}";

        $this->assertSaved($this->submitForm($page, "/documentos", method: 'POST'), 'subir documento');
        $this->assertSame(1, ExpedientDocument::where('expedient_id', $expedient->id)->count());

        $before = Message::count();
        $this->assertSaved($this->submitForm($page, '/mensajes', ['contenido' => 'Mensaje de prueba del despacho'], 'POST'), 'enviar mensaje');
        $this->assertSame($before + 1, Message::count());
    }

    public function test_citas_alta_edicion_confirmar_y_cancelar(): void
    {
        $this->assertSaved($this->submitForm('/panel/citas/create', '/panel/citas', method: 'POST'), 'crear cita');
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->assertSaved($this->submitForm("/panel/citas/{$appointment->id}/edit", "/panel/citas/{$appointment->id}", method: 'PUT'), 'editar cita');
        // los botones de la lista son formularios que solo llevan el token
        $this->assertSaved($this->post("/panel/citas/{$appointment->id}/confirmar"), 'confirmar cita');
        $this->assertSame('confirmada', $appointment->fresh()->estado);
        $this->assertSaved($this->post("/panel/citas/{$appointment->id}/cancelar"), 'cancelar cita');
        $this->assertSame('cancelada', $appointment->fresh()->estado);
    }

    public function test_facturas_alta_y_cambio_de_estado(): void
    {
        $before = Invoice::count();
        $this->assertSaved($this->submitForm('/panel/facturas/create', '/panel/facturas', method: 'POST'), 'crear factura');
        $this->assertSame($before + 1, Invoice::count());

        $invoice = Invoice::where('estado', 'pendiente')->firstOrFail();
        // el botón «marcar como pagada» de la lista
        $this->assertSaved($this->submitForm('/panel/facturas', "/panel/facturas/{$invoice->id}/estado"), 'marcar factura como pagada');
        $this->assertSame('pagada', $invoice->fresh()->estado);
    }

    public function test_blog_alta_edicion_y_baja(): void
    {
        $before = BlogPost::count();
        $this->assertSaved($this->submitForm('/panel/blog/create', '/panel/blog', method: 'POST'), 'crear artículo');
        $this->assertSame($before + 1, BlogPost::count());

        $post = BlogPost::latest('id')->firstOrFail();
        $this->assertTrue($post->publicado);
        $this->assertSaved($this->submitForm("/panel/blog/{$post->id}/edit", "/panel/blog/{$post->id}", ['titulo' => 'Título cambiado'], 'PUT'), 'editar artículo');
        $this->assertSame('Título cambiado', $post->fresh()->titulo);

        // un artículo publicado desde el panel se ve en la web
        $this->get("/blog/{$post->slug}")->assertOk()->assertSee('Título cambiado');
        $this->assertSaved($this->submitForm('/panel/blog', "/panel/blog/{$post->id}", method: 'DELETE'), 'borrar artículo');
        $this->assertNull(BlogPost::find($post->id));
    }

    public function test_la_configuracion_se_guarda(): void
    {
        $this->assertSaved($this->submitForm('/panel/configuracion', '/panel/configuracion', ['company_name' => 'Despacho de prueba']), 'guardar configuración');
        $this->assertSame('Despacho de prueba', \App\Models\Setting::getValue('company_name'));
    }

    public function test_solicitudes_asignar_y_cambiar_estado(): void
    {
        $contact = ContactRequest::first() ?? ContactRequest::create([
            'nombre' => 'Visitante', 'email' => 'visitante@example.test', 'mensaje' => 'Hola', 'servicio_interes' => 'Fiscal', 'estado' => 'nuevo',
        ]);
        $page = "/panel/solicitudes/{$contact->id}";

        $this->assertSaved($this->submitForm($page, '/asignar'), 'asignar solicitud');
        $this->assertSaved($this->submitForm($page, '/estado'), 'cambiar estado de solicitud');
    }

    public function test_el_cliente_pide_cita_y_escribe_al_asesor(): void
    {
        $user = User::where('email', 'cliente@cliente.es')->firstOrFail();
        $this->actingAs($user);
        $expedient = $user->client->expedients()->firstOrFail();

        $this->assertSaved($this->submitForm('/cliente/citas', '/cliente/citas', method: 'POST'), 'pedir cita');
        $before = Message::count();
        $this->assertSaved($this->submitForm("/cliente/expedientes/{$expedient->id}", '/mensaje', ['contenido' => 'Hola, tengo una duda'], 'POST'), 'mensaje del cliente');
        $this->assertSame($before + 1, Message::count());
    }

    public function test_el_perfil_se_actualiza(): void
    {
        $this->assertSaved($this->submitForm('/profile', '/profile', ['name' => 'Nombre nuevo'], 'PATCH'), 'actualizar perfil');
        $this->assertSame('Nombre nuevo', auth()->user()->fresh()->name);
    }
}
