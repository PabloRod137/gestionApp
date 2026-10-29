<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Detrás de un proxy o un túnel con https las direcciones salen con https; sin configurarlo, las cabeceras X-Forwarded-* se ignoran. */
class ProxyDeConfianzaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        parent::tearDown();
    }

    private function conCabeceras()
    {
        return $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'demo.ejemplo.test', 'X-Forwarded-Port' => '443']);
    }

    public function test_sin_proxies_de_confianza_se_ignoran_las_cabeceras_reenviadas(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->conCabeceras()->get('/contacto')->assertOk()->assertDontSee('https://demo.ejemplo.test', false);
    }

    public function test_con_un_proxy_de_confianza_las_direcciones_salen_con_https(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->conCabeceras()->get('/contacto')->assertOk()->assertSee('https://demo.ejemplo.test/', false);
    }
}
