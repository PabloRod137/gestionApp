<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_web_publica_lleva_una_politica_de_contenido_estricta(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_la_api_no_se_guarda_en_cache(): void
    {
        $response = $this->getJson('/api/nortia/services');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_las_demas_pantallas_llevan_las_cabeceras_basicas_pero_no_la_politica_de_contenido(): void
    {
        // el inicio de sesión y el panel usan scripts incrustados (Breeze/Alpine)
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_ninguna_pagina_publica_usa_scripts_ni_estilos_incrustados(): void
    {
        foreach (['/', '/quienes-somos', '/servicios', '/inmuebles', '/blog', '/tienda', '/carrito', '/contacto', '/reservar-cita', '/aviso-legal'] as $path) {
            $html = $this->get($path)->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)(?![^>]*type="application\/ld\+json")/i', $html, "Script incrustado en $path");
            $this->assertDoesNotMatchRegularExpression('/<style\b|\sstyle="|\son[a-z]+="/i', $html, "Estilo o evento incrustado en $path");
        }
    }
}
