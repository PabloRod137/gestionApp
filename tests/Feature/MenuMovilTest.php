<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Menú de la web en el móvil: el overlay es position:fixed y no puede colgar de un elemento con backdrop-filter. */
class MenuMovilTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(public_path('css/main.css'));
    }

    public function test_en_movil_la_barra_no_tiene_backdrop_filter(): void
    {
        $css = $this->css();
        $i = strpos($css, '@media (max-width: 1180px)');
        $this->assertNotFalse($i);

        $this->assertMatchesRegularExpression('/\.nav\s*\{[^}]*backdrop-filter:\s*none/', substr($css, $i));
    }

    public function test_el_menu_abierto_ocupa_la_pantalla_y_oculta_el_chat(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/\.nav__menu\.open\s*\{[^}]*position:\s*fixed[^}]*bottom:\s*0/', $css);
        $this->assertStringContainsString('body.menu-abierto .chat-launcher { display: none; }', $css);
        $this->assertStringContainsString("classList.toggle('menu-abierto'", file_get_contents(public_path('js/features/nav.js')));
    }
}
