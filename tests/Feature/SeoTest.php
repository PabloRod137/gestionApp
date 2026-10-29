<?php

namespace Tests\Feature;

use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lo que pide la auditoría SEO y nos compete: sitemap y robots propios, tarjetas para redes, ficha local, WebP. */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_apunta_al_sitemap_del_propio_dominio_y_no_al_de_la_empresa_anterior(): void
    {
        $r = $this->get('/robots.txt')->assertOk();

        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $r->getContent());
        $this->assertStringNotContainsString('nortia.com', $r->getContent());
        $this->assertStringContainsString('Disallow: /panel/', $r->getContent());
        $this->assertStringNotContainsString('wp-', $r->getContent());
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_el_sitemap_lista_paginas_y_articulos_con_direcciones_absolutas(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/servicios').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/blog/compliance-pymes-2026').'</loc>', $xml);
        $this->assertStringNotContainsString('/carrito', $xml);
    }

    public function test_cada_pagina_lleva_open_graph_twitter_card_e_imagen_de_marca(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.url(Seo::IMAGEN).'">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Nortia — Asesoría legal', $html);
        $this->assertSame([1200, 630], array_slice(getimagesize(public_path(Seo::IMAGEN)), 0, 2));
    }

    public function test_el_titulo_no_empieza_por_un_guion_suelto(): void
    {
        foreach (['/', '/servicios', '/contacto', '/blog'] as $ruta) {
            $this->assertMatchesRegularExpression('/<title>\s*[\pL\pN]/u', $this->get($ruta)->getContent(), $ruta);
        }
    }

    public function test_la_ficha_local_es_json_valido_con_direccion_y_sin_valoraciones(): void
    {
        $html = $this->get('/')->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $ficha = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('LegalService', $ficha['@type']);
        $this->assertSame('Nortia', $ficha['name']);
        $this->assertSame('Santander', $ficha['address']['addressLocality']);
        $this->assertSame('39002', $ficha['address']['postalCode']);
        $this->assertSame('Mo-Fr 09:00-19:00', $ficha['openingHours']);
        $this->assertStringStartsWith(url('/'), $ficha['logo']);
        $this->assertArrayNotHasKey('aggregateRating', $ficha);
        $this->assertArrayNotHasKey('review', $ficha);
    }

    public function test_un_dato_de_relleno_no_entra_en_la_ficha_local(): void
    {
        config(['site.phone' => 'PENDIENTE', 'site.email' => 'PENDIENTE']);

        $ficha = Seo::fichaLocal();

        $this->assertArrayNotHasKey('email', $ficha);
    }

    public function test_el_horario_se_convierte_o_se_omite(): void
    {
        $this->assertSame('Mo-Fr 08:30-14:00', Seo::horario('Lunes a viernes, 8:30 – 14:00'));
        $this->assertNull(Seo::horario('Con cita previa'));
    }

    public function test_carrito_y_pagos_no_se_indexan(): void
    {
        foreach (['/carrito', '/pago-completado', '/pago-cancelado'] as $ruta) {
            $this->get($ruta)->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }
    }

    public function test_las_imagenes_de_las_paginas_se_sirven_en_webp(): void
    {
        foreach (['/', '/inmuebles', '/quienes-somos', '/blog/compliance-pymes-2026'] as $ruta) {
            $html = $this->get($ruta)->getContent();
            preg_match_all('#/assets/external/[\w.-]+#', $html, $m);
            foreach ($m[0] as $src) {
                $this->assertStringEndsWith('.webp', $src, "$ruta usa $src");
                $this->assertFileExists(public_path($src));
            }
        }
        $this->assertStringNotContainsString('.jpg', file_get_contents(public_path('js/data/blog-posts.js')));
    }
}
