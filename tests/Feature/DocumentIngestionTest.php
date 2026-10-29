<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\ExpedientDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use App\Events\DocumentIngestedEvent;
use Tests\TestCase;

class DocumentIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;
    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles so Spatie assignments work correctly
        $this->seed(\Database\Seeders\RolesSeeder::class);

        // Limpiar los discos locales de prueba
        Storage::fake('local');

        // Crear un usuario cliente para las pruebas
        $this->clientUser = User::factory()->create([
            'name' => 'Cliente de Pruebas',
            'email' => 'client-test@nortia.com',
        ]);
        $this->clientUser->assignRole('client');

        // Crear su ficha de cliente correspondiente
        $this->client = Client::create([
            'user_id' => $this->clientUser->id,
            'nif' => '12345678A',
            'razon_social' => 'Servicios Test S.L.',
            'tipo' => 'empresa',
            'telefono' => '600112233',
            'direccion' => 'Calle Falsa 123, Santander',
        ]);
    }

    /**
     * Test de ingesta automática por simulación de Email (Mock).
     */
    public function test_email_polling_command_ingests_attachment_correctly(): void
    {
        // Desactivar envío de correo real
        Mail::fake();

        $mockDir = storage_path('app/mock_emails');
        if (!file_exists($mockDir)) {
            mkdir($mockDir, 0777, true);
        }

        // Crear un archivo simulado en el directorio mock_emails
        // Patrón: email_nombrearchivo.ext
        $mockFilePath = $mockDir . '/client-test@nortia.com_balance_trimestral.xlsx';
        file_put_contents($mockFilePath, 'fake excel content');

        $this->assertTrue(file_exists($mockFilePath));

        // Ejecutar el comando Artisan
        $this->artisan('mail:poll')
            ->assertSuccessful();

        // Verificar que el archivo simulado se ha procesado y eliminado
        $this->assertFalse(file_exists($mockFilePath));

        // Verificar que se creó el registro en la base de datos
        $this->assertDatabaseHas('expedient_documents', [
            'client_id' => $this->client->id,
            'nombre' => 'balance_trimestral.xlsx',
            'canal_entrada' => 'email',
        ]);

        // Verificar que el archivo se guardó físicamente en el storage privado de Laravel
        $document = ExpedientDocument::where('client_id', $this->client->id)
            ->where('canal_entrada', 'email')
            ->first();

        $this->assertNotNull($document);
        Storage::disk('local')->assertExists($document->ruta);
    }

    /**
     * Test de ingesta por escáner de oficina local.
     */
    public function test_scanner_polling_command_maps_client_by_nif(): void
    {
        Mail::fake();

        $scanDir = storage_path('app/scans');
        if (!file_exists($scanDir)) {
            mkdir($scanDir, 0777, true);
        }

        // Crear archivo simulado del escáner que contiene el NIF en el nombre
        $scanFilePath = $scanDir . '/12345678A_documentacion_escaneada.pdf';
        file_put_contents($scanFilePath, 'fake pdf content');

        $this->assertTrue(file_exists($scanFilePath));

        // Ejecutar el comando del escáner
        $this->artisan('scans:poll')
            ->assertSuccessful();

        // Verificar que el archivo origen fue procesado y eliminado
        $this->assertFalse(file_exists($scanFilePath));

        // Verificar registro en BD
        $this->assertDatabaseHas('expedient_documents', [
            'client_id' => $this->client->id,
            'nombre' => '12345678A_documentacion_escaneada.pdf',
            'canal_entrada' => 'escaner',
        ]);

        // Verificar guardado en Storage privado
        $document = ExpedientDocument::where('client_id', $this->client->id)
            ->where('canal_entrada', 'escaner')
            ->first();

        $this->assertNotNull($document);
        Storage::disk('local')->assertExists($document->ruta);
    }

    /**
     * Test de ingesta por escáner de oficina usando ID del cliente.
     */
    public function test_scanner_polling_command_maps_client_by_id(): void
    {
        Mail::fake();

        $scanDir = storage_path('app/scans');
        if (!file_exists($scanDir)) {
            mkdir($scanDir, 0777, true);
        }

        // Crear archivo simulado del escáner que contiene "id_{id}" en el nombre
        $scanFilePath = $scanDir . '/id_' . $this->client->id . '_contrato.docx';
        file_put_contents($scanFilePath, 'fake word content');

        // Ejecutar escaneo
        $this->artisan('scans:poll')
            ->assertSuccessful();

        $this->assertFalse(file_exists($scanFilePath));

        $this->assertDatabaseHas('expedient_documents', [
            'client_id' => $this->client->id,
            'nombre' => 'id_' . $this->client->id . '_contrato.docx',
            'canal_entrada' => 'escaner',
        ]);
    }

    /**
     * Test del Webhook de WhatsApp recibiendo un archivo multimedia.
     */
    public function test_whatsapp_webhook_ingests_media_file_correctly(): void
    {
        Mail::fake();

        // Simular descarga HTTP del adjunto
        Http::fake([
            'https://api.whatsapp.com/v2/media/12345' => Http::response('fake whatsapp attachment content', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        // El webhook está apagado por defecto y exige credenciales: aquí se enciende con una clave compartida de prueba
        config(['services.whatsapp.enabled' => true, 'services.whatsapp.webhook_secret' => 'secreto-de-prueba', 'services.whatsapp.media_hosts' => ['api.whatsapp.com']]);
        \App\Support\UrlSegura::$resolutor = fn (string $host) => ['93.184.216.34'];

        // Simular petición POST de la pasarela de WhatsApp al webhook
        $response = $this->postJson('/api/v1/whatsapp/webhook', [
            'phone' => '34600112233', // Coincide con los últimos 9 dígitos de 600112233
            'media_url' => 'https://api.whatsapp.com/v2/media/12345',
            'filename' => 'resguardo_pago.pdf'
        ], ['Authorization' => 'Bearer secreto-de-prueba']);
        \App\Support\UrlSegura::$resolutor = null;

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success'
            ]);

        // Verificar BD
        $this->assertDatabaseHas('expedient_documents', [
            'client_id' => $this->client->id,
            'nombre' => 'resguardo_pago.pdf',
            'canal_entrada' => 'whatsapp',
        ]);

        // Verificar almacenamiento
        $document = ExpedientDocument::where('client_id', $this->client->id)
            ->where('canal_entrada', 'whatsapp')
            ->first();

        $this->assertNotNull($document);
        Storage::disk('local')->assertExists($document->ruta);
    }
}
