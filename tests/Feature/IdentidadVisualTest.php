<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identidad visual: tipografía alojada en el propio servidor (sin pedir nada a Google), una sola hoja de
 * estilos compilada por Vite/Tailwind para toda la web (pública, panel y cliente) y cada clase a mano
 * (las que no son utilidades de Tailwind) con su regla, para que no quede una sección sin estilos.
 */
class IdentidadVisualTest extends TestCase
{
    use RefreshDatabase;

    /** El origen que mantenemos a mano: las clases propias viven aquí, el resto lo genera Tailwind. */
    private function cssFuente(): string
    {
        return (string) file_get_contents(resource_path('css/app.css'));
    }

    /** Lo que de verdad sirve el navegador, tras pasar por Tailwind (purga, variantes, minificado). */
    private function cssCompilada(): string
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
        $fichero = $manifest['resources/css/app.css']['file'] ?? null;
        $this->assertNotNull($fichero, 'No se encuentra resources/css/app.css en el manifest de Vite: falta `npm run build`.');

        return (string) file_get_contents(public_path('build/'.$fichero));
    }

    public function test_las_fuentes_se_sirven_desde_el_propio_servidor_y_existen(): void
    {
        $css = $this->cssFuente();
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
        $this->assertStringNotContainsString('fonts.googleapis.com', $this->cssFuente());
    }

    public function test_hay_una_sola_hoja_de_estilos_para_toda_la_app_y_las_capas_viejas_no_existen(): void
    {
        // Hojas de estilos de versiones anteriores: ya no deben existir (sustituidas por Tailwind/Vite).
        $this->assertFileDoesNotExist(public_path('css/identidad-v1.css'));
        $this->assertFileDoesNotExist(public_path('css/main.css'));
        $this->assertFileDoesNotExist(public_path('css/chat-cliente.css'));

        // La web pública y el acceso de clientes cargan la misma hoja compilada (resources/css/app.css),
        // la misma que usan el panel y el portal: una sola identidad, un solo pipeline.
        $this->get('/')->assertSee('build/assets/app-', false)->assertDontSee('css/main.css', false);
        $this->get('/login')->assertSee('build/assets/app-', false)->assertDontSee('css/main.css', false)->assertDontSee('chat-cliente.css', false);

        // Antes, una capa entera corregía a la otra con !important; ahora quedan solo los casos justificados.
        $this->assertLessThanOrEqual(10, substr_count($this->cssFuente(), '!important'));
    }

    /**
     * Clases que no son de Tailwind (sin variantes `hover:`/`md:`, sin valores entre corchetes) tienen que
     * tener su regla a mano en resources/css/app.css — si no, Tailwind nunca las genera y quedan sin estilo.
     */
    public function test_cada_clase_propia_de_las_vistas_publicas_tiene_su_regla_en_app_css(): void
    {
        $css = $this->cssFuente();
        $definidas = [];
        preg_match_all('/\.([a-zA-Z][\w-]*)/', $css, $m);
        $definidas = array_flip($m[1]);

        // Clases usadas directamente como utilidad de Tailwind en el layout (no son de nuestro sistema).
        $utilidadesSueltas = ['font-sans', 'font-serif', 'antialiased', 'max-w-full', 'overflow-x-hidden', 'text-gray-700', 'bg-gray-50'];

        $esUtilidadTailwind = function (string $clase) use ($utilidadesSueltas): bool {
            if (in_array($clase, $utilidadesSueltas, true)) {
                return true;
            }
            // Variante (hover:, md:, max-[...]:, group-hover:...), valor arbitrario [..] u opacidad /NN
            if (str_contains($clase, ':') || str_contains($clase, '[') || preg_match('#/\d+$#', $clase)) {
                return true;
            }
            // Patrones de utilidades sueltas que no llevan prefijo de marca (btn, nav, hero...)
            return (bool) preg_match('/^-?(([a-z]+-)*(x|y)?\d+(\.\d+)?|auto|px|full|screen|none|block|flex|grid|hidden|inline.*|contents)$/', $clase)
                && ! preg_match('/^(btn|nav|hero|footer|section|container|badge|service|blog|proceso|pricing|testimonio|about|valor|cta|product|filter|form|booking|calendar|slot|shop|cart|article|map|icono|chat|client|portal|toast|skip|link)(-|__|$)/', $clase);
        };

        $sinEstilo = [];
        $archivos = array_merge(
            glob(resource_path('views/site/*.blade.php')),
            glob(resource_path('views/site/partials/*.blade.php')),
            glob(resource_path('views/site/blog/*.blade.php')),
            glob(resource_path('views/layouts/site.blade.php')),
            glob(resource_path('views/layouts/guest.blade.php')),
        );
        foreach ($archivos as $archivo) {
            preg_match_all('/class="([^"{}$@]+)"/', (string) file_get_contents($archivo), $usos);
            foreach ($usos[1] as $lista) {
                foreach (preg_split('/\s+/', trim($lista)) as $clase) {
                    if ($clase === '' || isset($definidas[$clase]) || $esUtilidadTailwind($clase)) {
                        continue;
                    }
                    $sinEstilo[$clase] = basename($archivo);
                }
            }
        }

        $this->assertSame([], $sinEstilo, 'Clases propias sin regla en app.css: '.json_encode($sinEstilo));
    }

    /**
     * El JS de la web pública arma algunas clases por su cuenta (p. ej. el campo trampa antispam, o el
     * color de cada día del calendario). Tailwind no evalúa JS: si no están en `content` o en `safelist`
     * de tailwind.config.js, las purga en silencio y el navegador nunca ve la regla.
     */
    public function test_las_clases_que_arma_el_js_de_la_web_publica_sobreviven_a_tailwind(): void
    {
        $css = $this->cssCompilada();
        foreach (['lg-hp-field', 'calendar-day--available', 'calendar-day--selected', 'calendar-day--disabled', 'slot-btn--selected', 'form-status--error', 'toast--error'] as $clase) {
            $this->assertStringContainsString('.'.$clase, $css, "Tailwind ha purgado .$clase (clase que solo arma el JS)");
        }
    }

    public function test_la_pagina_actual_queda_marcada_en_el_menu(): void
    {
        $this->get('/')->assertSee('data-nav="inicio" aria-current="page"', false)->assertDontSee('data-nav="blog" aria-current', false);
        $this->get('/blog')->assertSee('data-nav="blog" aria-current="page"', false)->assertDontSee('data-nav="inicio" aria-current', false);
        $this->get('/blog/compliance-pymes-2026')->assertSee('data-nav="blog" aria-current="page"', false);
        $this->get('/servicios')->assertSee('data-nav="servicios" aria-current="page"', false);
        $this->get('/contacto')->assertSee('data-nav="contacto" aria-current="page"', false);
        $this->get('/aviso-legal')->assertDontSee('aria-current="page"', false);
        // y el CSS lo pinta en dorado y subrayado
        $this->assertMatchesRegularExpression('/\.nav__menu a\[aria-current="page"\]/', $this->cssFuente());
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
