<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identidad visual: tipografía alojada en el propio servidor (sin pedir nada a Google), una sola hoja de estilos
 * para la web pública y cada clase de las vistas con su regla (así no vuelve a quedar una sección sin estilos).
 */
class IdentidadVisualTest extends TestCase
{
    use RefreshDatabase;

    private function css(): string
    {
        return (string) file_get_contents(public_path('css/main.css'));
    }

    public function test_las_fuentes_se_sirven_desde_el_propio_servidor_y_existen(): void
    {
        $css = $this->css();
        preg_match_all("#url\('(/fonts/[^']+)'\)#", $css, $m);
        $this->assertGreaterThanOrEqual(3, count($m[1]), 'Faltan declaraciones @font-face');
        foreach ($m[1] as $ruta) {
            $this->assertFileExists(public_path(ltrim($ruta, '/')), "No existe $ruta");
        }
        $this->assertFileExists(public_path('fonts/LICENSE-Inter.txt'));
        $this->assertFileExists(public_path('fonts/LICENSE-SourceSerif4.txt'));
        $this->assertStringContainsString("'Source Serif 4'", $css);
        $this->assertStringContainsString("'Inter'", $css);
    }

    public function test_ninguna_pagina_ni_capa_pide_fuentes_a_terceros(): void
    {
        foreach (['/', '/servicios', '/contacto', '/reservar-cita', '/login', '/aviso-legal'] as $ruta) {
            $html = $this->get($ruta)->getContent();
            $this->assertStringNotContainsString('fonts.googleapis.com', $html, $ruta);
            $this->assertStringNotContainsString('fonts.gstatic.com', $html, $ruta);
        }
        foreach (glob(resource_path('views/layouts/*.blade.php')) as $capa) {
            $this->assertStringNotContainsString('fonts.googleapis.com', (string) file_get_contents($capa), basename($capa));
        }
        $this->assertStringNotContainsString('fonts.googleapis.com', $this->css());
    }

    public function test_hay_una_sola_hoja_de_estilos_publica_y_la_vieja_capa_de_correcciones_no_existe(): void
    {
        $this->assertFileDoesNotExist(public_path('css/identidad-v1.css'));
        $this->get('/')->assertSee('css/main.css', false)->assertDontSee('identidad-v1', false);
        // antes, una capa entera corregía a la otra con !important; ahora quedan solo los casos justificados
        $this->assertLessThanOrEqual(10, substr_count($this->css(), '!important'));
    }

    public function test_cada_clase_de_las_vistas_publicas_tiene_su_regla_en_el_css(): void
    {
        $css = $this->css();
        $definidas = [];
        preg_match_all('/\.([a-zA-Z][\w-]*)/', $css, $m);
        $definidas = array_flip($m[1]);

        $sinEstilo = [];
        $archivos = array_merge(
            glob(resource_path('views/site/*.blade.php')),
            glob(resource_path('views/site/partials/*.blade.php')),
            glob(resource_path('views/layouts/site.blade.php')),
        );
        foreach ($archivos as $archivo) {
            preg_match_all('/class="([^"{}$@]+)"/', (string) file_get_contents($archivo), $usos);
            foreach ($usos[1] as $lista) {
                foreach (preg_split('/\s+/', trim($lista)) as $clase) {
                    if ($clase !== '' && ! isset($definidas[$clase])) {
                        $sinEstilo[$clase] = basename($archivo);
                    }
                }
            }
        }

        $this->assertSame([], $sinEstilo, 'Clases sin regla en main.css: '.json_encode($sinEstilo));
    }

    public function test_la_pagina_actual_queda_marcada_en_el_menu(): void
    {
        $this->get('/')->assertSee('data-nav="inicio" aria-current="page"', false)->assertDontSee('data-nav="blog" aria-current', false);
        $this->get('/blog')->assertSee('data-nav="blog" aria-current="page"', false)->assertDontSee('data-nav="inicio" aria-current', false);
        $this->get('/blog/compliance-pymes-2026')->assertSee('data-nav="blog" aria-current="page"', false);
        $this->get('/servicios')->assertSee('data-nav="servicios" aria-current="page"', false);
        $this->get('/contacto')->assertSee('data-nav="contacto" aria-current="page"', false);
        $this->get('/aviso-legal')->assertDontSee('aria-current="page"', false);
        // y el CSS lo pinta como el hover: dorado y subrayado
        $this->assertMatchesRegularExpression('/\.nav__menu a\[aria-current="page"\]/', $this->css());
    }

    public function test_las_opiniones_solo_se_muestran_si_el_despacho_aporta_opiniones_reales(): void
    {
        $this->get('/')->assertDontSee('Lo que dicen nuestros clientes')->assertDontSee('Carlos M.');

        config(['site.testimonials' => [['texto' => 'Opinión de prueba', 'autor' => 'Persona Inventada', 'detalle' => 'Cargo — Empresa']]]);

        $this->get('/')->assertSee('Lo que dicen nuestros clientes')->assertSee('«Opinión de prueba»', false)->assertSee('Persona Inventada');
    }

    public function test_la_direccion_del_despacho_sale_en_contacto_y_en_el_pie(): void
    {
        $this->get('/contacto')->assertSee('Avenida de Calvo Sotelo 24, 39002 Santander, Cantabria');
        $this->get('/')->assertSee('Avenida de Calvo Sotelo 24, 39002 Santander, Cantabria');

        config(['site.address' => 'Otra Calle 1, Santander']);
        $this->get('/contacto')->assertSee('Otra Calle 1, Santander');
    }

    public function test_los_iconos_de_contacto_son_svg_y_no_emojis(): void
    {
        foreach (['/', '/contacto', '/reservar-cita', '/servicios', '/quienes-somos'] as $ruta) {
            $html = $this->get($ruta)->getContent();
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html, "Emoji en $ruta");
        }
    }
}
