<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Las pruebas nunca pueden salirse de su entorno aislado, aunque el terminal tenga variables propias. */
class EntornoDePruebasTest extends TestCase
{
    public function test_las_pruebas_usan_el_entorno_de_pruebas_y_una_base_en_memoria(): void
    {
        $this->assertTrue($this->app->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('array', config('cache.default'));
    }

    public function test_el_arranque_de_las_pruebas_impone_las_variables_que_las_aislan(): void
    {
        $arranque = (string) file_get_contents(base_path('tests/bootstrap.php'));
        foreach (['APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'SESSION_DRIVER', 'CACHE_STORE', 'ALLOW_SAMPLE_DATA'] as $variable) {
            $this->assertStringContainsString("'$variable' =>", $arranque, "tests/bootstrap.php debe imponer $variable");
        }
        $this->assertStringContainsString('tests/bootstrap.php', (string) file_get_contents(base_path('phpunit.xml')));
    }

    public function test_el_proyecto_no_se_ha_quedado_apuntando_a_la_base_real(): void
    {
        $this->assertStringNotContainsString('despacho.sqlite', (string) config('database.connections.sqlite.database'));
    }
}
