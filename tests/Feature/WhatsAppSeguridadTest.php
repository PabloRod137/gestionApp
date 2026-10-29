<?php

namespace Tests\Feature;

use App\Support\NombreArchivoSeguro;
use App\Support\UrlSegura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** El webhook de WhatsApp (integración heredada) no puede ser una puerta abierta: apagado, autenticado y sin descargas a ciegas. */
class WhatsAppSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/whatsapp/webhook';

    protected function setUp(): void
    {
        parent::setUp();
        UrlSegura::$resolutor = fn (string $host) => ['93.184.216.34']; // una IP pública cualquiera: las pruebas no usan la red
    }

    protected function tearDown(): void
    {
        UrlSegura::$resolutor = null;
        parent::tearDown();
    }

    private function encender(array $extra = []): void
    {
        config(['services.whatsapp' => array_merge([
            'enabled' => true, 'verify_token' => 'verificacion-de-prueba', 'api_token' => 'clave-api-de-prueba', 'app_secret' => null,
            'webhook_secret' => 'secreto-de-prueba', 'media_hosts' => ['lookaside.fbsbx.com', '*.fbcdn.net'], 'max_bytes' => 1024,
        ], $extra)]);
    }

    private function cuerpo(array $extra = []): array
    {
        return $extra + ['phone' => '34600112233', 'media_url' => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=1', 'filename' => 'resguardo.pdf'];
    }

    public function test_viene_apagado_por_defecto_y_no_responde_a_nadie(): void
    {
        $this->assertFalse(config('services.whatsapp.enabled'));
        Http::fake();

        $this->postJson(self::URL, $this->cuerpo())->assertNotFound();
        $this->getJson(self::URL.'?hub_mode=subscribe&hub_verify_token=x&hub_challenge=1')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_encendido_sin_ningun_secreto_configurado_rechaza_todo(): void
    {
        $this->encender(['webhook_secret' => null, 'app_secret' => null]);
        Http::fake();

        $this->postJson(self::URL, $this->cuerpo())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_sin_credenciales_o_con_unas_falsas_no_se_descarga_nada(): void
    {
        $this->encender();
        Http::fake();

        $this->postJson(self::URL, $this->cuerpo())->assertStatus(401);
        $this->postJson(self::URL, $this->cuerpo(), ['Authorization' => 'Bearer otra-cosa'])->assertStatus(401);
        $this->postJson(self::URL, $this->cuerpo(), ['X-Hub-Signature-256' => 'sha256=falsa'])->assertStatus(401);
        Http::assertNothingSent();
    }

    public function test_la_firma_de_meta_se_acepta_si_coincide_con_el_cuerpo(): void
    {
        $this->encender(['webhook_secret' => null, 'app_secret' => 'secreto-de-la-app']);
        Http::fake(['lookaside.fbsbx.com/*' => Http::response('x', 404)]);
        $cuerpo = json_encode($this->cuerpo());
        $firma = 'sha256='.hash_hmac('sha256', $cuerpo, 'secreto-de-la-app');

        // firma buena: pasa la autenticación (la descarga falla con 404 y devuelve 502, no 401)
        $this->call('POST', self::URL, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $firma, 'HTTP_ACCEPT' => 'application/json'], $cuerpo)->assertStatus(502);
        // firma de otro cuerpo: no
        $otra = 'sha256='.hash_hmac('sha256', 'otro cuerpo', 'secreto-de-la-app');
        $this->call('POST', self::URL, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $otra, 'HTTP_ACCEPT' => 'application/json'], $cuerpo)->assertStatus(401);
    }

    public function test_no_descarga_de_servidores_fuera_de_la_lista_ni_de_redes_internas_ni_sin_https(): void
    {
        $this->encender();
        Http::fake();
        $cabeceras = ['Authorization' => 'Bearer secreto-de-prueba'];

        foreach ([
            'https://atacante.example/robar',                        // fuera de la lista: la clave de la API no puede ir allí
            'http://lookaside.fbsbx.com/archivo',                    // sin https
            'https://127.0.0.1/admin', 'https://169.254.169.254/latest/meta-data', 'https://10.0.0.5/interno',   // direcciones internas
            'https://lookaside.fbsbx.com.atacante.example/x',        // truco de dominio
            'https://usuario:clave@lookaside.fbsbx.com/x',           // credenciales en la dirección
            'https://lookaside.fbsbx.com:8443/x',                    // puerto raro
        ] as $url) {
            $this->postJson(self::URL, $this->cuerpo(['media_url' => $url]), $cabeceras)->assertStatus(422);
        }
        Http::assertNothingSent();
    }

    public function test_un_servidor_de_la_lista_que_resuelve_a_una_red_interna_se_rechaza(): void
    {
        $this->encender();
        UrlSegura::$resolutor = fn (string $host) => ['192.168.1.10'];
        Http::fake();

        $this->postJson(self::URL, $this->cuerpo(), ['Authorization' => 'Bearer secreto-de-prueba'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_la_clave_de_la_api_solo_va_al_servidor_permitido_y_no_se_siguen_redirecciones(): void
    {
        $this->encender();
        Http::fake(['lookaside.fbsbx.com/*' => Http::response('', 302, ['Location' => 'https://atacante.example/capturar'])]);

        $this->postJson(self::URL, $this->cuerpo(), ['Authorization' => 'Bearer secreto-de-prueba'])->assertStatus(502);

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=1' && $r->hasHeader('Authorization', 'Bearer clave-api-de-prueba'));
    }

    public function test_un_archivo_mas_grande_que_el_limite_se_rechaza(): void
    {
        $this->encender(['max_bytes' => 100]);
        Http::fake(['lookaside.fbsbx.com/*' => Http::response(str_repeat('a', 500), 200, ['Content-Type' => 'application/pdf'])]);

        $this->postJson(self::URL, $this->cuerpo(), ['Authorization' => 'Bearer secreto-de-prueba'])->assertStatus(413);
    }

    public function test_un_nombre_con_rutas_no_escribe_fuera_de_la_carpeta_temporal(): void
    {
        $this->encender();
        Http::fake(['lookaside.fbsbx.com/*' => Http::response('%PDF-1.4 contenido', 200, ['Content-Type' => 'application/pdf'])]);
        $fuera = storage_path('app/temp/whatsapp/../../PRUEBA_ESCAPE.txt');
        @unlink($fuera);

        $this->postJson(self::URL, $this->cuerpo(['filename' => '../../PRUEBA_ESCAPE.txt']), ['Authorization' => 'Bearer secreto-de-prueba']);

        $this->assertFileDoesNotExist(storage_path('app/PRUEBA_ESCAPE.txt'));
        $this->assertFileDoesNotExist(storage_path('PRUEBA_ESCAPE.txt'));
    }

    public function test_el_nombre_de_archivo_se_limpia(): void
    {
        $this->assertSame('passwd', NombreArchivoSeguro::limpiar('../../../etc/passwd'));
        $this->assertSame('x.php', NombreArchivoSeguro::limpiar('..\\..\\windows\\x.php'));
        $this->assertSame('documento.bin', NombreArchivoSeguro::limpiar(''));
        $this->assertSame('documento.bin', NombreArchivoSeguro::limpiar('...'));
        $this->assertSame('factura enero.pdf', NombreArchivoSeguro::limpiar("factura enero.pdf\0"));
        $this->assertSame('informe_final_.pdf', NombreArchivoSeguro::limpiar('informe<final>.pdf'));
        $this->assertSame('ñandú.pdf', NombreArchivoSeguro::limpiar('ñandú.pdf'));
        $this->assertLessThanOrEqual(120, mb_strlen(NombreArchivoSeguro::limpiar(str_repeat('a', 400).'.pdf')));
        $this->assertStringEndsWith('.pdf', NombreArchivoSeguro::limpiar(str_repeat('a', 400).'.pdf'));
        $this->assertStringNotContainsString('/', NombreArchivoSeguro::limpiar('a/b/c.txt'));
    }

    public function test_url_segura_acepta_comodines_pero_no_trucos_de_dominio(): void
    {
        $this->assertTrue(UrlSegura::permitida('https://scontent.xx.fbcdn.net/v/archivo', ['*.fbcdn.net']));
        $this->assertFalse(UrlSegura::permitida('https://fbcdn.net/archivo', ['*.fbcdn.net']));
        $this->assertFalse(UrlSegura::permitida('https://evilfbcdn.net/archivo', ['*.fbcdn.net']));
        $this->assertFalse(UrlSegura::permitida('https://scontent.fbcdn.net.evil.com/archivo', ['*.fbcdn.net']));
        $this->assertFalse(UrlSegura::permitida('ftp://scontent.fbcdn.net/archivo', ['*.fbcdn.net']));
        $this->assertFalse(UrlSegura::permitida('no es una url', ['*.fbcdn.net']));
    }
}
