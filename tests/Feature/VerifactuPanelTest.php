<?php

namespace Tests\Feature;

use App\Http\Controllers\Panel\InvoiceController;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Verifactu\Facturacion;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreaFacturas;
use Tests\TestCase;

/** Pantallas de facturas: el flujo borrador → emitir → cobrar / anular / rectificar, y lo que ve el cliente. */
class VerifactuPanelTest extends TestCase
{
    use CreaFacturas, RefreshDatabase;

    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 10:15:30', 'Europe/Madrid'));
        $this->seed(RolesSeeder::class);
        $this->datosDelDespacho();
        $this->worker = User::factory()->create();
        $this->worker->assignRole('worker');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function formulario(Client $cliente, string $accion, array $extra = []): array
    {
        return $extra + [
            'client_id' => $cliente->id, 'estado' => 'pendiente', 'accion' => $accion, 'retencion_irpf' => '15',
            'conceptos' => ['Honorarios', 'Tasas'], 'cantidades' => [1, 1], 'precios' => ['200.00', '30.00'],
            'tipos_iva' => [21, 21], 'calificaciones' => ['S1', 'N1'],
        ];
    }

    public function test_guardar_borrador_no_numera_ni_registra(): void
    {
        $cliente = $this->clienteDePrueba();

        $r = $this->actingAs($this->worker)->post('/panel/facturas', $this->formulario($cliente, 'borrador'));

        $f = Invoice::firstOrFail();
        $r->assertRedirect(route('panel.facturas.show', $f->id));
        $this->assertSame('borrador', $f->emision);
        $this->assertStringStartsWith('BORRADOR-', $f->numero);
        $this->assertSame(2, $f->lines()->count());
        $this->assertSame('0.00', $f->lines()->where('calificacion', 'N1')->first()->tipo_iva);
        $this->assertSame(0, $f->registros()->count());
    }

    public function test_guardar_y_emitir_numera_y_crea_el_registro(): void
    {
        $cliente = $this->clienteDePrueba();

        $this->actingAs($this->worker)->post('/panel/facturas', $this->formulario($cliente, 'emitir'))->assertSessionHas('success');

        $f = Invoice::firstOrFail();
        $this->assertSame('F2026-0001', $f->numero);
        $this->assertSame('emitida', $f->emision);
        $this->assertSame('230.00', $f->base_imponible);
        $this->assertSame('42.00', $f->cuota_iva);
        $this->assertSame('272.00', $f->total);
        $this->assertSame('30.00', $f->retencion_importe);
        $this->assertSame(1, $f->registros()->count());
        $this->assertDatabaseHas('audit_logs', ['accion' => 'emitir_factura', 'modelo_id' => $f->id, 'user_id' => $this->worker->id]);
    }

    public function test_en_produccion_emitir_con_el_nif_del_cliente_mal_deja_el_borrador_y_explica_por_que(): void
    {
        config(['verifactu.entorno' => 'produccion']);
        $cliente = $this->clienteDePrueba('12345678A');
        $this->actingAs($this->worker)->post('/panel/facturas', $this->formulario($cliente, 'borrador'));
        $f = Invoice::firstOrFail();

        $this->actingAs($this->worker)->post("/panel/facturas/{$f->id}/emitir")
            ->assertRedirect(route('panel.facturas.show', $f->id))
            ->assertSessionHas('error');

        $this->assertSame('borrador', $f->fresh()->emision);
        $this->actingAs($this->worker)->get("/panel/facturas/{$f->id}")->assertOk()->assertSee('NIF del cliente no es válido');
    }

    public function test_un_borrador_se_edita_y_se_borra_desde_el_panel(): void
    {
        $cliente = $this->clienteDePrueba();
        $f = $this->borrador($cliente);

        $this->actingAs($this->worker)->get("/panel/facturas/{$f->id}/edit")->assertOk()->assertSee('Asesoramiento');
        $this->actingAs($this->worker)->put("/panel/facturas/{$f->id}", $this->formulario($cliente, 'borrador', ['conceptos' => ['Nuevo'], 'cantidades' => [1], 'precios' => ['10'], 'tipos_iva' => [21], 'calificaciones' => ['S1']]))->assertRedirect();
        $this->assertSame(['Nuevo'], $f->fresh()->lines->pluck('concepto')->all());

        $this->actingAs($this->worker)->delete("/panel/facturas/{$f->id}")->assertRedirect(route('panel.facturas.index'));
        $this->assertNull(Invoice::find($f->id));
    }

    public function test_una_emitida_no_se_edita_ni_se_borra_pero_si_se_cobra(): void
    {
        $f = Facturacion::emitir($this->borrador($this->clienteDePrueba()));

        $this->actingAs($this->worker)->get("/panel/facturas/{$f->id}/edit")->assertForbidden();
        $this->actingAs($this->worker)->put("/panel/facturas/{$f->id}", $this->formulario($f->client, 'borrador'))->assertForbidden();
        $this->actingAs($this->worker)->delete("/panel/facturas/{$f->id}")->assertRedirect(route('panel.facturas.show', $f->id))->assertSessionHas('error');
        $this->assertNotNull(Invoice::find($f->id));

        $this->actingAs($this->worker)->patch("/panel/facturas/{$f->id}/estado", ['estado' => 'pagada'])->assertRedirect();
        $this->assertSame('pagada', $f->fresh()->estado);
    }

    public function test_la_ficha_de_una_emitida_muestra_su_registro_y_las_acciones_de_anular_y_rectificar(): void
    {
        $f = Facturacion::emitir($this->borrador($this->clienteDePrueba()));

        $this->actingAs($this->worker)->get("/panel/facturas/{$f->id}")->assertOk()
            ->assertSee('F2026-0001')->assertSee('Registros de facturación')->assertSee(substr($f->registros->first()->huella, 0, 16))
            ->assertSee('Pendiente de envío')->assertSee('Anular esta factura')->assertSee('Rectificar esta factura')->assertDontSee('Emitir factura');
    }

    public function test_anular_desde_el_panel_exige_motivo_y_crea_el_registro(): void
    {
        $f = Facturacion::emitir($this->borrador($this->clienteDePrueba()));

        $this->actingAs($this->worker)->post("/panel/facturas/{$f->id}/anular", ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->actingAs($this->worker)->post("/panel/facturas/{$f->id}/anular", ['motivo' => 'Duplicada'])->assertSessionHas('success');

        $this->assertSame('anulada', $f->fresh()->emision);
        $this->assertSame(['alta', 'anulacion'], $f->registros()->pluck('tipo')->all());
    }

    public function test_rectificar_crea_un_borrador_enlazado_y_lo_deja_listo_para_corregir(): void
    {
        $f = Facturacion::emitir($this->borrador($this->clienteDePrueba()));

        $r = $this->actingAs($this->worker)->post("/panel/facturas/{$f->id}/rectificar", ['tipo' => 'S', 'clave' => 'R1', 'motivo' => 'Error en el importe']);

        $nueva = Invoice::where('rectifica_invoice_id', $f->id)->firstOrFail();
        $r->assertRedirect(route('panel.facturas.edit', $nueva->id));
        $this->assertSame(['borrador', 'R1', 'S'], [$nueva->emision, $nueva->tipo_factura, $nueva->tipo_rectificativa]);
        $this->actingAs($this->worker)->get("/panel/facturas/{$nueva->id}/edit")->assertOk()->assertSee('Rectifica la factura F2026-0001');
    }

    public function test_el_pdf_de_una_emitida_lleva_el_qr_tributario_y_el_de_un_borrador_no(): void
    {
        $emitida = Facturacion::emitir($this->borrador($this->clienteDePrueba()));
        $borrador = $this->borrador($this->clienteDePrueba('12345678Z', 'Otro'));

        $conQr = view('pdf.invoice', InvoiceController::datosPdf($emitida->fresh(['client', 'lines'])))->render();
        $sinQr = view('pdf.invoice', InvoiceController::datosPdf($borrador->fresh(['client', 'lines'])))->render();

        $this->assertStringContainsString('QR tributario:', $conQr);
        $this->assertStringContainsString('data:image/png;base64,', $conQr);
        $this->assertStringContainsString('VERI*FACTU', $conQr);
        $this->assertStringContainsString('Factura verificable en la sede electrónica de la AEAT', $conQr);
        $this->assertStringContainsString('F2026-0001', $conQr);
        $this->assertStringNotContainsString('QR tributario', $sinQr);
        $this->assertStringContainsString('BORRADOR', $sinQr);

        $this->actingAs($this->worker)->get("/panel/facturas/{$emitida->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_el_cliente_no_ve_borradores_y_las_anuladas_no_son_deuda(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole('client');
        $cliente = Client::create(['codigo' => 'C-X', 'razon_social' => 'Cliente Portal', 'nif' => '89890001K', 'tipo' => 'empresa', 'user_id' => $usuario->id]);
        $borrador = $this->borrador($cliente, [['Secreto del borrador', 1, 50.0]]);
        $emitida = Facturacion::emitir($this->borrador($cliente, [['Visible', 1, 100.0]]));
        $anulada = Facturacion::emitir($this->borrador($cliente, [['Equivocada', 1, 999.0]]));
        Facturacion::anular($anulada, 'Error');

        $pagina = $this->actingAs($usuario)->get('/cliente/facturas')->assertOk();
        $pagina->assertSee('F2026-0001')->assertDontSee('Secreto del borrador')->assertDontSee('BORRADOR-');
        $this->actingAs($usuario)->get("/cliente/facturas/{$borrador->id}/pdf")->assertNotFound();
        $this->actingAs($usuario)->get("/cliente/facturas/{$emitida->id}/pdf")->assertOk();

        $this->assertSame(1, $cliente->invoices()->pendientesDeCobro()->count());
        $this->actingAs($usuario)->get('/cliente/dashboard')->assertOk()->assertDontSee('999');
    }

    public function test_el_listado_distingue_borradores_emitidas_y_filtra_por_situacion(): void
    {
        $cliente = $this->clienteDePrueba();
        Facturacion::emitir($this->borrador($cliente));
        $this->borrador($cliente, [['Pendiente de emitir', 1, 5.0]]);

        $this->actingAs($this->worker)->get('/panel/facturas')->assertOk()->assertSee('F2026-0001')->assertSee('Borrador');
        $this->actingAs($this->worker)->get('/panel/facturas?emision=borrador')->assertOk()->assertDontSee('F2026-0001');
    }
}
