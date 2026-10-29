<?php

namespace Tests;

use App\Support\Pdf\ConversorPdf;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Support\FakeConversorPdf;

abstract class TestCase extends BaseTestCase
{
    /**
     * Última barrera de seguridad: las pruebas solo se ejecutan contra una base de datos en memoria.
     * RefreshDatabase borra y recrea las tablas, así que una prueba lanzada con DB_DATABASE apuntando a
     * database/despacho.sqlite (la base real) destruiría los datos del despacho. phpunit.xml ya fuerza estos
     * valores; esto detiene la ejecución antes de tocar nada si alguien los cambia o los quita.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $conexion = config('database.default');
        $base = config("database.connections.$conexion.database");
        if ($conexion !== 'sqlite' || $base !== ':memory:' || ! $this->app->environment('testing')) {
            throw new RuntimeException(
                "Pruebas detenidas por seguridad: se esperaba APP_ENV=testing y la base sqlite en memoria, pero hay '{$conexion}' ('{$base}') en '{$this->app->environment()}'. "
                .'Revisa phpunit.xml y las variables de tu terminal (DB_DATABASE, APP_ENV). No se ha tocado ninguna base de datos.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // las pruebas nunca arrancan LibreOffice: usan un conversor simulado (hay una prueba aparte con el real, si está instalado)
        FakeConversorPdf::reset();
        $this->app->bind(ConversorPdf::class, FakeConversorPdf::class);
    }
}
