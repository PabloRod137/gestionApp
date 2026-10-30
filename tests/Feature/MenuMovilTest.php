<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Menú de la web en el móvil: el overlay es position:fixed y no puede colgar de un elemento con backdrop-filter. */
class MenuMovilTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/app.css'));
    }

    public function test_en_movil_la_barra_pierde_el_desenfoque_que_rompe_el_menu(): void
    {
        $css = $this->css();
        $i = strpos($css, '@media (max-width: 1180px)');
        $this->assertNotFalse($i);

        // backdrop-blur-md convierte la barra en el bloque contenedor de sus hijos fixed (el menú se queda
        // del alto de la barra): en móvil hay que quitárselo con la propia utilidad de Tailwind, no con CSS
        // a mano (un `backdrop-filter: none` suelto se lo come el minificador en producción).
        $this->assertMatchesRegularExpression('/\.nav\s*\{\s*@apply[^}]*backdrop-blur-none/', substr($css, $i));
    }

    public function test_el_menu_abierto_ocupa_la_pantalla_y_oculta_el_chat(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/\.nav__menu\.open\s*\{[^}]*@apply[^}]*\bfixed\b[^}]*\bbottom-0\b/s', $css);
        $this->assertMatchesRegularExpression('/body\.menu-abierto \.chat-launcher\s*\{\s*@apply[^}]*\bhidden\b/', $css);
        $this->assertStringContainsString("classList.toggle('menu-abierto'", (string) file_get_contents(public_path('js/features/nav.js')));
    }

    public function test_el_compilado_realmente_quita_el_desenfoque_en_movil(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
        $fichero = $manifest['resources/css/app.css']['file'] ?? null;
        $this->assertNotNull($fichero, 'Falta `npm run build`.');
        $compilada = (string) file_get_contents(public_path('build/'.$fichero));

        $i = strpos($compilada, '1180px');
        $this->assertNotFalse($i, 'No se encuentra el punto de corte de 1180px en el CSS compilado.');
        $bloque = substr($compilada, $i, 400);
        // Vacía la variable que compone backdrop-filter: sin blur real, aunque -webkit- siga mencionada.
        $this->assertMatchesRegularExpression('/\.nav\{--tw-backdrop-blur:\s*;/', $bloque);
    }
}
