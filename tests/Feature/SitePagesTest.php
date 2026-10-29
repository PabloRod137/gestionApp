<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        '/', '/quienes-somos', '/servicios', '/inmuebles', '/blog', '/tienda', '/carrito', '/contacto', '/reservar-cita',
        '/aviso-legal', '/politica-de-privacidad', '/politica-de-cookies', '/condiciones-de-contratacion',
        '/pago-completado', '/pago-cancelado',
        '/blog/compliance-pymes-2026', '/blog/terreno-urbano-playa-cuberris',
    ];

    public function test_todas_las_paginas_publicas_responden(): void
    {
        foreach (self::PAGES as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_una_entrada_de_blog_inexistente_da_404(): void
    {
        $this->get('/blog/no-existe')->assertNotFound();
        $this->get('/blog/..%2Fhome')->assertNotFound();
    }

    public function test_las_direcciones_antiguas_redirigen(): void
    {
        $this->get('/inmobiliaria')->assertRedirect('/inmuebles')->assertStatus(301);
        $this->get('/actualidad')->assertRedirect('/blog')->assertStatus(301);
    }

    public function test_todos_los_enlaces_internos_llevan_a_una_pagina_existente(): void
    {
        $visited = [];
        foreach (self::PAGES as $path) {
            preg_match_all('/href="(\/[^"#]*)/', $this->get($path)->getContent(), $found);
            foreach ($found[1] as $href) {
                if (isset($visited[$href]) || preg_match('#^/(css|js|assets|fonts)/#', $href)) {
                    continue;
                }
                $visited[$href] = $this->get($href)->getStatusCode();
            }
        }

        $broken = array_filter($visited, fn (int $status) => $status >= 400);
        $this->assertSame([], $broken, 'Enlaces rotos: '.json_encode($broken));
    }

    public function test_la_pagina_lleva_el_token_csrf_y_los_datos_de_contacto_de_la_configuracion(): void
    {
        config(['site.phone' => '600 111 222', 'site.email' => 'prueba@ejemplo.test']);

        $this->get('/contacto')
            ->assertSee('name="csrf-token"', false)
            ->assertSee('600 111 222')
            ->assertSee('prueba@ejemplo.test');
    }

    public function test_los_datos_legales_pendientes_se_muestran_como_pendientes(): void
    {
        $this->get('/aviso-legal')->assertSee('PENDIENTE DE COMPLETAR POR EL DESPACHO', false);
    }
}
