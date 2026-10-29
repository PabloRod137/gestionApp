<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retoques de la web pública: menú, seis líneas de servicio, portal inmobiliario,
 * Área Cliente en el pie y formulario de contacto. Lo que ya teníamos mejor (estilos, reservas, blog, textos legales) no se toca.
 */
class SitioPublicoRetoquesTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        '/', '/quienes-somos', '/servicios', '/inmuebles', '/blog', '/tienda', '/carrito', '/contacto', '/reservar-cita',
        '/aviso-legal', '/politica-de-privacidad', '/politica-de-cookies', '/condiciones-de-contratacion',
        '/blog/compliance-pymes-2026', '/blog/piso-centro-urbano-santander',
    ];

    private const SEIS = ['legal', 'fiscal', 'laboral', 'comunidades', 'inmobiliario', 'seguros'];

    private function html(string $path = '/'): string
    {
        return $this->get($path)->assertOk()->getContent();
    }

    /** Texto entre dos marcas del HTML. */
    private function entre(string $html, string $desde, string $hasta): string
    {
        $i = strpos($html, $desde);
        $this->assertNotFalse($i, "No aparece {$desde}");
        $j = strpos($html, $hasta, $i);

        return substr($html, $i, ($j === false ? strlen($html) : $j) - $i);
    }

    private function cabecera(string $html): string
    {
        return $this->entre($html, '<header>', '</header>');
    }

    private function pie(string $html): string
    {
        return $this->entre($html, '<footer', '</footer>');
    }

    public function test_el_menu_principal_sigue_el_orden_de_la_web_nueva(): void
    {
        preg_match_all('/<a [^>]*href="([^"#]+)"/', $this->entre($this->cabecera($this->html()), '<ul class="nav__menu"', '</ul>'), $m);

        $this->assertSame(['/', '/quienes-somos', '/servicios', '/blog', '/inmuebles', '/contacto', '/reservar-cita', '/carrito'], $m[1]);
    }

    public function test_el_area_cliente_sale_del_menu_y_queda_en_el_pie_y_la_tienda_tambien(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('/login', $this->cabecera($html));
        $this->assertStringContainsString('href="/login"', $this->pie($html));
        $this->assertStringContainsString('href="/tienda"', $this->pie($html));
    }

    public function test_el_desplegable_y_el_pie_ofrecen_las_seis_lineas_de_servicio_en_orden(): void
    {
        $html = $this->html();
        $esperado = array_map(fn ($s) => "/servicios#{$s}", self::SEIS);

        preg_match_all('/href="(\/servicios#[a-z]+)"/', $this->entre($this->cabecera($html), 'class="nav__dropdown"', '</div>'), $m);
        $this->assertSame($esperado, $m[1]);

        preg_match_all('/href="(\/servicios#[a-z]+)"/', $this->entre($this->pie($html), 'footer__col-title">Servicios', '</ul>'), $m);
        $this->assertSame($esperado, $m[1]);
    }

    public function test_la_pagina_de_servicios_tiene_las_seis_lineas_y_conserva_los_enlaces_antiguos(): void
    {
        $html = $this->html('/servicios');

        foreach (self::SEIS as $id) {
            $this->assertStringContainsString("<article class=\"service-card service-detail\" data-cat=\"{$id}\" id=\"{$id}\">", $html, "Falta la línea {$id}");
            $this->assertStringContainsString("data-cat=\"{$id}\">", $this->entre($html, 'class="filter-tabs"', '</div>'), "Falta la pestaña {$id}");
        }
        // las direcciones antiguas (#activos, #vehiculos) siguen llevando a la sección de inmobiliario
        $this->assertStringContainsString('id="activos"', $html);
        $this->assertStringContainsString('id="vehiculos"', $html);
        $this->assertStringNotContainsString('Transmisión de activos', $html);
    }

    public function test_compliance_y_proteccion_de_datos_quedan_dentro_de_legal(): void
    {
        $legal = $this->entre($this->html('/servicios'), 'id="legal"', 'id="fiscal"');

        $this->assertStringContainsString('compliance', mb_strtolower($legal));
        $this->assertStringContainsString('protección de datos', mb_strtolower($legal));
    }

    public function test_seguros_no_promete_contratacion_ni_precio(): void
    {
        $seguros = $this->entre($this->html('/servicios'), 'id="seguros"', '</article>');

        $this->assertStringNotContainsStringIgnoringCase('pendiente de validación', $seguros);
        $this->assertStringNotContainsString('€', $seguros);
        $this->assertStringContainsString('siniestro', mb_strtolower($seguros));
    }

    public function test_ninguna_pagina_publica_enseña_notas_de_desarrollo_ni_recursos_de_la_empresa_anterior(): void
    {
        foreach (self::PAGES as $path) {
            $html = $this->html($path);
            $this->assertStringNotContainsStringIgnoringCase('pendiente de validación', $html, "{$path} muestra una nota de desarrollo");
            $this->assertStringNotContainsString('Transmisión de activos', $html, "{$path} usa el nombre antiguo");
            $this->assertStringNotContainsString('nortia.com/wp-content', $html, "{$path} carga recursos de la empresa anterior");
        }
    }

    public function test_la_portada_presenta_las_seis_lineas_con_su_enlace(): void
    {
        $html = $this->html();
        $rejilla = $this->entre($html, 'id="servicios"', 'aria-label="Cómo trabajamos"');

        foreach (self::SEIS as $id) {
            $this->assertStringContainsString("href=\"/servicios#{$id}\"", $rejilla, "La portada no enlaza a {$id}");
        }
        $this->assertSame(6, substr_count($rejilla, 'class="service-card"'));
        $this->assertStringContainsString('Asesoría jurídica, fiscal e inmobiliaria', $html);
        $this->assertMatchesRegularExpression('/hero__stat-num">6<\/span>\s*<span class="hero__stat-label">áreas de práctica/', $html);
    }

    public function test_la_portada_explica_como_se_presupuesta_sin_enseñar_precios_inventados(): void
    {
        $bloque = $this->entre($this->html(), 'aria-label="Cómo se presupuesta"', '</section>');

        $this->assertStringContainsString('Presupuesto', $bloque);
        $this->assertStringNotContainsString('€', $bloque);
        $this->assertSame(3, preg_match_all('/<div class="pricing-card( pricing-card--featured)?">/', $bloque));
    }

    public function test_el_portal_inmobiliario_tiene_su_titulo_filtros_y_la_promocion_de_compra_segura(): void
    {
        $html = $this->html('/inmuebles');

        $this->assertStringContainsString('<h1>Portal inmobiliario</h1>', $html);
        foreach (['Todos', 'Pisos', 'Terrenos'] as $filtro) {
            $this->assertStringContainsString($filtro, $this->entre($html, 'class="filter-tabs"', '</div>'));
        }
        $this->assertStringContainsString('href="/servicios#inmobiliario"', $this->entre($html, 'class="portal-promo"', '</section>'));
        // Compra segura es una promoción, no una categoría de inmueble
        $this->assertStringNotContainsString('data-cat="compra-segura"', $html);
    }

    public function test_el_formulario_de_contacto_ofrece_las_seis_areas_una_sola_vez(): void
    {
        $select = $this->entre($this->html('/contacto'), '<select class="form-control" id="c-service"', '</select>');
        preg_match_all('/<option[^>]*>([^<]+)<\/option>/', $select, $m);

        $this->assertSame(['Legal', 'Fiscal', 'Laboral', 'Comunidades', 'Inmobiliario', 'Seguros', 'Otro'], $m[1]);
    }

    public function test_el_menu_llama_contactar_a_contacto_y_las_citas_reservables_son_las_cinco_con_cita(): void
    {
        $this->assertStringContainsString('>Contactar</a>', $this->cabecera($this->html()));

        $this->getJson('/api/nortia/services')->assertOk()
            ->assertJsonPath('services.*.id', ['legal', 'fiscal', 'laboral', 'comunidades', 'inmobiliario']);
    }
}
