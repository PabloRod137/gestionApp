<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Setting;
use App\Support\Despacho;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DespachoTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_ajustes_del_panel_mandan_sobre_los_datos_legales(): void
    {
        config(['legal.legal_entity_name' => 'Entidad legal S.L.', 'legal.legal_tax_id' => 'B11111111']);
        Setting::setValue('company_name', 'Nombre comercial');
        Setting::setValue('company_nif', 'B22222222');

        $datos = Despacho::datos();

        $this->assertSame('Nombre comercial', $datos['nombre']);
        $this->assertSame('B22222222', $datos['nif']);
    }

    public function test_sin_ajustes_se_usan_los_datos_legales_y_de_contacto_de_la_web(): void
    {
        config(['legal.legal_entity_name' => 'Entidad legal S.L.', 'legal.legal_tax_id' => 'B11111111', 'site.phone' => '600 000 000']);

        $datos = Despacho::datos();

        $this->assertSame('Entidad legal S.L.', $datos['nombre']);
        $this->assertSame('B11111111', $datos['nif']);
        $this->assertSame('600 000 000', $datos['telefono']);
    }

    public function test_un_dato_de_relleno_nunca_se_imprime(): void
    {
        // por defecto los datos legales son «PENDIENTE DE COMPLETAR…»
        $datos = Despacho::datos();

        $this->assertSame('', $datos['nif']);
        $this->assertSame('', $datos['direccion']);
        $this->assertSame('Nortia', $datos['nombre']);
        $this->assertStringNotContainsString('PENDIENTE', Despacho::lineaDeContacto());
    }

    public function test_la_factura_y_los_correos_no_llevan_datos_de_otra_empresa(): void
    {
        $this->seed();
        Setting::setValue('company_name', 'Despacho de prueba');
        Setting::setValue('company_nif', 'B22222222');
        $invoice = Invoice::with(['client', 'lines', 'expedient'])->firstOrFail();

        $pdf = view('pdf.invoice', \App\Http\Controllers\Panel\InvoiceController::datosPdf($invoice))->render();

        $this->assertStringContainsString('Despacho de prueba', $pdf);
        $this->assertStringContainsString('B22222222', $pdf);
        foreach (['B39016161', '1961'] as $old) {
            $this->assertStringNotContainsString($old, $pdf);
        }
        foreach (['emails.new_message', 'emails.document_ingested'] as $mail) {
            $this->assertStringNotContainsString('Paseo de Pereda', file_get_contents(resource_path('views/'.str_replace('.', '/', $mail).'.blade.php')));
        }
    }
}
