<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Services\DocumentClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;
    protected User $clientUser;
    protected User $advisorUser;
    protected Expedient $expedient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);

        Storage::fake('local');

        // Crear usuarios
        $this->clientUser = User::factory()->create([
            'name' => 'Cliente de Pruebas',
            'email' => 'client-test@nortia.com',
        ]);
        $this->clientUser->assignRole('client');

        $this->advisorUser = User::factory()->create([
            'name' => 'Asesor de Pruebas',
            'email' => 'advisor-test@nortia.com',
        ]);
        $this->advisorUser->assignRole('worker');

        // Crear cliente
        $this->client = Client::create([
            'user_id' => $this->clientUser->id,
            'nif' => '12345678A',
            'razon_social' => 'Servicios Test S.L.',
            'tipo' => 'empresa',
            'telefono' => '600112233',
            'direccion' => 'Calle Falsa 123, Santander',
        ]);

        // Crear servicio
        $service = \App\Models\Service::create([
            'nombre' => 'Servicio Fiscal',
            'descripcion' => 'Servicio Fiscal de Prueba',
            'precio_base' => 100.00,
            'tipo' => 'fiscal',
            'activo' => true
        ]);

        // Crear expediente
        $this->expedient = Expedient::create([
            'client_id' => $this->client->id,
            'service_id' => $service->id,
            'titulo' => 'Expediente de Prueba',
            'descripcion' => 'Descripción de prueba',
            'estado' => 'abierto',
            'fecha_apertura' => now(),
        ]);
    }

    /**
     * Test de clasificación exitosa mockeando la API de Anthropic.
     */
    public function test_document_classification_success(): void
    {
        // Mockear la respuesta HTTP de Anthropic Claude
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    [
                        'text' => json_encode([
                            'categoria' => 'nomina',
                            'confianza' => 'alta'
                        ])
                    ]
                ]
            ], 200)
        ]);

        // Configurar la API key en config para simular entorno configurado
        config(['services.anthropic.key' => 'fake-api-key']);

        // Crear documento
        $document = ExpedientDocument::create([
            'expedient_id' => $this->expedient->id,
            'client_id' => $this->client->id,
            'nombre' => 'nomina_abril_2026.pdf',
            'ruta' => 'expedientes/1/nomina_abril_2026.pdf',
            'tipo_mime' => 'application/pdf',
            'subido_por' => $this->clientUser->id,
            'visibilidad' => 'interno',
            'canal_entrada' => 'portal'
        ]);

        // Escribir un archivo fake en Storage
        Storage::disk('local')->put($document->ruta, 'fake pdf content');

        // Clasificar
        app(DocumentClassificationService::class)->classify($document);

        // Refrescar y comprobar base de datos
        $document->refresh();

        $this->assertEquals('nomina', $document->categoria);
        $this->assertEquals('alta', $document->categoria_confianza);
    }

    /**
     * Test de clasificación cuando la API de Anthropic falla (debe fallar silenciosamente).
     */
    public function test_document_classification_fails_gracefully(): void
    {
        // Mockear fallo en la llamada HTTP
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response('Internal Server Error', 500)
        ]);

        config(['services.anthropic.key' => 'fake-api-key']);

        $document = ExpedientDocument::create([
            'expedient_id' => $this->expedient->id,
            'client_id' => $this->client->id,
            'nombre' => 'archivo_raro.docx',
            'ruta' => 'expedientes/1/archivo_raro.docx',
            'tipo_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'subido_por' => $this->clientUser->id,
            'visibilidad' => 'interno',
            'canal_entrada' => 'portal'
        ]);

        // Clasificar (no debe lanzar excepción alguna)
        app(DocumentClassificationService::class)->classify($document);

        $document->refresh();

        $this->assertNull($document->categoria);
        $this->assertNull($document->categoria_confianza);
    }

    /**
     * Test de la ruta PATCH para actualizar la categoría manualmente.
     */
    public function test_advisors_can_manually_update_document_category(): void
    {
        $document = ExpedientDocument::create([
            'expedient_id' => $this->expedient->id,
            'client_id' => $this->client->id,
            'nombre' => 'contrato_arrendamiento.pdf',
            'ruta' => 'expedientes/1/contrato_arrendamiento.pdf',
            'tipo_mime' => 'application/pdf',
            'subido_por' => $this->clientUser->id,
            'visibilidad' => 'interno',
            'canal_entrada' => 'portal',
            'categoria' => 'otro',
            'categoria_confianza' => 'baja'
        ]);

        // Autenticar como asesor/personal de la gestoría
        $response = $this->actingAs($this->advisorUser)
            ->patchJson(route('panel.documentos.categoria', $document->id), [
                'categoria' => 'contrato'
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $document->refresh();

        $this->assertEquals('contrato', $document->categoria);
        $this->assertEquals('alta', $document->categoria_confianza);
    }

    /**
     * Test de validación de la ruta PATCH.
     */
    public function test_updating_document_category_requires_valid_value(): void
    {
        $document = ExpedientDocument::create([
            'expedient_id' => $this->expedient->id,
            'client_id' => $this->client->id,
            'nombre' => 'factura_luz.pdf',
            'ruta' => 'expedientes/1/factura_luz.pdf',
            'tipo_mime' => 'application/pdf',
            'subido_por' => $this->clientUser->id,
            'visibilidad' => 'interno',
            'canal_entrada' => 'portal'
        ]);

        // Enviar un valor inválido
        $response = $this->actingAs($this->advisorUser)
            ->patchJson(route('panel.documentos.categoria', $document->id), [
                'categoria' => 'categoria_inexistente'
            ]);

        $response->assertStatus(422);

        $document->refresh();
        $this->assertNull($document->categoria);
    }
}
